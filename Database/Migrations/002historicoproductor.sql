USE dbmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Migración manual, de una sola ejecución. Agrega el histórico de estado, la
-- ubicación observada y la actividad del productor (plan de remodelado EIF400
-- §7, §9, §17), y prepara tbproductordireccion para su futuro histórico de
-- dirección (plan §8) sin romper el enlace vigente de tres columnas.
--
-- Una base limpia NO ejecuta este archivo: Database/SqlScripts/000instalacioncompleta.sql
-- ya crea las tablas y columnas nuevas. Repetir esta migración es seguro
-- (CREATE TABLE IF NOT EXISTS es idempotente); el único paso no idempotente es
-- el ALTER TABLE de tbproductordireccion, protegido por la comprobación previa.
--
-- Comprobar antes de ejecutar. Si devuelve 0, la migración ya se aplicó:
--   SELECT COUNT(*) FROM information_schema.columns
--   WHERE table_schema = 'dbmercadoganadero'
--     AND table_name = 'tbproductordireccion'
--     AND column_name = 'tbproductordireccionfechainicio';
--
-- Respaldar antes:
--   Tools/backup-database.sh <Entrega> <Autor>
--
-- Ejecutar:
--   docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
--     < Database/Migrations/002historicoproductor.sql
--
-- Esta migración NO retira tbproductorestado de tbproductor ni tbcomprador:
-- Application/Model/Productor.php y el módulo de comprador siguen
-- dependiendo de ellos. Esa limpieza es un cambio de aplicación aparte
-- (plan §4 y §6) y debe hacerse junto con el PHP correspondiente.

-- Paso 1: columnas de fecha en el enlace de dirección. Ejecutar solamente si
-- la comprobación del encabezado devolvió 0; de lo contrario ya están creadas
-- y este ALTER termina con el error 1060 de MySQL (columna duplicada).
ALTER TABLE tbproductordireccion
    ADD COLUMN tbproductordireccionfechainicio DATETIME NULL,
    ADD COLUMN tbproductordireccionfechafin DATETIME NULL;

-- Paso 2: tablas históricas nuevas. CREATE TABLE IF NOT EXISTS ya es
-- idempotente por sí solo.
CREATE TABLE IF NOT EXISTS tbproductorestadoperiodo (
    tbproductorestadoperiodoid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorestadoperiodoestado TINYINT(1) NOT NULL,
    tbproductorestadoperiodofechainicio DATETIME NOT NULL,
    tbproductorestadoperiodofechafin DATETIME NULL,
    tbproductorestadoperiodomotivo VARCHAR(250) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductorubicacion (
    tbproductorubicacionid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductorubicacionlatitud DECIMAL(10,7) NOT NULL,
    tbproductorubicacionlongitud DECIMAL(10,7) NOT NULL,
    tbproductorubicacionprecision DECIMAL(10,2) NULL,
    tbproductorubicacionfecha DATETIME NOT NULL,
    tbproductorubicacionorigen VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tbproductoractividad (
    tbproductoractividadid INT NOT NULL,
    tbproductorid INT NOT NULL,
    tbproductoractividadtipo VARCHAR(60) NOT NULL,
    tbproductoractividadfecha DATETIME NOT NULL,
    tbproductoractividadorigen VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Paso 3: estructura final esperada.
DESCRIBE tbproductordireccion;
DESCRIBE tbproductorestadoperiodo;
DESCRIBE tbproductorubicacion;
DESCRIBE tbproductoractividad;
