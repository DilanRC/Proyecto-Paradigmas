<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="/">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Encuentra transporte ganadero u ofrece fletes en TinderCows.">
    <meta name="theme-color" content="#151a18">
    <title>Fletes | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/onboarding.css?v=front-2">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/fletes.js?v=front-2"></script>
</head>
<body class="public-home">
<div class="activity-shell">
    <header class="public-header public-header--product">
        <a class="public-brand" href="./" aria-label="TinderCows, inicio">
            <span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48"></span>
            <span>Tinder<strong>Cows</strong></span>
        </a>
        <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
            <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
            <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            <a class="is-active" href="fletes"><i class="fa-solid fa-truck" aria-hidden="true"></i><span>Fletes</span></a>
            <a href="mi-actividad"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>Mi actividad</span></a>
        </nav>
        <div class="public-header__actions"><button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button></div>
    </header>

    <section class="activity-header" aria-labelledby="fletes-title">
        <p class="section-kicker">Transporte ganadero</p>
        <h1 id="fletes-title">Fletes sin convertir al usuario en un CRUD.</h1>
        <p>Desde aquí una persona puede ofrecer transporte o gestionar esa actividad sin entrar al panel administrativo de Transportistas.</p>
    </section>

    <div class="activity-grid">
        <section class="activity-panel">
            <div class="step-heading"><span class="step-number"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span><div><h2>¿Deseas ofrecer fletes?</h2><p>Solo pediremos los datos que todavía hagan falta. El vehículo puede asociarse después.</p></div></div>
            <div id="fletes-state" class="business-note" role="status" aria-live="polite"></div>
            <div class="activity-actions" style="justify-content:flex-start;margin-top:18px">
                <a id="fletes-primary" class="activity-button activity-button--primary" href="registro/transportista">Quiero ofrecer fletes</a>
                <a class="activity-button" href="mi-actividad">Ver mi actividad</a>
            </div>
        </section>

        <aside class="activity-panel">
            <h2>Regla aplicada</h2>
            <p>Transportista es una actividad de negocio de la misma Persona. Activarla o desactivarla no crea ni elimina otra identidad.</p>
            <ul class="rules-list"><li>No obliga a ser productor.</li><li>No exige vehículo durante el alta inicial.</li><li>Se puede desactivar y reactivar.</li><li>La administración técnica queda separada del flujo público.</li></ul>
        </aside>
    </div>
</div>
</body>
</html>
