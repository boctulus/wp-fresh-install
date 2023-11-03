<?php declare(strict_types=1);

namespace simplerest\core\libs;

class System
{
    static $res_code;

    static function getOS(){
        return defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : PHP_OS;
    }

    static function isLinux(){
        $os = static::getOS();

        return ($os == 'Linux');
    }

    static function isWindows(){
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
            return true;
        }

        $os = static::getOS();

        return ($os == 'Windows' || $os == 'WIN32' || $os == 'WINNT');
    }

    static function isUnix(){
        $os = static::getOS();

        return (in_array($os, ['Linux', 'BSD', 'Darwin', ' NetBSD', 'FreeBSD', 'Solaris']));
    }

    /*
        Es el server IIS ?
    */
    function isIIS() {
        $server_software = strtolower( $_SERVER['SERVER_SOFTWARE'] );
        if ( strpos( $server_software, 'microsoft-iis') !== false ) {
            return true;
        }
    
        return false;
    }

    // https://www.php.net/manual/en/function.is-executable.php#123883
    static function isExecutableInPath(string $filename) : bool
    {
        if (is_executable($filename)) {
            return true;
        }

        if ($filename !== basename($filename)) {
            return false;
        }

        $paths = explode(PATH_SEPARATOR, getenv("PATH"));
        
        foreach ($paths as $path) {

            $f = $path . DIRECTORY_SEPARATOR . $filename;

            if (is_executable($f)) {
                return true;
            }
        }

        return false;
    }

    /*
        Returns PHP path
        as it is needed to be used with runInBackground()

        Pre-requisito: php.exe debe estar en el PATH
    */  
    static function getPHP(){
        static $location;

        if ($location !== null){
            return $location;
        }

        $location =  trim(System::isWindows() ? shell_exec("where php.exe") : "php");
        
        return  $location;
    }

    /*
        https://factory.dev/pimcore-knowledge-base/how-to/execute-php-pimcore

        Ver tambi'en
        https://gist.github.com/damienalexandre/1300820
        https://stackoverflow.com/questions/13257571/call-command-vs-start-with-wait-option
    */
    static function runInBackground(string $cmd, string $output_path = null, $ignore_user_abort = true, int $execution_time = 0)
    {
        ignore_user_abort($ignore_user_abort);
        set_time_limit($execution_time);

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                if ($output_path !== null){
                    $cmd .= " >> $output_path";
                }
    
                $WshShell = new \COM("WScript.Shell");
                $oExec = $WshShell->Exec($cmd);
                $pid = (int) $oExec->ProcessID;
                $WshShell = null;
    
                break;
            case 'Linux':
                if ($output_path !== null){
                    $pid = (int) shell_exec("nohup nice -n 19 $cmd > $output_path 2>&1 & echo $!");
                } else {
                    $pid = (int) shell_exec("nohup nice -n 19 $cmd > /dev/null 2>&1 & echo $!");
                }

                break;
            default:
            // unsupported
            return false;
        }

        return $pid ?? null;
    }

    static function exec($command, ...$args){
        $extra = implode(' ', array_values($args));

        exec("$command $extra", $ret, static::$res_code);
        
        return $ret;
    }

    /*
        Ejecuta un comando / script situandose primero en el root del proyecto
    */
    static function execAtRoot($command, ...$args){
        $extra = implode(' ', array_values($args));

        $current_dir = getcwd();

		chdir(ROOT_PATH);
        exec("$command $extra", $ret, static::$res_code);
        chdir($current_dir);
        
        return $ret;
    }

    static function resultCode(){
        return static::$res_code;
    }

    /*
        Ejecuta un comando "com"
    */
    static function com($command, ...$args){
        return static::execAtRoot(static::getPHP() . " com $command", ...$args);
    }


    /*        
       "Memory profilers"
        
        - Xhprof PHP Memory Profiler
        
        XHprof has a simple user interface that will help you discover PHP memory leaks. It can also identify the performance issues that make PHP memory leaks happen.

        - Xdebug PHP Profiler
        
        XDebug is a standard PHP profiler that you can use to discover a variety of performance issues in your scripts. The lightweight profiler doesn’t use much memory, so you can run it alongside your PHP scripts for real-time performance debugging.

        - PHP-memprof
        
        PHP-memprof is a stand-alone PHP memory profiler that can tell you exactly how much memory each of your functions uses. It can even trace an allocated byte back to a function.

        - New Relic
    */

    /**
     * Determines whether a PHP ini value is changeable at runtime.
     *
     * Tomado del core de WordPress
     * 
     * Uso. Ej:
     * 
     * System::isINIChangeable('memory_limit') === false
     *
     * @link https://www.php.net/manual/en/function.ini-get-all.php
     *
     * @param string $setting The name of the ini setting to check.
     * @return bool True if the value is changeable at runtime. False otherwise.
     */
    static function isINIChangeable(string $setting) {
        static $ini_all;

        if ( ! isset( $ini_all ) ) {
            $ini_all = false;
            // Sometimes `ini_get_all()` is disabled via the `disable_functions` option for "security purposes".
            if ( function_exists( 'ini_get_all' ) ) {
                $ini_all = ini_get_all();
            }
        }

        // Bit operator to workaround https://bugs.php.net/bug.php?id=44936 which changes access level to 63 in PHP 5.2.6 - 5.2.17.
        if ( isset( $ini_all[ $setting ]['access'] ) && ( INI_ALL === ( $ini_all[ $setting ]['access'] & 7 ) || INI_USER === ( $ini_all[ $setting ]['access'] & 7 ) ) ) {
            return true;
        }

        // If we were unable to retrieve the details, fail gracefully to assume it's changeable.
        if ( ! is_array( $ini_all ) ) {
            return true;
        }

        return false;
    }


    /*
        dd(System::getMemoryLimit(), 'Memory limit');
    */
    static function getMemoryLimit()
    {
        return ini_get('memory_limit');
    }

    /*
        Ej:

        setMemoryLimit('768M');
    */
    static function setMemoryLimit(string $limit)
    {
        if (!static::isINIChangeable('memory_limit')){
            return false;
        }

        ini_set('memory_limit', $limit);
    }

    /*
        dd(System::getMemoryUsage(), 'Memory usage');
        dd(System::getMemoryUsage(true), 'Memory usage (real)');
    */
    static function getMemoryUsage(bool $real_usage = false){
        return (round(memory_get_usage($real_usage) / 1048576,2)) . 'M'; 
    }

    /*      
        dd(System::getMemoryPeakUsage(), 'Memory peak usage');
        dd(System::getMemoryPeakUsage(true), 'Memory peak usage (real)');
    */
    static function getMemoryPeakUsage(bool $real_usage = false){
        return (round(memory_get_peak_usage($real_usage) / 1048576, 2)) . 'M';
    }
}

