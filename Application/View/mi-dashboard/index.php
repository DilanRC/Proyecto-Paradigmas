<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Indicadores de tus subastas en Ganado Cerca.">
    <meta name="theme-color" content="#151a18">
    <title>Mi dashboard | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-9">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-10">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/mi-actividad.css?v=panel-4">
    <link rel="stylesheet" href="css/mi-dashboard.css?v=dashboard-4">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-19"></script>
    <script type="module" src="js/mi-dashboard.js?v=dashboard-4"></script>
</head>
<body class="public-home activity-page dashboard-page">
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48"></span><span>Ganado<strong>Cerca</strong></span></a>
            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            </nav>
            <div class="public-header__actions">
                <form class="public-search" action="explorar" method="get" role="search" data-public-search><button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button><div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-dashboard">Buscar publicaciones</label><input id="busqueda-publica-dashboard" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div></form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="activity-main dash">
            <header class="dash-cabecera">
                <div>
                    <h1 id="dashboard-title">Mi dashboard</h1>
                    <p class="dash-subtitulo" id="dashboard-subtitulo">Cómo te va en las subastas · últimos 30 días</p>
                </div>
                <!-- Un dashboard por rol. Solo aparecen los roles que la persona tiene. -->
                <div class="dash-segmento" role="tablist" aria-label="Qué dashboard ver" hidden>
                    <button type="button" role="tab" id="tab-vendedor" aria-controls="dashboard-panel" aria-selected="true" data-dashboard-rol="VENDEDOR" hidden><i class="fa-solid fa-store" aria-hidden="true"></i><span>Como vendedor</span></button>
                    <button type="button" role="tab" id="tab-comprador" aria-controls="dashboard-panel" aria-selected="false" data-dashboard-rol="COMPRADOR" hidden><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i><span>Como comprador</span></button>
                </div>
            </header>

            <form id="dashboard-filtros" class="dash-tarjeta dash-filtros" aria-label="Filtrar indicadores" novalidate>
                <label class="dash-campo"><span>Periodo</span><select name="periodo">
                    <option value="7">Últimos 7 días</option>
                    <option value="30" selected>Últimos 30 días</option>
                    <option value="90">Últimos 90 días</option>
                    <option value="365">Últimos 12 meses</option>
                    <option value="todo">Todo el tiempo</option>
                    <option value="rango">Elegir fechas</option>
                </select></label>
                <label class="dash-campo" data-rango hidden><span>Desde</span><input name="desde" type="date"></label>
                <label class="dash-campo" data-rango hidden><span>Hasta</span><input name="hasta" type="date"></label>
                <label class="dash-campo"><span>Tipo de ganado</span><select name="especieId"><option value="">Todos</option></select></label>
                <label class="dash-campo"><span>Categoría</span><select name="tipoId"><option value="">Todas</option></select></label>
                <label class="dash-campo"><span>Raza</span><select name="razaId"><option value="">Todas</option></select></label>
                <label class="dash-campo"><span>Ubicación</span><select name="provincia">
                    <option value="">Todo el país</option>
                    <option>San José</option><option>Alajuela</option><option>Cartago</option><option>Heredia</option>
                    <option>Guanacaste</option><option>Puntarenas</option><option>Limón</option>
                </select></label>
                <label class="dash-campo" data-solo-comprador hidden><span>Radio de búsqueda</span><select name="radioKm">
                    <option value="10">10 km</option><option value="25">25 km</option><option value="50" selected>50 km</option>
                    <option value="100">100 km</option><option value="200">200 km</option>
                </select></label>
                <div class="dash-filtros__acciones">
                    <button id="dashboard-ubicacion" class="dash-boton" type="button" aria-describedby="dashboard-ubicacion-estado" data-solo-comprador hidden><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i><span>Usar mi ubicación</span></button>
                    <button class="dash-boton" type="reset">Limpiar</button>
                </div>
                <small id="dashboard-ubicacion-estado" class="dash-filtros__nota" role="status" data-solo-comprador hidden></small>
                <small id="dashboard-filtros-error" class="field-error dash-filtros__nota" role="alert"></small>
            </form>

            <section id="dashboard-panel" role="tabpanel" aria-labelledby="tab-vendedor" aria-busy="true">
                <div id="dashboard-loading" class="resource-state" role="status" aria-live="polite">Cargando tus indicadores…</div>
                <div id="dashboard-error" class="resource-state resource-state--error" role="alert" hidden><p id="dashboard-error-message">No pudimos cargar tus indicadores.</p><button id="dashboard-retry" class="dash-boton" type="button">Reintentar</button></div>
                <div id="dashboard-sin-rol" class="panel-empty" hidden><strong>Todavía no tienes un dashboard</strong><p id="dashboard-sin-rol-texto">Cada dashboard aparece cuando activas su actividad (Vendedor o Comprador). <a href="participar">Elegir cómo participo</a></p></div>
                <div id="dashboard-vacio" class="panel-empty" hidden><strong>No hay subastas en este periodo</strong><p id="dashboard-vacio-texto">Prueba con otro periodo o quita algún filtro.</p></div>
                <!-- mi-dashboard.js arma aquí las secciones: resultados, estados, gráficos, actividad y la tabla de ejemplares. -->
                <div id="dashboard-contenido" class="dash-contenido" aria-live="polite" hidden></div>
            </section>
        </main>
        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand"><a class="public-brand public-brand--footer" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40"></span><span>Ganado<strong>Cerca</strong></span></a><p>Descubre ganado y oportunidades cerca de ti.</p></div>
            <div class="public-footer__links"><div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar">Explorar</a><a href="subastas">Subastas</a><a href="sobre-nosotros">Nosotros</a></div><div><strong>Cuenta</strong><a href="mi-actividad">Mi panel</a><a href="mi-dashboard">Mi dashboard</a><a href="ajustes">Ajustes</a><a href="como-usar">Ayuda de uso</a></div><div class="public-footer__legal"><strong>Legal</strong><a href="privacidad">Privacidad</a><a href="terminos">Términos</a><a href="legal">Información legal</a></div></div>
        </footer>
    </div>

    <div class="toast-region" aria-label="Notificaciones"><div class="toast" id="toast-status" role="status" aria-live="polite"></div><div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div></div>
</body>
</html>
