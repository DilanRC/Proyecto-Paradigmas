<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Productores clasificados como compradores en TinderCows">
    <title>Compradores | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/compradores.js"></script>
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
                <a class="rural-panel__nav-item" href="productores.php">Productores</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="compradores.php">Compradores<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="transportistas.php">Transportistas</a>
                <a class="rural-panel__nav-item" href="vehiculos.php">Vehículos</a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Landing</a><a class="rural-panel__admin-link" href="login.php">Login</a></div>

            <section class="page-header" aria-labelledby="page-title">
                <div><span class="label">Clasificación derivada</span><h1 id="page-title">Compradores</h1><p>Productores con una clasificación <strong>COMPRADOR</strong> abierta. Comprador no se da de alta desde este panel: la clasificación pertenece al histórico del Productor y esta vista únicamente la consulta.</p></div>
            </section>

            <section class="panel" aria-label="Productores clasificados como compradores" aria-busy="true" id="panel-compradores">
                <div class="tools">
                    <label class="search"><span class="screen-reader-only">Buscar productor clasificado</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="busqueda-comprador" type="search" autocomplete="off" placeholder="Buscar por nombre o identificación"></label>
                </div>
                <div class="list-summary">
                    <p id="total-compradores" aria-live="polite">Cargando clasificaciones…</p>
                    <div class="pagination" aria-label="Paginación de compradores">
                        <button class="link-button" id="pagina-anterior" type="button">Anterior</button>
                        <span id="pagina-actual" aria-live="polite">Página 1</span>
                        <button class="link-button" id="pagina-siguiente" type="button">Siguiente</button>
                        <button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button>
                    </div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Productor</th><th>Identificación</th><th>Contacto</th><th>Clasificado desde</th><th>Origen</th><th>Disponibilidad</th></tr></thead><tbody id="cuerpo-compradores"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>Ningún productor está clasificado como comprador</h2><p>Mientras el mecanismo que deriva la clasificación del comportamiento (T10) no exista, esta lista solo puede mostrar clasificaciones ya registradas o migradas; no permite crearlas a mano.</p></div>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar los compradores</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Vista de solo lectura · Fuente: <code>tbproductorclasificacionperiodo</code> · TinderCows</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-detalle" aria-labelledby="titulo-detalle">
        <div class="modal__header"><div><span class="label">Clasificación del productor</span><h2 id="titulo-detalle">Detalle</h2></div><button class="close-button" id="cerrar-detalle" type="button" aria-label="Cerrar detalle">×</button></div>
        <div class="modal__content">
            <dl class="detail-grid" id="detalle-contenido"></dl>
            <section class="capacidades" aria-labelledby="titulo-capacidades">
                <h3 class="capacidades__titulo" id="titulo-capacidades">Relaciones de esta persona</h3>
                <p class="capacidades__nota">La misma identidad puede ser Productor, tener una clasificación Comprador y/o estar registrada como Transportista. Comprador no se administra desde aquí.</p>
                <ul class="capacidades__lista" id="lista-capacidades" aria-live="polite" aria-busy="false"></ul>
            </section>
        </div>
        <div class="modal__actions"><button class="button button--secondary" id="cerrar-detalle-secundario" type="button">Cerrar</button></div>
    </dialog>

    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
