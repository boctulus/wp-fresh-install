<?php declare(strict_types=1);

namespace simplerest\core\libs;

class StdOut
{
    static $render = true;
    static $path;
    static $log_includes_datetime;

    static function pprint($v, bool $additional_carriage_return = false){
        if (static::$path !== null){
            ob_start();
            d($v, null, $additional_carriage_return);
            $content = ob_get_contents();
            ob_end_clean();

            if (static::$log_includes_datetime){
                $content = at(). "\t" . $content;
            }

            file_put_contents(static::$path, $content, FILE_APPEND);
        }

        if (static::$render){
            dd($v, null, $additional_carriage_return);
        }
    }

    static function toFile(string $path, bool $only = true){
        static::$path   = $path;
        static::$render = !$only; 
    }

    static function toLog(bool $only = true, bool $include_datetime = true){
        static::$log_includes_datetime = $include_datetime;
        static::toFile(LOGS_PATH . '/' . config()['log_file'], $only);
    }

    static function hideResponse(){
        self::$render = false;
    }

    static function showResponse(bool $status = true){
        self::$render = $status;
    }
}

