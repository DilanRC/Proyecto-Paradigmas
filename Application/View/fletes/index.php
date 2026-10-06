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
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-8">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/onboarding.css?v=publish-3">
    <link rel="stylesheet" href="css/mi-actividad.css?v=panel-4">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-13"></script>
    <script type="module" src="js/fletes.js?v=fletes-2"></script>
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
            <h1 id="fletes-title">Fletes cerca de ti.</h1>
            <p>Encuentra transportistas que cubren tu zona, o publica tu servicio para que te encuentren.</p>
        </section>

        <div class="activity-grid">
            <div class="panel-col">
                <section class="activity-panel" aria-labelledby="cercanos-title">
                    <div class="panel-head"><h2 id="cercanos-title">Fletes disponibles<span class="panel-count" id="cercanos-count"></span></h2></div>
                    <form id="cercanos-filtro" class="vehicle-form-grid" novalidate>
                        <label class="field"><span>Capacidad mínima (cabezas)</span><input id="cercanos-capacidad" name="capacidadMinima" type="number" min="1" max="200" step="1" inputmode="numeric" value="1"></label>
                    </form>
                    <div id="cercanos-estado" class="resource-state" role="status" aria-live="polite">Buscando fletes cerca de ti…</div>
                    <button id="cercanos-ubicacion" class="activity-button" type="button" hidden><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>Usar mi ubicación</button>
                    <div id="cercanos-lista" class="panel-rows" aria-live="polite"></div>
                </section>

                <section id="ofertas-panel" class="activity-panel" aria-labelledby="ofertas-title" hidden>
                    <div class="panel-head"><h2 id="ofertas-title">Mis ofertas<span class="panel-count" id="ofertas-count"></span></h2><button id="oferta-nueva" class="activity-button activity-button--sm" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i>Publicar oferta</button></div>
                    <div id="ofertas-estado" class="resource-state" role="status" aria-live="polite">Cargando tus ofertas…</div>
                    <div id="ofertas-lista" class="panel-rows" aria-live="polite"></div>
                    <div id="ofertas-sin-vehiculo" class="panel-todo" hidden>
                        <p><strong>Primero registra un vehículo.</strong> Cada oferta de flete sale con uno de tus vehículos.</p>
                        <a class="activity-button activity-button--sm" href="mi-actividad">Agregar vehículo</a>
                    </div>
                </section>
            </div>

            <aside class="activity-panel">
                <div class="step-heading"><span class="step-number"><i class="fa-solid fa-truck-fast" aria-hidden="true"></i></span><div><h2>¿Quieres ofrecer fletes?</h2><p>Activa Transportista y publica tu servicio con tu vehículo y tu zona.</p></div></div>
                <div id="fletes-state" class="business-note" role="status" aria-live="polite"></div>
                <div class="activity-actions" style="justify-content:flex-start;margin-top:18px">
                    <a id="fletes-primary" class="activity-button activity-button--primary" href="registro/transportista">Quiero ofrecer fletes</a>
                    <a class="activity-button" href="mi-actividad">Ver mi panel</a>
                </div>
                <ul class="rules-list"><li>Ofrece transporte sin vender ganado.</li><li>Dices desde dónde sales y hasta cuántos km cubres.</li><li>Pausa tu oferta cuando no tengas disponibilidad.</li></ul>
            </aside>
        </div>
    </main>

    <dialog id="oferta-modal" class="activity-dialog activity-dialog--farm" aria-labelledby="oferta-modal-title">
        <form id="oferta-form" method="dialog" novalidate>
            <div class="activity-dialog__header"><div><p class="section-kicker">Mi oferta de flete</p><h2 id="oferta-modal-title">Publicar oferta</h2></div><button id="oferta-close" class="dialog-close" type="button" aria-label="Cerrar">×</button></div>
            <p class="fieldset-help">Los clientes cuya ubicación esté dentro de tu radio verán esta oferta, de la más cercana a la más lejana.</p>
            <div class="vehicle-form-grid">
                <label class="field field--full"><span>Vehículo</span><select id="oferta-vehiculo" name="vehiculoId" required aria-describedby="oferta-vehiculo-error"></select><small id="oferta-vehiculo-error" class="field-error" data-oferta-error="vehiculoId"></small></label>
                <label class="field"><span>Radio de cobertura (km)</span><input name="radioKm" type="number" min="1" max="500" step="1" inputmode="numeric" required aria-describedby="oferta-radio-error"><small id="oferta-radio-error" class="field-error" data-oferta-error="radioKm"></small></label>
                <label class="field"><span>Capacidad (cabezas)</span><input name="capacidad" type="number" min="1" max="200" step="1" inputmode="numeric" required aria-describedby="oferta-capacidad-error"><small id="oferta-capacidad-error" class="field-error" data-oferta-error="capacidad"></small></label>
                <label class="field"><span>Precio base (₡) <span class="label">opcional</span></span><input name="precio" type="number" min="0" step="1" inputmode="numeric" aria-describedby="oferta-precio-error"><small id="oferta-precio-error" class="field-error" data-oferta-error="precio"></small></label>
                <label class="field field--full"><span>Descripción y disponibilidad <span class="label">opcional</span></span><textarea name="descripcion" maxlength="500" rows="3" placeholder="Ej.: Lunes a sábado, jaula para 10 reses" aria-describedby="oferta-descripcion-error"></textarea><small id="oferta-descripcion-error" class="field-error" data-oferta-error="descripcion"></small></label>
            </div>
            <div id="oferta-zona" class="farm-address-editor">
                <p class="fieldset-help"><strong>Zona base.</strong> Desde dónde sales. Marca el punto en el mapa: es lo que se usa para medir la distancia.</p>
                <div class="farm-address-editor__grid">
                    <label class="field"><span>Provincia</span><select name="provincia" data-farm-province></select></label>
                    <label class="field"><span>Cantón</span><select name="canton" data-farm-canton></select></label>
                    <label class="field"><span>Distrito</span><select name="distrito" data-farm-district disabled><option value="">Seleccione un distrito</option></select></label>
                    <label class="field"><span>Pueblo</span><input name="pueblo" data-farm-town maxlength="150" list="oferta-pueblos" autocomplete="off" disabled><datalist id="oferta-pueblos" data-farm-town-list></datalist></label>
                    <label class="field field--full"><span>Señas</span><textarea name="senas" data-farm-directions maxlength="500" rows="2"></textarea></label>
                </div>
                <div data-farm-map></div>
                <small class="field-error" data-oferta-error="direccion"></small>
            </div>
            <p id="oferta-form-status" class="auth-status" role="status" aria-live="polite"></p>
            <div class="activity-dialog__actions"><button id="oferta-cancel" class="activity-button" type="button">Cancelar</button><button id="oferta-save" class="activity-button activity-button--primary" type="submit">Publicar oferta</button></div>
        </form>
    </dialog>
</div>
</body>
</html>
