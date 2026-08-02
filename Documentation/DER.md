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

La dirección es obligatoria 1:1. Las fincas son 0:N. La línea de bitácora es
lógica y no una FK para conservar el historial independientemente del dominio.
