# wp-cleaner

"WP fresh install" (o tambien llamado "WP cleaner") es una herramienta para borrar y/o reinstalar de forma segura complementos y temas de un sitio en Wordpress que pudo haber sido infectado y/o estar corrupto y sin tener que subir decenas de miles de archivos via FTP como el propio WP.

Esto NO es un plugin sino una herramienta que debe configurarse y colocarse en el raiz del dominio dentro de /public_html y le permitirá reemplazar todo el contenido de un WordPress infectado sin tocar algunos archivos o carpetas críticos como el wp-config.php o la carpeta de uploads dentro de wp-content. 

Esta herramienta está en estado "Beta" y es experimental. Haga una copia de seguridad previamente !!!

Motivación:

Si una instalación de WordPress está infectada no hay forma segura de des-infectarlo sin apagar por completo WordPress y subir todos los archivos y en caso que el servidor fuera shared webhosting muy básico es tedioso y lento!!

Este plugin descarga los ZIPs que Ud. quiera directo al servidor e instala de forma directa.


Pasos:

1.- Liste todos los plugins y themes y busque el enlace al .zip dentro de la tienda de WordPress que se encuentra al seguir el boton "Descargar".

Si el plugin o theme estuviera en GitHub, descargelo... renombre la carpeta de "{complemento}-master" a "{complemento}" y vuelva a zipearlo. Estos los subira Ud manualmente al terminar.

2.- Edite los arrays que aparecen dentro del archivo wp-cleaner.php para que reflejen lo que Ud. desea restaurar de fuentes confiables.

3.- Cree una copia de seguridad fuera del servidor.

2.- Anule el index.php de WordPress para evitar siga llamando archivos infectados.

4.- Copie la carpeta "wp-cleaner" a nivel de raíz o sea dentro de /public_html o al mismo nivel de las carpetas wp-content, wp-admin, wp-includes

5- Cree un archivo "invoker.php"

    <?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    if (is_dir(__DIR__ . '/wp-cleaner')){
        chdir(__DIR__ . '/wp-cleaner');
    }

    if (is_file('/wp-cleaner/wp-cleaner.php')){
        require_once __DIR__ . '/wp-cleaner/wp-cleaner.php';
    }

    dd('Proceso terminado');

6.- Ejecute desde el navegador el script en "invoker.php" yendo a https://{dominio}/invoker.php

O via SSH o.... enlacelo desde el index.php asi:

    // here
    if (is_file(__DIR__ . '/invoker.php')){
        require_once __DIR__ . '/invoker.php';
    }

    /** Loads the WordPress Environment and Template */
    require __DIR__ . '/wp-blog-header.php';
