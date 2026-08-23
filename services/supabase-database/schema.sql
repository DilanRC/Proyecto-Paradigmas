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
    tbdireccionid INTEGER NOT NULL,
    tbproductordireccionfechainicio TIMESTAMP WITHOUT TIME ZONE NULL,
    tbproductordireccionfechafin TIMESTAMP WITHOUT TIME ZONE NULL
);

CREATE TABLE IF NOT EXISTS public.tbproductorestadoperiodo (
    tbproductorestadoperiodoid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbproductorestadoperiodoestado SMALLINT NOT NULL,
    tbproductorestadoperiodofechainicio TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbproductorestadoperiodofechafin TIMESTAMP WITHOUT TIME ZONE NULL,
    tbproductorestadoperiodomotivo VARCHAR(250) NULL
);

CREATE TABLE IF NOT EXISTS public.tbproductorubicacion (
    tbproductorubicacionid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbproductorubicacionlatitud NUMERIC(10,7) NOT NULL,
    tbproductorubicacionlongitud NUMERIC(10,7) NOT NULL,
    tbproductorubicacionprecision NUMERIC(10,2) NULL,
    tbproductorubicacionfecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbproductorubicacionorigen VARCHAR(40) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbproductoractividad (
    tbproductoractividadid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbproductoractividadtipo VARCHAR(60) NOT NULL,
    tbproductoractividadfecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbproductoractividadorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbdireccion (
    tbdireccionid INTEGER NOT NULL,
    tbdireccionprovincia VARCHAR(100) NOT NULL,
    tbdireccioncanton VARCHAR(100) NOT NULL,
    tbdirecciondistrito VARCHAR(100) NOT NULL,
    tbdireccionpueblo VARCHAR(150) NULL,
    tbdireccionsenas VARCHAR(500) NULL
);

CREATE TABLE IF NOT EXISTS public.tbfinca (
    tbfincaid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbfincanombre VARCHAR(150) NOT NULL,
    tbfincaestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbfincadireccion (
    tbfincadireccionid INTEGER NOT NULL,
    tbfincaid INTEGER NOT NULL,
    tbdireccionid INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbpagometodo (
    tbpagometodoid INTEGER NOT NULL,
    tbpagometodonombre VARCHAR(100) NOT NULL,
    tbpagometododescripcion VARCHAR(250) NOT NULL,
    tbpagometodoactivo SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbtransportista (
    tbtransportistaid INTEGER NOT NULL,
    tbtransportistaidentificacionnumero VARCHAR(250) NOT NULL,
    tbtransportistaidentificaciontipo VARCHAR(40) NOT NULL,
    tbtransportistanombre VARCHAR(150) NOT NULL,
    tbtransportistatelefono VARCHAR(20) NOT NULL,
    tbtransportistacorreoelectronico VARCHAR(150) NOT NULL,
    tbtransportistaestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbvehiculo (
    tbvehiculoid INTEGER NOT NULL,
    tbvehiculoplaca VARCHAR(20) NOT NULL,
    tbvehiculovin VARCHAR(50) NOT NULL,
    tbvehiculomodelo VARCHAR(100) NOT NULL,
    tbvehiculoestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbtransportistavehiculo (
    tbtransportistavehiculoid INTEGER NOT NULL,
    tbtransportistaid INTEGER NOT NULL,
    tbvehiculoid INTEGER NOT NULL
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
ALTER TABLE public.tbproductorestadoperiodo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbproductorubicacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbproductoractividad ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbdireccion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbfinca ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbfincadireccion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbpagometodo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbtransportista ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbvehiculo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbtransportistavehiculo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbbitacora ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbcomprador ENABLE ROW LEVEL SECURITY;
