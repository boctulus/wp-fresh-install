# wp-fresh-install

"WP Fresh Install" (also known as "WP Cleaner") is a tool designed to safely delete and/or reinstall plugins and themes on a WordPress site that may have been infected and/or corrupted. This tool does not require uploading tens of thousands of files via FTP like WordPress itself.

This is NOT a plugin but a tool that should be configured and placed in the root directory of the domain within `/public_html`. It will allow you to replace all the content of an infected WordPress site without touching critical files or folders such as `wp-config.php` or the `uploads` folder within `wp-content`.

This tool is in "Beta" state and is experimental. Please make a backup beforehand!

## Motivation

If a WordPress installation is infected, there is no safe way to disinfect it without completely shutting down WordPress and uploading all the files. If the server is basic shared web hosting, this process can be tedious and slow!

This tool downloads the ZIPs you need directly to the server and installs them immediately.

## Steps

1. List all plugins and themes and find the download link for the ZIP file from the WordPress store by following the "Download" button.

   If the plugin or theme is on GitHub, download it, rename the folder from `{plugin}-master` to `{plugin}`, and re-zip it. You will upload these manually later.

2. Edit the arrays in the `wp-fresh-install.php` file to reflect the plugins and themes you wish to restore from trusted sources.

3. Create a backup off the server.

4. Disable the WordPress `index.php` to prevent it from calling infected files.

5. Copy the "wp-fresh-install" folder to the root directory, i.e., within `/public_html` or at the same level as the `wp-content`, `wp-admin`, and `wp-includes` folders.

6. Create an `invoker.php` file:

    ```php
    <?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if (is_dir(__DIR__ . '/wp-fresh-install')){
        chdir(__DIR__ . '/wp-fresh-install');
    }

    if (is_file(__DIR__ . '/wp-fresh-install/wp-fresh-install.php')){
        require_once __DIR__ . '/wp-fresh-install/wp-fresh-install.php';
    }

    dd('Process completed');
    ```

7. Configuration

Arrays to Configure

a. **`$to_wipe`**: This array specifies the relative paths within the `wp-content` directory that should be deleted. Adjust these values to include the specific folders or files you want to remove from the WordPress site before performing the reinstallation.

Example:
```php
$to_wipe  = [
    'wp-content/plugins/sales-agent-coupons-1',
    'wp-content/wp-rss-feed',
    'wp-content/wp-rss-feed-1'
];
```

Adjust this array according to the folders or files that need to be removed to clean the WordPress installation.

b. $zip_urls: This array contains URLs for ZIP files needed to reinstall WordPress, themes, and plugins. Adjust these URLs to point to the specific versions you want to install.

Example:
```php
$zip_urls = [
    'wp'     => 'https://es.wordpress.org/latest-es_ES.zip',
    'themes' => [
        'https://downloads.wordpress.org/theme/astra.4.4.0.zip'
    ],
    'plugins'=> [
        // URLs for plugins here
    ]
];
```

c. Tasks

At the end of the script, there are several tasks that need to be adjusted according to your specific needs. These tasks are commented out in the script and should be uncommented and adjusted based on your workflow:

Example:
```php
/*
    Tasks
*/

wipe(); // Deletes the files and folders specified in $to_wipe

download_wp(); // Downloads the WordPress ZIP file specified in $zip_urls['wp']
install_wp();  // Installs WordPress from the downloaded ZIP file

download_plugins(); // Downloads plugins from the URLs specified in $zip_urls['plugins']
install_plugins();  // Installs plugins from the downloaded ZIP files

download_themes(); // Downloads themes from the URLs specified in $zip_urls['themes']
install_themes();  // Installs themes from the downloaded ZIP files
```

'wp': URL for the WordPress ZIP file to download and install.
'themes': Array of URLs for theme ZIP files to install.
'plugins': Array of URLs for plugin ZIP files to install. Complete this array with the necessary URLs.

7. Execute the script in `invoker.php` from the browser by navigating to `https://{domain}/invoker.php`.

   Alternatively, run it via SSH or include it in `index.php` as follows:

    ```php
    // here
    if (is_file(__DIR__ . '/invoker.php')){
        require_once __DIR__ . '/invoker.php';
    }

    /** Loads the WordPress Environment and Template */
    require __DIR__ . '/wp-blog-header.php';
    ```
