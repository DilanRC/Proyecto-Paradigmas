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
    tbcomprador {
        INT tbcompradorid
        VARCHAR tbcompradoridentificacionnumero
        VARCHAR tbcompradoridentificaciontipo
        VARCHAR tbcompradornombre
        VARCHAR tbcompradortelefono
        VARCHAR tbcompradorcorreoelectronico
        TINYINT tbcompradorestado
    }
    tbproductor ||--|| tbproductordireccion : "asociación lógica por tbproductorid"
    tbproductor ||--o{ tbfinca : "asociación lógica por tbproductorid"
    tbproductor ||--o{ tbbitacora : "referencia lógica por identificación"
```

Las líneas muestran asociaciones usadas por PHP, no restricciones de MySQL.
`tbcomprador` es independiente y todavía no participa en el CRUD de
productores.
El esquema define cero claves, restricciones, índices, valores `DEFAULT`,
`AUTO_INCREMENT` y objetos programables. PHP calcula cada identificador
mediante `MAX(id) + 1` bajo un bloqueo nombrado y envía todos los valores con
sentencias preparadas.
