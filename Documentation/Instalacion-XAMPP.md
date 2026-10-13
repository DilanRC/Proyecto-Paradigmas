# Ejecutar TinderCows con XAMPP en Linux

La aplicación requiere **PHP 8.0 o posterior**, Apache 2.4 y MySQL/MariaDB.
La revisión ejecutó el paquete XAMPP 8.0.30 en Linux con PHP 8.0.30, Apache
2.4.58, MariaDB 10.4.32 y phpMyAdmin 5.2.1. El sistema usado fue CachyOS;
**la versión de Ubuntu y de XAMPP del profesor Brenes es desconocida**.
Compruebe esa computadora antes de la entrega: estas pruebas no prometen
compatibilidad con cualquier versión de Linux o XAMPP.

## Requisitos y evidencia

| Componente | Requisito / resultado comprobado |
|---|---|
| PHP | Mínimo PHP 8.0: tipos unión, `mixed` y propiedades promovidas. La aplicación sustituye las clases `readonly` y carga el polyfill de `array_is_list()` en `Configuration/Compatibility.php`. |
| Extensiones | `pdo_mysql` para la conexión y lectura del esquema; `mysqli` para importar SQL; `mbstring`, `openssl`, `json` y `fileinfo`; `allow_url_fopen=On` para solicitudes HTTPS de autenticación. |
| Tiempo | `Configuration/Configuration.php` fija UTC para fechas del negocio. La configuración CLI no demuestra la configuración de Apache. |
| Apache | 2.4, `mod_rewrite`, `AllowOverride All` para la carpeta del paquete y `Public/`; `mod_headers` para los encabezados de protección. |
| MariaDB | 10.4.32 de XAMPP: esquema de 48 tablas con su llave primaria y ninguna otra restricción ni índice; batería completa de 80 pruebas PHP aprobada, incluida la regresión de observaciones. Migración 025 aplicada sobre una base con datos. |
| MySQL | 5.7.44: importación desde cero, migración 025 (también el aborto con identificadores repetidos) y tres pruebas de integración de publicaciones/inventario aprobadas. MySQL 8.0 sigue siendo la referencia de Compose; las tres pruebas de 5.7 no equivalen a certificar toda la aplicación. |
| Otros motores | MySQL 8.0.46 y MariaDB 11.8.8 (contenedores): instalación con 47 llaves primarias y 13 pruebas de modelo cada uno. No se ejecutó la batería completa ni la aplicación por HTTP sobre ellos. |
| Otras versiones de PHP | 8.1, 8.2, 8.3 y 8.4 (contenedores): sintaxis de todos los archivos y `runtime_compatibility_test`. La aplicación completa solo se ejecutó con PHP 8.0.30 de XAMPP. |
| Frontend | 436 pruebas automatizadas aprobadas (Node); son pruebas de fuentes y contratos, no sustituyen la revisión en navegador. |
| phpMyAdmin | 5.2.1: importación desde su interfaz sobre un servidor vacío; 74 consultas de esquema y semillas 101/102/104 con 6/6/8 consultas. Diagnóstico posterior: 47 tablas, 47 llaves primarias y cero restricciones, índices, triggers o rutinas adicionales (antes de la tabla 48). Migraciones 025 y 026 importadas por el mismo formulario sobre una base anterior con datos. |

Los Dockerfiles usan PHP 8.3 como entorno de despliegue. Ese valor no es el
mínimo de lenguaje de la aplicación. PHP anterior a 8.0 queda fuera del alcance.
Las versiones de XAMPP y las dependencias del sistema del profesor siguen
pendientes de comprobación en su equipo.

## Entrega ZIP para revisión

El paquete `GanadoCerca.zip` está pensado para extraerse como
`<htdocs>/GanadoCerca`. En una instalación XAMPP cuyo directorio web sea el
predeterminado, el flujo es:

