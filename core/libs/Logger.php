<?php declare(strict_types=1);

namespace simplerest\core\libs;;

use simplerest\core\libs\Files;

/*
	Idealmente implementar PSR 3 logger

	https://www.php-fig.org/psr/psr-3/
*/
class Logger
{
    static $logFile = 'log.txt';

    static function getLogFilename(bool $full_path = false)
    {
        if (static::$logFile == null){
            static::$logFile = config()['log_file'];
        }

        return ($full_path ? LOGS_PATH : '') . static::$logFile;
    }
    
    static function truncate($log_file = null){
        Files::writeOrFail(LOGS_PATH . ($log_file ?? static::getLogFilename()), '');
    }
    
    static function getContent(?string $file = null){
        if ($file == null){
	        $file = static::getLogFilename();
        }

        $path = LOGS_PATH . $file;

		if (!file_exists($path)){
			return false;
		}

		return Files::readOrFail($path);
    }


	/*
		Resultado:

		<?php 

		$arr = array (
		'x' => 'Z',
		);
	*/
	static function varExport($data, $path = null, $variable = null){
		if ($path === null){
			$path = LOGS_PATH . 'export.php';
		} else {
			if (!Strings::contains('/', $path) && !Strings::contains(DIRECTORY_SEPARATOR, $path)){
				$path = LOGS_PATH . $path;
			}
		}

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

	static function JSONExport($data, ?string $path = null, bool $pretty = false){
		if ($path === null){
			$path = LOGS_PATH . 'exported.json';
		} else {
			if (!Strings::contains('/', $path) && !Strings::contains(DIRECTORY_SEPARATOR, $path)){
				$path = LOGS_PATH . $path;
			}
		}

		$flags = JSON_UNESCAPED_SLASHES;

		if ($pretty){
			$flags = $flags|JSON_PRETTY_PRINT;
		}

		$bytes = Files::writeOrFail($path, json_encode($data, $flags));
		return ($bytes > 0);
	}

	static function log($data, ?string $path = null, $append = true, bool $extra_cr = false){	
		if ($path === null){
			$path = LOGS_PATH . static::getLogFilename();
		} else {
			if (!Strings::contains('/', $path) && !Strings::contains(DIRECTORY_SEPARATOR, $path)){
				$path = LOGS_PATH . $path;
			}
		}

		if (is_array($data) || is_object($data))
			$data = json_encode($data, JSON_UNESCAPED_SLASHES);
		
		$data = date("Y-m-d H:i:s"). "\t" .$data;

		return Files::writeOrFail($path, $data. "\n" . ($extra_cr ? "\n" : ""),  $append ? FILE_APPEND : 0);
	}

	static function dump($object, ?string $path = null, $append = false){
		if ($path === null){
			$path = LOGS_PATH . static::getLogFilename();
		} else {
			if (!Strings::contains('/', $path) && !Strings::contains(DIRECTORY_SEPARATOR, $path)){
				$path = LOGS_PATH . $path;
			}
		}

		if ($append){
			Files::writeOrFail($path, var_export($object,  true) . "\n", FILE_APPEND);
		} else {
			Files::writeOrFail($path, var_export($object,  true) . "\n");
		}		
	}

	static function dd($data, $msg, bool $append = true){
		static::log([$msg => $data], null, $append);
	}

	static function logError($error){
		if ($error instanceof \Exception){
			$error = $error->getMessage();
		}

		static::log($error, 'errors.txt');
	}

	static function logSQL(string $sql_str){
		static::log($sql_str, 'sql_log.txt');
	}

}

