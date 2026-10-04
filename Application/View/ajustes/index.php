<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ajustes de tu cuenta en Ganado Cerca: perfil y formas de participar.">
    <meta name="theme-color" content="#151a18">
    <title>Ajustes de cuenta | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-7">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/onboarding.css?v=front-2">
    <link rel="stylesheet" href="css/mi-actividad.css?v=panel-2">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-9"></script>
    <script type="module" src="js/ajustes.js?v=ajustes-2"></script>
</head>
<body class="public-home activity-page settings-page">
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
            <section id="activity-loading" class="activity-panel" role="status" aria-live="polite"><div class="purchase-loader"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Cargando tus ajustes…</span></div></section>
            <section id="activity-error" class="activity-panel" hidden role="alert"><div class="purchase-result"><h2>No pudimos cargar tus ajustes</h2><p id="activity-error-message">Intenta nuevamente.</p><button id="activity-retry" class="activity-button activity-button--primary" type="button">Reintentar</button></div></section>

            <div id="activity-content" class="settings-layout" hidden>
                <nav class="settings-nav" aria-label="Secciones de ajustes">
                    <a href="ajustes#perfil" data-settings-link="perfil"><i class="fa-solid fa-id-card" aria-hidden="true"></i>Perfil</a>
                    <a href="ajustes#participacion" data-settings-link="participacion"><i class="fa-solid fa-route" aria-hidden="true"></i>Cómo participo</a>
                </nav>
                <div class="settings-sections">
                    <div class="settings-heading">
                        <a class="panel-link" href="mi-actividad"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Mi panel</a>
                        <h1 id="settings-title">Ajustes de cuenta</h1>
                    </div>

                    <section class="activity-panel" id="perfil" aria-labelledby="perfil-title" tabindex="-1">
                        <div class="panel-head"><h2 id="perfil-title">Perfil</h2></div>
                        <dl id="profile-list" class="settings-profile"></dl>
                        <p class="settings-hint">Tu perfil es uno solo y se usa igual cuando compras, vendes o transportas.</p>
                    </section>

                    <section class="activity-panel" id="participacion" aria-labelledby="participacion-title" tabindex="-1">
                        <div class="panel-head"><h2 id="participacion-title">Cómo participo</h2></div>
                        <div id="activity-list" class="settings-toggles" aria-live="polite"></div>
                        <p class="settings-hint">Si desactivas una actividad, tus otros datos no se borran.</p>
                    </section>
                </div>
            </div>
            <p id="activity-status" class="auth-status" role="status" aria-live="polite"></p>
        </main>
        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand"><a class="public-brand public-brand--footer" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40"></span><span>Ganado<strong>Cerca</strong></span></a><p>Descubre ganado y oportunidades cerca de ti.</p></div>
            <div class="public-footer__links"><div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar">Explorar</a><a href="sobre-nosotros">Nosotros</a><a href="./#como-funciona">Cómo funciona</a></div><div><strong>Cuenta</strong><a href="mi-actividad">Mi panel</a><a href="ajustes">Ajustes</a><a href="como-usar">Ayuda de uso</a><a href="sobre-nosotros">Sobre Ganado Cerca</a></div><div class="public-footer__legal"><strong>Legal</strong><a href="privacidad">Privacidad</a><a href="terminos">Términos</a><a href="legal">Información legal</a></div></div>
        </footer>
    </div>

    <div class="toast-region" aria-label="Notificaciones"><div class="toast" id="toast-status" role="status" aria-live="polite"></div><div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div></div>

</body>
</html>
