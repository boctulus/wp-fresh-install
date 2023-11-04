# wp-fresh-install

Esto NO es un plugin sino una herramienta que debe configurarse y colocarse en el raiz del dominio dentro de /public_html y le permitirá reemplazar todo el contenido de un WordPress infectado sin tocar algunos archivos o carpetas críticos como el wp-config.php o la carpeta de uploads dentro de wp-content. 

Esta herramienta está en estado "Beta" y es experimental. Haga una copia de seguridad previamente !!!

Motivación:

Si una instalación de WordPress está infectada no hay forma segura de des-infectarlo sin apagar por completo WordPress y subir todos los archivos si se contara con un shared webhosting muy básico es tedioso y lento!!

Este plugin descarga los ZIPs que Ud. quiera directo al servidor e instala de forma directa.


Pasos:

1.- Liste todos los plugins y themes y busque el enlace al .zip dentro de la tienda de WordPress que se encuentra al seguir el boton "Descargar".

Si el plugin o theme estuviera en GitHub, descargelo... renombre la carpeta de "{complemento}-master" a "{complemento}" y vuelva a zipearlo. Estos los subira Ud manualmente al terminar.

2.- Edite los arrays que aparecen dentro del archivo wp-fresh-install.php para que reflejen lo que Ud. desea restaurar de fuentes confiables.

3.- Cree una copia de seguridad fuera del servidor.

2.- Anule el index.php de WordPress para evitar siga llamando archivos infectados.

4.- Copie la carpeta "wp-fresh-install" a nivel de raíz o sea dentro de /public_html o al mismo nivel de las carpetas wp-content, wp-admin, wp-includes

5- Cree un archivo "invoke_installer.php"

    <?php

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    chdir(__DIR__ . '/wp-installer');

    require_once __DIR__ . '/wp-installer/wp-installer.php';

    dd('Proceso termiando');

6.- Ejecute desde el navegador el script en "invoke_installer.php" yendo a https://{dominio}/invoke_installer.php