# DER - Modelo simplificado de productores

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
        VARCHAR tbproductoresIdentificacionNumero
        VARCHAR tbproductoresdireccionProvincia
        VARCHAR tbproductoresdireccionCanton
        VARCHAR tbproductoresdireccionDistrito
        VARCHAR tbproductoresdireccionPueblo
        VARCHAR tbproductoresdireccionSenas
    }
    tbproductoresfinca {
        VARCHAR tbproductoresIdentificacionNumero
        VARCHAR tbproductoresfincaNombre
        TINYINT tbproductoresfincaEstado
    }
    tbbitacora {
        BIGINT tbbitacoraId
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
    tbproductores ||--|| tbproductoresdireccion : "relación lógica de aplicación"
    tbproductores ||--o{ tbproductoresfinca : "relación lógica de aplicación"
    tbproductores ||--o{ tbbitacora : "referencia lógica"
```

La única PRIMARY KEY es
`tbproductores.tbproductoresIdentificacionNumero`. Dirección, finca y bitácora
no tienen PRIMARY KEY. El esquema no contiene ninguna FOREIGN KEY. Las líneas
del diagrama representan cardinalidades lógicas controladas por la aplicación,
no restricciones referenciales de MySQL.

`tbbitacoraId` conserva `AUTO_INCREMENT` mediante un índice ordinario no único;
ese índice no es una PRIMARY KEY ni una FOREIGN KEY.
