CREATE TABLE IF NOT EXISTS public.tbproductor (
    tbproductorid INTEGER NOT NULL,
    tbproductoridentificacionnumero VARCHAR(250) NOT NULL,
    tbproductoridentificaciontipo VARCHAR(40) NOT NULL,
    tbproductornombre VARCHAR(150) NOT NULL,
    tbproductortelefono VARCHAR(20) NOT NULL,
    tbproductorcorreoelectronico VARCHAR(150) NOT NULL,
    tbproductorestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbproductordireccion (
    tbproductordireccionid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbproductordireccionprovincia VARCHAR(100) NOT NULL,
    tbproductordireccioncanton VARCHAR(100) NOT NULL,
    tbproductordirecciondistrito VARCHAR(100) NOT NULL,
    tbproductordireccionpueblo VARCHAR(150) NULL,
    tbproductordireccionsenas VARCHAR(500) NULL
);

CREATE TABLE IF NOT EXISTS public.tbfinca (
    tbfincaid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbfincanombre VARCHAR(150) NOT NULL,
    tbfincaestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbbitacora (
    tbbitacoraid BIGINT NOT NULL,
    tbbitacoraentidad VARCHAR(80) NOT NULL,
    tbbitacoraregistroidentificacionnumero VARCHAR(250) NOT NULL,
    tbbitacoraaccion VARCHAR(30) NOT NULL,
    tbbitacorafecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbbitacoradatosanteriores JSONB NULL,
    tbbitacoradatosnuevos JSONB NULL,
    tbbitacoraactortipo VARCHAR(30) NOT NULL,
    tbbitacorausuarioid BIGINT NULL,
    tbbitacoraorigen VARCHAR(100) NOT NULL,
    tbbitacorasolicitudid VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbcomprador (
    tbcompradorid INTEGER NOT NULL,
    tbcompradoridentificacionnumero VARCHAR(250) NOT NULL,
    tbcompradoridentificaciontipo VARCHAR(40) NOT NULL,
    tbcompradornombre VARCHAR(150) NOT NULL,
    tbcompradortelefono VARCHAR(20) NOT NULL,
    tbcompradorcorreoelectronico VARCHAR(150) NOT NULL,
    tbcompradorestado SMALLINT NOT NULL
);

ALTER TABLE public.tbproductor ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbproductordireccion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbfinca ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbbitacora ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbcomprador ENABLE ROW LEVEL SECURITY;