1. Extraiga el ZIP en `htdocs/GanadoCerca` y arranque Apache y MySQL desde el
   panel de XAMPP. No necesita cambiar `httpd.conf`, configurar un VirtualHost,
   abrir una terminal ni importar SQL manualmente.
2. Abra <http://localhost/GanadoCerca/Public/>. En el pie de página aparece
   **Preparar base MySQL local**; el asistente solo se muestra desde loopback.
3. Escriba el host, puerto, usuario y contraseña local de MySQL. **Comprobar
   conexión** solo consulta el servidor. Si `bdmercadoganadero` no existe, una
   segunda pantalla pide confirmar antes de crear la base y cargar el esquema
   canónico, el método de pago inicial, la política de acceso administrativo y
   los catálogos de animales/vacunas. La semilla `103exampleproductores.sql`
   con personas ficticias no se importa.
4. Abra <http://localhost/phpmyadmin/> y compruebe `bdmercadoganadero`: debe
   contener 48 tablas, cada una con su llave primaria `<tabla>id`.

Si la base ya existe, el asistente la consulta sin completar ni modificarla.
Solo permite guardar una conexión cuando confirma las 48 tablas y sus llaves.
La contraseña permanece en la sesión PHP del servidor, guardada en una carpeta
temporal privada por copia del proyecto; una conexión confirmada se guarda en
un archivo temporal fuera de `htdocs` con permisos privados. No se crea una
cuenta de MySQL, una cuenta de Supabase ni contraseñas de aplicación.
Todo valor no vacío de `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` o `DB_PASS`
configurado en Apache o `.env` tiene prioridad sobre esa conexión guardada; para
usar el asistente, deje vacíos esos campos. Las asignaciones vacías de `.env.example`
no bloquean el asistente.
El asistente web protege ese almacenamiento con permisos POSIX y por eso solo
se admite en XAMPP para Linux con la extensión PHP `posix`. En Windows, use la
instalación manual documentada; el asistente web no guarda credenciales con ACL
que no pueda verificar.

El modo ZIP requiere que Apache aplique los archivos `.htaccess` del paquete
(`AllowOverride All`). El paquete niega el acceso a los archivos privados en la
raíz y concede acceso solo a `Public/`. Con `AllowOverride None`, PHP puede
responder 503 para la aplicación, pero Apache aún podría entregar archivos
estáticos privados; ese modo no está soportado para esta entrega. La prueba de
compatibilidad se limita a XAMPP 8.0.30/Linux con Apache 2.4.58, MariaDB 10.4.32
y `AllowOverride All`; compruebe el XAMPP de destino antes de la revisión.

Compruebe el ejecutable PHP de su instalación; sustituya `/opt/lampp` si XAMPP
está en otra carpeta:

```bash
/opt/lampp/bin/php -v
/opt/lampp/bin/php -m
/opt/lampp/bin/php Tools/diagnostico-local.php
```

El diagnóstico es CLI, solo lectura y está fuera de `Public/`. Informa versiones,
extensiones, OpenSSL, UTC, conectividad PDO, tablas ausentes, llaves primarias, otras
restricciones, índices adicionales, triggers, rutinas y si este PHP reúne los
requisitos del asistente web (Linux, `mysqli` y `posix`). Devuelve 1 si falla un
requisito general de la aplicación o la conexión/esquema; que el asistente web no
esté disponible no invalida una instalación manual que ya tenga su conexión PDO.
Puede mostrar fallo de conexión hasta que configure `.env`; no muestra contraseñas
ni el texto privado de las excepciones. La versión de **PHP Apache es desconocida**
para este comando: compruebe por separado qué módulo PHP carga el servidor.

## Alternativa avanzada: publicar solo `Public/` como DocumentRoot

1. Copie el repositorio completo en una carpeta fuera de `htdocs`, por ejemplo
   `/opt/lampp/Proyecto-Paradigmas`. Mantenga la raíz del repositorio fuera de
   `htdocs`: incluye configuración privada, SQL, pruebas y documentación.
   El proyecto completo debe estar en la entrega; copiar solo `Public/` elimina
   clases y vistas necesarias.
