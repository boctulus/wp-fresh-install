<?php

namespace simplerest\core\libs;

class FileCache extends Cache
{
    /*
        La $key puede ser una url o el nombre de un archivo
    */
    static function getCachePath(string $key) : string {
        static $path;

        if (isset($path[$key])){
            return $path[$key];
        }

        $filename = sha1($key);

        $path[$key] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename . '.cache';
        return $path[$key];
    }

    /*
        Logica para saber si un archivo usado como cache ha expirado

        Si se ha utilizado put() debe usarse con $was_serialized = 1
    */
    static function expiredFile(string $path, ?int $expiration_time = null, bool $was_serialized = false) : bool {
        $exists = file_exists($path);

        if (!$exists){
            return true;
        }

        if (!$was_serialized){
            if ($expiration_time !== null){
                $updated_at = filemtime($path);

                if (static::expired($updated_at, $expiration_time)){
                    // Cache has expired, delete the file
                    unlink($path);
                
                    return true;
                }
            }
            
            return false;
        }

        $content = file_get_contents($path);
        $data    = unserialize($content);

        if ($data['expires_at'] < time()) {
            // Cache has expired, delete the file
            unlink($path);

            return true;
        }

        return false;
    }

    /*
        @param string $key
        @param mixed  $value    
        @param int    $exp_time en segundos
    */
    static function put($key, $value, $exp_time)
    {
        $path = static::getCachePath($key);
        $expiresAt = time() + ($exp_time);
        $data = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
        $content = serialize($data);

        if (file_put_contents($path, $content) !== false) {
            return true;
        }

        return false;
    }

    static function get($key, $default = null)
    {
        $path = static::getCachePath($key);

        if (!file_exists($path)) {
            return $default;
        }

        $content = file_get_contents($path);
        $data    = unserialize($content);

        if ($data['expires_at'] < time()) {
            // Cache has expired, delete the file
            unlink($path);
            return $default;
        }

        return $data['value'];
    }

    static function forget($key)
    {
        $path = static::getCachePath($key);

        if (!file_exists($path)) {
            return;
        }
        
        unlink($path);
    }
}

