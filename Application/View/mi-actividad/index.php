<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Administra cómo participas en TinderCows.">
    <meta name="theme-color" content="#151a18">
    <title>Mi actividad | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/onboarding.css?v=front-2">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
    <script type="module" src="js/mi-actividad.js?v=front-2"></script>
</head>
<body class="public-home">
<div class="activity-shell">
    <header class="auth-header">
        <a class="public-brand" href="./" aria-label="TinderCows, inicio">
            <span class="public-brand__logo" aria-hidden="true">
                <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
            </span>
            <span>Tinder<strong>Cows</strong></span>
        </a>
        <nav class="public-nav public-nav--primary" aria-label="Navegación de cuenta">
            <a href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            <a class="is-active" href="mi-actividad.php"><i class="fa-solid fa-user-gear" aria-hidden="true"></i><span>Mi actividad</span></a>
        </nav>
        <div class="auth-header__actions">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
            <button class="auth-back" id="cerrar-sesion" type="button"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Salir</span></button>
        </div>
    </header>

    <section class="activity-header" aria-labelledby="activity-title">
        <p class="section-kicker">Tu espacio</p>
        <h1 id="activity-title">Mi actividad</h1>
        <p>Administra las formas en que participas en TinderCows. Desactivar una actividad no borra tu persona ni las demás actividades.</p>
    </section>

    <div id="welcome-banner" class="welcome-banner" hidden role="status" aria-live="polite">
        Registro completado. Ya puedes decidir qué hacer primero.
    </div>

    <div class="activity-grid">
        <section class="activity-panel" aria-labelledby="capabilities-title">
            <div class="step-heading"><span class="step-number"><i class="fa-solid fa-route" aria-hidden="true"></i></span><div><h2 id="capabilities-title">Cómo participas</h2><p>Estas son actividades de negocio independientes.</p></div></div>
            <div id="activity-list" class="activity-list" aria-live="polite"></div>
        </section>

        <aside class="activity-panel" aria-labelledby="profile-title">
            <div class="step-heading"><span class="step-number"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span><div><h2 id="profile-title">Tu identidad</h2><p>Se comparte entre todas tus actividades.</p></div></div>
            <dl id="profile-list" class="profile-list"></dl>
            <div class="business-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>No creamos una persona distinta cuando compras, vendes o transportas.</p></div>
        </aside>
    </div>

    <p id="activity-status" class="auth-status" role="status" aria-live="polite"></p>
</div>
</body>
</html>
