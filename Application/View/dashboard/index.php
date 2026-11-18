<!DOCTYPE html>
<html lang="es">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Centro de inteligencia y trazabilidad de Ganado Cerca">
    <title>Dashboard | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-10">
    <link rel="stylesheet" href="css/components.css?v=official-shell-3">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-3">
    <link rel="stylesheet" href="css/admin-dashboard.css?v=dashboard-8">
    <script type="module" src="js/dashboard.js?v=intel-6"></script>
    <script defer src="js/shared/panel-runtime.js?v=runtime-1"></script>
</head>
<body class="rural-panel admin-dashboard">
    <?php require dirname(__DIR__) . '/shared/panel-runtime.php'; ?>
    <aside class="rural-panel__sidebar">
        <div class="rural-panel__sidebar-brand">
            <span class="brand__logo brand__logo--light" aria-hidden="true"><img src="assets/logo_light-128.webp" alt="" width="44" height="44"></span>
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
                <a class="rural-panel__nav-item" href="admin/publicaciones">Publicaciones</a>
                <a class="rural-panel__nav-item" href="admin/fletes">Fletes</a>
                <a class="rural-panel__nav-item" href="admin/subastas">Subastas</a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Sitio público</a><a class="rural-panel__admin-link" href="admin/entrar">Cerrar sesión</a></div>

            <header class="intel-cabecera">
                <div>
                    <span class="label">Centro de inteligencia</span>
                    <h1 id="page-title">Actividad de Ganado Cerca</h1>
                    <p class="intel-cabecera__texto">Lo que pasa en la plataforma: personas, ventas, subastas y transporte. Abre un área para ver sus registros.</p>
                </div>
                <form class="intel-periodo" id="intel-periodo" aria-label="Periodo del análisis" novalidate>
                    <label class="intel-campo"><span>Periodo</span><select name="periodo">
                        <option value="30" selected>Últimos 30 días</option>
                        <option value="90">Últimos 90 días</option>
                        <option value="365">Últimos 12 meses</option>
                        <option value="todo">Todo el tiempo</option>
                        <option value="rango">Elegir fechas</option>
                    </select></label>
                    <label class="intel-campo" data-rango hidden><span>Desde</span><input name="desde" type="date"></label>
                    <label class="intel-campo" data-rango hidden><span>Hasta</span><input name="hasta" type="date"></label>
                    <button class="button button--secondary button--small" id="dashboard-refresh" type="submit"><i class="fa-solid fa-rotate" aria-hidden="true"></i>Actualizar</button>
                    <p class="dashboard-status" id="dashboard-status" role="status"></p>
                </form>
            </header>

            <section class="intel-zona" aria-labelledby="intel-global-titulo">
                <h2 class="intel-zona__titulo" id="intel-global-titulo">Visión global</h2>
                <div class="intel-kpis" id="intel-kpis" aria-busy="true">
                    <?= str_repeat('<article class="intel-kpi intel-kpi--cargando"><span></span><span></span><span></span></article>', 6) ?>
                </div>
                <h3 class="intel-subtitulo">Red ganadera hoy</h3>
                <div class="dashboard-metrics" id="dashboard-metrics" aria-busy="true">
                    <?= str_repeat('<article class="dashboard-metric dashboard-metric--loading"><span></span><span></span><span></span></article>', 4) ?>
                </div>
            </section>

            <section class="intel-zona" aria-labelledby="intel-arbol-titulo">
                <h2 class="intel-zona__titulo" id="intel-arbol-titulo">Actividad por área</h2>
                <div class="intel-arbol" id="intel-arbol" aria-busy="true"><p class="screen-reader-only" role="status">Cargando áreas…</p><?= str_repeat('<div class="intel-proceso intel-proceso--cargando" aria-hidden="true"><span></span><span></span></div>', 4) ?></div>
            </section>

            <section class="intel-zona" aria-labelledby="intel-actividad-titulo">
                <h2 class="intel-zona__titulo" id="intel-actividad-titulo">Análisis por actividad</h2>
                <div class="intel-dos">
                    <section class="intel-panel intel-panel--abierto" id="intel-transportistas" aria-labelledby="intel-transportistas-titulo">
                        <h3 class="intel-panel__titulo" id="intel-transportistas-titulo">Transportistas</h3>
                        <div class="intel-panel__cuerpo" id="intel-transportistas-cuerpo"><p class="intel-vacio">Cargando transportistas…</p></div>
                    </section>
                    <div class="intel-panel intel-panel--abierto">
                        <form class="intel-busqueda" id="intel-busqueda" role="search">
                            <label for="intel-q"><strong>Buscar una persona</strong><small>Escribe al menos 2 letras del nombre o el alias</small></label>
                            <div class="intel-busqueda__fila"><input id="intel-q" name="q" type="search" maxlength="80" autocomplete="off" placeholder="Nombre o alias" aria-controls="intel-persona"></div>
                        </form>
                        <div id="intel-persona" class="intel-panel__cuerpo" aria-live="polite"></div>
                    </div>
                </div>
            </section>

            <section class="intel-zona" aria-labelledby="intel-control-titulo">
                <h2 class="intel-zona__titulo" id="intel-control-titulo">Revisión de datos</h2>
                <div class="intel-dos" id="intel-control"></div>
            </section>
        </div>
    </main>
</body>
</html>
