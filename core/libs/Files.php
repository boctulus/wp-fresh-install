<?php declare(strict_types=1);

namespace simplerest\core\libs;

use simplerest\core\libs\System;
use simplerest\core\libs\SortedIterator;

class Files 
{
	static protected $backup_path;
	static protected $callable;

	const LINUX_DIR_SLASH = '/';
	const WIN_DIR_SLASH   = '\\';
	const SLASHES 		  = ['/', '\\'];

	/*
		Chequea si una ruta existe
	*/
	static function exists(string $path){
		if (strlen($path) > 4096){
			return false;
		}

		return file_exists($path);
	}

	static function existsOrFail(string $path){
		if (strlen($path) > 4096){
			return false;
		}

		if (!file_exists($path)){
			$path = static::convertSlashes($path);
			throw new \Exception("File not found for '$path'");
		}
	}

	/*
		Descarga localmente archivos dadas una o varias urls

		$url 	   string|array url para descarga
		$dest_path string       ubicacion donde se dejaran las descargas
	*/
	static function download($url, string $dest_dir){
		if (is_string($url)){
			$url = [ $url ];
		}

		$cli = new ApiClient();
		$cli
			->setBinary()
			->withoutStrictSSL();

		foreach ($url as $uri){	
			Url::validateOrFail($uri);

			$filename = static::getFilenameFromURL($uri);	
			$bytes    = $cli->download($dest_dir . DIRECTORY_SEPARATOR . $filename, $uri);
		}

		// retorna la longitud del ultimo archivo descargado (util si es solo uno)
		return $bytes;
	}

	/*
		Fuerza la descarga del archivo
	*/
	static function forceDownload($file){
		if (!file_exists($file)) {
			throw new \InvalidArgumentException("File not found for '$file'");
		}

		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.basename($file).'"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		@readfile($file);
		exit;
	}

	/*
		Si es false, funciones curl no funcionaran
	*/
	static function isCurlAvailable(){
		return extension_loaded('curl');
	}

	/*
		Si es false, file_get_contents() no funcionara
	*/
	static function isAllowUrlFopenEnabled(){
		return ini_get('allow_url_fopen');
	}

	/*
		https://www.codewall.co.uk/write-php-array-to-csv-file/
		https://fuelingphp.com/how-to-convert-associative-array-to-csv-in-php/
	*/
	static function arrayToCSV(string $filename, Array $array){
		if (!Strings::endsWith('.csv', strtolower($filename))){
			$filename .= '.csv';
		}

		$f = fopen($filename, 'a'); // Configure fopen to create, open, and write data.
 
		fputcsv($f, array_keys($array[0])); // Add the keys as the column headers
		
		// Loop over the array and passing in the values only.
		foreach ($array as $row)
		{
			fputcsv($f, $row);
		}
		// Close the file
		fclose($f);
	}

	static function getCSV(string $path, string $separator = ",", bool $header = true, bool $assoc = true, $header_defs = null){	
		$rows = [];

		ini_set('auto_detect_line_endings', 'true');

		$handle = fopen($path,'r');

		if ($header){
			$cabecera = fgetcsv($handle, null, $separator);
			$ch       = count($cabecera);
		}  else {
			$assoc    = false;
		}

		// Puedo re-definir
		if ($header_defs != null){
			$cabecera = $header_defs;
		}
		
		$i = 0;
		while ( ($data = fgetcsv($handle, null, $separator) ) !== FALSE ) {
			if ($assoc){
				for ($j=0;$j<$ch; $j++){					
					$head_key = $cabecera[$j];
					$val      = $data[$j] ?? '';

					$rows[$i][$head_key] = $val;
				}
			} else {
				$rows[] = $data;
			}	

			$i++;		
		}
		
		ini_set('auto_detect_line_endings', 'false');

		if ($header){
			return [
				'rows'   => $rows,
				'header' => $cabecera ?? []
			];
		} 

		return $rows;		
	}

	/*
		Hace un "diff" entre dos rutas de archivos
	*/
	static function diff(string $path1, string $path2, bool $discard_dirs = false){
		$files_path1 = static::removePath(
            static::deepScan($path1, $discard_dirs), $path1
        );

        $files_path2 = static::removePath(
            static::deepScan($path2, $discard_dirs), $path2
        );

		return array_diff($files_path1, $files_path2);
	}

	/*
		Remueve el path (la parte constante) de un array de un array de entradas de directorio 

		Ej:

			$clean_path  = static::removePath($path, static::PLUGINDIR)[0];

		Ej:

			$clean_paths = static::removePath([ $path1, $path2, $path_n ], static::PLUGINDIR);
	*/
	static function removePath($to_clean, string $path) {
		$path_non_trailing_slash = Strings::removeTrailingSlash($path);

		$len = strlen($path_non_trailing_slash) + 1;

		if (!is_array($to_clean)){
			return substr($to_clean, $len);
		}

        foreach ($to_clean as $ix => $f){
            $to_clean[$ix] = substr($f, $len);
        }

		return $to_clean;
	}

