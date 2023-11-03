<?php

namespace simplerest\core\libs;

use simplerest\core\libs\Strings;
use simplerest\core\libs\ApiClient;

class Url
{
    static function validate(string $url){
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    static function validateOrFail(string $url){
        if (!filter_var($url, FILTER_VALIDATE_URL)){
            throw new \InvalidArgumentException("URL '$url' is invalid");
        }
    }

    static function inArray(array $links, $link) {
        foreach ($links as $existingLink) {
            $existingLinkId = parse_url($existingLink, PHP_URL_QUERY);
            $linkId = parse_url($link, PHP_URL_QUERY);

            if ($existingLinkId === $linkId) {
                return true;
            }
        }
        return false;
    }
    
    static function isValid(string $url){
        if (!Strings::startsWith('http://', $url) && !Strings::startsWith('https://', $url)){
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL);
    }

    /*
        Obtiene la url final luego de redirecciones

        (no siempre funciona)

        https://stackoverflow.com/a/7555543/980631
    */
    static function getFinalUrl($url) {
        stream_context_set_default(array(
            'http' => array(
                'method' => 'HEAD'
            )
        ));

        $headers = get_headers($url, 1);

        if ($headers !== false && isset($headers['Location'])) {
            return $headers['Location'];
        }

        return false;
    }

    static function lastSlug(string $url){
		return $slug =  Strings::last(rtrim($url, '/'), '/');
	}

     /*
        Normaliza urls a fin de que así el "path" de la url termine o no con "/",
        queden sin era barra antes de la parte de queries con lo cual
        al momento de "cachear" no habrá duplicados.

        Ej:

        https://www.easyfarma.cl/categoria-producto/dermatologia/proteccion-solar/?page=2

        es convertido en

        https://www.easyfarma.cl/categoria-producto/dermatologia/proteccion-solar?page=2

    */
    static function normalize(string $url){
        if (!Strings::startsWith('http', $url)){
            throw new \InvalidArgumentException("Invalid url '$url'");
        }

        $p = parse_url($url);

        $p['path'] = rtrim($p['path'], '/');
        $query     = isset($p['query']) ? "?{$p['query']}" : '';

        return "{$p['scheme']}://{$p['host']}{$p['path']}$query";
    }

    // Body decode
    static function bodyDecode(string $data)
    {
        $headers  = apache_request_headers();
        $content_type = $headers['Content-Type'] ?? null;

        if (!empty($content_type)){
            // Podría ser un switch-case aceptando otros MIMEs

            if (Strings::contains('application/x-www-form-urlencoded', $headers['Content-Type'])){
                $data = urldecode($data);
                $data = Url::parseStrQuery($data);

            } else {
                $data = json_decode($data, true);

                if ($data === null) {
                    throw new \Exception("JSON inválido");
                }
            }

        }

        return $data;
    }

    /*
		Patch for parse_str() native function

		It could be more efficient and precise if I use a preg_replace_callback and
		take note about which parameter was substituted
	*/
	static function parseStrQuery(string $s) : Array{
		$rep = '__DOT__';

		$s = str_replace('.', $rep, $s);

		parse_str($s, $result);

		foreach ($result as $k => $v){
			$pos = strpos($k, $rep);

			if ($pos !== false){
				$k2 =  str_replace($rep, '.', $k);
				$result[$k2] = $v;
				unset($result[$k]);
			} else {
                // parche 2022
                $result[$k] = str_replace($rep, '.', $result[$k]);
            }
		}

		return $result;
	}

    static function getSlugs($url = null, $as_string = false){
        $url          = $url ?? static::currentUrl();

        $segments_str = parse_url($url, PHP_URL_PATH);
        $segments_str = Strings::rTrim('/', Strings::lTrim('/', $segments_str));

        if ($as_string){
            return (trim($segments_str) !== '' ? '/' : '') . $segments_str;
        }
    
        $slugs = explode('/', $segments_str);
        
        if (count($slugs) === 1 && empty(trim($slugs[0]))){
            $slugs = [];
        }

        return $slugs;
    }

	static function queryString($url = null) : Array {
        if ($url !== null){
            return static::parseStrQuery($url);
    }

        if (!isset($_SERVER['QUERY_STRING'])){
            return [];
        }

		return static::parseStrQuery($_SERVER['QUERY_STRING']);
	}


    static function query($url = null){
        return static::queryString($url);
    }
  
    /*
        Si esta cerrado el puerto 443 puede demorar demasiado en contestar !
    */
    static function hasSSL( $domain ) {
        /*
            Si el puerto 443 esta cerrado,....

            Warning: fsockopen(): unable to connect to ssl://{dominio}:443
            (No se puede establecer una conexión ya que el equipo de destino denegó expresamente dicha conexión)
        */

        $ssl_check = @fsockopen( 'ssl://' . $domain, 443, $errno, $errstr, 30 );
        
        if ( $ssl_check ) { 
            fclose( $ssl_check ); 
        }
        
        return (bool) $ssl_check;
    }

    /*
        https://gist.github.com/jubalm/3447495
    */
    static function isSSL() {
        if ( isset($_SERVER['HTTPS']) ) {
            if ( 'on' == strtolower($_SERVER['HTTPS']) )
                return true;
        
            if ( '1' == $_SERVER['HTTPS'] )
                return true;
        } elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
            return true;
        
        }

        return false;
    }