2. Configure Apache manualmente. En `httpd.conf` confirme que se cargan
   `mod_rewrite`, el módulo PHP correcto y la inclusión de `httpd-vhosts.conf`.
   Configure este VirtualHost en el archivo correspondiente a su instalación:

   ```apache
   TraceEnable Off
   <VirtualHost *:80>
       ServerName proyecto-paradigmas.local
       DocumentRoot "/opt/lampp/Proyecto-Paradigmas/Public"
       ErrorDocument 405 /api/metodo-no-permitido.php
       <Directory "/opt/lampp/Proyecto-Paradigmas/Public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   `TraceEnable Off` desactiva TRACE en Apache. `ErrorDocument 405` utiliza la
   respuesta JSON del proyecto cuando Apache rechaza el método antes de llegar
   al endpoint PHP; esa configuración pertenece al servidor y no puede hacerse
   desde `.htaccess` para TRACE. Los errores de aplicación mantienen su contrato
   JSON sin recargar la página.
3. Añada `127.0.0.1 proyecto-paradigmas.local` a `/etc/hosts`, valide la
   configuración de Apache y reinicie sus servicios desde el panel de XAMPP o
   con el procedimiento de su instalación. Ejecute con privilegios
   de administrador solo los pasos que lo requieran; el instalador del
   proyecto no ejecuta `sudo` automáticamente.
4. Abra <http://proyecto-paradigmas.local/>. No publique la raíz del repositorio
   ni la exponga como subcarpeta. Si usa otro puerto, inclúyalo en el VirtualHost
   y en todas las URL de las comprobaciones.

Apache necesita permiso de lectura sobre los archivos y permiso de recorrido
sobre las carpetas del repositorio. Mantenga la propiedad del proyecto en su
usuario, no use `chmod 777` ni conceda escritura global. Dé lectura a `.env`
solo al usuario o grupo de PHP que la necesite. Los archivos de usuario se
almacenan en Supabase Storage; no haga escribible toda la raíz para resolver
un error de permisos. Revise el log local de Apache si una ruta devuelve 403.

## Base nueva mediante phpMyAdmin

1. Inicie MariaDB/MySQL y abra <http://localhost/phpmyadmin>. Confirme el servidor,
   puerto y versión que aparecen en phpMyAdmin: el servidor de sistema y el de
   XAMPP pueden coexistir en puertos distintos.
2. Importe los archivos en este orden desde la página del servidor. El primero
   crea y selecciona `bdmercadoganadero`:

   ```text
   Database/SqlScripts/000instalacioncompleta.sql
   Database/SeedData/101initialpagometodo.sql
   Database/SeedData/102administrador.sql
   Database/SeedData/103exampleproductores.sql   (opcional: datos ficticios)
   Database/SeedData/104catalogosanimal.sql
   ```

   Después de importar el esquema, seleccione `bdmercadoganadero` en el panel
   izquierdo y use su pestaña **Importar** para cada semilla. Esto evita mensajes
   auxiliares de phpMyAdmin sobre una base no seleccionada, aunque el SQL use
   `USE`. El esquema canónico crea 48 tablas con atributos y tipos, cada una con
   su llave primaria (`<tabla>id`), y deliberadamente sin FK, UNIQUE, CHECK, AUTO_INCREMENT,
   índices adicionales, triggers ni rutinas.
   Los cuatro campos que guardan JSON usan `LONGTEXT`; PHP valida y codifica su
   contenido. El alias `JSON` de MariaDB puede crear CHECK implícitos y por eso
   no se usa en el esquema vigente. No importe respaldos antiguos como esquema.
3. Cree en phpMyAdmin una cuenta local exclusiva para la aplicación, con acceso
   desde el host de conexión y permisos sobre `bdmercadoganadero` solamente.
   Elija una contraseña local nueva; no reutilice credenciales de producción.
4. Copie `.env.example` a `.env` en la raíz privada del repositorio y configure:

   ```dotenv
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=bdmercadoganadero
   DB_USER=tinder_cows
   DB_PASS=<contraseña local creada en phpMyAdmin>
   ```

   `.env` no debe entrar en Git ni en un ZIP compartido con credenciales reales.
   Incluya `.env.example` y esta guía dentro del proyecto de entrega. No configure
   `POSTGRES_URL` ni `POSTGRES_URL_NON_POOLING` para la instalación MySQL: esas
   variables seleccionan otro motor. Las variables del servidor tienen prioridad
   sobre `.env`, por lo que debe revisar configuraciones antiguas de Apache.

## Base existente: respaldo y migraciones

Exporte un respaldo completo en phpMyAdmin antes de modificar una base existente.
`CREATE TABLE IF NOT EXISTS` no actualiza columnas antiguas. Compruebe qué
migraciones ya están aplicadas y ejecute solo las pendientes, en su orden; no
importe nuevamente las semillas demo sobre datos reales.

La migración `Database/Migrations/024jsontextportable.sql` convierte los cuatro
campos JSON de bitácora y observaciones en `LONGTEXT`. Su ejecución fue comprobada
en MariaDB 10.4.32: preserva el contenido y retira los CHECK implícitos del alias
JSON. El diagnóstico debe informar cero restricciones fuera de las llaves primarias después. Si quedan CHECK
creados manualmente u otras diferencias, deténgase y revise la definición y el
respaldo antes de corregirlas; esta migración no reconstruye cualquier DB antigua.

La migración `Database/Migrations/025llavesprimarias.sql` declara la llave primaria
`<tabla>id` en las tablas de una base creada antes de esa regla. No cambia datos.
Primero cuenta los identificadores repetidos y, si encuentra alguno, muestra
`MIGRACION_ABORTADA_IDENTIFICADORES_REPETIDOS` y no declara ninguna llave; corrija esas
filas y vuelva a ejecutarla (se puede repetir). Aplíquela después de la 024 y antes de
la `026personacorreocambio.sql`, que crea la tabla 48.

Desde phpMyAdmin: seleccione `bdmercadoganadero`, pestaña **Importar**, y suba cada
migración en orden. phpMyAdmin no se detiene en el primer error como el cliente `mysql`:
por eso cada `ALTER` de la 025 vuelve a exigir que no haya repetidos. Con un identificador
repetido, la importación muestra el error `#1146 ... MIGRACION_ABORTADA_IDENTIFICADORES_REPETIDOS`
y la base queda con las mismas llaves que tenía. Los `ALTER` confirman solos: no hay
`ROLLBACK` de una llave ya declarada, así que haga el respaldo antes.

