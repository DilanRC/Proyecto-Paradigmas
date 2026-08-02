# DER — Modelo simplificado de productores

```mermaid
erDiagram
    tbproductores {
        VARCHAR tbproductoresIdentificacionNumero PK
        VARCHAR tbproductoresIdentificacionTipo
        VARCHAR tbproductoresNombre
        VARCHAR tbproductoresTelefono
        VARCHAR tbproductoresCorreoElectronico
        TINYINT tbproductoresEstado
    }
    tbproductoresdireccion {
        VARCHAR tbproductoresIdentificacionNumero PK,FK
        VARCHAR tbproductoresdireccionProvincia
        VARCHAR tbproductoresdireccionCanton
        VARCHAR tbproductoresdireccionDistrito
        VARCHAR tbproductoresdireccionPueblo
        VARCHAR tbproductoresdireccionSenas
    }
    tbproductoresfinca {
        VARCHAR tbproductoresIdentificacionNumero PK,FK
        VARCHAR tbproductoresfincaNombre PK
        TINYINT tbproductoresfincaEstado
    }
    tbbitacora {
        BIGINT tbbitacoraId PK
        VARCHAR tbbitacoraEntidad
        VARCHAR tbbitacoraRegistroIdentificacionNumero
        VARCHAR tbbitacoraAccion
        DATETIME tbbitacoraFecha
        JSON tbbitacoraDatosAnteriores
        JSON tbbitacoraDatosNuevos
        VARCHAR tbbitacoraActorTipo
        BIGINT tbusuarioId
        VARCHAR tbbitacoraOrigen
        VARCHAR tbbitacoraSolicitudId
    }
    tbproductores ||--|| tbproductoresdireccion : "tiene"
    tbproductores ||--o{ tbproductoresfinca : "registra"
    tbproductores ||--o{ tbbitacora : "referencia lógica"
```

En `tbproductoresfinca`, ambas columnas marcadas `PK` forman una única PRIMARY
KEY compuesta. La dirección es obligatoria 1:1 por la transacción de la
aplicación; la PK/FK compartida impide más de una dirección y evita huérfanos.
Las fincas son 0:N. La línea de bitácora es una referencia lógica, no una FK,
para conservar el historial. Las FK de dirección y finca aplican
`ON UPDATE RESTRICT` y `ON DELETE RESTRICT`.