    static function httpProtocol(){
        $config = config();

        if (isset($config['https']) && $config['https'] != null){
            $is_ssl = ($config['https'] && !strtolower($config['https']) == 'off');
        } else {
            /*
                Chequear si isSSL() funciona bien porque hasSSL() puede quedarse esperando si el puerto esta cerrado
            */

            //$is_ssl = self::hasSSL($_SERVER['HTTP_HOST'])
           $is_ssl = static::isSSL();
        }

        return $is_ssl ? 'https' : 'http';
    }

    static function getProtocol(?string $url = null){
        if (empty($url)){
            return static::httpProtocol();
        }
       
        if (Strings::startsWith('http://', $url)){
            return 'http';
        }

        if (Strings::startsWith('https://', $url)){
            return 'https';
        }

        return null;
    }

    static function getProtocolOrFail(?string $url = null){
        $protocol = static::getProtocol($url);

        if (empty($protocol)){
            throw new \InvalidArgumentException("Impossible to determinatte if the protocol is http or https");
        }

        return $protocol;
    }
    
    /*
        Devuelve algo como:

        Host: http://simplerest.lan:80
    */
    static function constructHostHeader() {
        $host     = static::getHostname();
        $protocol = static::httpProtocol();
    
        $port = ($protocol == 'https') ? '443' : '80'; // default port for http and https
        $header = "Host: $host:$port\r\n";
        return $header;
    }  

    /**
     * urlCheck - complement for parse_url
     *
     * @param  string $url
     *
     * @return bool
     */
    static function urlCheck(string $url){
        $sym = null;

        $len = strlen($url);
        for ($i=0; $i<$len; $i++){
            if ($url[$i] == '?'){
                if ($sym == '?' || $sym == '&')
                    return false;

                $sym = '?';
            }elseif ($url[$i] == '&'){
                if ($sym === null)
                    return false;

                $sym = '&';
            }
        }
        return true;
    }

    static function isPostman(){
        static $is;

        if ($is !== null){
            return $is;
        }

        if (!isset($_SERVER['HTTP_USER_AGENT'])){
            $is = false;
            return $is;
        }

		$is = Strings::startsWith('Postman', $_SERVER['HTTP_USER_AGENT']);
        return $is;
	}

    static function isInsomnia(){
        static $is;

        if ($is !== null){
            return $is;
        }

        if (!isset($_SERVER['HTTP_USER_AGENT'])){
            $is = false;
            return $is;
        }

        $is = Strings::startsWith('insomnia', $_SERVER['HTTP_USER_AGENT']);
        return $is;
    }

