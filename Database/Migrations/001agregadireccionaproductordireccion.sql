USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Migración manual. Solo para bases creadas ANTES de este avance, donde
-- tbproductordireccion existe sin la columna de enlace. Una base limpia ya la
-- recibe desde Database/SqlScripts/003createproductoresdireccion.sql y no debe
-- ejecutar este archivo: repetirlo termina con el error 1060 de MySQL.
--
-- Comprobar antes de ejecutar. Si devuelve 1, la migración ya se aplicó:
--   SELECT COUNT(*) FROM information_schema.columns
--   WHERE table_schema = 'dbtindervacas'
--     AND table_name = 'tbproductordireccion'
--     AND column_name = 'tbdireccionid';
--
-- Ejecutar:
--   docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" \
--     < Database/Migrations/001agregadireccionaproductordireccion.sql

ALTER TABLE tbproductordireccion
    ADD COLUMN tbdireccionid INT NULL AFTER tbproductorid;

-- Las nuevas tablas del avance se crean con CREATE TABLE IF NOT EXISTS, así que
-- volver a ejecutar Database/SqlScripts/007 a 012 sobre una base existente las
-- agrega sin tocar lo ya creado.
