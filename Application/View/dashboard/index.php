<!DOCTYPE html>
<html lang="es">
<head>
    <base href="/">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Resumen administrativo de TinderCows">
    <title>Dashboard | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <link rel="stylesheet" href="css/admin-dashboard.css?v=dashboard-2">
    <script type="module" src="js/dashboard.js"></script>
</head>
<body class="rural-panel admin-dashboard">
    <aside class="rural-panel__sidebar">
        <div class="rural-panel__sidebar-brand">
            <span class="brand__logo brand__logo--light" aria-hidden="true"><img src="assets/logo_light.png" alt="" width="44" height="44"></span>
            <span class="rural-panel__sidebar-brand-name">Tinder<strong>Cows</strong></span>
        </div>
        <nav class="rural-panel__nav" aria-label="Administración">
            <p class="rural-panel__nav-label">Administración</p>
            <div class="rural-panel__nav-list">
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="admin/dashboard">Dashboard<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="admin/productores">Productores</a>
                <a class="rural-panel__nav-item" href="admin/compradores">Compradores</a>
                <a class="rural-panel__nav-item" href="admin/transportistas">Transportistas</a>
                <a class="rural-panel__nav-item" href="admin/vehiculos">Vehículos</a>
                <a class="rural-panel__nav-item" href="admin/metodos-pago">Métodos de pago</a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Sitio público</a><a class="rural-panel__admin-link" href="admin/entrar">Cerrar sesión</a></div>

            <section class="dashboard-hero" aria-labelledby="page-title">
                <div class="dashboard-hero__copy">
                    <span class="label">Centro de control</span>
                    <h1 id="page-title">La red ganadera, en una sola vista.</h1>
                    <p>Revise el pulso de TinderCows, encuentre accesos rápidos y mantenga la operación cerca de lo importante.</p>
                    <div class="dashboard-hero__actions"><a class="button button--primary" href="admin/productores"><i class="fa-solid fa-users" aria-hidden="true"></i>Revisar productores</a><a class="button button--secondary" href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i>Ver sitio público</a></div>
                </div>
            </section>

            <section class="dashboard-section" aria-labelledby="summary-title">
                <div class="dashboard-section__heading"><div><span class="label">Resumen vivo</span><h2 id="summary-title">Lo que está pasando ahora</h2></div><button class="button button--secondary button--small" id="dashboard-refresh" type="button"><i class="fa-solid fa-rotate" aria-hidden="true"></i>Actualizar</button></div>
                <div class="dashboard-metrics" id="dashboard-metrics" aria-busy="true" aria-live="polite">
                    <article class="dashboard-metric dashboard-metric--loading"><span></span><span></span><span></span></article>
                    <article class="dashboard-metric dashboard-metric--loading"><span></span><span></span><span></span></article>
                    <article class="dashboard-metric dashboard-metric--loading"><span></span><span></span><span></span></article>
                    <article class="dashboard-metric dashboard-metric--loading"><span></span><span></span><span></span></article>
                </div>
                <p class="dashboard-status" id="dashboard-status" role="status"></p>
            </section>

            <section class="dashboard-grid">
                <article class="dashboard-card dashboard-card--feature"><div class="dashboard-card__body"><span class="label">Contexto ganadero</span><h2>Preparados para conectar oportunidades reales.</h2><p>La administración reúne personas, fincas, transporte y publicaciones para que cada operación tenga un lugar claro.</p><a class="text-link" href="admin/transportistas">Revisar logística <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div></article>
                <article class="dashboard-card dashboard-card--links"><span class="label">Accesos rápidos</span><h2>Ir directo al trabajo</h2><div class="dashboard-quick-links"><a href="admin/compradores"><i class="fa-solid fa-handshake" aria-hidden="true"></i><span><strong>Compradores</strong><small>Consultar actividad</small></span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><a href="admin/vehiculos"><i class="fa-solid fa-truck-pickup" aria-hidden="true"></i><span><strong>Vehículos</strong><small>Revisar disponibilidad</small></span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a><a href="admin/metodos-pago"><i class="fa-solid fa-wallet" aria-hidden="true"></i><span><strong>Métodos de pago</strong><small>Gestionar catálogo</small></span><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a></div></article>
            </section>

            <p class="rural-panel__footnote">Dashboard administrativo · Los indicadores provienen de las APIs de TinderCows</p>
        </div>
    </main>
</body>
</html>
