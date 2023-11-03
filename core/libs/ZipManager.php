<?php

namespace simplerest\core\libs;

class ZipManager 
{
    static $include_macosx_dir = true;

    static function setIncludeMacOSXDir(bool $include = false) {
        static::$include_macosx_dir = $include;
    }

    /*
        https://stackoverflow.com/a/1334949/980631

        Modified version by @boctulus

        TODO

        Si no esta presente la extension, intentar usar el comando zip
    */
    static function zip(string $ori, string $dst, ?Array $exclude = null, bool $overwrite = true)
    {
        if (!extension_loaded('zip') || !file_exists($ori)) {
            return false;
        }
    
        $zip = new \ZipArchive();
        if (!$zip->open($dst, $overwrite && file_exists($dst) ? \ZipArchive::OVERWRITE : \ZipArchive::CREATE)) {
            return false;
        }
    
        if (is_null($exclude)){
            $exclude = [];
        }

        $ori = str_replace('\\', '/', realpath($ori));
    
        if (is_dir($ori) === true)
        {
            $new_excluded = [];
            foreach ($exclude as $ix => $file){
                if (!Files::isAbsolutePath($file)){
                    $exclude[$ix] = Files::getAbsolutePath($file, $ori);
                }

                if (is_dir($exclude[$ix])){
                    $new_excluded = array_merge($new_excluded, Files::recursiveGlob($exclude[$ix] . '/*'));  
                }
            }

            $exclude = array_merge(array_values($exclude), array_values($new_excluded));

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ori), \RecursiveIteratorIterator::SELF_FIRST);
    
            foreach ($files as $file)
            {
                $file = str_replace('\\', '/', $file);
    
                // Ignore "." and ".." folders
                if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                    continue;
    
                $file = realpath($file);
    
                if (!empty($exclude) && in_array($file, $exclude)){
                    continue;
                }

                if (is_dir($file) === true && !in_array($file, $exclude))
                {
                    $zip->addEmptyDir(str_replace($ori . '/', '', $file . '/'));
                }
                else if (is_file($file) === true)
                {
                    $zip->addFromString(str_replace($ori . '/', '', $file), file_get_contents($file));
                }
            }
        }
        else if (is_file($ori) === true)
        {
            $zip->addFromString(basename($ori), file_get_contents($ori));
        }
    
        return $zip->close();
    }

    protected static function isUnzipCommandAvailable() {
        // Verificar si el comando unzip está disponible en el sistema
        $output = [];
        $return_var = 0;
        exec('unzip -v', $output, $return_var);
        
        return $return_var === 0;
    }

    static function verifyUnzip(string $zipFile, string $destination)
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) === true) {
            $extractedFiles = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $extractedFiles[] = $zip->getNameIndex($i);
            }

            $zip->close();

            foreach ($extractedFiles as $file) {
                $extractedFilePath = $destination . '/' . $file;
                if (!file_exists($extractedFilePath)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /*
        TODO

        Implementar $overwrite
    */
    public static function unzip(string $file_path, $destination = null, bool $verify = true) {
        // Utilizar la ruta de destino si se proporciona
        if ($destination !== null) {
            $destination_folder = rtrim($destination, '/');
        } else {
            $destination_folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('unzip_');
        }
    
        // Verificar si la extensión ZipArchive está disponible
        if (extension_loaded('zip')) {
            $zip = new \ZipArchive();
            
            // Abrir el archivo zip
            if ($zip->open($file_path) !== true) {
                throw new \Exception("ZIP file was unable to be opened");                
            }
    
            $extraction_folder = rtrim($zip->getNameIndex(0), '/');
            $extraction_folder = Strings::beforeIfContains($extraction_folder, '/'); // parche -probado en Windows-
            $extraction_folder = trim($extraction_folder);
    
            // Extraer los archivos en la carpeta de destino
            $zip->extractTo($destination_folder);
            $zip->close();
    
            if ($verify && !static::verifyUnzip($file_path, $destination)){
                throw new \Exception("Uncompression failed or finished with errors");                    
            }
    
            if (static::$include_macosx_dir === false) {
                // Eliminar la carpeta "__MACOSX" si existe en la ruta de destino
                $macosx_dir = $destination_folder . '__MACOSX';

                if (is_dir($macosx_dir)) {
                    Files::delTree($macosx_dir, true);
                }
            }

            // Retornar la ruta de la carpeta de destino
            return $extraction_folder;
        }
        
        // Verificar si el comando unzip está disponible
        if (self::isUnzipCommandAvailable()) {
            // ...
        }
        
        // Lanzar excepción si ninguna opción está disponible
        throw new \Exception('No se puede descomprimir el archivo. El servicio no está disponible.');
    }

}