	/* 
		str_replace sobre archivo
	*/
	static function replace(string $filename, $search, $replace){
		$file  = file_get_contents($filename);
		$file  = str_replace($search, $replace, $file);

		return file_put_contents($filename, $file);
	}

	/* 
		preg_replace sobre archivo
	*/
	static function pregReplace(string $filename, $search, $replace){
		$file = file_get_contents($filename);
		$file = preg_replace($search, $replace, $file);
		
		return file_put_contents($filename, $file);
	}

	/*
		Convierte todos los slashes de la ruta a los apropiados o especificados
	*/
	static function convertSlashes($path, $to_slash = null){
		if ($to_slash === null){
			$to_slash = DIRECTORY_SEPARATOR;
		}

		$path = str_replace(static::SLASHES, $to_slash, $path);

		return $path;
	}

	/**
	 * Normaliza una ruta eliminando los segmentos ".." y convierte las barras diagonales a un formato específico si se especifica.
	 *
	 * @param string $path La ruta a normalizar.
	 * @param string|null $to_slash (Opcional) El formato de barras diagonales al que se debe convertir la ruta (por ejemplo, '\\' para Windows).
	 * @return string La ruta normalizada.
	 */
	static function normalize($path, $to_slash = null){
		$path = str_replace('\/', '/', $path);
		$path = str_replace('\\', '/', $path);

		$segmentos = explode('/', $path);
		$nuevaRuta = [];
	
		foreach ($segmentos as $segmento) {
			if ($segmento === '..') {
				array_pop($nuevaRuta);
			} else {
				$nuevaRuta[] = $segmento;
			}
		}
	
        $path = implode('/', $nuevaRuta);

		return static::convertSlashes($path, $to_slash);
	}

	static function isAbsolutePath(string $path){
		$path = static::convertSlashes($path);

		if (Strings::contains('..', $path) || Strings::startsWith('.' . DIRECTORY_SEPARATOR , $path)|| Strings::startsWith('..' . DIRECTORY_SEPARATOR , $path)){
			return false;
		}

		if (PHP_OS_FAMILY === "Windows") {
			if (preg_match('~[A-Z]:'.preg_quote('\\').'~i', $path)){
				return true;
			}

			if (Strings::startsWith('\\', $path)){
				return true;
			}
		}

		if (Strings::startsWith(DIRECTORY_SEPARATOR, $path)){
			return true;
		}

		return false;
	}

	/*
		Determina si una ruta comienza con root (\) tanto en Unix como Windows

		A diferencia de isAbsolutePath() aca no importa si hay ".." en medio
	*/
	static function startsWithRoot(string $path){
		if (PHP_OS_FAMILY === "Windows") {
			if (preg_match('~[A-Z]:'.preg_quote('\\').'~i', $path)){
				return true;
			}

			if (Strings::startsWith('\\', $path)){
				return true;
			}
		}

		if (Strings::startsWith(DIRECTORY_SEPARATOR, $path)){
			return true;
		}

		return false;
	}


	/*
		Returns absolute path relative to root path
	*/
	static function getAbsolutePath(string $path, string $relative_to =  null){
		// sin chequear si tiene uso actualmente.
		if ($relative_to !== null){
			return Strings::removeTrailingSlash($relative_to) . DIRECTORY_SEPARATOR . ltrim(ltrim($path, '/'), '\\');
		}

		if (static::isAbsolutePath($path)){
			return $path;
		}

		/*
			realpath() requiere que la ruta exista
		*/
		if (!file_exists($path)){
			throw new \Exception("Path '$path' not found");
		}
		
		return realpath($path);
	}

	static function getRelativePath(string $abs_path, string $relative_to){
		$path = Strings::diff($abs_path, $relative_to); 
		if ($path[0] = '/' || $path[0] == '\\'){
			$path = substr($path, 1);
		}

		return $path;
	}

	/*
		Ej:

		$zips    = Files::glob($ori, '*.zip');
		$entries = Files::glob($content_dir, '*', GLOB_ONLYDIR, '__MACOSX');
	*/
	static function glob(string $path, string $pattern, $flags = 0, $exclude = null){
		$last_char = Strings::lastChar($path);

		if ($last_char != '/' && $last_char != '\\'){
			$path .= DIRECTORY_SEPARATOR;
		}

		$entries = glob($path . $pattern, $flags);

		if (!empty($exclude)){
			if (!is_array($exclude)){
				$exclude = [ $exclude ];
			}

			foreach ($entries as $ix => $entry){
				$filename = basename($entry);
				
				if (in_array($filename, $exclude)){
					unset($entries[$ix]);
				}
			}
		}

		foreach ($entries as $ix => $entry){
			$entries[$ix] = realpath($entry);
		}

		return $entries;
	}

