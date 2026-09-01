CREATE TABLE IF NOT EXISTS public.tbpersona (
    tbpersonaid INTEGER NOT NULL,
    tbpersonaidentificacionnumero VARCHAR(250) NOT NULL,
    tbpersonaidentificaciontipo VARCHAR(40) NOT NULL,
    tbpersonanombre VARCHAR(150) NOT NULL,
    tbpersonatelefono VARCHAR(20) NOT NULL,
    tbpersonacorreoelectronico VARCHAR(150) NOT NULL,
    tbpersonaestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbproductor (
    tbproductorid INTEGER NOT NULL,
    tbpersonaid INTEGER NOT NULL
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
    tbpersonaid INTEGER NOT NULL,
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
    tbpersonaid INTEGER NOT NULL,
    tbcompradorestado SMALLINT NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbproductorclasificacionperiodo (
    tbproductorclasificacionperiodoid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbproductorclasificacionperiodotipo VARCHAR(30) NOT NULL,
    tbproductorclasificacionperiodofechainicio TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbproductorclasificacionperiodofechafin TIMESTAMP WITHOUT TIME ZONE NULL,
    tbproductorclasificacionperiodomotivo VARCHAR(250) NULL
);

CREATE TABLE IF NOT EXISTS public.tbanimal (
    tbanimalid INTEGER NOT NULL,
    tbanimalidentificacion VARCHAR(100) NULL,
    tbanimalsexo VARCHAR(20) NULL,
    tbanimalraza VARCHAR(100) NULL,
    tbanimalcaracteristicas VARCHAR(500) NULL,
    tbanimalfecharegistroensistema TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbanimalorigenregistro VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbanimalproduccionsalud (
    tbanimalproduccionsaludid INTEGER NOT NULL,
    tbanimalid INTEGER NOT NULL,
    tbanimalproduccionsaludfecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbanimalproduccionsaludorigen VARCHAR(100) NOT NULL,
    tbanimalproduccionsaludcontexto VARCHAR(250) NULL,
    tbanimalproduccionsaludedadmeses INTEGER NULL,
    tbanimalproduccionsaludpeso NUMERIC(10,2) NULL,
    tbanimalproduccionsaludproposito VARCHAR(80) NULL,
    tbanimalproduccionsaludestadoreproductivo VARCHAR(80) NULL,
    tbanimalproduccionsaludpartos INTEGER NULL,
    tbanimalproduccionsaludlitrosleche NUMERIC(10,2) NULL,
    tbanimalproduccionsaludproduccion JSONB NULL,
    tbanimalproduccionsaludsalud JSONB NULL
);

CREATE TABLE IF NOT EXISTS public.tbanimalpublicacion (
    tbanimalpublicacionid INTEGER NOT NULL,
    tbanimalid INTEGER NOT NULL,
    tbproductorvendedorid INTEGER NOT NULL,
    tbfincaid INTEGER NOT NULL,
    tbanimalpublicacionfecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbanimalpublicacionprecio NUMERIC(12,2) NULL,
    tbanimalpublicaciontitulo VARCHAR(150) NULL,
    tbanimalpublicaciondescripcion VARCHAR(500) NULL,
    tbanimalpublicacionorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbcompra (
    tbcompraid INTEGER NOT NULL,
    tbanimalid INTEGER NOT NULL,
    tbproductorcompradorid INTEGER NOT NULL,
    tbfincaorigenid INTEGER NULL,
    tbcomprafecha DATE NOT NULL,
    tbcomprahora TIME WITHOUT TIME ZONE NULL,
    tbcompralugar VARCHAR(250) NULL,
    tbcompraprecio NUMERIC(12,2) NOT NULL,
    tbpagometodoid INTEGER NOT NULL,
    tbcompraorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbventa (
    tbventaid INTEGER NOT NULL,
    tbanimalid INTEGER NOT NULL,
    tbproductorvendedorid INTEGER NOT NULL,
    tbproductorcompradorid INTEGER NOT NULL,
    tbfincaid INTEGER NULL,
    tbcompraid INTEGER NULL,
    tbventafecha DATE NOT NULL,
    tbventahora TIME WITHOUT TIME ZONE NULL,
    tbventalugar VARCHAR(250) NULL,
    tbventadireccionid INTEGER NULL,
    tbventaproposito VARCHAR(80) NULL,
    tbventaprecio NUMERIC(12,2) NOT NULL,
    tbpagometodoid INTEGER NOT NULL,
    tbventaedadmeses INTEGER NULL,
    tbventapeso NUMERIC(10,2) NULL,
    tbventarazasnapshot VARCHAR(100) NULL,
    tbventaorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbanimalinteraccion (
    tbanimalinteraccionid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbanimalid INTEGER NOT NULL,
    tbanimalinteracciontipo VARCHAR(30) NOT NULL,
    tbanimalinteraccionaccion VARCHAR(30) NOT NULL,
    tbanimalinteraccionfecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbanimalinteraccionorigen VARCHAR(100) NULL
);

CREATE TABLE IF NOT EXISTS public.tbcarrito (
    tbcarritoid INTEGER NOT NULL,
    tbproductorid INTEGER NOT NULL,
    tbcarritofechacreacion TIMESTAMP WITHOUT TIME ZONE NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbcarritoanimal (
    tbcarritoanimalid INTEGER NOT NULL,
    tbcarritoid INTEGER NOT NULL,
    tbanimalid INTEGER NOT NULL,
    tbcarritoanimalaccion VARCHAR(30) NOT NULL,
    tbcarritoanimalfecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbcarritoanimalorigen VARCHAR(100) NULL
);

CREATE TABLE IF NOT EXISTS public.tbtransportistaestadoperiodo (
    tbtransportistaestadoperiodoid INTEGER NOT NULL,
    tbtransportistaid INTEGER NOT NULL,
    tbtransportistaestadoperiodoestado SMALLINT NOT NULL,
    tbtransportistaestadoperiodofechainicio TIMESTAMP WITHOUT TIME ZONE NULL,
    tbtransportistaestadoperiodofechafin TIMESTAMP WITHOUT TIME ZONE NULL,
    tbtransportistaestadoperiodomotivo VARCHAR(250) NULL,
    tbtransportistaestadoperiodofecharegistroensistema TIMESTAMP WITHOUT TIME ZONE NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbtransportistaflete (
    tbtransportistafleteid INTEGER NOT NULL,
    tbtransportistaid INTEGER NOT NULL,
    tbproductororigenid INTEGER NULL,
    tbfincaorigenid INTEGER NULL,
    tbdireccionorigenid INTEGER NULL,
    tbdirecciondestinoid INTEGER NULL,
    tbvehiculoid INTEGER NULL,
    tbtransportistafletefecha DATE NOT NULL,
    tbtransportistafletehora TIME WITHOUT TIME ZONE NULL,
    tbtransportistafletedescripcion VARCHAR(500) NULL,
    tbtransportistafletecantidadcabezas INTEGER NULL,
    tbtransportistafletedistanciakm NUMERIC(10,2) NULL,
    tbtransportistafleteprecio NUMERIC(12,2) NULL,
    tbpagometodoid INTEGER NOT NULL,
    tbtransportistafleteorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbtransportistaresena (
    tbtransportistaresenaid INTEGER NOT NULL,
    tbtransportistaid INTEGER NOT NULL,
    tbpersonaid INTEGER NOT NULL,
    tbtransportistafleteid INTEGER NULL,
    tbtransportistaresenafecha TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbtransportistaresenacalificacion INTEGER NOT NULL,
    tbtransportistaresenacomentario VARCHAR(500) NULL,
    tbtransportistaresenaorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbanimalpublicacionestadoperiodo (
    tbanimalpublicacionestadoperiodoid INTEGER NOT NULL,
    tbanimalpublicacionid INTEGER NOT NULL,
    tbanimalpublicacionestadoperiodoestado VARCHAR(30) NOT NULL,
    tbanimalpublicacionestadoperiodofechainicio TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbanimalpublicacionestadoperiodofechafin TIMESTAMP WITHOUT TIME ZONE NULL,
    tbanimalpublicacionestadoperiodomotivo VARCHAR(250) NULL,
    tbanimalpublicacionestadoperiodoorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbcarritoestadoperiodo (
    tbcarritoestadoperiodoid INTEGER NOT NULL,
    tbcarritoid INTEGER NOT NULL,
    tbcarritoestadoperiodoestado VARCHAR(30) NOT NULL,
    tbcarritoestadoperiodofechainicio TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbcarritoestadoperiodofechafin TIMESTAMP WITHOUT TIME ZONE NULL,
    tbcarritoestadoperiodomotivo VARCHAR(250) NULL,
    tbcarritoestadoperiodoorigen VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS public.tbtransportistahorario (
    tbtransportistahorarioid INTEGER NOT NULL,
    tbtransportistaid INTEGER NOT NULL,
    tbtransportistahorariodiasemana VARCHAR(15) NOT NULL,
    tbtransportistahorariohorainicio TIME WITHOUT TIME ZONE NOT NULL,
    tbtransportistahorariohorafin TIME WITHOUT TIME ZONE NOT NULL,
    tbtransportistahorariofechainicio TIMESTAMP WITHOUT TIME ZONE NOT NULL,
    tbtransportistahorariofechafin TIMESTAMP WITHOUT TIME ZONE NULL,
    tbtransportistahorarioorigen VARCHAR(100) NOT NULL
);

ALTER TABLE public.tbpersona ENABLE ROW LEVEL SECURITY;
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
ALTER TABLE public.tbproductorclasificacionperiodo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbanimal ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbanimalproduccionsalud ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbanimalpublicacion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbcompra ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbventa ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbanimalinteraccion ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbcarrito ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbcarritoanimal ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbtransportistaestadoperiodo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbtransportistaflete ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbtransportistaresena ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbanimalpublicacionestadoperiodo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbcarritoestadoperiodo ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.tbtransportistahorario ENABLE ROW LEVEL SECURITY;
