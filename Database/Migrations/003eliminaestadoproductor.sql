USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Migración manual, de una sola ejecución. Retira tbproductorestado de
-- tbproductor después de trasladar su valor al histórico de periodos
-- (plan §4, §7). Idempotente: si la columna no existe, solamente
-- confirma que la tabla ya tiene la estructura objetivo.
--
-- Una base limpia NO ejecuta este archivo: Database/SqlScripts/000instalacioncompleta.sql
-- ya crea la tabla sin la columna.
--
-- Comprobar antes de ejecutar:
--   SELECT COUNT(*) FROM information_schema.columns
--   WHERE table_schema = 'dbtindervacas'
--     AND table_name = 'tbproductor'
--     AND column_name = 'tbproductorestado';
-- Si devuelve 0, esta migración ya se aplicó; repetirla no daña nada.
--
-- Ejecutar:
--   docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
--     < Database/Migrations/003eliminaestadoproductor.sql

-- Paso 1: backfill de periodos iniciales para productores que aún no
-- tienen ninguno (solo pueden ser productores heredados sin migrar).
-- El consecutivo MAX+1 se calcula una sola vez con variable, porque
-- el INSERT múltiple evita SELECT sobre la misma tabla destino.
SET @primerId = COALESCE(
    (SELECT MAX(tbproductorestadoperiodoid) FROM tbproductorestadoperiodo), 0
);

INSERT INTO tbproductorestadoperiodo
    (tbproductorestadoperiodoid, tbproductorid, tbproductorestadoperiodoestado,
     tbproductorestadoperiodofechainicio, tbproductorestadoperiodofechafin,
     tbproductorestadoperiodomotivo)
SELECT @primerId := @primerId + 1,
       p.tbproductorid,
       p.tbproductorestado,
       NOW(),
       NULL,
       'Migración 003: estado heredado'
FROM tbproductor p
WHERE NOT EXISTS (
    SELECT 1 FROM tbproductorestadoperiodo ep
    WHERE ep.tbproductorid = p.tbproductorid
);

-- Paso 2: comprobar que la columna existe antes de eliminar.
-- Suprimir una columna inexistente da error 1091.
SET @existe = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = 'dbtindervacas'
      AND table_name = 'tbproductor'
      AND column_name = 'tbproductorestado'
);
SET @sql = IF(@existe > 0,
    'ALTER TABLE tbproductor DROP COLUMN tbproductorestado',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Paso 3: estructura final.
DESCRIBE tbproductor;