    static function isPostmanOrInsomnia(){
		static $is;

		if ($is !== null){
			return $is;
		}

		$is = static::isPostman() || static::isInsomnia();

		return $is;
	}


    /*
        Usar antes de parseStrQuery()
    */
    static function hasQueryParam(string $url, ?string $param = null){
        if ($param === null){
            return !empty(Strings::after($url, '?'));
        }

        $p = parse_url($url);

        if (!isset($p['query'])){
            return null;
        }

        $query_arr = static::parseStrQuery($p['query']);

        return isset($query_arr[$param]);
    }

    static function getQueryString(string $url){
        return Strings::after($url, '?');
    }

    /*
        Uso:

        Si se envia algo como 'http://google.com?x=3&y=4'

        retorna algo como:

        Array
        (
            [x] => 3
            [y] => 4
        )
    */
    static function getQueryParams(string $url){
        $str = static::getQueryString($url);

        if (empty($str)){
            return [];
        }

        parse_str($str, $output);

        return $output;
    }

    /*
        @return string|array
    */
    static function getQueryParam(string $url = null, $param = null, $autodecode = true) {
        if (empty($url)){
            $url = static::currentUrl();
        }

        if ($param === null){
            return static::getQueryParams($url);
        }

        if (!Strings::startsWith('http', $url)){
            throw new \InvalidArgumentException("URL '$url' is invalid");
        }

        $query = parse_url($url, PHP_URL_QUERY);

        $x = null;
        if ($query != null){
            $q = explode('&', $query);
            foreach($q as $p){
                if (Strings::startsWith($param . '=', $p)){
                    $_x = explode('=', $p);
                    $x  = $_x[count($_x)-1];
                }
            }
        }

        if ($autodecode && $x !== null && Strings::contains('%2F', $x)){
            $x = urldecode($x);
        }

        return $x;
    }

    static function encodeParams(array $data, string $numeric_prefix = "", ?string $arg_separator = '&', int $encoding_type = PHP_QUERY_RFC1738){
        return http_build_query($data, $numeric_prefix, $arg_separator, $encoding_type);
    }

    static function buildUrl(string $base_url, array $data, string $numeric_prefix = "", ?string $arg_separator = null, int $encoding_type = PHP_QUERY_RFC1738){
        return  Strings::removeTrailingSlash($base_url) . '?'. static::encodeParams($data);
    }
    
    /*
        Agrega o cambia un parametro en una url

        Ej:

        echo Url::addQueryParam('http://simplerest.lan/api/v1/products', 'q', 'fiesta') . "\n";
        echo Url::addQueryParam('http://simplerest.lan/api/v1/products?v=1', 'q', 'fiesta') . "\n";
        echo Url::addQueryParam('http://simplerest.lan/api/v1/products?v=1', 'v', '3') . "\n";
    */
    public static function addQueryParam(string $url, $param_name, $param_value) {
        $parsed_url = Strings::before($url, '?');
        $query_arr  = Url::getQueryParams($url);

        $query_arr[$param_name] = $param_value;
     
        return static::buildUrl($parsed_url, $query_arr);
    }

    static function currentUrl(){
        if (is_cli()){
            return '';
        }

        $actual_link = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        return $actual_link;
    }

    static function getCurrentUrl(){
        return static::currentUrl();
    }

    static function getHostname($url = null, bool $include_protocol = false)
    {
        if (is_cli() && empty($url)){
            return config()['app_url'];
        }

        if (is_null($url)){
            $url = static::currentUrl();
        }

        $url_info = parse_url($url);

        if (!isset($url_info['path'])){
            throw new \Exception("URL invalid");
        }

        $hostname = ($url_info['host'] ?? '') . (isset($url_info['port']) ? ':'. $url_info['port'] : '');

        if ($include_protocol){
            return $url_info['scheme'] . '://' . $hostname;
        } else {
            return $hostname;
        }
    }

