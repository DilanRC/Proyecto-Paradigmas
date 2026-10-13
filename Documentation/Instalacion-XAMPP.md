# Ejecutar TinderCows con XAMPP en Linux

Esta guía instala el proyecto completo bajo Apache/PHP y MariaDB de XAMPP, y
usa phpMyAdmin para revisar la base. El proyecto requiere PHP 8.3: esa es la
versión que fijan `Dockerfile` y `Dockerfile.vercel`. Instalar XAMPP no garantiza
compatibilidad por sí solo; hay que comprobar la versión que trae su PHP.

## Compatibilidad comprobada

| Componente | Requisito del proyecto | Evidencia / límite |
|---|---|---|
| PHP | 8.3, con `pdo_mysql` y `mbstring` | Las imágenes del proyecto usan `php:8.3-apache`. La aplicación usa clases `readonly` y `array_is_list`, ambas incompatibles con PHP 8.0. |
| Apache | `mod_rewrite`, `mod_headers` recomendado y `AllowOverride All` para `Public/` | Las rutas de página y API se resuelven en `Public/.htaccess`. |
| Base de datos | MySQL 8.0 es la referencia del proyecto | Compose usa `mysql:8.0`. Aunque XAMPP incluya MariaDB, no se debe inferir compatibilidad de toda la aplicación sin ejecutar sus pruebas sobre esa versión. |
| phpMyAdmin | Se usa como interfaz local para importar y revisar datos | No reemplaza al servidor MariaDB/MySQL ni a las credenciales PDO de la aplicación. |