Comprobado importando por el formulario de phpMyAdmin 5.2.1 sobre MariaDB 10.4.32: una
base con el esquema anterior (47 tablas sin llave, con semillas) quedó con 47 llaves y los
mismos datos (`CHECKSUM TABLE` igual antes y después); repetirla no cambió nada; con un
identificador repetido no declaró ninguna llave y, tras corregirlo, terminó en 48 tablas y
48 llaves con la 026. También con el cliente `mysql` en MariaDB 10.4.32, MySQL 5.7.44 y
MySQL 8.0.

## Instalador CLI alternativo

`bash instalar-local.sh` instala esquema y semillas básicas en el destino
configurado. Usa siempre TCP con `DB_HOST` y `DB_PORT`, tanto para root como para
la cuenta de aplicación. `DB_PASS` debe estar configurada localmente para la
cuenta de aplicación; el script ya no distribuye una contraseña predeterminada.
Configura `DB_ROOT_PASS` localmente si root exige contraseña; no la ponga en
comandos compartidos ni en documentación.

Puede definir `MYSQL_CLIENT` con la ruta al cliente, o `XAMPP_ROOT` con la carpeta
de XAMPP. Por defecto busca el cliente bajo `/opt/lampp` y luego en PATH.
`DB_ACCOUNT_HOST` define el host de la cuenta SQL creada y su valor por defecto
es `localhost`; para un servidor remoto debe corresponder al origen permitido
para el cliente. El entorno tiene prioridad sobre `.env`. El nombre de DB debe
ser `bdmercadoganadero`, ya que esquema y semillas lo utilizan explícitamente.

