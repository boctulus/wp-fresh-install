<?php declare(strict_types=1);

namespace simplerest\core\libs;

use simplerest\core\libs\Url; 

// Dumper
class VarDump
{
	public static $render       = true;
	public static $render_trace = false;

	static function p(){
		return (php_sapi_name() == 'cli' || Url::isPostmanOrInsomnia()) ? PHP_EOL . PHP_EOL : '<p/>';
	}

	static function br(){
		return (php_sapi_name() == 'cli' || Url::isPostmanOrInsomnia())  ? PHP_EOL : '<br/>';;
	}

	protected static function pre(callable $fn, ...$args){
		echo '<pre>';
		$fn($args);
		echo '</pre>';
	}

	protected static function export($v = null, $msg = null, bool $additional_carriage_return = false, bool $msg_at_top = true) 
	{	
		$type = gettype($v);

		$postman = Url::isPostmanOrInsomnia();
		
		$cli     = (php_sapi_name() == 'cli');
		$br      = static::br();
		$p       = static::p();

		$pre = !$cli;	

		if ($postman || $type != 'array'){
			$pre = false;
		}
		
		$fn = function($x) use ($type, $postman, $pre){
			$pp = function ($fn, $dato) use ($pre){
				if ($pre){
					self::pre(function() use ($fn, $dato){ 
						$fn($dato);
					});
				} else {
					$fn($dato);
				}
			};

			switch ($type){
				case 'boolean':
				case 'string':
				case 'double':
				case 'float':
					$pp('print_r', $x);
					break;
				case 'array':
					if ($postman){
						$pp('var_export', $x);
					} else {
						$pp('print_r', $x);
					}
					break;	
				case 'integer':
					$pp('var_export', $x);
					break;
				default:
				$pp('var_dump', $x);
			}	
		};
		
		if ($type == 'boolean'){
			$v = $v ? 'true' : 'false';
		}	

		if ($msg_at_top && !empty($msg)){
			$cfg = config();
			$ini = $cfg['var_dump_separators']['start'] ?? '--| ';
			$end = $cfg['var_dump_separators']['end']   ?? '';

			echo "{$ini}$msg{$end}". (!$pre ? $br : '');
		}
			
		$fn($v);			
	
		switch ($type){
			case 'boolean':
			case 'string':
			case 'double':
			case 'float':	
			case 'integer':
				$include_break = true;
				break;
			case 'array':
				$include_break = $postman;
				break;	
			default:
				$include_break = false;
		}	

		if (!$msg_at_top && !empty($msg)){
			$cfg = config();
			$ini = $cfg['var_dump_separators']['start'] ?? '--| ';
			$end = $cfg['var_dump_separators']['end']   ?? '';

			echo "{$ini}$msg{$end}". (!$pre ? $br : '');
		}

		if (!$cli && !$postman && $type != 'array'){
			echo $br;
		}

		if ($include_break && ($cli ||$postman)){
			echo $br;
		}

		if ($additional_carriage_return){
			echo $br;
		}
	}	

	static public function dd($val = null, $msg = null, bool $additional_carriage_return = false, bool $msg_at_top = true)
	{
		if (!static::$render){
			return;
		}

		//var_dump(static::$render_trace);

		if (static::$render_trace){
			$file = debug_backtrace()[1]['file'];
			$line = debug_backtrace()[1]['line'];
		
			static::export("{$file}:{$line}", "LOCATION", true, $msg_at_top);
		}

		self::export($val, $msg, $additional_carriage_return, $msg_at_top);
	}

	static function hideResponse(){
        self::$render = false;
    }

    static function showResponse(bool $status = true){
        self::$render = $status;
    }

	static function hideTrace(){
        self::$render_trace = false;
    }

    static function showTrace(bool $status = true){
        self::$render_trace = $status;
    }
}