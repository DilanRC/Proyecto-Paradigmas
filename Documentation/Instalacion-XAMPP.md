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

No hace falta terminal, VirtualHost, crear ni editar `.env`, tocar Apache ni
importar SQL. Hace falta Internet: las cuentas se crean en Supabase Auth.

1. Extraiga `GanadoCerca.zip` **directamente en `htdocs`**. El ZIP ya trae la
   carpeta `GanadoCerca/`: no cree antes una carpeta con ese nombre. Debe quedar
   `htdocs/GanadoCerca/Public/`, no `htdocs/GanadoCerca/GanadoCerca/Public/`.
2. Inicie Apache y MySQL desde el panel de XAMPP.
3. Abra <http://localhost/GanadoCerca/Public/> (o <http://localhost/GanadoCerca/>,
   que redirige ahí). Si XAMPP usa otro puerto, agréguelo a la dirección.
4. La primera vez, la página lo lleva sola a **Preparar la base local**:
   - **Comprobar conexión.** Los valores de XAMPP ya vienen escritos (`127.0.0.1`,
     `3306`, `root`, sin contraseña). Cámbielos solo si su MySQL usa otro puerto
     o contraseña. Este paso no escribe nada.
   - Marque la confirmación y pulse **Instalar esquema y catálogos**. Crea
     `bdmercadoganadero` con sus 48 tablas, el método de pago, la política de
     administrador y los catálogos de animales y vacunas.
5. Pulse **Ver la portada** y luego **Crear cuenta** para registrar su propio
   usuario. Al terminar entra a Explorar con la sesión iniciada.
6. En <http://localhost/phpmyadmin/> abra `bdmercadoganadero`: 48 tablas, cada
   una con su llave primaria `<tabla>id`. Su registro aparece en `tbpersona`,
   `tbcomprador` y `tbbitacora`.

La instalación no trae personas, publicaciones ni cuentas de ejemplo: una base
recién creada tiene cero filas en `tbpersona` y en `tbanimalpublicacion`, y las
pantallas muestran sus estados vacíos hasta que usted registra datos.

### Qué trae el `.env` del ZIP

`GanadoCerca/.env` viene listo y Apache no lo entrega por HTTP (responde 403):

| Variable | Valor | Para qué |
|---|---|---|
| `DB_DRIVER` | `mysql` | Obliga a usar MySQL local aunque el servidor herede una `POSTGRES_URL`. |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | `127.0.0.1`, `3306`, `bdmercadoganadero`, `root`, vacío | Valores de fábrica de XAMPP. Son el punto de partida: lo que confirme en el asistente los reemplaza sin editar el archivo. |
| `SUPABASE_URL`, `SUPABASE_PUBLISHABLE_KEY` | Proyecto de Ganado Cerca | Valores públicos: el navegador los recibe de `api/v1/auth/config`. |

| `SUPABASE_SECRET_KEY` | Solo en el ZIP privado de Calidad | Clave del servidor; nunca llega al navegador. |

No trae URL de PostgreSQL. La clave secreta la usan tres operaciones del
servidor: abrir el documento de identidad desde el panel de administración
(`/admin/documentos`), cambiar el correo de una cuenta desde Ajustes y los
avisos instantáneos de subastas. El resto, incluidos registro, inicio de
sesión, publicaciones, fotos, fletes y solicitudes de compra, no la necesita.

Esa clave administra el proyecto de Supabase (cuentas y archivos). Por eso el
ZIP que la trae se entrega solo a Calidad, no se publica, y la clave se cambia
en el panel de Supabase al terminar la evaluación. Un ZIP generado sin
`ENTREGA_ENV_PRIVADO` no la incluye.

Los datos del negocio se guardan en MySQL local. En Supabase solo queda la
cuenta (correo y contraseña) y las fotos que se suban. El proyecto tiene
"Confirm email" desactivado: la cuenta queda activa al registrarse y no llega
ningún correo de confirmación.

### Lo que el asistente hace y no hace

