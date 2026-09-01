# DER - Productor núcleo y compatibilidad legacy

```mermaid
erDiagram
    tbpersona {
        INT tbpersonaid
        VARCHAR tbpersonaidentificacionnumero
        VARCHAR tbpersonaidentificaciontipo
        VARCHAR tbpersonanombre
        VARCHAR tbpersonatelefono
        VARCHAR tbpersonacorreoelectronico
        TINYINT tbpersonaestado
    }
    tbproductor { INT tbproductorid INT tbpersonaid }
    tbcomprador { INT tbcompradorid INT tbpersonaid TINYINT tbcompradorestado }
    tbtransportista { INT tbtransportistaid INT tbpersonaid TINYINT tbtransportistaestado }
    tbproductordireccion { INT tbproductordireccionid INT tbproductorid INT tbdireccionid DATETIME tbproductordireccionfechainicio DATETIME tbproductordireccionfechafin }
    tbdireccion { INT tbdireccionid VARCHAR tbdireccionprovincia VARCHAR tbdireccioncanton VARCHAR tbdirecciondistrito VARCHAR tbdireccionpueblo VARCHAR tbdireccionsenas }
    tbfinca { INT tbfincaid INT tbproductorid VARCHAR tbfincanombre TINYINT tbfincaestado }
    tbfincadireccion { INT tbfincadireccionid INT tbfincaid INT tbdireccionid }
    tbpagometodo { INT tbpagometodoid VARCHAR tbpagometodonombre VARCHAR tbpagometododescripcion TINYINT tbpagometodoactivo }
    tbvehiculo { INT tbvehiculoid VARCHAR tbvehiculoplaca VARCHAR tbvehiculovin VARCHAR tbvehiculomodelo TINYINT tbvehiculoestado }
    tbtransportistavehiculo { INT tbtransportistavehiculoid INT tbtransportistaid INT tbvehiculoid }
    tbproductorestadoperiodo { INT tbproductorestadoperiodoid INT tbproductorid TINYINT tbproductorestadoperiodoestado DATETIME tbproductorestadoperiodofechainicio DATETIME tbproductorestadoperiodofechafin VARCHAR tbproductorestadoperiodomotivo }
    tbproductorubicacion { INT tbproductorubicacionid INT tbproductorid DECIMAL tbproductorubicacionlatitud DECIMAL tbproductorubicacionlongitud DECIMAL tbproductorubicacionprecision DATETIME tbproductorubicacionfecha VARCHAR tbproductorubicacionorigen }
    tbproductoractividad { INT tbproductoractividadid INT tbproductorid VARCHAR tbproductoractividadtipo DATETIME tbproductoractividadfecha VARCHAR tbproductoractividadorigen }
    tbbitacora { BIGINT tbbitacoraid VARCHAR tbbitacoraentidad VARCHAR tbbitacoraregistroidentificacionnumero VARCHAR tbbitacoraaccion DATETIME tbbitacorafecha JSON tbbitacoradatosanteriores JSON tbbitacoradatosnuevos VARCHAR tbbitacoraactortipo BIGINT tbbitacorausuarioid VARCHAR tbbitacoraorigen VARCHAR tbbitacorasolicitudid }

    tbpersona ||--o| tbproductor : "capacidad por tbpersonaid"
    tbpersona ||--o| tbcomprador : "legacy por tbpersonaid"
    tbpersona ||--o| tbtransportista : "capacidad por tbpersonaid"
    tbproductor ||--|| tbproductordireccion : "residencia"
    tbproductordireccion }o--|| tbdireccion : "ubicacion"
    tbproductor ||--o{ tbfinca : "fincas"
    tbfinca ||--|| tbfincadireccion : "direccion"
    tbfincadireccion }o--|| tbdireccion : "ubicacion"
    tbtransportista ||--o{ tbtransportistavehiculo : "vehiculos"
    tbtransportistavehiculo }o--|| tbvehiculo : "vehiculo"
    tbproductor ||--o{ tbproductorestadoperiodo : "historico de estado"
    tbproductor ||--o{ tbproductorubicacion : "ubicaciones"
    tbproductor ||--o{ tbproductoractividad : "actividad"
```

## Identidad, capacidades y legacy

`tbpersona` es la fuente única de identificación, tipo, nombre, teléfono,
correo y disponibilidad global. `tbproductor` y `tbtransportista` conservan sus
IDs operativos y solo contienen el enlace `tbpersonaid` y su estado de
participación. `tbcomprador` se conserva como estructura legacy; P0-C define
que Comprador y Vendedor son clasificaciones históricas derivadas del
Productor.

El estado efectivo se calcula en PHP:

```text
capacidad efectiva = tbpersonaestado activo Y perfil.estado activo
```

Desactivar un perfil no afecta los otros. Desactivar `tbpersona` vuelve
inoperantes las capacidades actuales. `DELETE` de las APIs desactiva únicamente
el perfil y `PATCH` únicamente lo reactiva, siempre que la persona esté activa.

## Relaciones y política física

El modelo contiene exactamente 15 tablas. Las líneas del DER son relaciones
conceptuales usadas por PHP. MySQL y PostgreSQL no declaran PK, FK, UNIQUE,
CHECK, índices, ENUM, valores DEFAULT, AUTO_INCREMENT, triggers, rutinas ni
eventos. En términos del contrato docente: el esquema mantiene cero claves y
cero restricciones, índices u objetos programables. PHP garantiza IDs,
unicidad, coherencia, transacciones y bloqueos.

`tbproductordireccion` y `tbfincadireccion` solo enlazan ubicaciones de
`tbdireccion`. `tbproductorubicacion` conserva posiciones GPS append-only y no
reemplaza la residencia editable. Los IDs de perfiles y todas las relaciones
existentes permanecen iguales durante la migración.