     /*
        Salida:

        https://practicatest.cl
    */
    static function getBaseUrl(?string $url = null){
        return static::getHostname($url, true);
    }

    /*
        Salida:

        practicatest.cl
    */
    static function getDomain(?string $url = null){
        return static::getHostname($url, false);
    }

    /*
        Wrapper sobre curl y file_get_contents()

        Le da prioridad a file_get_contents() ya que si el certificado no es valido es mejor usar ApiClient que bypasearlo

        Se limito a casos donde la respuesta tiene http code igual a 200
    */
    static function getUrlContent(string $url, bool $json_decode = false, bool $ignore_ssl_cert = false){
        $allow_url_open = Files::isAllowUrlFopenEnabled();
        $curl_available = Files::isCurlAvailable();

        if (!$allow_url_open && !$curl_available){
            throw new \Exception("No way to get url contents");
        }

        if ($allow_url_open){
            $res = file_get_contents($url);

            if ($json_decode){
                return json_decode($res, true);
            }
        }        
    
        $client = new ApiClient($url);

        $res = $client
        ->setDecode($json_decode)
        ->when($ignore_ssl_cert, function($c){
            $c->disableSSL();
        })
        ->get();

        $http_code = $res->getStatus();

        if ($http_code != 200){
            throw new \Exception("Http status code: $http_code. Expected: 200. Error: {$res->getError()}");
        }

       return $res->getResponse()['data'];
    }

    static function download(string $url, $dest_path = null, bool $disable_ssl = true, Array $options = []){
        if (empty($dest_path)){
            $dest_path = STORAGE_PATH;
        }
    
        $client = new ApiClient($url);

        $options = array_merge([
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/48.0.2564.82 Safari/537.36',
            CURLOPT_HEADER    => true,
            CURLOPT_VERBOSE   => true
        ], $options);

        $client->addOptions([
            $options
        ]);

        $client
        ->when($disable_ssl, function($questo){
            $questo->disableSSL();
        })
        ->get();

        $res = $client->getResponse(false);

        if (!empty($res) && isset($res['data'])){
            $data     = $res['data'];
            $filename = $client->getFilename();

            if (empty($filename)){
                throw new \Exception("Filename not found");
            }

            $bytes = file_put_contents($dest_path . $filename, $data);

            return ($bytes !== 0);
        }

        return false;
    }

    static function cache(string $url, bool $force_reload = false, bool $fail_if_zero_length = false){
        $str  = str_replace(['%'], ['p'], urlencode(Url::normalize($url))) . '.html';
        $str  = str_replace('/', '', $str);

		// quizas mejor usar tmpfile()
		// https://www.php.net/manual/en/function.tmpfile.php
		//
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $str;

        if (file_exists($path) && $force_reload === false){
            return file_get_contents($path);
        }

		$client = new ApiClient();
        $res    = $client->consumeApi($url, 'GET', null, null, null, false);
		
		if ($res['http_code'] != 200){
			return;
		}

		$str = $res['data'];

		if ($str === null || strlen($str) == 0){
			if ($fail_if_zero_length){
				throw new \Exception("Zero length file");
			}
		}
        
        file_put_contents($path, $str);

        return $str;
    }

    /*
        https://stackoverflow.com/a/13646735/980631
    */
    static function getVisitorIP()
    {
        // Get real visitor IP behind CloudFlare network
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                  $_SERVER['REMOTE_ADDR']    = $_SERVER["HTTP_CF_CONNECTING_IP"];
                  $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];
    
        if(filter_var($client, FILTER_VALIDATE_IP))
        {
            $ip = $client;
        }
        elseif(filter_var($forward, FILTER_VALIDATE_IP))
        {
            $ip = $forward;
        }
        else
        {
            $ip = $remote;
        }
    
        return $ip;
    }
   
    // alias
    static function ip(){
        return static::getVisitorIP();
    }
}

