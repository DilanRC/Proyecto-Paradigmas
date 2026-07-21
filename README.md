# Proyecto Paradigmas - TinderCows

Aplicación web académica para administrar productores, con módulos futuros para ganado y subastas.

## Entorno de desarrollo con Docker

Docker proporciona el mismo entorno de PHP 8.3, Apache y MySQL 8.0 para cada integrante del equipo.

```bash
cp .env.example .env
docker compose up --build -d
```

En Windows PowerShell, use `Copy-Item .env.example .env`. Abra la aplicación en `http://localhost:8080` y Adminer en `http://localhost:8081`.

Datos de conexión de Adminer: sistema `MySQL`, servidor `db`, usuario `DB_USER`, contraseña `DB_PASS` y base de datos `DB_NAME`.

Comandos útiles:

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f db
docker compose down
```

Reconstruya después de modificar el Dockerfile o la configuración de Apache con `docker compose up --build -d`. Los scripts de inicialización se ejecutan solamente cuando se crea el volumen de MySQL por primera vez. `docker compose down -v` elimina ese volumen de base de datos; úselo con cuidado y luego ejecute `docker compose up --build -d` para crear una base de datos de desarrollo limpia.

## Ejecución local

1. Ejecute `Database/SqlScripts/001_create_producers.sql` en MySQL.
2. Opcionalmente cargue `Database/SeedData/001_example_producers.sql`.
3. Inicie PHP con `php -S localhost:8000 -t Public`.
4. Abra `http://localhost:8000`.

Valores predeterminados de conexión: `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_NAME=tinder_cows`, `DB_USER=root` y `DB_PASS` vacío.

## Estructura

`Application/` contiene el código MVC, `Configuration/` contiene los auxiliares de base de datos y HTTP, `Public/` es la raíz pública, `Database/` contiene el esquema y los datos iniciales, `Documentation/` contiene los documentos del proyecto y `Tests/` contiene los scripts de validación.

## API de productores

| Método | Ruta | Acción |
|---|---|---|
| `GET` | `/api/producers.php` | Listar o buscar productores |
| `GET` | `/api/producers.php?id=1` | Consultar un productor |
| `POST` | `/api/producers.php` | Crear un productor |
| `PUT` | `/api/producers.php` | Actualizar un productor |
| `DELETE` | `/api/producers.php` | Desactivar un productor |

Todos los cuerpos de solicitud y las respuestas usan `application/json`. La API usa los campos de respuesta `success`, `message`, `data` y, opcionalmente, `errors`.
