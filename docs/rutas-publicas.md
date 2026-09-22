# Rutas públicas de TinderCows

La aplicación separa la URL que ve la persona de los wrappers PHP y de la
implementación interna. Apache recibe las rutas limpias en
`Public/.htaccess`, las reescribe internamente y conserva PHP como detalle de
servidor.

## Páginas

| Ruta pública | Superficie | Uso |
|---|---|---|
| `/` | Pública | Inicio |
| `/explorar` | Pública | Explorar publicaciones |
| `/entrar` | Pública | Inicio de sesión |
| `/registro` | Pública | Registro guiado |
| `/registro/productor` | Pública | Registro guiado con Productor preseleccionado |
| `/registro/comprador` | Pública | Registro guiado con Comprador preseleccionado |
| `/registro/transportista` | Pública | Registro guiado con Transportista preseleccionado |
| `/fletes` | Pública | Oferta de transporte |
| `/mi-actividad` | Privada de Persona | Dashboard de identidad, actividades, fincas y vehículos propios |
| `/publicar` | Privada de Persona | Publicar ganado |
| `/sobre-nosotros` | Informativa | Información del producto |
| `/como-usar` | Informativa | Guía de uso |
| `/privacidad` | Legal | Política de privacidad |
| `/terminos` | Legal | Términos de uso |
| `/legal` | Legal | Información legal |
| `/admin/entrar` | Privada | Entrada administrativa |
| `/admin/dashboard` | Administrativa | Resumen central e indicadores |
| `/admin/productores` | Administrativa | Productores |
| `/admin/compradores` | Administrativa | Compradores |
| `/admin/transportistas` | Administrativa | Transportistas |
| `/admin/vehiculos` | Administrativa | Vehículos |
| `/admin/metodos-pago` | Administrativa | Métodos de pago |

## API versionada

Todas las operaciones JSON nuevas usan `/api/v1/`. El cliente ya no genera
rutas con `.php`; los endpoints PHP anteriores se mantienen solo como
compatibilidad técnica durante la migración.

| Ruta | Recurso |
|---|---|
| `/api/v1/auth/config` | Configuración pública de autenticación |
| `/api/v1/admin/status` | Estado de autorización administrativa |
| `/api/v1/actividad` | Actividad de la Persona autenticada |
| `/api/v1/mi-vehiculos` | Vehículos propios del Transportista autenticado; nunca recibe una Persona o Transportista objetivo |
| `/api/v1/mi-fincas` | Fincas propias del Productor autenticado; nunca recibe una Persona o Productor objetivo |
| `/api/v1/identidad` | Identidad pública autenticada |
| `/api/v1/registro` | Alta transaccional de identidad y actividades |
| `/api/v1/capacidades` | Activar, desactivar o consultar capacidades |
| `/api/v1/publicaciones` | Lectura y publicación de ganado |
| `/api/v1/publicaciones/interacciones` | Pasar, interesarse y contactar |
| `/api/v1/productores` | CRUD administrativo de Productores |
| `/api/v1/productores/direccion` | Dirección principal de Productor |
| `/api/v1/productores/ubicacion` | Ubicación histórica de Productor |
| `/api/v1/compradores` | Consulta administrativa de Compradores |
| `/api/v1/transportistas` | CRUD administrativo de Transportistas |
| `/api/v1/transportistas/vehiculos` | Asociación Transportista-Vehículo |
| `/api/v1/vehiculos` | CRUD administrativo de Vehículos |
| `/api/v1/metodos-pago` | CRUD administrativo de métodos de pago |
| `/api/v1/fincas/direccion` | Dirección de finca |

## Política de información en URL

- Nunca se colocan contraseñas, tokens, JWT, correos de autenticación ni
  teléfonos en rutas públicas.
- Las rutas de destino y la capacidad inicial usan nombres de recurso, no
  identificaciones personales.
- Las operaciones de escritura envían sus datos por JSON en el cuerpo HTTPS.
- Los filtros de consulta deben tratarse como datos potencialmente sensibles:
  no se deben convertir en enlaces compartibles ni registrar en la interfaz.
Las lecturas administrativas y las consultas de fincas se envían como `POST`
de consulta con un cuerpo JSON `{ "consulta": { ... } }`. Así, identificaciones,
búsquedas y coordenadas no aparecen en el historial del navegador, proxies ni
encabezados `Referer`. Las escrituras también usan JSON; una consulta nunca se
confunde con una creación porque el servidor exige la envoltura `consulta`.

## Compatibilidad

Las URLs antiguas como `/registro.php` o `/productores.php` se redirigen a sus
equivalentes limpios. Los `/api/*.php` antiguos siguen respondiendo para no
romper pruebas, integraciones existentes o marcadores, pero no son generados
por la interfaz.
