<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Las publicaciones de ganado que marcaste con Me interesa en Ganado Cerca.">
    <meta name="theme-color" content="#151a18">
    <title>Me interesa | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-8">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/onboarding.css?v=publish-3">
    <link rel="stylesheet" href="css/mi-actividad.css?v=panel-4">
    <link rel="stylesheet" href="css/explore.css?v=explore-9">
    <link rel="stylesheet" href="css/solicitud.css?v=solicitud-1">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-13"></script>
    <script type="module" src="js/me-interesa.js?v=interesa-3"></script>
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
                <form class="public-search" action="explorar" method="get" role="search" data-public-search><button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button><div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-ajustes">Buscar publicaciones</label><input id="busqueda-publica-ajustes" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div></form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="activity-main">
            <div class="settings-heading">
                <a class="panel-link" href="mi-actividad"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Mi panel</a>
                <h1 id="saved-title">Me interesa</h1>
                <p class="settings-hint">Las publicaciones que marcaste. Si una se vende o se pausa, sigue aquí como “No disponible” hasta que la quites.</p>
            </div>
            <div id="saved-loading" class="resource-state" role="status" aria-live="polite">Cargando tus publicaciones…</div>
            <div id="saved-error" class="resource-state resource-state--error" role="alert" hidden><p id="saved-error-message">No pudimos cargar tus publicaciones.</p><button id="saved-retry" class="activity-button" type="button">Reintentar</button></div>
            <div id="saved-empty" class="panel-empty" hidden><strong>Aún no marcaste ninguna publicación</strong><p>Toca “Me interesa” en Explorar y las verás aquí.</p><a class="activity-button activity-button--sm" href="explorar">Explorar ganado</a></div>
            <div id="saved-list" class="explore-deck__viewport" role="region" aria-label="Publicaciones que me interesan" aria-live="polite" tabindex="0" hidden></div>
        </main>
        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand"><a class="public-brand public-brand--footer" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40"></span><span>Ganado<strong>Cerca</strong></span></a><p>Descubre ganado y oportunidades cerca de ti.</p></div>
            <div class="public-footer__links"><div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar">Explorar</a><a href="sobre-nosotros">Nosotros</a><a href="./#como-funciona">Cómo funciona</a></div><div><strong>Cuenta</strong><a href="mi-actividad">Mi panel</a><a href="ajustes">Ajustes</a><a href="como-usar">Ayuda de uso</a><a href="sobre-nosotros">Sobre Ganado Cerca</a></div><div class="public-footer__legal"><strong>Legal</strong><a href="privacidad">Privacidad</a><a href="terminos">Términos</a><a href="legal">Información legal</a></div></div>
        </footer>
    </div>

    <div class="toast-region" aria-label="Notificaciones"><div class="toast" id="toast-status" role="status" aria-live="polite"></div><div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div></div>

</body>
</html>
