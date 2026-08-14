USE dbtindervacas;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Migración manual, de una sola ejecución. Traslada la residencia del productor
-- desde las columnas heredadas de tbproductordireccion hacia tbdireccion y deja
-- la tabla de enlace con tres columnas.
--
-- Una base limpia NO ejecuta este archivo: Database/SqlScripts/003 ya crea la
-- tabla normalizada. Repetir la migración termina con el error 1091 de MySQL
-- porque las columnas heredadas ya no existen.
--
-- Comprobar antes de ejecutar. Si devuelve 0, la migración ya se aplicó:
--   SELECT COUNT(*) FROM information_schema.columns
--   WHERE table_schema = 'dbtindervacas'
--     AND table_name = 'tbproductordireccion'
--     AND column_name = 'tbproductordireccionprovincia';
--
-- Respaldar antes:
--   Tools/backup-database.sh <Entrega> <Autor>
--
-- Ejecutar:
--   docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u"$MYSQL_USER" "$MYSQL_DATABASE"' \
--     < Database/Migrations/001normalizadireccionproductor.sql

-- Paso 0: la columna de enlace puede no existir todavía.
ALTER TABLE tbproductordireccion
    ADD COLUMN tbdireccionid INT NULL AFTER tbproductorid;

START TRANSACTION;

-- Paso 1: desplazamiento de identificadores. Cada residencia recibe un
-- tbdireccionid propio y estable, calculado como el máximo actual más su
-- tbproductordireccionid. La aplicación sigue siendo la dueña de los
-- consecutivos: aquí solo se convierten los que ya existen.
SET @desplazamiento := (SELECT COALESCE(MAX(tbdireccionid), 0) FROM tbdireccion);

-- Paso 2: crear en tbdireccion la ubicación de cada residencia todavía sin enlace.
INSERT INTO tbdireccion (
    tbdireccionid,
    tbdireccionprovincia,
    tbdireccioncanton,
    tbdirecciondistrito,
    tbdireccionpueblo,
    tbdireccionsenas
)
SELECT @desplazamiento + tbproductordireccionid,
       tbproductordireccionprovincia,
       tbproductordireccioncanton,
       tbproductordirecciondistrito,
       tbproductordireccionpueblo,
       tbproductordireccionsenas
FROM tbproductordireccion
WHERE tbdireccionid IS NULL;

-- Paso 3: enlazar cada residencia con la ubicación recién creada.
UPDATE tbproductordireccion
SET tbdireccionid = @desplazamiento + tbproductordireccionid
WHERE tbdireccionid IS NULL;

COMMIT;

-- Paso 4: comprobaciones obligatorias antes de eliminar nada.
-- Las tres consultas deben devolver 0. Si alguna no lo hace, DETENERSE:
-- restaurar el respaldo y revisar los datos.
SELECT COUNT(*) AS residencias_sin_enlace
FROM tbproductordireccion
WHERE tbdireccionid IS NULL;

SELECT COUNT(*) AS enlaces_sin_ubicacion
FROM tbproductordireccion pd
LEFT JOIN tbdireccion d ON d.tbdireccionid = pd.tbdireccionid
WHERE d.tbdireccionid IS NULL;

SELECT COUNT(*) AS ubicaciones_con_datos_distintos
FROM tbproductordireccion pd
INNER JOIN tbdireccion d ON d.tbdireccionid = pd.tbdireccionid
WHERE d.tbdireccionprovincia <> pd.tbproductordireccionprovincia
   OR d.tbdireccioncanton <> pd.tbproductordireccioncanton
   OR d.tbdirecciondistrito <> pd.tbproductordirecciondistrito
   OR NOT (d.tbdireccionpueblo <=> pd.tbproductordireccionpueblo)
   OR NOT (d.tbdireccionsenas <=> pd.tbproductordireccionsenas);

-- Paso 5: eliminar las columnas heredadas y dejar el enlace obligatorio.
-- Ejecutar solamente si las tres consultas anteriores devolvieron 0.
ALTER TABLE tbproductordireccion
    DROP COLUMN tbproductordireccionprovincia,
    DROP COLUMN tbproductordireccioncanton,
    DROP COLUMN tbproductordirecciondistrito,
    DROP COLUMN tbproductordireccionpueblo,
    DROP COLUMN tbproductordireccionsenas,
    MODIFY COLUMN tbdireccionid INT NOT NULL;

-- Paso 6: estructura final esperada. Tres columnas, sin llaves ni valores
-- automáticos.
DESCRIBE tbproductordireccion;
