USE bdmercadoganadero;

-- Calidad pide una llave primaria por tabla (sin llaves foráneas ni otras
-- restricciones). Cada tabla ya tiene su identificador `<tabla>id NOT NULL`,
-- que PHP genera bajo NamedLock; aquí solo se declara como PRIMARY KEY.
-- No cambia datos ni agrega AUTO_INCREMENT.
--
-- Haga un respaldo antes. Si alguna tabla tiene identificadores repetidos la
-- migración muestra el error "MIGRACION_ABORTADA_IDENTIFICADORES_REPETIDOS" y
-- no declara ninguna llave, tampoco en phpMyAdmin (que no se detiene en el
-- error): corra la consulta de abajo para ver en qué tabla están, corríjalos a
-- mano y vuelva a ejecutarla. Se puede repetir: las tablas que ya tienen llave
-- se saltan. Los ALTER no se pueden deshacer con ROLLBACK (cada uno confirma solo).

-- tbadministrador entró al esquema canónico el 21/09 sin migración propia: una base
-- anterior (o un respaldo antiguo restaurado) no la tiene y la comprobación de abajo
-- fallaba al no encontrarla. Se crea vacía; el administrador inicial lo agrega
-- Database/SeedData/102administrador.sql.
CREATE TABLE IF NOT EXISTS tbadministrador (
    tbadministradorid INT NOT NULL,
    tbadministradorcorreoelectronico VARCHAR(150) NOT NULL,
    tbadministradorestado TINYINT(1) NOT NULL,
    PRIMARY KEY (tbadministradorid)
) ENGINE=InnoDB;

