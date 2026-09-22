# Ejecutar TinderCows con XAMPP

El proyecto debe servirse con Apache usando `Public/` como raíz pública. No se
debe abrir `http://localhost/` esperando que XAMPP adivine el proyecto: esa URL
pertenece al dashboard de XAMPP si no se configuró un VirtualHost.

## Opción recomendada: VirtualHost

1. Copie el repositorio, por ejemplo, en:

   `C:/xampp/htdocs/Proyecto-Paradigmas`

2. En `C:/xampp/apache/conf/extra/httpd-vhosts.conf`, agregue:

   ```apache
   <VirtualHost *:80>
       ServerName proyecto-paradigmas.local
       DocumentRoot "C:/xampp/htdocs/Proyecto-Paradigmas/Public"

       <Directory "C:/xampp/htdocs/Proyecto-Paradigmas/Public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. En `C:/Windows/System32/drivers/etc/hosts`, agregue como administrador:

   ```text
   127.0.0.1 proyecto-paradigmas.local
   ```

4. En `C:/xampp/apache/conf/httpd.conf`, confirme que estas líneas no estén
   comentadas:

   ```apache
   LoadModule rewrite_module modules/mod_rewrite.so
   Include conf/extra/httpd-vhosts.conf
   ```

5. Reinicie Apache desde XAMPP y abra:

   <http://proyecto-paradigmas.local/>

Con esta opción la aplicación usa `/` como base y las rutas limpias, CSS, JS,
imágenes y API funcionan igual que en Docker.

## Opción rápida: subcarpeta de htdocs

Si no desea configurar un VirtualHost, deje el repositorio en:

`C:/xampp/htdocs/Proyecto-Paradigmas`

Active `mod_rewrite` y `AllowOverride All`, reinicie Apache y abra exactamente:

<http://localhost/Proyecto-Paradigmas/Public/>

No abra solamente <http://localhost/>: esa es la página de XAMPP. Las vistas
calculan automáticamente la base `/Proyecto-Paradigmas/Public/` para que los
assets no se soliciten desde `/css`, `/js` o `/api` en la raíz de XAMPP.

## Base de datos

La configuración de PHP debe apuntar al MySQL de XAMPP. Copie `.env.example` a
`.env` y ajuste al menos `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME`,
`DB_USER` y `DB_PASS` según la instalación del profesor. Las tablas deben estar
creadas antes de probar las pantallas que consultan la API.
