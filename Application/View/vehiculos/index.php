<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Administración de vehículos de TinderCows">
    <title>Vehículos | TinderCows</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/vehiculos.js" defer></script>
</head>
<body class="rural-panel">
    <aside class="rural-panel__sidebar">
        <div class="rural-panel__sidebar-brand">
            <span class="brand__icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M13 12 6 7c-1 7 2 10 7 11m22-6 7-5c1 7-2 10-7 11"/><path d="M11 24c0-10 5-16 13-16s13 6 13 16v7c0 7-5 11-13 11S11 38 11 31Z"/><path d="M16 29c0-4 3-6 8-6s8 2 8 6-3 7-8 7-8-3-8-7Z"/><circle cx="18" cy="20" r="2"/><circle cx="30" cy="20" r="2"/><circle cx="21" cy="29" r="1.5"/><circle cx="27" cy="29" r="1.5"/></svg></span>
            <span class="rural-panel__sidebar-brand-name">Tinder<strong>Cows</strong></span>
        </div>
        <nav class="rural-panel__nav" aria-label="Administración">
            <p class="rural-panel__nav-label">Administración</p>
            <div class="rural-panel__nav-list">
                <a class="rural-panel__nav-item" href="productores.php">Productores</a>
                <a class="rural-panel__nav-item" href="transportistas.php">Transportistas</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="vehiculos.php">Vehículos<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="pagometodos.php">Métodos de pago</a>
            </div>
        </nav>
        <div class="rural-panel__sidebar-footer">
            <p>Gestión de la red ganadera.</p>
            <p>TinderCows · 2026</p>
        </div>
    </aside>

    <main class="rural-panel__main">
        <div class="rural-panel__glow" aria-hidden="true"></div>
        <div class="rural-panel__content">
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Regresar</a></div>

            <section class="page-header" aria-labelledby="page-title">
                <div><span class="label">Registro de vehículos</span><h1 id="page-title">Vehículos</h1><p>Administre los vehículos disponibles para asignar a transportistas.</p></div>
                <button class="button button--primary" id="crear-vehiculo" type="button"><span aria-hidden="true">＋</span>Crear vehículo</button>
            </section>

            <section class="panel" aria-label="Lista de vehículos" aria-busy="true" id="panel-vehiculos">
                <div class="tools">
                    <label class="search"><span class="screen-reader-only">Buscar vehículo</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="busqueda-vehiculo" type="search" autocomplete="off" placeholder="Buscar por placa, VIN o modelo"></label>
                    <label class="filter"><span>Estado</span><select id="filtro-estado"><option value="TODOS">Todos</option><option value="ACTIVO">Activos</option><option value="INACTIVO">Inactivos</option></select></label>
                </div>
                <div class="list-summary">
                    <p id="total-vehiculos" aria-live="polite">Cargando vehículos…</p>
                    <div class="pagination" aria-label="Paginación de vehículos">
                        <button class="link-button" id="pagina-anterior" type="button">Anterior</button>
                        <span id="pagina-actual" aria-live="polite">Página 1</span>
                        <button class="link-button" id="pagina-siguiente" type="button">Siguiente</button>
                        <button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button>
                    </div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Placa</th><th>VIN</th><th>Modelo</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-vehiculos"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No se encontraron vehículos</h2><p>Modifique la búsqueda o cree el primero.</p></div>
                    <div class="loading-state" id="estado-carga" aria-live="polite"><span class="loader" aria-hidden="true"></span>Cargando información…</div>
                </div>
            </section>

            <p class="rural-panel__footnote">Directorio de vehículos · Datos protegidos por TinderCows</p>
        </div>
    </main>

    <dialog class="modal" id="modal-vehiculo" aria-labelledby="titulo-modal">
        <form id="formulario-vehiculo" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label" id="subtitulo-modal">Nuevo registro</span><h2 id="titulo-modal">Crear vehículo</h2></div><button class="close-button" id="cerrar-modal" type="button" aria-label="Cerrar formulario">×</button></div>
            <div class="modal__content">
                <input type="hidden" id="vehiculo-id" name="vehiculoId">
                <fieldset><legend>Datos del vehículo</legend><div class="form-grid">
                    <label class="field"><span>Placa <b aria-hidden="true">*</b></span><input id="placa" name="placa" type="text" maxlength="20" autocomplete="off" required aria-describedby="error-placa"><small class="field__error" id="error-placa" data-error-for="placa"></small></label>
                    <label class="field"><span>VIN <b aria-hidden="true">*</b></span><input id="vin" name="vin" type="text" maxlength="50" autocomplete="off" required aria-describedby="error-vin"><small class="field__error" id="error-vin" data-error-for="vin"></small></label>
                    <label class="field field--full"><span>Modelo <b aria-hidden="true">*</b></span><input id="modelo" name="modelo" type="text" maxlength="100" autocomplete="off" required aria-describedby="error-modelo"><small class="field__error" id="error-modelo" data-error-for="modelo"></small></label>
                </div></fieldset>
                <p class="form-note"><b aria-hidden="true">*</b> Campos obligatorios</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-formulario" type="button">Cancelar</button><button class="button button--primary" id="guardar-vehiculo" type="submit">Guardar vehículo</button></div>
        </form>
    </dialog>

    <dialog class="modal modal--confirmation" id="modal-desactivar" aria-labelledby="titulo-desactivar"><div class="confirmation__icon" aria-hidden="true">!</div><h2 id="titulo-desactivar">Desactivar vehículo</h2><p id="mensaje-desactivar">El vehículo dejará de estar disponible para asignaciones.</p><div class="modal__actions"><button class="button button--secondary" id="cancelar-desactivacion" type="button">Cancelar</button><button class="button button--danger" id="confirmar-desactivacion" type="button">Desactivar</button></div></dialog>

    <dialog class="modal" id="modal-detalle" aria-labelledby="titulo-detalle">
        <div class="modal__header"><div><span class="label">Ficha del vehículo</span><h2 id="titulo-detalle">Detalle</h2></div><button class="close-button" id="cerrar-detalle" type="button" aria-label="Cerrar detalle">×</button></div>
        <div class="modal__content"><dl class="detail-grid" id="detalle-contenido"></dl></div>
        <div class="modal__actions"><button class="button button--secondary" id="cerrar-detalle-secundario" type="button">Cerrar</button><button class="button button--primary" id="editar-desde-detalle" type="button">Editar</button></div>
    </dialog>
    <div class="notification" id="notificacion" role="status" aria-live="polite" hidden></div>
</body>
</html>
