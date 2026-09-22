<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Encuentra transporte ganadero u ofrece fletes en Ganado Cerca.">
    <meta name="theme-color" content="#151a18">
    <title>Fletes | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/public-product.css?v=product-2">
    <link rel="stylesheet" href="css/onboarding.css?v=front-2">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-3"></script>
    <script type="module" src="js/fletes.js?v=front-2"></script>
</head>
<body class="public-home">
<div class="public-shell">
    <header class="public-header public-header--product">
        <a class="public-brand" href="./">
            <span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48"></span>
            <span>Ganado<strong>Cerca</strong></span>
        </a>
        <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
            <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
            <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            <a href="./#nosotros"><i class="fa-solid fa-people-group" aria-hidden="true"></i><span>Nosotros</span></a>
            <a href="./#como-funciona"><i class="fa-solid fa-route" aria-hidden="true"></i><span>Cómo funciona</span></a>
            <a class="is-active" href="fletes"><i class="fa-solid fa-truck" aria-hidden="true"></i><span>Fletes</span></a>
        </nav>
        <div class="public-header__actions">
            <form class="public-search" action="explorar" method="get" role="search" data-public-search data-open="false">
                <button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button>
                <div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-fletes">Buscar publicaciones</label><input id="busqueda-publica-fletes" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div>
            </form>
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
            <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
        </div>
    </header>

    <main class="activity-shell">
        <section class="activity-header" aria-labelledby="fletes-title">
            <p class="section-kicker">Transporte ganadero</p>
            <h1 id="fletes-title">Ofrece transporte para ganado.</h1>
            <p>Comparte tu disponibilidad para movilizar ganado y mantén los datos de tu servicio en un solo lugar.</p>
        </section>

        <div class="activity-grid">
            <section class="activity-panel">
                <div class="step-heading"><span class="step-number"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span><div><h2>¿Quieres ofrecer fletes?</h2><p>Empieza con tus datos de contacto. Podrás agregar los datos de tu vehículo después.</p></div></div>
                <div id="fletes-state" class="business-note" role="status" aria-live="polite"></div>
                <div class="activity-actions" style="justify-content:flex-start;margin-top:18px">
                    <a id="fletes-primary" class="activity-button activity-button--primary" href="registro/transportista">Quiero ofrecer fletes</a>
                    <a class="activity-button" href="mi-actividad">Ver mi actividad</a>
                </div>
            </section>

            <aside class="activity-panel">
                <h2>Tu servicio, a tu ritmo</h2>
                <p>Comienza cuando estés listo. La información que agregues queda disponible desde tu perfil.</p>
                <ul class="rules-list"><li>Ofrece transporte sin vender ganado.</li><li>Agrega vehículos cuando los tengas listos.</li><li>Actualiza tu disponibilidad desde Mi actividad.</li></ul>
            </aside>
        </div>
    </main>
</div>
</body>
</html>
