<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Administra tu identidad y tus actividades en Ganado Cerca.">
    <meta name="theme-color" content="#151a18">
    <title>Mi actividad | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/public-product.css?v=product-2">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/onboarding.css?v=front-2">
    <link rel="stylesheet" href="css/mi-actividad.css?v=activity-1">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-3"></script>
    <script type="module" src="js/mi-actividad.js?v=activity-1"></script>
</head>
<body class="public-home activity-page">
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48"></span><span>Ganado<strong>Cerca</strong></span></a>
            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            </nav>
            <div class="public-header__actions">
                <form class="public-search" action="explorar" method="get" role="search" data-public-search><button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button><div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-actividad">Buscar publicaciones</label><input id="busqueda-publica-actividad" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div></form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="activity-main">
            <section class="activity-header" aria-labelledby="activity-title"><p class="section-kicker">Tu espacio</p><h1 id="activity-title">Mi actividad</h1><p>Administra tu identidad y las formas en que participas en Ganado Cerca. Desactivar una actividad no borra tu persona ni las demás actividades.</p></section>
            <div id="welcome-banner" class="welcome-banner" hidden role="status" aria-live="polite">Registro completado. Ya puedes decidir qué hacer primero.</div>
            <section id="activity-loading" class="activity-panel" role="status" aria-live="polite"><div class="purchase-loader"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Cargando tu identidad y actividades…</span></div></section>
            <section id="activity-error" class="activity-panel" hidden role="alert"><div class="purchase-result"><h2>No pudimos cargar Mi actividad</h2><p id="activity-error-message">Intenta nuevamente.</p><button id="activity-retry" class="activity-button activity-button--primary" type="button">Reintentar</button></div></section>

            <div id="activity-content" hidden>
                <section class="activity-summary" aria-labelledby="summary-title"><div class="section-heading"><p class="section-kicker">Resumen</p><h2 id="summary-title">Tu cuenta de un vistazo</h2></div><div class="activity-summary__grid"><article class="summary-card"><span>Actividades activas</span><strong id="summary-active">0</strong></article><article class="summary-card"><span>Por configurar</span><strong id="summary-pending">0</strong></article><article class="summary-card"><span>Fincas</span><strong id="summary-farms">0</strong></article><article class="summary-card"><span>Vehículos</span><strong id="summary-vehicles">0</strong></article></div></section>
                <div class="activity-grid">
                    <section class="activity-panel" id="actividades" aria-labelledby="capabilities-title"><div class="step-heading"><span class="step-number"><i class="fa-solid fa-route" aria-hidden="true"></i></span><div><h2 id="capabilities-title">Cómo participas</h2><p>Estas son actividades de negocio independientes.</p></div></div><div id="activity-list" class="activity-list" aria-live="polite"></div></section>
                    <aside class="activity-panel" aria-labelledby="profile-title"><div class="step-heading"><span class="step-number"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span><div><h2 id="profile-title">Tu identidad</h2><p>Se comparte entre todas tus actividades.</p></div></div><dl id="profile-list" class="profile-list"></dl><div class="business-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>No creamos una persona distinta cuando compras, vendes o transportas.</p></div></aside>
                </div>
                <div class="activity-resource-grid">
                    <section class="activity-panel" aria-labelledby="farms-title"><div class="section-heading section-heading--row"><div><p class="section-kicker">Productor</p><h2 id="farms-title">Mis fincas</h2></div><button id="farm-add" class="activity-button activity-button--primary" type="button" hidden>Agregar finca</button></div><div id="farms-loading" class="resource-state" role="status" aria-live="polite">Cargando fincas…</div><div id="farms-error" class="resource-state resource-state--error" role="alert" hidden><p id="farms-error-message">No pudimos cargar tus fincas.</p><button id="farms-retry" class="activity-button" type="button">Reintentar</button></div><div id="farms-content" hidden><div id="farms-list" class="resource-list" aria-live="polite"></div><p id="farms-empty" class="resource-empty" hidden>Aún no tienes fincas registradas.</p></div></section>
                    <section class="activity-panel" aria-labelledby="vehicles-title"><div class="section-heading section-heading--row"><div><p class="section-kicker">Transportista</p><h2 id="vehicles-title">Mis vehículos</h2></div><button id="vehicle-add" class="activity-button activity-button--primary" type="button" hidden>Agregar vehículo</button></div><div id="vehicles-loading" class="resource-state" role="status" aria-live="polite">Cargando vehículos…</div><div id="vehicles-error" class="resource-state resource-state--error" role="alert" hidden><p id="vehicles-error-message">No pudimos cargar tus vehículos.</p><button id="vehicles-retry" class="activity-button" type="button">Reintentar</button></div><div id="vehicles-content" hidden><div id="vehicles-list" class="resource-list" aria-live="polite"></div><p id="vehicles-empty" class="resource-empty" hidden>Aún no tienes vehículos registrados.</p></div></section>
                </div>
            </div>
            <p id="activity-status" class="auth-status" role="status" aria-live="polite"></p>
        </main>
        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand"><a class="public-brand public-brand--footer" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40"></span><span>Ganado<strong>Cerca</strong></span></a><p>Descubre ganado y oportunidades cerca de ti.</p></div>
            <div class="public-footer__links"><div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar">Explorar</a><a href="./#nosotros">Nosotros</a><a href="./#como-funciona">Cómo funciona</a></div><div><strong>Cuenta</strong><a href="mi-actividad">Mi actividad</a><a href="como-usar">Ayuda de uso</a><a href="sobre-nosotros">Sobre Ganado Cerca</a></div><div class="public-footer__legal"><strong>Legal</strong><a href="privacidad">Privacidad</a><a href="terminos">Términos</a><a href="legal">Información legal</a></div></div>
        </footer>
    </div>

    <div class="toast-region" aria-label="Notificaciones"><div class="toast" id="toast-status" role="status" aria-live="polite"></div><div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div></div>

    <dialog id="vehicle-modal" class="activity-dialog" aria-labelledby="vehicle-modal-title"><form id="vehicle-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Mi vehículo</p><h2 id="vehicle-modal-title">Agregar vehículo</h2></div><button id="vehicle-close" class="dialog-close" type="button" aria-label="Cerrar">×</button></div><p id="vehicle-form-help" class="fieldset-help">Completa los datos que usaremos para administrar tu oferta de transporte.</p><div class="vehicle-form-grid"><label class="field"><span>Placa</span><input id="vehicle-placa" name="placa" maxlength="20" autocomplete="off" required aria-describedby="vehicle-placa-error"><small id="vehicle-placa-error" class="field-error" data-vehicle-error="placa"></small></label><label class="field"><span>VIN</span><input id="vehicle-vin" name="vin" maxlength="50" autocomplete="off" required aria-describedby="vehicle-vin-error"><small id="vehicle-vin-error" class="field-error" data-vehicle-error="vin"></small></label><label class="field field--full"><span>Modelo</span><input id="vehicle-modelo" name="modelo" maxlength="100" autocomplete="off" required aria-describedby="vehicle-modelo-error"><small id="vehicle-modelo-error" class="field-error" data-vehicle-error="modelo"></small></label></div><p id="vehicle-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button id="vehicle-cancel" class="activity-button" type="button">Cancelar</button><button id="vehicle-save" class="activity-button activity-button--primary" type="submit">Guardar vehículo</button></div></form></dialog>
    <dialog id="farm-modal" class="activity-dialog activity-dialog--farm" aria-labelledby="farm-modal-title"><form id="farm-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Mi finca</p><h2 id="farm-modal-title">Agregar finca</h2></div><button id="farm-close" class="dialog-close" type="button" aria-label="Cerrar">×</button></div><p id="farm-form-help" class="fieldset-help">Agrega solo los datos de esta finca. La dirección y el punto exacto son opcionales.</p><div class="farm-form-grid"><label class="field field--full"><span>Nombre de finca</span><input id="farm-name" name="nombreFinca" maxlength="150" autocomplete="off" required aria-describedby="farm-name-error"><small id="farm-name-error" class="field-error" data-farm-error="nombreFinca"></small></label><details id="farm-address" class="farm-address-editor field--full"><summary>Dirección y punto exacto <span class="label">opcional</span></summary><div class="farm-address-editor__grid"><label class="field"><span>Provincia</span><select name="provincia" data-farm-province></select><small class="field-error" data-farm-error="provincia"></small></label><label class="field"><span>Cantón</span><select name="canton" data-farm-canton></select><small class="field-error" data-farm-error="canton"></small></label><label class="field"><span>Distrito</span><select name="distrito" data-farm-district disabled><option value="">Seleccione un distrito</option></select><small class="field-error" data-farm-error="distrito"></small></label><label class="field"><span>Pueblo</span><input name="pueblo" data-farm-town maxlength="150" list="farm-town-list" autocomplete="off" disabled><datalist id="farm-town-list" data-farm-town-list></datalist><small class="field-error" data-farm-error="pueblo"></small></label><label class="field field--full"><span>Señas</span><textarea name="senas" data-farm-directions maxlength="500" rows="2" aria-describedby="farm-directions-error"></textarea><small id="farm-directions-error" class="field-error" data-farm-error="senas"></small></label></div><div data-farm-map></div><small class="field-error" data-farm-error="direccionFinca"></small></details></div><p id="farm-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button id="farm-cancel" class="activity-button" type="button">Cancelar</button><button id="farm-save" class="activity-button activity-button--primary" type="submit">Guardar finca</button></div></form></dialog>
</body>
</html>
