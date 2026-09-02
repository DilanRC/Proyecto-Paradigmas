# Rutas de TinderCows

Estado documentado: 2026-09-01, rama `dev`.

## Ejecución local

La aplicación expone Apache en `http://localhost:8080` mediante `compose.yaml` (`8080:80`).

```bash
docker compose up --build
```

La base MySQL usa el puerto interno `3306` y, por defecto, se publica en el host por `3309`. phpMyAdmin se publica en `http://127.0.0.1:8081`.

## Sitio público

| Ruta | Propósito |
|---|---|
| `/` | Inicio público y presentación del producto. |
| `/explorar.php` | Explorar publicaciones de muestra mediante tarjetas deslizables, búsqueda y filtros rápidos. |
| `/sobre-nosotros.php` | Descripción de TinderCows como producto. |
| `/como-usar.php` | Guía de exploración, búsqueda, cuenta y acciones. |
| `/privacidad.php` | Estado actual de privacidad y datos. |
| `/terminos.php` | Términos de uso. |
| `/legal.php` | Información legal y pendientes antes de operación real. |
| `/login.php` | Acceso local de demostración; por defecto vuelve a `/explorar.php`. |

La navegación primaria pública expone Inicio, Explorar, Nosotros y Cómo funciona. Privacidad, Términos e Información legal viven en el footer, no en el navbar.

## Administración interna

Estas rutas pasan por `Public/js/shared/auth-gate.js`. El gate comprueba un marcador local de `sessionStorage`; **no es autenticación de servidor**.

| Ruta | Módulo |
|---|---|
| `/productores.php` | Productores. |
| `/compradores.php` | Compradores derivados. |
| `/transportistas.php` | Transportistas. |
| `/vehiculos.php` | Vehículos. |
| `/pagometodos.php` | Métodos de pago. |

El login acepta `?next=<ruta-permitida>` para volver a un destino local permitido. La lista contempla `/explorar.php` y las cinco rutas administrativas anteriores. Un acceso público sin `next` nunca abre administración: vuelve a `/explorar.php`.

## APIs del proyecto

| Ruta |
|---|
| `/api/productores.php` |
| `/api/productores-direccion.php` |
| `/api/productores-ubicacion.php` |
| `/api/fincas-direccion.php` |
| `/api/compradores.php` |
| `/api/transportistas.php` |
| `/api/transportistas-vehiculos.php` |
| `/api/vehiculos.php` |
| `/api/pagometodos.php` |
| `/api/metodo-no-permitido.php` |

`/api/metodo-no-permitido.php` es una respuesta auxiliar para métodos HTTP no admitidos; no es una pantalla navegable.

## Estado de autenticación

### Implementado

- Formulario de acceso con validación de navegador.
- Marcador de sesión local en `sessionStorage`.
- Redirección pública por defecto hacia `explorar.php`.
- Redirección a `login.php?next=...` cuando una ruta administrativa no tiene marcador local válido.
- Cierre de la sesión local desde el shell administrativo.
- La interfaz privada se mantiene oculta hasta que el gate del frontend valida el marcador.

### No implementado todavía

- Validación real de credenciales contra backend.
- Autorización de servidor basada en la sesión visual del frontend.
- Catálogo real de ganado/subastas conectado a la vista Explorar.
- Persistencia real de favoritos, contacto y pujas.
- Política definitiva de privacidad, retención y ejercicio de derechos.

La autorización real de API continúa siendo un mecanismo separado del login visual y debe comprobarse mediante la capa Bearer/Supabase correspondiente.