-- Sin valor previo: si el cálculo falla, una sesión que ya tenía @repetidos = 0 no debe alterar nada.
SET @repetidos = NULL;
SET @repetidos = (SELECT COUNT(*) FROM (
    SELECT 'tbpersona' AS tabla FROM tbpersona GROUP BY tbpersonaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbadministrador' AS tabla FROM tbadministrador GROUP BY tbadministradorid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductor' AS tabla FROM tbproductor GROUP BY tbproductorid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductordireccion' AS tabla FROM tbproductordireccion GROUP BY tbproductordireccionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbfinca' AS tabla FROM tbfinca GROUP BY tbfincaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbbitacora' AS tabla FROM tbbitacora GROUP BY tbbitacoraid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcomprador' AS tabla FROM tbcomprador GROUP BY tbcompradorid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbdireccion' AS tabla FROM tbdireccion GROUP BY tbdireccionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbfincadireccion' AS tabla FROM tbfincadireccion GROUP BY tbfincadireccionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbpagometodo' AS tabla FROM tbpagometodo GROUP BY tbpagometodoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportista' AS tabla FROM tbtransportista GROUP BY tbtransportistaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbvehiculo' AS tabla FROM tbvehiculo GROUP BY tbvehiculoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportistavehiculo' AS tabla FROM tbtransportistavehiculo GROUP BY tbtransportistavehiculoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductorpersonatelefonohistorico' AS tabla FROM tbproductorpersonatelefonohistorico GROUP BY tbproductorpersonatelefonohistoricoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcompradorpersonatelefonohistorico' AS tabla FROM tbcompradorpersonatelefonohistorico GROUP BY tbcompradorpersonatelefonohistoricoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductorestadoperiodo' AS tabla FROM tbproductorestadoperiodo GROUP BY tbproductorestadoperiodoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductorubicacion' AS tabla FROM tbproductorubicacion GROUP BY tbproductorubicacionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductoractividad' AS tabla FROM tbproductoractividad GROUP BY tbproductoractividadid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbproductorclasificacionperiodo' AS tabla FROM tbproductorclasificacionperiodo GROUP BY tbproductorclasificacionperiodoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimal' AS tabla FROM tbanimal GROUP BY tbanimalid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalproduccionsalud' AS tabla FROM tbanimalproduccionsalud GROUP BY tbanimalproduccionsaludid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalpublicacion' AS tabla FROM tbanimalpublicacion GROUP BY tbanimalpublicacionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalpublicacionestadoperiodo' AS tabla FROM tbanimalpublicacionestadoperiodo GROUP BY tbanimalpublicacionestadoperiodoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcompra' AS tabla FROM tbcompra GROUP BY tbcompraid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbventa' AS tabla FROM tbventa GROUP BY tbventaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalinteraccion' AS tabla FROM tbanimalinteraccion GROUP BY tbanimalinteraccionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalpublicacioninteraccion' AS tabla FROM tbanimalpublicacioninteraccion GROUP BY tbanimalpublicacioninteraccionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcarrito' AS tabla FROM tbcarrito GROUP BY tbcarritoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcarritoanimal' AS tabla FROM tbcarritoanimal GROUP BY tbcarritoanimalid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcarritoestadoperiodo' AS tabla FROM tbcarritoestadoperiodo GROUP BY tbcarritoestadoperiodoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportistaestadoperiodo' AS tabla FROM tbtransportistaestadoperiodo GROUP BY tbtransportistaestadoperiodoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportistahorario' AS tabla FROM tbtransportistahorario GROUP BY tbtransportistahorarioid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportistaflete' AS tabla FROM tbtransportistaflete GROUP BY tbtransportistafleteid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportistaresena' AS tabla FROM tbtransportistaresena GROUP BY tbtransportistaresenaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbregistroconsulta' AS tabla FROM tbregistroconsulta GROUP BY tbregistroconsultaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbtransportistaoferta' AS tabla FROM tbtransportistaoferta GROUP BY tbtransportistaofertaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbcomprasolicitud' AS tabla FROM tbcomprasolicitud GROUP BY tbcomprasolicitudid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbespecie' AS tabla FROM tbespecie GROUP BY tbespecieid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimaltipo' AS tabla FROM tbanimaltipo GROUP BY tbanimaltipoid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbraza' AS tabla FROM tbraza GROUP BY tbrazaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalpublicacionanimal' AS tabla FROM tbanimalpublicacionanimal GROUP BY tbanimalpublicacionanimalid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbvacuna' AS tabla FROM tbvacuna GROUP BY tbvacunaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbanimalvacunacion' AS tabla FROM tbanimalvacunacion GROUP BY tbanimalvacunacionid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbsubasta' AS tabla FROM tbsubasta GROUP BY tbsubastaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbsubastapuja' AS tabla FROM tbsubastapuja GROUP BY tbsubastapujaid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbsubastacomentario' AS tabla FROM tbsubastacomentario GROUP BY tbsubastacomentarioid HAVING COUNT(*) > 1 UNION ALL
    SELECT 'tbnotificacion' AS tabla FROM tbnotificacion GROUP BY tbnotificacionid HAVING COUNT(*) > 1
) duplicados);

SET @sql = IF(@repetidos = 0, 'DO 0',
    'SELECT * FROM MIGRACION_ABORTADA_IDENTIFICADORES_REPETIDOS');
PREPARE comprobacion FROM @sql; EXECUTE comprobacion; DEALLOCATE PREPARE comprobacion;