- Solo responde a visitas desde la misma computadora (`localhost`); desde otra
  máquina o detrás de un proxy responde 404.
- Abrir o recargar una página nunca escribe en MySQL. Crear la base exige el
  botón, la casilla de confirmación y que `bdmercadoganadero` **no exista**.
- Al terminar crea la cuenta MySQL `ganadocerca_app`, con contraseña aleatoria y
  permisos solo de lectura y escritura de datos sobre `bdmercadoganadero`. La
  aplicación usa esa cuenta; `root` solo se usa para instalar. Si el usuario con
  el que instala no puede crear cuentas, la aplicación conserva la conexión que
  usted escribió.
- La conexión se guarda fuera de `htdocs`, en la carpeta temporal de XAMPP
  (`/opt/lampp/temp`, que sobrevive a un reinicio; si no existe, `/tmp`), con
  permisos `0700`/`0600`.
- **Instalación interrumpida:** si Apache o MySQL se detienen a medias, la página
  lo indica y, tras comprobar la conexión, ofrece **Terminar la instalación**.
  Repite el mismo SQL: crea las tablas que falten y deja los datos iniciales en
  su valor original; no borra nada. Solo se ofrece contra el mismo servidor y
  puerto donde empezó.
- **Base eliminada:** si borra `bdmercadoganadero` en phpMyAdmin, el asistente
  permite instalarla de nuevo.
- **Conexión guardada que dejó de servir** (cambió la contraseña de MySQL o se
  eliminó la cuenta `ganadocerca_app`): compruebe la conexión y pulse **Guardar
  conexión local**; reemplaza la anterior sin tocar los datos.
- Una base que ya existe y que este asistente no creó no se modifica ni se
  completa. Solo se usa si tiene las 48 tablas, sus llaves primarias y las mismas
  columnas (nombre, tipo y nulabilidad) que el esquema actual; si no, el
  asistente lista las diferencias y explica cómo respaldarla y renombrarla en
  phpMyAdmin, o cómo migrarla (sección "Base existente").
- Las variables `DB_*` que defina **Apache** (no el `.env`) mandan sobre el
  asistente.
- El asistente necesita Linux y la extensión PHP `posix` para proteger ese
  archivo. En Windows use la instalación manual.

### Condiciones de Apache

- **Apache debe aplicar los `.htaccess` del paquete** (`AllowOverride All`, el
  valor de fábrica de XAMPP para `htdocs`) y tener `mod_rewrite` (también de
  fábrica). Sin `mod_rewrite` la aplicación responde 503 con el motivo y los
  archivos internos siguen negados.
- **`AllowOverride None` no está soportado.** Apache ignora las reglas del
  paquete y entrega los archivos estáticos de la carpeta, **incluido `.env` con
  la clave secreta de Supabase**.
  La aplicación lo detecta y responde 503, pero no puede impedir esa descarga:
  PHP no interviene cuando se pide un archivo estático. No use el ZIP en un
  servidor configurado así.
- Probado con XAMPP 8.0.30 para Linux (Apache 2.4.58, PHP 8.0.30, MariaDB
  10.4.32) y Chromium. El XAMPP del equipo de revisión no se ha comprobado.

El ZIP se genera con `ENTREGA_ENV_PRIVADO=<.env local> bash Tools/empaquetar-entrega.sh`
a partir del commit actual y agrega el `.env` descrito arriba (sin esa variable,
el `.env` sale sin la clave secreta). No incluye `.git`, respaldos ni
memoria de agentes. Como trae configuración real, se entrega en privado: no se
sube al repositorio ni se adjunta al PR.

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
En una instalación manual puede mostrar fallo de conexión hasta que configure `.env`; no muestra contraseñas
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

   `.env` no debe entrar en Git ni en un ZIP público con credenciales reales (el
   ZIP de entrega a Calidad es privado; ver "Qué trae el `.env` del ZIP").
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
cuenta de aplicación; el script exige `DB_PASS` y no trae una contraseña predeterminada propia (la de `.env.example` es la de desarrollo con Docker).
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
