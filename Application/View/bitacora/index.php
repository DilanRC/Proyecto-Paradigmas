<!DOCTYPE html>
<html lang="es">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bitácora de cambios de TinderCows">
    <title>Bitácora | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/bitacora.js?v=bitacora-1"></script>
</head>
<body class="rural-panel">
    <aside class="rural-panel__sidebar">
        <div class="rural-panel__sidebar-brand">
            <span class="brand__logo brand__logo--light" aria-hidden="true"><img src="assets/logo_light.png" alt="" width="44" height="44"></span>
            <span class="rural-panel__sidebar-brand-name">Tinder<strong>Cows</strong></span>
        </div>
        <nav class="rural-panel__nav" aria-label="Administración">
            <p class="rural-panel__nav-label">Administración</p>
            <div class="rural-panel__nav-list">
                <a class="rural-panel__nav-item" href="admin/dashboard">Dashboard</a>
                <a class="rural-panel__nav-item" href="admin/productores">Productores</a>
                <a class="rural-panel__nav-item" href="admin/compradores">Compradores</a>
                <a class="rural-panel__nav-item" href="admin/transportistas">Transportistas</a>
                <a class="rural-panel__nav-item" href="admin/vehiculos">Vehículos</a>
                <a class="rural-panel__nav-item" href="admin/publicaciones">Publicaciones</a>
                <a class="rural-panel__nav-item" href="admin/metodos-pago">Métodos de pago</a>
                <a class="rural-panel__nav-item" href="admin/administradores">Administradores</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="admin/bitacora">Bitácora<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Landing</a><a class="rural-panel__admin-link" href="entrar">Login</a></div>

            <section class="page-header" aria-labelledby="page-title">
                <div><span class="label">Seguridad</span><h1 id="page-title">Bitácora</h1><p>Consulte quién cambió qué y cuándo. Es solo lectura.</p></div>
            </section>

            <section class="panel" aria-label="Eventos de la bitácora" aria-busy="true" id="panel-bitacora">
                <div class="tools">
                    <label class="search"><span class="screen-reader-only">Buscar por persona o registro</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="busqueda-bitacora" type="search" maxlength="150" autocomplete="off" placeholder="Nombre, identificación o correo de quien hizo el cambio, o el registro"></label>
                    <label class="filter"><span>Entidad</span><select id="filtro-entidad"><option value="">Todas</option></select></label>
                    <label class="filter"><span>Desde</span><input id="filtro-desde" type="date"></label>
                    <label class="filter"><span>Hasta</span><input id="filtro-hasta" type="date"></label>
                </div>
                <p class="form-note" id="error-filtros" role="alert"></p>
                <div class="list-summary">
                    <p id="total-bitacora" aria-live="polite">Cargando bitácora…</p>
                    <div class="pagination" aria-label="Paginación de la bitácora">
                        <button class="link-button" id="pagina-anterior" type="button">Anterior</button>
                        <span id="pagina-actual" aria-live="polite">Página 1</span>
                        <button class="link-button" id="pagina-siguiente" type="button">Siguiente</button>
                        <button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button>
                    </div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Fecha</th><th>Entidad</th><th>Acción</th><th>Registro</th><th>Hecho por</th><th><span class="screen-reader-only">Detalle</span></th></tr></thead><tbody id="cuerpo-bitacora"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No hay eventos</h2><p>Modifique la búsqueda, la entidad o las fechas.</p></div>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar la bitácora</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Seguridad · Las fechas se muestran en su hora local · Los teléfonos nunca se guardan en la bitácora</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-detalle" aria-labelledby="titulo-detalle">
        <div class="modal__header"><div><span class="label">Evento de la bitácora</span><h2 id="titulo-detalle">Detalle</h2></div><button class="close-button" id="cerrar-detalle" type="button" aria-label="Cerrar detalle">×</button></div>
        <div class="modal__content"><dl class="detail-grid" id="detalle-contenido"></dl></div>
        <div class="modal__actions"><button class="button button--secondary" id="cerrar-detalle-secundario" type="button">Cerrar</button></div>
    </dialog>
    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
