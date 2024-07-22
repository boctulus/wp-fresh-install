<?php

namespace simplerest;

use simplerest\core\libs\Files;
use simplerest\core\libs\Logger;
use simplerest\core\libs\Strings;
use simplerest\core\libs\ZipManager;

/*
	This is NOT a plugin. Place at same level as WordPress	

	@author Pablo Bozzolo

	# Which tasks are performed?

	This script can clean an infected WordPress website installation performing:

	- Folder wiping
	- WordPress core installation
	- Plugins auto-downloading and instalation
	- Theme auto-downloading and instalation

	# Instructions

	- Manually adjust the plugin and theme arrays to install.
	- Go to the end of this script and ajust tasks to be executed.
*/

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/core/helpers/config.php';

include __DIR__ ."/core/libs/SortedIterator.php";
include __DIR__ ."/core/libs/Date.php";
include __DIR__ ."/core/libs/Cache.php";
include __DIR__ ."/core/libs/FileCache.php";
include __DIR__ ."/core/libs/Files.php";
include __DIR__ ."/core/libs/Url.php";
include __DIR__ ."/core/libs/Arrays.php";
include __DIR__ ."/core/libs/Logger.php";
include __DIR__ ."/core/libs/Strings.php";
include __DIR__ ."/core/libs/System.php";
include __DIR__ ."/core/libs/VarDump.php";
include __DIR__ ."/core/libs/StdOut.php";
include __DIR__ ."/core/libs/ApiClient.php";
include __DIR__ ."/core/libs/ZipManager.php";

require_once __DIR__ . '/core/helpers/debug.php';
require_once __DIR__ . '/core/helpers/system.php';


$root_files = Files::glob(__DIR__ . '/..', '*');

$root_dir = realpath(__DIR__ . '/../'); //


/*
	WP + Plugins a instalar
*/

$to_wipe  = [
	'wp-content/plugins/sales-agent-coupons-1',
	'wp-content/wp-rss-feed',
	'wp-content/wp-rss-feed-1'
];

$zip_urls = [
	'wp'     => 'https://es.wordpress.org/latest-es_ES.zip',
	'themes' => [
		'https://downloads.wordpress.org/theme/astra.4.4.0.zip'
	],
	'plugins'=> [
		// ...
	]
];

$folder_zip_wp      = DOWNLOADS_PATH . '/wp';
$folder_zip_plugins = DOWNLOADS_PATH . '/plugins';
$folder_zip_themes  = DOWNLOADS_PATH . '/themes';

#Files::mkDirOrFail($folder_zip_wp);
#Files::mkDirOrFail($folder_zip_plugins);
#Files::mkDirOrFail($folder_zip_themes);


function wipe(){
	global $to_wipe, $root_dir;

	foreach ($to_wipe as $ix => $file) {
		if (!Files::isAbsolutePath($file)) {
			$to_wipe[$ix] = $file = "$root_dir/$file";
		}

		if (!file_exists($file)){
			dd("File not found: '$file'");
			continue; //
		}

		if (is_dir($file)) {
			Files::deleteDirectory($file);
		} else {
			Files::delete($file);
		}
	}
}

function download_wp(){
	global $folder_zip_wp, $zip_urls;

	Files::download($zip_urls['wp'], $folder_zip_wp);
}

function download_plugins(){
	global  $folder_zip_plugins,  $zip_urls;
	
	foreach ($zip_urls['plugins'] as $url){
		Files::download($url, $folder_zip_plugins);
	}
}

function download_themes(){
	global $folder_zip_themes, $zip_urls;

	foreach ($zip_urls['themes'] as $url){
		Files::download($url, $folder_zip_themes);
	}
}

function install_wp(){
	global $folder_zip_wp;

	$zips = Files::glob($folder_zip_wp, '*.zip');

	foreach ($zips as $zip){
		dd("$zip -> ". WP_ROOT_PATH);
		ZipManager::unzip($zip, WP_ROOT_PATH);
	}

	Files::copy(__DIR__ . "/../wordpress", __DIR__ . "/../");
}

function install_plugins(){
	global $folder_zip_plugins;

	$zips = Files::glob($folder_zip_plugins, '*.zip');

	foreach ($zips as $zip){
		dd("$zip -> ". PLUGINS_PATH);
		ZipManager::unzip($zip, PLUGINS_PATH);
	}
}

function install_themes(){
	global $folder_zip_themes;

	$zips = Files::glob($folder_zip_themes, '*.zip');

	foreach ($zips as $zip){
		dd("$zip -> ". THEMES_PATH);
		ZipManager::unzip($zip, THEMES_PATH);
	}
}


/*
	Tasks
*/

wipe();

#download_wp();
#install_wp();

#download_plugins();
#install_plugins();

#download_themes();
#install_themes();