La instalación XAMPP Linux observada durante esta preparación es XAMPP 8.0.30,
con PHP 8.0.30 y MariaDB 10.4.32. **Ese PHP no ejecuta esta rama**: por
ejemplo, PHP 8.0 no puede analizar `Application/Auth/ActorContext.php`, y no
define `array_is_list()` usado por `Configuration/Configuration.php` y los
validadores. PHP añadió esa función en 8.1 ([manual de PHP](https://www.php.net/manual/en/function.array-is-list.php)). No intente resolverlo cambiando solo la configuración de Apache.

Antes de copiar el proyecto, compruebe el PHP que realmente trae XAMPP:

```bash
/opt/lampp/bin/php -v
/opt/lampp/bin/php -m
/opt/lampp/bin/php -r 'echo PHP_VERSION, " ", PHP_VERSION_ID, PHP_EOL;'
```

Use una distribución XAMPP para Linux con PHP 8.3, la versión base declarada
por el proyecto, y compruebe que Apache cargue esa versión y que estén
disponibles `pdo_mysql` y `mbstring`. Las versiones posteriores no se han
certificado en esta revisión. Si el XAMPP disponible solo trae PHP 8.0, 8.1 o
8.2, esta rama no queda cubierta por el requisito del proyecto. La
[página pública de descargas de Apache Friends](https://www.apachefriends.org/download.html?lang=en)
consultada durante esta revisión solo enumera para Linux hasta
PHP 8.2.12; por tanto, no se puede recomendar allí una descarga Linux con PHP
8.3 como solución ya verificada. En ese caso, coordine con el profesor una
versión que cumpla PHP 8.3 antes de la revisión. La antigüedad de Ubuntu también
puede impedir ejecutar un instalador XAMPP reciente por dependencias del
sistema; esta guía no promete compatibilidad con todas las versiones de Linux.

## Instalar el proyecto

1. Inicie Apache y MariaDB desde el panel de XAMPP o con el comando oficial de
   su instalación. En Linux, XAMPP suele instalarse en `/opt/lampp` ([FAQ de
   Apache Friends](https://www.apachefriends.org/faq_linux.html)); iniciar o
   detener esos servicios puede requerir permisos de administrador.
2. Copie o clone el repositorio completo fuera del directorio público, por
   ejemplo:

   ```text
   /opt/lampp/Proyecto-Paradigmas
   ```

   No copie únicamente `Public/`: sus vistas, controladores, configuración,
   SQL y recursos viven en el resto del repositorio. Mantenga la raíz del
   repositorio fuera de `htdocs`: si queda dentro, Apache podría servir `.env`,
   SQL y documentación con la configuración predeterminada.
3. En `/opt/lampp/etc/httpd.conf`, confirme que `mod_rewrite` esté cargado y
   que esté activa la inclusión de
   `conf/extra/httpd-vhosts.conf`. En ese archivo, publique solo `Public/`:

   ```apache
   <VirtualHost *:80>
       ServerName proyecto-paradigmas.local
       DocumentRoot "/opt/lampp/Proyecto-Paradigmas/Public"
       <Directory "/opt/lampp/Proyecto-Paradigmas/Public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   Añada `127.0.0.1 proyecto-paradigmas.local` a `/etc/hosts`, reinicie Apache
   y abra:

   <http://proyecto-paradigmas.local/>

   La aplicación calcula esa base para rutas, JavaScript, CSS e imágenes. No
   use `/opt/lampp/htdocs/Proyecto-Paradigmas` como carpeta del repositorio ni
   apunte `DocumentRoot` a la raíz del repositorio.

## Crear la base desde cero

1. Abra phpMyAdmin en <http://localhost/phpmyadmin> y confirme que el servidor
   corresponde al MariaDB incluido en XAMPP.
2. Importe en este orden, desde la página del servidor de phpMyAdmin (la
   primera secuencia crea y selecciona `bdmercadoganadero`):

   ```text
   Database/SqlScripts/000instalacioncompleta.sql
   Database/SeedData/101initialpagometodo.sql
   Database/SeedData/102administrador.sql
   Database/SeedData/103exampleproductores.sql   (opcional: datos ficticios)
   Database/SeedData/104catalogosanimal.sql
   ```

   `000instalacioncompleta.sql` es el esquema canónico del proyecto. Crea las
   47 tablas vigentes deliberadamente sin PK, FK, UNIQUE, CHECK,
   AUTO_INCREMENT, triggers ni rutinas. No importe los respaldos antiguos de
   `Database/Backups/` como esquema vigente. `000instalacioncompleta.sql` crea
   tablas ausentes, pero no actualiza las columnas de una base ya existente;
   esa base requiere sus migraciones `Database/Migrations/` en orden.
3. En phpMyAdmin cree un usuario exclusivo para la aplicación con acceso desde
   `127.0.0.1` y permisos sobre `bdmercadoganadero` solamente. No use la cuenta
   `root` de MariaDB en la aplicación. Guarde una contraseña local nueva; no
   reutilice credenciales de producción ni publique `.env`.
4. En la raíz del repositorio, copie `.env.example` a `.env` y deje estos
   valores de base de datos acordes con el usuario creado:

   ```dotenv
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=bdmercadoganadero
   DB_USER=tinder_cows
   DB_PASS=<contraseña local creada en phpMyAdmin>
   ```

   No agregue variables `POSTGRES_URL` ni `POSTGRES_URL_NON_POOLING` para esta
   instalación MySQL. `Configuration/Configuration.php` carga `.env` en
   solicitudes PHP; `Database.php` toma esas variables para PDO. Los valores
   incluidos en `.env.example` son para desarrollo desechable, no son una
   contraseña segura para una instalación compartida.

## Comprobar el arranque

Después de guardar `.env`, compruebe que Apache sirve la portada y que la API
contesta JSON:

```bash
curl -fsS -o /dev/null -w 'Portada: HTTP %{http_code}\n' http://proyecto-paradigmas.local/
curl -fsS -o /dev/null -w 'Configuración de autenticación: HTTP %{http_code}\n' http://proyecto-paradigmas.local/api/v1/auth/config
curl -s -o /dev/null -w 'Archivo .env (debe ser HTTP 404): HTTP %{http_code}\n' http://proyecto-paradigmas.local/.env
```

En phpMyAdmin, seleccione `bdmercadoganadero` y compruebe que las tablas y las
semillas estén presentes. Los cambios de datos del CRUD se envían a endpoints
JSON mediante `fetch()`; no se debe navegar directamente a un archivo PHP de
API para probar un CRUD.

Para pruebas automatizadas completas, use el flujo Docker del `README.md` con
una base local desechable. No ejecute pruebas sobre datos reales: parte de la
batería altera filas y estructuras para comprobar fallos. Las pruebas Docker
comprueban MySQL 8.0 y PHP 8.3; no certifican por sí solas el paquete MariaDB,
Apache, phpMyAdmin o el sistema operativo de una instalación XAMPP concreta.

## Servicios externos y modo sin Internet

XAMPP solo aloja Apache, PHP y MariaDB. Vercel es un destino de despliegue, no
un requisito para ejecutar la copia local. **XAMPP no vuelve la aplicación autónoma ni
offline.** Esta rama usa Supabase Auth para registro, inicio de sesión y
validación de sesión; también usa Storage y Realtime para funciones de archivos
y avisos. Mapas, geocodificación, tipografías y bibliotecas JavaScript se cargan
de proveedores externos. Sin red, o si esos proveedores no están disponibles,
esas funciones fallan o quedan degradadas aunque la portada PHP y la conexión
local a MySQL respondan.

La tabla `tbadministrador` solo declara qué correo tiene autorización
administrativa en el proyecto; no crea la cuenta ni la contraseña de Supabase.
Para probar el acceso administrativo se requiere una cuenta real autorizada en
Supabase y conectividad al servicio. `SUPABASE_SECRET_KEY` es opcional para
partes básicas del sitio, pero se necesita para operaciones privilegiadas de
Storage y administración de usuarios; nunca se envía al navegador ni se debe
copiar desde producción a una computadora de revisión.
