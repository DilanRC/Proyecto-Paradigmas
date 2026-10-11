<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Subastas de ganado en vivo en Ganado Cerca.">
    <meta name="theme-color" content="#151a18">
    <title>Subastas | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-10">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/onboarding.css?v=publish-3">
    <link rel="stylesheet" href="css/mi-actividad.css?v=panel-4">
    <link rel="stylesheet" href="css/subastas.css?v=subastas-1">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-16"></script>
    <script type="module" src="js/subastas.js?v=subastas-2"></script>
</head>
<body class="public-home activity-page subastas-page">
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48"></span><span>Ganado<strong>Cerca</strong></span></a>
            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            </nav>
            <div class="public-header__actions">
                <form class="public-search" action="explorar" method="get" role="search" data-public-search><button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button><div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-subastas">Buscar publicaciones</label><input id="busqueda-publica-subastas" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div></form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="activity-main">
            <!-- Lista: activas, historial, mis subastas y donde pujé -->
            <section id="subastas-lista" aria-labelledby="subastas-title">
                <div class="subastas-heading">
                    <div>
                        <h1 id="subastas-title">Subastas</h1>
                        <p class="settings-hint">Puja en vivo por ganado. Cada puja y cada comentario se ven al instante en todas las cuentas.</p>
                    </div>
                    <div class="subastas-heading__actions">
                        <span class="subastas-vivo" data-subastas-vivo data-conectado="false" role="status" aria-live="polite">Conectando…</span>
                        <button id="subasta-abrir" class="activity-button activity-button--primary" type="button"><i class="fa-solid fa-gavel" aria-hidden="true"></i>Abrir subasta</button>
                    </div>
                </div>
                <div class="subastas-tools">
                    <div class="subastas-tabs" role="tablist" aria-label="Qué subastas ver">
                        <button type="button" role="tab" aria-selected="true" data-subastas-vista="ACTIVAS">Activas</button>
                        <button type="button" role="tab" aria-selected="false" data-subastas-vista="HISTORIAL">Historial</button>
                        <button type="button" role="tab" aria-selected="false" data-subastas-vista="MIAS">Mis subastas</button>
                        <button type="button" role="tab" aria-selected="false" data-subastas-vista="PARTICIPO">Donde pujé</button>
                    </div>
                    <label class="field subastas-buscar"><span class="screen-reader-only">Buscar subastas</span><input id="subastas-q" type="search" maxlength="100" autocomplete="off" placeholder="Buscar por título, raza o zona"></label>
                </div>
                <div id="subastas-loading" class="resource-state" role="status" aria-live="polite">Cargando subastas…</div>
                <div id="subastas-error" class="resource-state resource-state--error" role="alert" hidden><p id="subastas-error-message">No pudimos cargar las subastas.</p><button id="subastas-retry" class="activity-button" type="button">Reintentar</button></div>
                <div id="subastas-empty" class="panel-empty" hidden><strong id="subastas-empty-title">No hay subastas activas</strong><p id="subastas-empty-text">Cuando alguien abra una, aparecerá aquí al instante.</p></div>
                <div id="subastas-grid" class="subastas-grid" aria-live="polite"></div>
            </section>

            <!-- Detalle de una subasta (subastas?id=N) -->
            <section id="subasta-detalle" class="subasta-detalle" aria-labelledby="subasta-titulo" hidden>
                <a class="panel-link" href="subastas"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Todas las subastas</a>
                <div id="subasta-loading" class="resource-state" role="status" aria-live="polite">Cargando la subasta…</div>
                <div id="subasta-error" class="resource-state resource-state--error" role="alert" hidden><p id="subasta-error-message">No pudimos cargar la subasta.</p><button id="subasta-retry" class="activity-button" type="button">Reintentar</button></div>
                <div id="subasta-contenido" class="subasta-layout" hidden>
                    <div class="subasta-principal">
                        <div class="subasta-foto" id="subasta-foto"><i class="fa-solid fa-cow" aria-hidden="true"></i></div>
                        <div class="activity-panel">
                            <p class="section-kicker"><span id="subasta-estado" class="subasta-estado"></span> <span class="subastas-vivo" data-subastas-vivo data-conectado="false">Conectando…</span></p>
                            <h1 id="subasta-titulo"></h1>
                            <p id="subasta-descripcion" class="settings-hint"></p>
                            <dl id="subasta-datos" class="subasta-datos"></dl>
                        </div>
                        <div id="subasta-trato" class="activity-panel subasta-trato" hidden aria-labelledby="subasta-trato-titulo">
                            <h2 id="subasta-trato-titulo"></h2>
                            <p id="subasta-trato-texto" class="settings-hint"></p>
                            <dl id="subasta-trato-datos" class="subasta-datos"></dl>
                            <div id="subasta-fletes" hidden>
                                <h3>Fletes que cubren la finca</h3>
                                <p class="settings-hint">Llama al transportista para coordinar el traslado. También puedes buscar más en Fletes.</p>
                                <ul id="subasta-fletes-lista" class="subasta-lista"></ul>
                            </div>
                        </div>
                        <div class="activity-panel" aria-labelledby="subasta-comentarios-titulo">
                            <h2 id="subasta-comentarios-titulo">Comentarios</h2>
                            <ul id="subasta-comentarios" class="subasta-comentarios" aria-live="polite"></ul>
                            <form id="subasta-comentar" class="subasta-comentar" novalidate>
                                <label class="field"><span>Escribe un comentario</span><textarea name="texto" maxlength="500" rows="2" required aria-describedby="subasta-comentar-error"></textarea><small id="subasta-comentar-error" class="field-error"></small></label>
                                <button class="activity-button" type="submit">Comentar</button>
                            </form>
                        </div>
                    </div>
                    <aside class="subasta-lateral">
                        <div class="activity-panel subasta-precio">
                            <p class="subasta-precio__etiqueta" id="subasta-precio-etiqueta">Puja más alta</p>
                            <p class="subasta-precio__monto" id="subasta-precio"></p>
                            <p class="subasta-precio__extra" id="subasta-pujas-total"></p>
                            <p class="subasta-reloj"><i class="fa-regular fa-clock" aria-hidden="true"></i> <span id="subasta-reloj" role="timer" aria-live="off"></span></p>
                            <form id="subasta-pujar" class="subasta-pujar" novalidate hidden>
                                <label class="field"><span>Tu puja (₡)</span><input name="monto" type="number" min="1" step="1" inputmode="numeric" required aria-describedby="subasta-minimo subasta-pujar-error"><small id="subasta-minimo" class="field-help"></small><small id="subasta-pujar-error" class="field-error"></small></label>
                                <button class="activity-button activity-button--primary" type="submit"><i class="fa-solid fa-gavel" aria-hidden="true"></i>Pujar</button>
                            </form>
                            <p id="subasta-motivo" class="settings-hint" hidden></p>
                            <button id="subasta-cancelar" class="activity-button activity-button--text" type="button" hidden>Cancelar subasta</button>
                        </div>
                        <div class="activity-panel" aria-labelledby="subasta-pujas-titulo">
                            <h2 id="subasta-pujas-titulo">Pujas</h2>
                            <ol id="subasta-pujas" class="subasta-lista" aria-live="polite"></ol>
                        </div>
                    </aside>
                </div>
            </section>
        </main>
        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand"><a class="public-brand public-brand--footer" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40"></span><span>Ganado<strong>Cerca</strong></span></a><p>Descubre ganado y oportunidades cerca de ti.</p></div>
            <div class="public-footer__links"><div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar">Explorar</a><a href="subastas">Subastas</a><a href="sobre-nosotros">Nosotros</a></div><div><strong>Cuenta</strong><a href="mi-actividad">Mi panel</a><a href="ajustes">Ajustes</a><a href="como-usar">Ayuda de uso</a><a href="sobre-nosotros">Sobre Ganado Cerca</a></div><div class="public-footer__legal"><strong>Legal</strong><a href="privacidad">Privacidad</a><a href="terminos">Términos</a><a href="legal">Información legal</a></div></div>
        </footer>
    </div>

    <dialog id="subasta-modal" class="activity-dialog" aria-labelledby="subasta-modal-title"><form id="subasta-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Subastas</p><h2 id="subasta-modal-title">Abrir subasta</h2></div><button class="dialog-close" type="button" aria-label="Cerrar" data-subasta-cerrar>×</button></div><p class="fieldset-help">Elige un animal de tu inventario (Mi panel → Mis animales). La subasta empieza al abrirla y todas las cuentas la ven al instante.</p><div class="vehicle-form-grid"><label class="field field--full"><span>Animal</span><select name="animalId" required></select><small class="field-error" data-subasta-error="animalId"></small></label><label class="field field--full"><span>Finca donde está</span><select name="fincaNombre" required></select><small class="field-error" data-subasta-error="fincaNombre"></small></label><label class="field field--full"><span>Título</span><input name="titulo" maxlength="150" autocomplete="off" required placeholder="Ej.: Vaca Jersey de 3 años"><small class="field-error" data-subasta-error="titulo"></small></label><label class="field field--full"><span>Descripción <span class="label">opcional</span></span><textarea name="descripcion" maxlength="500" rows="3"></textarea><small class="field-error" data-subasta-error="descripcion"></small></label><label class="field"><span>Precio base (₡)</span><input name="precioBase" type="number" min="1" max="9999999999" step="1" inputmode="numeric" required><small class="field-error" data-subasta-error="precioBase"></small></label><label class="field"><span>Incremento mínimo (₡)</span><input name="incrementoMinimo" type="number" min="1" max="9999999999" step="1" inputmode="numeric" required value="10000"><small class="field-error" data-subasta-error="incrementoMinimo"></small></label><label class="field field--full"><span>Duración</span><select name="duracionMinutos" required><option value="5">5 minutos</option><option value="15">15 minutos</option><option value="30">30 minutos</option><option value="60" selected>1 hora</option><option value="180">3 horas</option><option value="720">12 horas</option><option value="1440">1 día</option><option value="4320">3 días</option><option value="10080">7 días</option></select><small class="field-error" data-subasta-error="duracionMinutos"></small></label><div class="field field--full" data-foto-campo="subasta"><span>Foto <span class="label">opcional</span></span><div class="publish-dropzone"><input class="screen-reader-only" id="subasta-foto-archivo" type="file" accept="image/jpeg,image/png,image/webp"><label class="public-secondary publish-dropzone__button" for="subasta-foto-archivo"><i class="fa-solid fa-upload" aria-hidden="true"></i><span>Elegir imagen</span></label><small class="field-help">JPG, PNG o WebP, hasta 5 MB.</small></div><div class="publish-preview" data-foto-preview hidden><img alt="Vista previa de la foto"><button class="public-secondary" type="button" data-foto-quitar><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Quitar foto</span></button></div><small class="field-error" data-foto-error role="alert"></small></div></div><p id="subasta-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button class="activity-button" type="button" data-subasta-cerrar>Cancelar</button><button class="activity-button activity-button--primary" type="submit">Abrir subasta</button></div></form></dialog>

    <div class="toast-region" aria-label="Notificaciones"><div class="toast" id="toast-status" role="status" aria-live="polite"></div><div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div></div>
</body>
</html>