El instalador valida identificadores y puerto. No cambia la contraseña de una
cuenta preexistente: aborta si esa cuenta no acepta `DB_PASS` antes de borrar o
conceder permisos. `--reinstalar` borra la base seleccionada y requiere un respaldo
si desea conservarla. Un fallo de importación puede dejar datos parciales: revise
el error y el estado antes de reintentar. El script no reemplaza la migración de
una base existente.

## Arranque, JSON y errores

Después de configurar la base, ejecute el diagnóstico y compruebe HTTP:

```bash
/opt/lampp/bin/php Tools/diagnostico-local.php
curl -fsS -o /dev/null -w 'Portada: HTTP %{http_code}\n' http://proyecto-paradigmas.local/
curl -fsS -o /dev/null -w 'Auth: HTTP %{http_code}\n' http://proyecto-paradigmas.local/api/v1/auth/config
curl -s -o /dev/null -w 'Archivo .env (debe ser HTTP 404): HTTP %{http_code}\n' http://proyecto-paradigmas.local/.env
curl -i -X TRACE http://proyecto-paradigmas.local/api/v1/publicaciones
```

La última solicitud debe responder 405 y `Content-Type: application/json`.
Revise la portada y las rutas limpias en navegador, no solo el HTTP 200. Las API
CRUD se consumen con `fetch()`/AJAX y JSON, sin recargar toda la página. PHP vuelve
a validar los datos y mantiene negocio, solicitudes e interfaz separados.

Para ejecutar pruebas que escriben datos, use exclusivamente una base local
desechable. Consulte `Tests/README.md`; no ejecute esa batería sobre producción
ni sobre la DB de una revisión con datos que necesite conservar. La regresión
`Tests/latest_observation_portability_test.php` comprueba observación reciente por
fecha/id, misma fila y totales sin multiplicación en MySQL 5.7 y MariaDB 10.4.

## Instalación sin descargas y servicios externos

El código PHP, SQL, vistas, JavaScript y recursos incluidos se ejecutan desde el
repositorio completo: **no requieren Node ni Composer para XAMPP**. Puede copiar
el paquete en una memoria USB e importar el SQL sin Internet, si XAMPP ya está
instalado con las extensiones necesarias. Node pertenece al servicio opcional de
Compose; XAMPP valida las sesiones directamente mediante Supabase Auth.

Eso no permite operación completa sin Internet. Auth, Storage, Realtime y mapas
externos requieren red: Supabase Auth participa en registro, confirmación, login
y validación de sesión; Storage en archivos; Realtime en avisos. Mapas, teselas,
geocodificación, algunas tipografías y bibliotecas también dependen de proveedores.
Los iconos Font Awesome se incluyen localmente; eso resuelve solo esos iconos.
Vercel es el destino de despliegue y no es necesario para el servidor XAMPP.

Use un proyecto Supabase de pruebas con URL y clave pública configuradas en `.env`
para revisar registro/login. La clave pública llega al navegador; una clave
secreta debe quedar solo en configuración del servidor y nunca copiarse desde
producción a una PC de revisión. `tbadministrador` autoriza un correo, pero no
crea la cuenta ni la contraseña de Supabase. El acceso administrativo necesita
una cuenta autorizada y conectividad. Si falla la red, las funciones externas
quedan degradadas; la portada local no demuestra que Auth o Storage funcionen.