	// https://stackoverflow.com/a/17161106/980631
	static function recursiveGlob($pattern, $flags = 0) {
		$files = glob($pattern, $flags); 
		foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT|GLOB_BRACE) as $dir) {
			$files = array_merge($files, static::recursiveGlob($dir.'/'.basename($pattern), $flags));
		}

		foreach ($files as $ix => $f){
			$files[$ix] = Strings::removeUnnecessarySlashes($f);
		}	

		return $files;
	}

	/*
		@param $dir directorio a escanear
		@paran $discard_dir descartar o no directorios de la lista resultante
	*/
	static function deepScan(string $dir, bool $discard_dirs = false) {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::SELF_FIRST );

		$ret = [];
        foreach ( $iterator as $path ) {
            $str = $path->__toString();
		
            if ($path->isDir()) {     
				if ($discard_dirs){
					continue;
				}

                if (Strings::endsWith(DIRECTORY_SEPARATOR . '.', $str) || Strings::endsWith(DIRECTORY_SEPARATOR . '..', $str)){
                    continue;
                }
            } 

			$ret[] = $path->__toString();
        }

		return $ret;
	}

	/*
		Extract directory from some path
	*/
	static function getDir(string $path, bool $should_exist = false, bool $throw = false){
		if (!$should_exist){
			if (!is_dir($path)){
				$path = str_replace(static::SLASHES, DIRECTORY_SEPARATOR, $path);
				$path = Strings::beforeLast($path, DIRECTORY_SEPARATOR);
			}

			return $path;
		}

		$_path = realpath($path);

		if ($_path === false){
			if ($throw){
				throw new \InvalidArgumentException("PATH '$path' no existe");
			}

			return '';
		}

		$path = $_path;

		if (is_dir($path)){
			return $path;
		}

		return dirname($path);
	}

	static function setBackupDirectory(string $path) : void {
		static::$backup_path = $path;

		static::mkDir(static::$backup_path);
	}

	static function setCallback(?callable $fn) : void {
		static::$callable = $fn;
	}
	
	/*
		Copy single files
		
		@return bool
	*/
	static function cp(string $ori, string $dst, bool $simulate = false, bool $overwrite = true, ?callable $callable = null){
		$ori = trim($ori);
        $dst = trim($dst);
		
		if (!is_file($ori)){
			return false;
		}

		if (!file_exists($ori)){
			throw new \InvalidArgumentException("$dst does not exist");
		}

		if (is_dir($dst)){
			$filename = basename($ori);
			$dst = Strings::addTrailingSlash($dst) . $filename;
		}

		if (!$overwrite){
			$file_exists = file_exists($dst);

			if ($file_exists){
				StdOut::pprint("File $dst already exists");
				return;
			}
		}

		if (!empty(static::$backup_path)){
			// sino existiera el archivo en el destino, no tendría sentido respaldarlo

			$file_exists = $file_exists ?? file_exists($dst);

			if ($file_exists){			
				$ori_dir = static::getDir($ori);	

				$trailing_dst_path = Strings::diff($ori_dir, ROOT_PATH);
				
				static::$backup_path = Strings::addTrailingSlash(static::$backup_path);
				
				$bk_dir_path = static::$backup_path . $trailing_dst_path;

				if (!is_dir($bk_dir_path)) {
					static::mkDirOrFail($bk_dir_path);
				}

				if (!isset($filename)){
					$filename = basename($ori);
				}

				if (!@rename($dst, $bk_dir_path . DIRECTORY_SEPARATOR . $filename)){
					throw new \Exception("It was not possible to move $ori to $bk_dir_path");
				} else {
					StdOut::pprint("File $dst was backed up > $bk_dir_path --ok");
				}
			}
		}		
		
        StdOut::pprint("Copying $ori > $dst");

        if (!$simulate){
			$callable = $callable ?? static::$callable;

			if ($callable !== null){
				$file = file_get_contents($ori);

				// d($dst, 'DST------------');
				// d($file, 'FILEEEEEEEEEEEE');
				// exit; ////

				$file = call_user_func_array($callable, [$file, $dst]);

				$ok = (bool) file_put_contents($dst, $file);
			} else {
				$ok = @copy($ori, $dst);
			}
		
        } else {
            $ok = true;
        }       

        // if ($ok){
        //     StdOut::pprint("-- ok", true);
        // } else {
        //     StdOut::pprint("-- FAILED !", true);
        // }

        return $ok;
    }

	
	/*
		Copy recursively from one location to another.
		
        @param $ori source directory
		@param $dst destination directory
		@param $files to be copied
        @param $exclude files a excluir (de momento sin ruta). It can be an array or a glob pattern
    */
    static function copy(string $ori, string $dst, ?Array $files = null, ?Array $exclude = null, ?callable $callable = null)
    {
		if (empty($dst)){
			throw new \InvalidArgumentException("Destination dst can not be empty");
		}

		$to_cp  = [];
		$copied = [];

		$dst = Strings::removeTrailingSlash($dst);

		$ori_with_trailing_slash = Strings::addTrailingSlash($ori);
		$ori = Strings::removeTrailingSlash(trim($ori));
        $dst = trim($dst);

		if (empty($files)){
			$files = ['glob:*'];
		}	
		
		foreach ($files as $ix => $f){
			$f = trim($f);
			$f = str_replace(["\r\n", "\r", "\n"], '', $f);

			if (empty($f)){
				unset($files[$ix]);
			}
		}

		/*
			Glob included files
		*/
		$glob_includes = [];
		foreach ($files as $ix => $f){
			if (Strings::startsWith('glob:', $f, false)){
				$patt = substr($f, 5);
				$rec  = static::recursiveGlob($ori_with_trailing_slash . $patt);
				
				// glob includes son tomados como relativos
				foreach ($rec as $j => $rf){
					$rec[$j]  = static::getRelativePath($rf, $ori);
				}
				
				$glob_includes = array_merge($glob_includes, $rec);
				
				unset($files[$ix]);
			} 
			
			if (static::isAbsolutePath($f) && is_dir($f)){
				$files[$ix] = static::getRelativePath($f, $ori);
			}

			// Creo directorio para destino sino existiera 
			$dst_dir = $dst . Strings::diff(static::getDir($f), $ori);
			static::mkDirOrFail($dst_dir);
		}

		$files = array_merge($files, $glob_includes);
		

		if (empty($exclude)){
			$exclude = [];
		}

		$except_dirs = [];
		if (is_array($exclude)){
			/*
				Glob ignored files
			*/
			$glob_excepts = [];
			foreach ($exclude as $ix => $e){
				if (Strings::startsWith('glob:', $e)){
					$glob_excepts = array_merge($glob_excepts, static::recursiveGlob($ori_with_trailing_slash . substr($e, 5)));
					unset($exclude[$ix]);
				}
			}
			$exclude = array_merge($exclude, $glob_excepts);

			foreach ($exclude as $ix => $e){
				if (!static::isAbsolutePath($e)){
					$exclude[$ix] = trim(static::getAbsolutePath($ori . '/'. $e));
				}

				if (is_dir($exclude[$ix])){
					$except_dirs[] = $exclude[$ix];
				}
			}

			// d($exclude, 'exclude');
			// d($except_dirs, 'except_dirs');
			// exit;
		}
	
        foreach ($files as $_file){
			$_file = trim($_file);

			if (!self::isAbsolutePath($_file)){
				$file = DIRECTORY_SEPARATOR. $_file;
			} else {
				$file = $_file;
			}            

            if (Strings::startsWith('#', $_file) || Strings::startsWith(';', $_file)){
                continue;
            }
            
			/*
				$ori_path es o se hace relativo
			*/
			if (!self::isAbsolutePath($_file)){
				$ori_path = trim($ori . DIRECTORY_SEPARATOR . $_file);
				// $ori_path_abs = static::getAbsolutePath($ori_path);
				$is_file = is_file($ori_path); 
			} else {
				// $ori_path_abs = $_file;
				$ori_path = $_file;
				$ori_path = Strings::substract($ori_path, $ori_with_trailing_slash);
				$is_file = is_file($ori_path);
			}

			$ori_path = Strings::removeUnnecessarySlashes($ori_path);

			if ($is_file){	
				$_dir = static::getDir($ori_path);

				$rel = Strings::substract($_dir, $ori_with_trailing_slash);	
				$_dir_dst = Strings::addTrailingSlash($dst) . $rel;
			
				static::mkDir($_dir_dst);
			}

            if (is_dir($ori_path)){
                static::mkDir($dst . $file);

                $dit = new \RecursiveDirectoryIterator($ori_path, \RecursiveDirectoryIterator::SKIP_DOTS);
                $rit = new \RecursiveIteratorIterator($dit, \RecursiveIteratorIterator::SELF_FIRST);
				$sit = new SortedIterator($rit);

                foreach ($sit as $file) {
                    $file        = $file->getFilename();
                    $full_path   = $sit->current()->getPathname();
					//$current_dir = dirname($full_path);
					
					foreach ($except_dirs as $ix => $e){
						if (Strings::startsWith($e, $full_path)){
							StdOut::pprint("Skiping $file");
							continue 2;
						}
					}
						
					foreach ($exclude as $ix => $e){
						if ($full_path == $e){
                        	continue 2;
						}
					}

                    $dif = Strings::substract($full_path, $ori);
                    $dst_path =  trim($dst . $dif);

					// Creo directorios faltantes
                    if (is_dir($full_path)){
                        $path = pathinfo($dst_path);

                        $needed_path = Strings::substract($full_path, $ori_path);
                        $dirs = explode(DIRECTORY_SEPARATOR, $needed_path);
                        
                        $p = $dst . DIRECTORY_SEPARATOR . $_file;
            
                        foreach ($dirs as $dir){
                            if ($dir == ''){
                                continue;
                            }

                            $p .=  DIRECTORY_SEPARATOR . $dir;
                            
                            static::mkDir($p);
                        }
						
                        // no se pude copiar un directorio, solo archivos
                        continue;
                    }

					/*
						Aplico cache que evita copiar archivos dos veces
					*/
					$str = "$full_path;$dst_path";
					if (!isset($copied[$str])){
						$to_cp[] = [
							'ori_path'   => $full_path,
							'final_path' => $dst_path
						];

						$copied[$str] = 1;
					}
                    
                }
                continue;
            }

			if (static::isAbsolutePath($_file)){
				$_file = Strings::diff($_file, $ori_with_trailing_slash);
			}
			
			$final_path = $dst . DIRECTORY_SEPARATOR . $_file;

			/*
				Aplico cache que evita copiar archivos dos veces
			*/
			$str = "$ori_path;$final_path";
			if (!isset($copied[$str])){
				$to_cp[] = [
					'ori_path'   => $ori_path,
					'final_path' => $final_path
				];

				$copied[$str] = 1;
			}  
        }

		/*
			Copio efectivamente
		*/
		foreach ($to_cp as $f){
			if (in_array(trim($f['ori_path']), $exclude)){
				continue;
			}

			static::cp($f['ori_path'], $f['final_path'], false, true, $callable);
		}


    }

	/*
		Delete a single file

		@return bool
	*/
	static function delete(string $file){
		$file = realpath($file);		
		return @unlink($file);
	}

	/*
		Delete a single file (or fail)

		@return bool
	*/
	static function deleteOrFail(string $file){
		$file = realpath($file);
		
		if (!file_exists($file) || !is_file($file)){
			throw new \Exception("File $file does not exist");
		}
		
		$ok = @unlink($file);

		if (!$ok){
			throw new \Exception("File $file could not be erased");
		}

		return ;
	}

	/*
		https://stackoverflow.com/a/59912170/980631
	*/
	static function isDirEmpty($path) {
		$d = scandir($path, SCANDIR_SORT_NONE ); // get dir, without sorting improve performace (see Comment below). 

    	if ($d){

			// avoid "count($d)", much faster on big array. 
			// Index 2 means that there is a third element after ".." and "."

			return !isset($d[2]); 
		}

    	return false; // or throw an error
	}

	/*
		Delete all files (but not directories) from a path matching a glob pattern

		@return int counting of deleted files
	*/
	static function globDelete(string $dir, ?string $glob_pattern = '*.*', bool $recursive = false) {
		$dir = realpath($dir);

		if (is_null($glob_pattern)){
			$glob_pattern = '*.*';
		}
	
		if ($recursive){
			$files = static::recursiveGlob("$dir/$glob_pattern", GLOB_BRACE);
		} else {
			$files = glob("$dir/$glob_pattern", GLOB_BRACE);
		}

		$deleted = 0;
		foreach ($files as $file){
			$filename = basename($file);
			if ($filename == '.' || $filename == '..'){
				continue;
			}

			if (is_file($file)){
				if (unlink($file)){
					$deleted++;
				}
			}
		}

		// Borro directorios recursivamente
		// https://stackoverflow.com/a/27626153/980631
		$resultant_dirs = [];
		while($dirs = glob($dir . '/*', GLOB_ONLYDIR)) {
			$dir .= '/*';
			if(empty($resultant_dirs)) {
				$resultant_dirs = $dirs;
			} else {
				$resultant_dirs = array_merge($resultant_dirs, $dirs);
			}
		}

		$resultant_dirs = array_reverse($resultant_dirs);

		foreach($resultant_dirs as $d){
			if (!static::isDirEmpty($d)){
				continue;
			}

			rmdir($d);
		}

		return $deleted;
	}

	/*
		No es recursiva
	*/
	static function rmDirOrFail(string $dir){
		if (!is_dir($dir)){
			throw new \Exception("Directory '$dir' doesn't exist");
		}

		static::writableOrFail($dir);

		if (!static::isDirEmpty($dir)){
			throw new \Exception("Directory '$dir' is not empty");
		}

		return @rmdir($dir);
	}

	static function delTree(string $dir, bool $include_self = false, bool $throw = false) {
		if (!is_dir($dir)){
			if ($throw){
				throw new \InvalidArgumentException("Invalid directory '$dir'");
			} else {
				return;
			}
		}

		if (!$include_self){
			return static::globDelete($dir, '{*,.*,*.*}', true, true);
		}
		
		/*
			itay at itgoldman dot com
			https://www.php.net/rmdir
		*/
		if (is_dir($dir)) { 
			$objects = scandir($dir);
			foreach ($objects as $object) { 
				if ($object != "." && $object != "..") { 
					if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
						static::delTree($dir. DIRECTORY_SEPARATOR .$object, $include_self);
					else

					unlink($dir. DIRECTORY_SEPARATOR .$object); 
				} 
			}
			return @rmdir($dir); // *
		} 

		return false;
	}

	static function delTreeOrFail(string $dir, bool $include_self = false){
		static::delTree($dir, $include_self, true);
	}

	static function mkDir(string $dir, int $permissions = 0777, bool $recursive = true){
		$ok = null;

		if (!is_dir($dir)) {
			$ok = @mkdir($dir, $permissions, $recursive);
		}

		return $ok;
	}

	// chatGPT alternative to delTree()
	static function deleteDirectory(string $dir) {
		if (!is_dir($dir)) {
			return;
		}
	
		$files = array_diff(scandir($dir), array('.', '..'));
		foreach ($files as $file) {
			$path = $dir . '/' . $file;
			if (is_dir($path)) {
				self::deleteDirectory($path);
			} else {
				unlink($path);
			}
		}
	
		rmdir($dir);
	}
	
	static function mkDirOrFail(string $dir, int $permissions = 0777, $recursive = true, string $error = "Failed trying to create %s"){
		$ok = null;

		if (!is_dir($dir)) {
			$ok = @mkdir($dir, $permissions, $recursive);
			if ($ok !== true){
				throw new \Exception(sprintf($error, $dir));
			}
		}

		return $ok;
	}

	/*
		Recibe un PATH
		
		Si la ruta ya existe, nada que hacer.
		Sino existe la ruta se intenta crear el directorio

		Se diferencia de mkDir() en que no acepta un $path que deba ser un directorio
		sino es un path que apuntaria a un archivo (que puede no haber sido creado ni tampoco el directorio)

		@return	bool|null

		En caso de que sea dudoso de si se trata de un archivo o una ruta de directorio y no se efectura ninguna accion,
		devuelve null
	*/
	static function mkDestination(string $path)
	{
		// Si es un archivo o directorio (y existe)
		if (file_exists($path) || is_dir($path)){
			return true;
		}

		// Podria ser un path directorio (aun no creado) o un archivos
		if (Strings::containsAny(['\\', '/'], $path)){
            $dir = static::getDir($path);

            if (!is_dir($dir)){
                static::mkDirOrFail($dir);
				return true;
            }
        }
	}

	/*
		Verifica si un archivo o directorio se puede escribir
	*/
	static function isWritable(string $path)
	{
		$path = static::convertSlashes($path); // pasa cualquier barra a DIRECTORY_SEPARATOR

		if (is_dir($path)) {
			$dir = Strings::beforeLast($path, DIRECTORY_SEPARATOR);
			return static::isDirectoryWritable($dir);
		} else {
			if (file_exists($path)) {
				return static::isFileWritable($path);
			} else {
				$dir = Strings::beforeLast($path, DIRECTORY_SEPARATOR);
				return static::isDirectoryWritable($dir);
			}
		}

		return static::isFileWritable($path);
	}

	static function isDirectoryWritable(string $directory)
	{
		if (System::isWindows()) {
			// Verificar permisos de escritura en Windows
			return is_writable($directory);
		} else {
			// Verificar permisos de escritura en sistemas Unix (Linux, macOS, etc.)
			return is_writable($directory) && static::hasWritePermission($directory);
		}
	}

	static function isFileWritable(string $file)
	{
		if (System::isWindows()) {
			// Verificar permisos de escritura en Windows
			return is_writable($file);
		} else {
			// Verificar permisos de escritura en sistemas Unix (Linux, macOS, etc.)
			return is_writable($file) && static::hasWritePermission(dirname($file));
		}
	}

	static function isDirectoryWritableOrFail(string $directory){
		if (!static::isDirectoryWritable($directory)){
			throw new \Exception("$directory is not writable");
		}
	}

	static function hasWritePermission(string $path)
	{
		$stat = stat($path);
		$mode = $stat['mode'];

		// Verificar si el bit de permisos de escritura está configurado para el propietario
		if (($mode & 0x0080) !== 0) {
			return true;
		}

		// Verificar si el bit de permisos de escritura está configurado para el grupo
		if (($mode & 0x0010) !== 0) {
			return true;
		}

		// Verificar si el bit de permisos de escritura está configurado para otros usuarios
		if (($mode & 0x0002) !== 0) {
			return true;
		}

		return false;
	}

	static function writableOrFail(string $path, string $error = "Permission error. Path '%s' is not writable")
	{
		if (System::isWindows()) {
			return true;
		}

		if (!static::isWritable($path)) {
			$path = realpath($path);
			throw new \Exception(sprintf($error, $path));
		}
	}

	static function write(string $path, string $string, int $flags = 0) : bool {
		$ok = (bool) @file_put_contents($path, $string, $flags);
		return $ok;
	}

	/*
		Escribe archivo o falla.
	*/
	static function writeOrFail(string $path, $content, int $flags = 0){
		if (is_dir($path)){
			$path = realpath($path);
			throw new \InvalidArgumentException("$path is not a valid file. It's a directory!");
		} 

		$dir = static::getDir($path);

		if (!file_exists($path)){	
			static::mkDirOrFail($dir);
		}

		static::writableOrFail($dir);

		// Pruebo a ver si tiene __toString()
		if (is_string($content)){
			$string = $content;
		} elseif (is_object($content)){
			$string = (string) $content;
		} elseif (is_array($content)){
			$string = implode(PHP_EOL, $content);
		} 

		$bytes = @file_put_contents($path, $string, $flags);

		return $bytes;
	}

	static function append(string $path, string $string, $add_newline_before = true) : bool {
		return static::write($path, ($add_newline_before ? Strings::carriageReturn($path) : "") . $string, FILE_APPEND);
	}

	static function appendOrFail(string $path, string $string, $add_newline_before = true){
		static::writeOrFail($path, ($add_newline_before ? Strings::carriageReturn($path) : "") . $string, FILE_APPEND);
	}
	
	/*
		Para cache lo mejor sería usar PHP FAST CACHE 
		que,.... soporta varios drivers incluso REDIS

		https://www.phpfastcache.com/
	*/


	/*
		https://tqdev.com/2018-locking-file-cache-php
	*/
	static function file_put_contents_locking(string $filename, string $string, int $flags = LOCK_EX)
	{
		return file_put_contents($filename, $string, $flags);
	}

	// alias
	static function writter(string $filename, string $string, int $flags = LOCK_EX){
		return static::file_put_contents_locking($filename,$string, $flags);
	}

	/*
		https://tqdev.com/2018-locking-file-cache-php
	*/
	static function file_get_contents_locking(string $filename, int $flags = LOCK_SH)
	{
		$file = fopen($filename, 'rb');

		if ($file === false) {
			return false;
		}
		
		$lock = flock($file, $flags);
		
		if (!$lock) {
			fclose($file);
			return false;
		}
		
		$string = '';
		while (!feof($file)) {
			$string .= fread($file, 8192);
		}
		
		flock($file, LOCK_UN);
		fclose($file);
		
		return $string;
	}

	// alias
	static function reader(string $filename, int $flags = LOCK_SH){
		return static::file_get_contents_locking($filename, $flags);
	}

	static function read(string $path, bool $use_include_path = false, $context = null, int $offset = 0, $length = null){
		if (is_dir($path)){
			$path = realpath($path);
			throw new \InvalidArgumentException("$path is not a valid file. It's a directory!");
		} 

		if (!file_exists($path)){	
			return false;
		}

		if ($length !== null){
			$content = @file_get_contents($path, $use_include_path, $context, $offset, $length); // @
		} else {
			$content = @file_get_contents($path, $use_include_path, $context, $offset); // @
		}
		
		return $content;
	}

	static function readOrFail(string $path, bool $use_include_path = false, $context = null, int $offset = 0, $length = null){
		if (is_dir($path)){
			$path = realpath($path);
			throw new \InvalidArgumentException("$path is not a valid file. It's a directory!");
		} 

		if (!file_exists($path)){	
			throw new \InvalidArgumentException("Path '$path' does not exist!");
		}

		if ($length !== null){
			$content = @file_get_contents($path, $use_include_path, $context, $offset, $length); // @
		} else {
			$content = @file_get_contents($path, $use_include_path, $context, $offset); // @
		}
		
		if (strlen($content) === false){
			throw new \InvalidArgumentException("File '$path' can not be read");
		}

		return $content;
	}

	// alias
	static function getContent(string $path, bool $use_include_path = false, $context = null, int $offset = 0, $length = null){
		return static::read($path, $use_include_path, $context, $offset, $length);
	}

	// alias
	static function getContentOrFail(string $path, bool $use_include_path = false, $context = null, int $offset = 0, $length = null){
		return static::readOrFail($path, $use_include_path, $context, $offset, $length);
	}

	static function touch(string $filename, int $flags = 0){
		if (file_exists($filename)){
			return touch($filename);
		}

		return static::writeOrFail($filename, '', $flags) !== false;
	}

	static function getTempFilename(?string $extension = null){
		return sys_get_temp_dir(). DIRECTORY_SEPARATOR . Strings::randomString(60, false). ".$extension";
	}

	static function saveToTempFile($data, ?string $filename = null, $flags = null, $context = null){
		$filename = $filename ?? static::getTempFilename();
		static::writeOrFail($filename, $data, $flags, $context);

		return $filename;
	}

	static function getFromTempFile(string $filename){
		return static::readOrFail(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename);
	}

	static function fileExtension(string $filename){
		return Strings::last($filename, '.');
	}

	/*
		Mueve archivos y directorios de un directorio a otro

		Ej:

		Files::move($ori, $dst, ['license.txt']);

		Si el directorio destino no existe, se crea.

		@param string $exclude entradas (a ser excluidas). Solo a primer nivel.

		Ej:

		Files::move("some-path/plugins", "someother-path/plugins", [ "__MACOSX" ])
	*/
	static function move(string $from, string $to, ?array $exclude = null) {
		if (!is_dir($to)){
			Files::mkDirOrFail($to);
		} else {
			Files::isDirectoryWritableOrFail($to);
		}

		if (is_dir($from)) {
			if ($dh = opendir($from)) {
				while (($file = readdir($dh)) !== false) {
					if ($exclude !== null && in_array($file, $exclude)) {
						continue;
					}
					
					if ($file == "." || $file == "..") {
						continue;
					}
					
					$sourcePath      = $from . DIRECTORY_SEPARATOR . $file;
					$destinationPath = $to   . DIRECTORY_SEPARATOR . $file;
					
					//dd("Intentando mover $sourcePath a $destinationPath");

					if (!rename($sourcePath, $destinationPath)) {
						throw new \Exception("Failed to move file: $sourcePath");
					}
				}
				
				closedir($dh);
			}
		}
	}

	/**
	 * Check if a given URL extension matches the allowed extension(s).
	 *
	 * @param  string       $urlExtension The extension of the URL.
	 * @param  string|array $allowedExtensions The allowed extension(s) to match against.
	 * @return bool         Returns true if the URL extension matches any of the allowed extensions, false otherwise.
	 */
    static function matchExtension($urlExtension, $allowedExtensions) {
        if (is_array($allowedExtensions)) {
            return in_array($urlExtension, $allowedExtensions);
        } else {
            return $urlExtension === $allowedExtensions;
        }
    }

	/*
		Extrae el nombre de archivo de una url 
		(que podria coincidir con el ultimo segmento)
	*/
	static function getFilenameFromURL(string $url){
		return Strings::beforeIfContains(Strings::lastSegmentOrFail($url, '/'), '?');
	}

	/*
		Devuelve la extension de un archivo
		
		Funciona con paths y tambien con urls del tipo "https://some-domain.com/css/fontawesome.css?v=1"
	*/
	static function getExtension(string $file){
		return Strings::beforeIfContains(pathinfo($file, PATHINFO_EXTENSION), '?');
	}

	static function varExport($data, string $path, $variable = null){
		if ($variable === null){
			$bytes = Files::writeOrFail($path, '<?php '. "\r\n\r\n" . 'return ' . var_export($data, true). ';');
		} else {
			if (!Strings::startsWith('$', $variable)){
				$variable = '$'. $variable;
			}
			
			$bytes = Files::writeOrFail($path, '<?php '. "\r\n\r\n" . $variable . ' = ' . var_export($data, true). ';');
		}

		return ($bytes > 0);
	}

	static function JSONExport($data, string $path, bool $pretty = false){
		if (is_dir($path)){
			$path = Strings::trimTrailingSlash($path) . DIRECTORY_SEPARATOR . 'export.json';
		}

		$flags = JSON_UNESCAPED_SLASHES;

		if ($pretty){
			$flags = $flags|JSON_PRETTY_PRINT;
		}

		$bytes = Files::writeOrFail($path, json_encode($data, $flags));
		return ($bytes > 0);
	}
}   


