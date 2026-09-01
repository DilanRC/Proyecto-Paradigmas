USE dbmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Migración manual, de una sola ejecución. Cierra el vacío de tramo 6 del
-- plan de remodelado EIF400 (Base de datos y QA, §Tramo 6): por cada
-- productor con dirección, el enlace vigente debe tener un primer periodo
-- con fecha de inicio. La migración 002historicoproductor.sql agregó
-- tbproductordireccionfechainicio como NULL para no romper el ALTER sobre
-- filas ya existentes; esta migración es el backfill que faltaba.
--
-- No se inventa historia anterior a la migración: todas las filas afectadas
-- reciben la MISMA marca de tiempo, capturada una sola vez, que representa
-- el momento en que el histórico confiable empieza para esos enlaces
-- heredados (Documentation/Decisiones.md, frase de defensa del tramo 6).
--
-- Idempotente: solo toca filas con tbproductordireccionfechainicio IS NULL.
-- Repetirla no hace nada la segunda vez.
--
-- Comprobar antes de ejecutar. Si devuelve 0, no hay nada que migrar:
--   SELECT COUNT(*) FROM tbproductordireccion
--   WHERE tbproductordireccionfechainicio IS NULL;
--
-- Respaldar antes:
--   Tools/backup-database.sh <Entrega> <Autor>
--
-- Ejecutar:
--   docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
--     < Database/Migrations/005backfilldireccionfechainicio.sql

SET @fechaMigracion := NOW();

UPDATE tbproductordireccion
SET tbproductordireccionfechainicio = @fechaMigracion
WHERE tbproductordireccionfechainicio IS NULL;

-- Estructura y datos finales esperados: ninguna fila sin fecha de inicio.
SELECT COUNT(*) AS filas_sin_fecha_inicio
FROM tbproductordireccion
WHERE tbproductordireccionfechainicio IS NULL;

SELECT tbproductordireccionid, tbproductorid, tbdireccionid,
       tbproductordireccionfechainicio, tbproductordireccionfechafin
FROM tbproductordireccion
ORDER BY tbproductordireccionid;
