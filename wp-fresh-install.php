<?php

namespace simplerest;

use simplerest\core\libs\Files;
use simplerest\core\libs\Logger;
use simplerest\core\libs\Strings;
use simplerest\core\libs\ZipManager;

/*
	This is NOT a plugin. Place at same level as WorPress	

	@author Pablo Bozzolo

	Que hace?

	Limpia practicamente por completo un sitio de WP que este infectado

	Notas

	Ajustar manualmente los arrays de plugins y theme a instalar
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

$root_dir = '/';
if (count($root_files) > 0 && Strings::contains('/public_html/', $root_files[0])) {
	$root_dir = Strings::before($root_files[0], '/public_html/') . '/public_html';
}

// dd($root_dir, 'ROOT DIR');
// exit;

/*
	WP + Plugins a instalar
*/

$to_wipe  = [
	'trigono-staging',
	'wp-admin',
	'wp-includes',
	'wp-content/ai1wm-backups',
	'wp-content/cache',
	'wp-content/languages',
	'wp-content/mu-plugins',
	'wp-content/plugins',
	'wp-content/themes',
	'wp-content/updraft',
	'wp-content/upgrade',
	'wp-content/upgrade-temp-backup',
	'wp-content/wflogs',
	'wp-content/bfu-temp'
];

$zip_urls = [
	'wp'     => 'https://es.wordpress.org/latest-es_ES.zip',
	'themes' => [
		'https://downloads.wordpress.org/theme/astra.4.4.0.zip'
	],
	'plugins'=> [
		'https://downloads.wordpress.org/plugin/all-in-one-wp-migration.7.79.zip',
		'https://downloads.wordpress.org/plugin/antispam-bee.2.11.5.zip',
		'https://downloads.wordpress.org/plugin/tuxedo-big-file-uploads.2.1.2.zip',
		'https://downloads.wordpress.org/plugin/check-email.1.0.8.zip',
		'https://downloads.wordpress.org/plugin/duplicate-page.zip',
		'https://downloads.wordpress.org/plugin/elementor.3.17.2.zip',
		'https://downloads.wordpress.org/plugin/header-footer-elementor.1.6.17.zip',
		'https://downloads.wordpress.org/plugin/essential-addons-for-elementor-lite.5.8.13.zip',
		'https://downloads.wordpress.org/plugin/ajax-search-for-woocommerce.1.26.1.zip',
		'https://downloads.wordpress.org/plugin/google-listings-and-ads.2.5.10.zip',
		'https://downloads.wordpress.org/plugin/duracelltomi-google-tag-manager.1.18.1.zip',
		'https://downloads.wordpress.org/plugin/creame-whatsapp-me.5.0.13.zip',
		'https://downloads.wordpress.org/plugin/litespeed-cache.5.7.0.1.zip',
		'https://downloads.wordpress.org/plugin/metricool.zip',
		// 'https://github.com/proelements/proelements/archive/refs/heads/master.zip',
		'https://downloads.wordpress.org/plugin/woo-product-attachment.2.2.1.zip',
		'https://downloads.wordpress.org/plugin/woo-product-feed-pro.13.0.7.zip',
		'https://downloads.wordpress.org/plugin/woo-product-filter.zip',
		'https://downloads.wordpress.org/plugin/google-site-kit.1.111.1.zip',
		'https://downloads.wordpress.org/plugin/updraftplus.1.23.10.zip',
		'https://downloads.wordpress.org/plugin/woocommerce.8.2.1.zip',
		'https://downloads.wordpress.org/plugin/woocommerce-checkout-manager.7.2.9.zip',
		'https://downloads.wordpress.org/plugin/woocommerce-google-analytics-integration.1.8.8.zip',
		// 'https://github.com/placetopay/woocommerce-gateway-placetopay/archive/refs/heads/master.zip',
		'https://downloads.wordpress.org/plugin/wordfence.7.10.6.zip',
		'https://downloads.wordpress.org/plugin/wp-crontrol.1.16.0.zip',
		'https://downloads.wordpress.org/plugin/wp-mail-smtp.3.9.0.zip',
		'https://downloads.wordpress.org/plugin/wpforms-lite.1.8.4.1.zip',
		'https://downloads.wordpress.org/plugin/wordpress-seo.21.5.zip',
	]
];

$folder_zip_wp      = DOWNLOADS_PATH . '/wp';
$folder_zip_plugins = DOWNLOADS_PATH . '/plugins';
$folder_zip_themes  = DOWNLOADS_PATH . '/themes';

Files::mkDirOrFail($folder_zip_wp);
Files::mkDirOrFail($folder_zip_plugins);
Files::mkDirOrFail($folder_zip_themes);


function wipe(){
	global $to_wipe, $root_dir;

	foreach ($to_wipe as $ix => $file) {
		if (!Files::isAbsolutePath($file)) {
			$to_wipe[$ix] = $file = "$root_dir/$file";
		}

		if (!file_exists($file)){
			Logger::logError("No se pudo encontrar '$file'");
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

download_wp();
install_wp();

download_plugins();
install_plugins();

download_themes();
install_themes();