-- Cada ALTER vuelve a exigir @repetidos = 0: la importación de phpMyAdmin sigue ejecutando
-- sentencias después del error de arriba (el cliente mysql no) y dejaba la base a medio migrar.

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbpersona' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbpersona ADD PRIMARY KEY (tbpersonaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbadministrador' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbadministrador ADD PRIMARY KEY (tbadministradorid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductor' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductor ADD PRIMARY KEY (tbproductorid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductordireccion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductordireccion ADD PRIMARY KEY (tbproductordireccionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbfinca' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbfinca ADD PRIMARY KEY (tbfincaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbbitacora' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbbitacora ADD PRIMARY KEY (tbbitacoraid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcomprador' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcomprador ADD PRIMARY KEY (tbcompradorid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbdireccion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbdireccion ADD PRIMARY KEY (tbdireccionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbfincadireccion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbfincadireccion ADD PRIMARY KEY (tbfincadireccionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbpagometodo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbpagometodo ADD PRIMARY KEY (tbpagometodoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportista' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportista ADD PRIMARY KEY (tbtransportistaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbvehiculo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbvehiculo ADD PRIMARY KEY (tbvehiculoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistavehiculo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportistavehiculo ADD PRIMARY KEY (tbtransportistavehiculoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductorpersonatelefonohistorico' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductorpersonatelefonohistorico ADD PRIMARY KEY (tbproductorpersonatelefonohistoricoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcompradorpersonatelefonohistorico' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcompradorpersonatelefonohistorico ADD PRIMARY KEY (tbcompradorpersonatelefonohistoricoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductorestadoperiodo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductorestadoperiodo ADD PRIMARY KEY (tbproductorestadoperiodoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductorubicacion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductorubicacion ADD PRIMARY KEY (tbproductorubicacionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductoractividad' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductoractividad ADD PRIMARY KEY (tbproductoractividadid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbproductorclasificacionperiodo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbproductorclasificacionperiodo ADD PRIMARY KEY (tbproductorclasificacionperiodoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimal' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimal ADD PRIMARY KEY (tbanimalid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalproduccionsalud' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalproduccionsalud ADD PRIMARY KEY (tbanimalproduccionsaludid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalpublicacion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalpublicacion ADD PRIMARY KEY (tbanimalpublicacionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalpublicacionestadoperiodo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalpublicacionestadoperiodo ADD PRIMARY KEY (tbanimalpublicacionestadoperiodoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcompra' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcompra ADD PRIMARY KEY (tbcompraid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbventa' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbventa ADD PRIMARY KEY (tbventaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalinteraccion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalinteraccion ADD PRIMARY KEY (tbanimalinteraccionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalpublicacioninteraccion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalpublicacioninteraccion ADD PRIMARY KEY (tbanimalpublicacioninteraccionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcarrito' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcarrito ADD PRIMARY KEY (tbcarritoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcarritoanimal' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcarritoanimal ADD PRIMARY KEY (tbcarritoanimalid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcarritoestadoperiodo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcarritoestadoperiodo ADD PRIMARY KEY (tbcarritoestadoperiodoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaestadoperiodo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportistaestadoperiodo ADD PRIMARY KEY (tbtransportistaestadoperiodoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistahorario' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportistahorario ADD PRIMARY KEY (tbtransportistahorarioid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaflete' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportistaflete ADD PRIMARY KEY (tbtransportistafleteid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaresena' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportistaresena ADD PRIMARY KEY (tbtransportistaresenaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbregistroconsulta' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbregistroconsulta ADD PRIMARY KEY (tbregistroconsultaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbtransportistaoferta' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbtransportistaoferta ADD PRIMARY KEY (tbtransportistaofertaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbcomprasolicitud' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbcomprasolicitud ADD PRIMARY KEY (tbcomprasolicitudid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbespecie' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbespecie ADD PRIMARY KEY (tbespecieid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimaltipo' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimaltipo ADD PRIMARY KEY (tbanimaltipoid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbraza' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbraza ADD PRIMARY KEY (tbrazaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalpublicacionanimal' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalpublicacionanimal ADD PRIMARY KEY (tbanimalpublicacionanimalid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbvacuna' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbvacuna ADD PRIMARY KEY (tbvacunaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbanimalvacunacion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbanimalvacunacion ADD PRIMARY KEY (tbanimalvacunacionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbsubasta' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbsubasta ADD PRIMARY KEY (tbsubastaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbsubastapuja' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbsubastapuja ADD PRIMARY KEY (tbsubastapujaid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbsubastacomentario' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbsubastacomentario ADD PRIMARY KEY (tbsubastacomentarioid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;

SET @sql = IF(@repetidos = 0 AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbnotificacion' AND CONSTRAINT_TYPE = 'PRIMARY KEY') = 0,
    'ALTER TABLE tbnotificacion ADD PRIMARY KEY (tbnotificacionid)', 'DO 0');
PREPARE paso FROM @sql; EXECUTE paso; DEALLOCATE PREPARE paso;
