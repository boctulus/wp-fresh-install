<?php

use simplerest\core\libs\StdOut;

function is_cli(){
	return (php_sapi_name() == 'cli');
}

function is_unix(){
	return (DIRECTORY_SEPARATOR === '/');
}

/*
	Tiempo en segundos de sleep

	Acepta valores decimales. Ej: 0.7 o 1.3
*/
function nap($time, $echo = false){
	if ($echo){
		StdOut::pprint("Taking a nap of $time seconds");
	}

	if (!is_numeric($time)){
		throw new \InvalidArgumentException("Time should be a number");
	}

	$time = ((float) ($time)) * 1000000;

	return usleep($time);	 
}