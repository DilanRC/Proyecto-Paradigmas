USE bdmercadoganadero;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Comprobación de los datos iniciales del avance.
-- Ejecutar:
--   docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" \
--     < Database/Tests/comprobaciondatosiniciales.sql

-- Esperado: una fila con 1 | Efectivo | Pago realizado en efectivo | 1
SELECT * FROM tbpagometodo;

-- Esperado: 1 fila y activo = 1.
SELECT COUNT(*) AS metodos_efectivo
FROM tbpagometodo
WHERE tbpagometodonombre = 'Efectivo' AND tbpagometodoactivo = 1;

-- Esperado: 0 filas. El alcance vigente no contempla otros métodos.
SELECT tbpagometodoid, tbpagometodonombre
FROM tbpagometodo
WHERE tbpagometodonombre <> 'Efectivo';
