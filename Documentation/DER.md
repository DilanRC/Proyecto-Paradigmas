# DER - Modelo docente sin restricciones

```mermaid
erDiagram
    tbproductor {
        INT tbproductorid
        VARCHAR tbproductoridentificacionnumero
        VARCHAR tbproductoridentificaciontipo
        VARCHAR tbproductornombre
        VARCHAR tbproductortelefono
        VARCHAR tbproductorcorreoelectronico
        TINYINT tbproductorestado
    }
    tbproductordireccion {
        INT tbproductordireccionid
        INT tbproductorid
        VARCHAR tbproductordireccionprovincia
        VARCHAR tbproductordireccioncanton
        VARCHAR tbproductordirecciondistrito
        VARCHAR tbproductordireccionpueblo
        VARCHAR tbproductordireccionsenas
    }
    tbfinca {
        INT tbfincaid
        INT tbproductorid
        VARCHAR tbfincanombre
        TINYINT tbfincaestado
    }
    tbbitacora {
        BIGINT tbbitacoraid
        VARCHAR tbbitacoraentidad
        VARCHAR tbbitacoraregistroidentificacionnumero
        VARCHAR tbbitacoraaccion
        DATETIME tbbitacorafecha
        JSON tbbitacoradatosanteriores
        JSON tbbitacoradatosnuevos
        VARCHAR tbbitacoraactortipo
        BIGINT tbbitacorausuarioid
        VARCHAR tbbitacoraorigen
        VARCHAR tbbitacorasolicitudid
    }
    tbproductor ||--|| tbproductordireccion : "asociación lógica por tbproductorid"
    tbproductor ||--o{ tbfinca : "asociación lógica por tbproductorid"
    tbproductor ||--o{ tbbitacora : "referencia lógica por identificación"
```

Las líneas muestran asociaciones usadas por PHP, no restricciones de MySQL.
El esquema define cero claves, restricciones, índices y `AUTO_INCREMENT`. PHP
calcula cada identificador mediante `MAX(id) + 1` bajo un bloqueo nombrado.
