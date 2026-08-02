# DER - Modelo docente sin restricciones

```mermaid
erDiagram
    tbproductor {
        INT tbproductorId
        VARCHAR tbproductorIdentificacionNumero
        VARCHAR tbproductorIdentificacionTipo
        VARCHAR tbproductorNombre
        VARCHAR tbproductorTelefono
        VARCHAR tbproductorCorreoElectronico
        TINYINT tbproductorEstado
    }
    tbproductordireccion {
        INT tbproductorId
        VARCHAR tbproductordireccionProvincia
        VARCHAR tbproductordireccionCanton
        VARCHAR tbproductordireccionDistrito
        VARCHAR tbproductordireccionPueblo
        VARCHAR tbproductordireccionSenas
    }
    tbproductorfinca {
        INT tbproductorId
        VARCHAR tbproductorfincaNombre
        TINYINT tbproductorfincaEstado
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
        BIGINT tbbitacoraUsuarioId
        VARCHAR tbbitacoraOrigen
        VARCHAR tbbitacoraSolicitudId
    }
    tbproductor ||--|| tbproductordireccion : "asociación lógica por tbproductorId"
    tbproductor ||--o{ tbproductorfinca : "asociación lógica por tbproductorId"
    tbproductor ||--o{ tbbitacora : "referencia lógica por identificación"
```

Las líneas muestran asociaciones usadas por PHP, no restricciones de MySQL.
El esquema define cero `PRIMARY KEY`, cero `FOREIGN KEY` y cero `CHECK`.
`tbproductorId` es `INT NOT NULL`, no es clave y no usa `AUTO_INCREMENT`; PHP
calcula el siguiente valor bajo un bloqueo de alta. `tbbitacoraId` conserva
`AUTO_INCREMENT` mediante un índice ordinario no único.
