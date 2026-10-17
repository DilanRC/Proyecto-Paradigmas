<!DOCTYPE html>
<html lang="es">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Subastas en vivo en TinderCows">
    <title>Subastas | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-9">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/adminsubastas.js?v=subastas-admin-2"></script>
    <script defer src="js/shared/panel-runtime.js?v=runtime-1"></script>
</head>
<body class="rural-panel">
    <?php require dirname(__DIR__) . '/shared/panel-runtime.php'; ?>
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
                <a class="rural-panel__nav-item" href="admin/fletes">Fletes</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="admin/subastas">Subastas<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="admin/documentos">Documentos</a>
                <a class="rural-panel__nav-item" href="admin/metodos-pago">Métodos de pago</a>
                <a class="rural-panel__nav-item" href="admin/catalogos">Catálogos</a>
                <a class="rural-panel__nav-item" href="admin/administradores">Administradores</a>
                <a class="rural-panel__nav-item" href="admin/bitacora">Bitácora</a>
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
                <div><span class="label">Comercio</span><h1 id="page-title">Subastas</h1><p>Las subastas en vivo y su historial. Se actualizan solas; los administradores pueden verlas, pero no pujan ni comentan.</p></div>
                <p class="label" id="estado-vivo" role="status" aria-live="polite">Conectando…</p>
            </section>

            <section class="panel" aria-label="Lista de subastas" aria-busy="true" id="panel-subastas">
                <div class="tools">
                    <label class="filter"><span>Ver</span><select id="filtro-vista"><option value="ACTIVAS">Activas</option><option value="HISTORIAL">Historial</option></select></label>
                    <label class="filter"><span>Buscar</span><input id="filtro-busqueda" type="search" maxlength="100" autocomplete="off" placeholder="Título, raza o zona"></label>
                </div>
                <div class="list-summary">
                    <p id="total-subastas" aria-live="polite">Cargando subastas…</p>
                    <div class="pagination"><button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button></div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Subasta</th><th>Vendedor</th><th>Animal</th><th>Precio</th><th>Pujas</th><th>Cierre</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-subastas"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No hay subastas</h2><p>Cuando un vendedor abra una, aparecerá aquí al instante.</p></div>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar las subastas</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Comercio · Solo lectura: la subasta la abre el vendedor y se cierra sola a la hora indicada · Cada puja queda en la bitácora</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-detalle" aria-labelledby="titulo-detalle">
        <div class="modal__header"><div><span class="label" id="subtitulo-detalle">Subasta</span><h2 id="titulo-detalle">Detalle</h2></div><button class="close-button" id="cerrar-detalle" type="button" aria-label="Cerrar detalle">×</button></div>
        <div class="modal__content">
            <dl class="detail-grid" id="detalle-datos"></dl>
            <h3>Pujas</h3>
            <dl class="detail-grid" id="detalle-pujas"></dl>
            <h3>Comentarios</h3>
            <dl class="detail-grid" id="detalle-comentarios"></dl>
        </div>
    </dialog>
    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
