<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Tu panel en Ganado Cerca: publicaciones, fincas y vehículos.">
    <meta name="theme-color" content="#151a18">
    <title>Mi panel | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-9">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/onboarding.css?v=publish-3">
    <link rel="stylesheet" href="css/mi-actividad.css?v=panel-4">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-14"></script>
    <script type="module" src="js/mi-actividad.js?v=panel-11"></script>
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
                <form class="public-search" action="explorar" method="get" role="search" data-public-search><button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button><div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-actividad">Buscar publicaciones</label><input id="busqueda-publica-actividad" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div></form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="activity-main">
            <section id="activity-loading" class="activity-panel" role="status" aria-live="polite"><div class="purchase-loader"><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Cargando tu panel…</span></div></section>
            <section id="activity-error" class="activity-panel" hidden role="alert"><div class="purchase-result"><h2>No pudimos cargar tu panel</h2><p id="activity-error-message">Intenta nuevamente.</p><button id="activity-retry" class="activity-button activity-button--primary" type="button">Reintentar</button></div></section>

            <div id="activity-content" hidden>
                <section class="panel-hello" aria-labelledby="activity-title">
                    <div>
                        <h1 id="activity-title">Hola<span id="panel-alias"></span></h1>
                        <p>Esto es lo que tienes activo en Ganado Cerca.</p>
                        <div class="panel-roles" id="panel-roles" aria-label="Tus actividades activas"></div>
                    </div>
                    <div class="panel-actions" id="panel-actions"></div>
                </section>
                <div id="welcome-banner" class="welcome-banner" hidden role="status" aria-live="polite">Registro completado. Ya puedes decidir qué hacer primero.</div>

                <section id="panel-no-activity" class="activity-panel panel-empty" hidden>
                    <strong>Todavía no tienes actividades activas</strong>
                    <p>Elige si quieres comprar, vender u ofrecer fletes para empezar.</p>
                    <a class="activity-button" href="ajustes#participacion">Elegir cómo participo</a>
                </section>

                <div class="panel-grid">
                    <div class="panel-col">
                        <section id="sol-recibidas-panel" class="activity-panel" aria-labelledby="sol-recibidas-title" hidden>
                            <div class="panel-head"><h2 id="sol-recibidas-title">Solicitudes recibidas<span class="panel-count" id="sol-recibidas-count"></span></h2></div>
                            <div id="sol-recibidas-list" class="panel-rows" aria-live="polite"></div>
                        </section>

                        <section id="sol-fletes-panel" class="activity-panel" aria-labelledby="sol-fletes-title" hidden>
                            <div class="panel-head"><h2 id="sol-fletes-title">Fletes que me piden<span class="panel-count" id="sol-fletes-count"></span></h2></div>
                            <div id="sol-fletes-list" class="panel-rows" aria-live="polite"></div>
                        </section>

                        <section id="sol-hechas-panel" class="activity-panel" aria-labelledby="sol-hechas-title" hidden>
                            <div class="panel-head" id="mis-solicitudes"><h2 id="sol-hechas-title">Mis solicitudes<span class="panel-count" id="sol-hechas-count"></span></h2></div>
                            <div id="sol-hechas-list" class="panel-rows" aria-live="polite"></div>
                        </section>

                        <section id="publications-panel" class="activity-panel" aria-labelledby="publications-title" hidden>
                            <div class="panel-head"><h2 id="publications-title">Mis publicaciones<span class="panel-count" id="publications-count"></span></h2></div>
                            <div id="publications-loading" class="resource-state" role="status" aria-live="polite">Cargando publicaciones…</div>
                            <div id="publications-error" class="resource-state resource-state--error" role="alert" hidden><p id="publications-error-message">No pudimos cargar tus publicaciones.</p><button id="publications-retry" class="activity-button" type="button">Reintentar</button></div>
                            <div id="publications-list" class="panel-rows" aria-live="polite"></div>
                            <div id="publications-empty" class="panel-empty" hidden></div>
                        </section>

                        <section id="animals-panel" class="activity-panel" aria-labelledby="animals-title" hidden>
                            <div class="panel-head"><h2 id="animals-title">Mis animales<span class="panel-count" id="animals-count"></span></h2><button id="animal-add" class="activity-button activity-button--sm" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i>Registrar animal</button></div>
                            <div id="animals-loading" class="resource-state" role="status" aria-live="polite">Cargando animales…</div>
                            <div id="animals-error" class="resource-state resource-state--error" role="alert" hidden><p id="animals-error-message">No pudimos cargar tus animales.</p><button id="animals-retry" class="activity-button" type="button">Reintentar</button></div>
                            <div id="animals-list" class="panel-rows" aria-live="polite"></div>
                            <p id="animals-empty" class="resource-empty" hidden>Registra aquí tus animales aunque no los vendas todavía: llevas su historial de vacunas y los publicas cuando quieras.</p>
                        </section>

                        <section id="farms-panel" class="activity-panel" aria-labelledby="farms-title" hidden>
                            <div class="panel-head"><h2 id="farms-title">Mis fincas<span class="panel-count" id="farms-count"></span></h2><button id="farm-add" class="activity-button activity-button--sm" type="button" hidden><i class="fa-solid fa-plus" aria-hidden="true"></i>Agregar finca</button></div>
                            <div id="farms-loading" class="resource-state" role="status" aria-live="polite">Cargando fincas…</div>
                            <div id="farms-error" class="resource-state resource-state--error" role="alert" hidden><p id="farms-error-message">No pudimos cargar tus fincas.</p><button id="farms-retry" class="activity-button" type="button">Reintentar</button></div>
                            <div id="farms-content" hidden><div id="farms-list" class="panel-rows" aria-live="polite"></div><p id="farms-empty" class="resource-empty" hidden>Aún no tienes fincas registradas.</p></div>
                        </section>
                    </div>

                    <div class="panel-col">
                        <section id="vehicles-panel" class="activity-panel" aria-labelledby="vehicles-title" hidden>
                            <div class="panel-head"><h2 id="vehicles-title">Mis vehículos<span class="panel-count" id="vehicles-count"></span></h2><button id="vehicle-add" class="activity-button activity-button--sm" type="button" hidden><i class="fa-solid fa-plus" aria-hidden="true"></i>Agregar</button></div>
                            <div id="vehicles-loading" class="resource-state" role="status" aria-live="polite">Cargando vehículos…</div>
                            <div id="vehicles-error" class="resource-state resource-state--error" role="alert" hidden><p id="vehicles-error-message">No pudimos cargar tus vehículos.</p><button id="vehicles-retry" class="activity-button" type="button">Reintentar</button></div>
                            <div id="vehicles-content" hidden>
                                <div id="vehicles-list" class="panel-rows" aria-live="polite"></div>
                                <div id="vehicles-empty" class="panel-todo" hidden>
                                    <p><strong>Te falta un paso para ofrecer fletes.</strong> Registra al menos un vehículo para aparecer en las búsquedas de transporte.</p>
                                    <button id="vehicle-add-empty" class="activity-button activity-button--sm" type="button">Agregar vehículo</button>
                                </div>
                            </div>
                        </section>

                        <section class="activity-panel panel-profile" aria-labelledby="profile-title">
                            <div class="panel-profile__identity">
                                <span class="panel-avatar" id="profile-avatar" aria-hidden="true"></span>
                                <div><h2 id="profile-title" class="panel-profile__name"></h2><p id="profile-alias"></p></div>
                            </div>
                            <a class="panel-link" href="ajustes#perfil">Ver perfil y ajustes <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                            <a class="panel-link" href="me-interesa">Mis publicaciones guardadas <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        </section>
                    </div>
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

    <dialog id="vehicle-modal" class="activity-dialog" aria-labelledby="vehicle-modal-title"><form id="vehicle-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Mi vehículo</p><h2 id="vehicle-modal-title">Agregar vehículo</h2></div><button id="vehicle-close" class="dialog-close" type="button" aria-label="Cerrar">×</button></div><p id="vehicle-form-help" class="fieldset-help">Completa los datos que usaremos para administrar tu oferta de transporte.</p><div class="vehicle-form-grid"><label class="field"><span>Placa</span><input id="vehicle-placa" name="placa" maxlength="20" autocomplete="off" required aria-describedby="vehicle-placa-error"><small id="vehicle-placa-error" class="field-error" data-vehicle-error="placa"></small></label><label class="field"><span>VIN</span><input id="vehicle-vin" name="vin" maxlength="50" autocomplete="off" required aria-describedby="vehicle-vin-error"><small id="vehicle-vin-error" class="field-error" data-vehicle-error="vin"></small></label><label class="field field--full"><span>Modelo</span><input id="vehicle-modelo" name="modelo" maxlength="100" autocomplete="off" required aria-describedby="vehicle-modelo-error"><small id="vehicle-modelo-error" class="field-error" data-vehicle-error="modelo"></small></label><div class="field field--full" data-foto-campo="vehicle"><span>Foto <span class="label">opcional</span></span><div class="publish-dropzone"><input class="screen-reader-only" id="vehicle-foto" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="vehicle-foto-ayuda"><label class="public-secondary publish-dropzone__button" for="vehicle-foto"><i class="fa-solid fa-upload" aria-hidden="true"></i><span>Elegir imagen</span></label><small id="vehicle-foto-ayuda" class="field-help">JPG, PNG o WebP, hasta 5 MB.</small></div><div class="publish-preview" data-foto-preview hidden><img alt="Vista previa de la foto"><button class="public-secondary" type="button" data-foto-quitar><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Quitar foto</span></button></div><small class="field-error" data-foto-error role="alert"></small></div></div><p id="vehicle-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button id="vehicle-cancel" class="activity-button" type="button">Cancelar</button><button id="vehicle-save" class="activity-button activity-button--primary" type="submit">Guardar vehículo</button></div></form></dialog>
    <dialog id="publication-modal" class="activity-dialog" aria-labelledby="publication-modal-title"><form id="publication-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Mi publicación</p><h2 id="publication-modal-title">Editar publicación</h2></div><button id="publication-close" class="dialog-close" type="button" aria-label="Cerrar">×</button></div><div class="vehicle-form-grid"><label class="field field--full"><span>Título</span><input id="publication-titulo" name="titulo" maxlength="150" autocomplete="off" required aria-describedby="publication-titulo-error"><small id="publication-titulo-error" class="field-error" data-publication-error="titulo"></small></label><label class="field"><span>Precio (₡) <span class="label">opcional</span></span><input id="publication-precio" name="precio" type="number" min="0" step="1" inputmode="numeric" aria-describedby="publication-precio-error"><small id="publication-precio-error" class="field-error" data-publication-error="precio"></small></label><label class="field field--full"><span>Descripción <span class="label">opcional</span></span><textarea id="publication-descripcion" name="descripcion" maxlength="500" rows="3" aria-describedby="publication-descripcion-error"></textarea><small id="publication-descripcion-error" class="field-error" data-publication-error="descripcion"></small></label><div class="field field--full" data-foto-campo="publication"><span>Foto <span class="label">opcional</span></span><div class="publish-dropzone"><input class="screen-reader-only" id="publication-foto" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="publication-foto-ayuda"><label class="public-secondary publish-dropzone__button" for="publication-foto"><i class="fa-solid fa-upload" aria-hidden="true"></i><span>Elegir imagen</span></label><small id="publication-foto-ayuda" class="field-help">JPG, PNG o WebP, hasta 5 MB.</small></div><div class="publish-preview" data-foto-preview hidden><img alt="Vista previa de la foto"><button class="public-secondary" type="button" data-foto-quitar><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Quitar foto</span></button></div><small class="field-error" data-foto-error role="alert"></small></div></div><p id="publication-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button id="publication-cancel" class="activity-button" type="button">Cancelar</button><button id="publication-save" class="activity-button activity-button--primary" type="submit">Guardar cambios</button></div></form></dialog>
    <dialog id="animal-modal" class="activity-dialog" aria-labelledby="animal-modal-title"><form id="animal-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Mis animales</p><h2 id="animal-modal-title">Registrar animal</h2></div><button class="dialog-close" type="button" aria-label="Cerrar" data-animal-cerrar>×</button></div><p class="fieldset-help">Queda en tu inventario sin publicarse. Todo es opcional.</p><div class="vehicle-form-grid"><label class="field"><span>Especie</span><select name="especieId"><option value="">Sin indicar</option></select><small class="field-error" data-animal-error="especieId"></small></label><label class="field"><span>Tipo de animal</span><select name="tipoId" disabled></select><small class="field-error" data-animal-error="tipoId"></small></label><label class="field"><span>Raza</span><select name="razaId" disabled></select><small class="field-error" data-animal-error="razaId"></small></label><label class="field"><span>Sexo</span><select name="sexo"><option value="">Sin indicar</option><option value="HEMBRA">Hembra</option><option value="MACHO">Macho</option></select><small class="field-error" data-animal-error="sexo"></small></label><label class="field field--full"><span>Arete SENASA</span><input name="arete" inputmode="numeric" maxlength="17" autocomplete="off" placeholder="188 0 01 0002345"><small class="field-error" data-animal-error="arete"></small></label><label class="field"><span>Fecha de nacimiento</span><input name="fechaNacimiento" type="date"><small class="field-error" data-animal-error="fechaNacimiento"></small></label><label class="field"><span><input name="fechaNacimientoEstimada" type="checkbox" disabled> Es aproximada</span></label><label class="field" data-animal-partos hidden><span>Partos</span><input name="partos" type="number" min="0" max="30" step="1" inputmode="numeric"><small class="field-error" data-animal-error="partos"></small></label><label class="field"><span>Peso (kg)</span><input name="peso" type="number" min="0" step="0.1" inputmode="decimal"><small class="field-error" data-animal-error="peso"></small></label><label class="field"><span>Edad (meses)</span><input name="edadMeses" type="number" min="0" step="1" inputmode="numeric"><small class="field-error" data-animal-error="edadMeses"></small></label><label class="field field--full"><span>Propósito</span><input name="proposito" maxlength="80" placeholder="Leche, carne, cría…"><small class="field-error" data-animal-error="proposito"></small></label></div><p id="animal-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button class="activity-button" type="button" data-animal-cerrar>Cancelar</button><button class="activity-button activity-button--primary" type="submit">Registrar animal</button></div></form></dialog>
    <dialog id="animal-publish-modal" class="activity-dialog" aria-labelledby="animal-publish-title"><form id="animal-publish-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker" id="animal-publish-name">Mis animales</p><h2 id="animal-publish-title">Publicar animal</h2></div><button class="dialog-close" type="button" aria-label="Cerrar" data-animal-cerrar>×</button></div><div class="vehicle-form-grid"><label class="field field--full"><span>Finca</span><select name="fincaNombre" required></select><small class="field-error" data-animal-error="fincaNombre"></small></label><label class="field field--full"><span>Título</span><input name="titulo" maxlength="150" autocomplete="off" required><small class="field-error" data-animal-error="titulo"></small></label><label class="field"><span>Precio (₡) <span class="label">opcional</span></span><input name="precio" type="number" min="0" step="1" inputmode="numeric"><small class="field-error" data-animal-error="precio"></small></label><label class="field field--full"><span>Descripción <span class="label">opcional</span></span><textarea name="descripcion" maxlength="500" rows="3"></textarea><small class="field-error" data-animal-error="descripcion"></small></label><div class="field field--full" data-foto-campo="animal-publish"><span>Foto <span class="label">opcional</span></span><div class="publish-dropzone"><input class="screen-reader-only" id="animal-publish-foto" type="file" accept="image/jpeg,image/png,image/webp"><label class="public-secondary publish-dropzone__button" for="animal-publish-foto"><i class="fa-solid fa-upload" aria-hidden="true"></i><span>Elegir imagen</span></label><small class="field-help">JPG, PNG o WebP, hasta 5 MB.</small></div><div class="publish-preview" data-foto-preview hidden><img alt="Vista previa de la foto"><button class="public-secondary" type="button" data-foto-quitar><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Quitar foto</span></button></div><small class="field-error" data-foto-error role="alert"></small></div></div><p id="animal-publish-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button class="activity-button" type="button" data-animal-cerrar>Cancelar</button><button class="activity-button activity-button--primary" type="submit">Publicar</button></div></form></dialog>
    <dialog id="animal-vaccines-modal" class="activity-dialog" aria-labelledby="animal-vaccines-title"><form id="animal-vaccine-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker" id="animal-vaccines-name">Mis animales</p><h2 id="animal-vaccines-title">Vacunas</h2></div><button class="dialog-close" type="button" aria-label="Cerrar" data-animal-cerrar>×</button></div><ul id="animal-vaccines-list" class="fieldset-help" aria-live="polite"></ul><div class="vehicle-form-grid"><label class="field"><span>Vacuna</span><select name="vacunaId" required><option value="">Elige la vacuna</option></select><small class="field-error" data-animal-error="vacunaId"></small></label><label class="field"><span>Fecha de aplicación</span><input name="fecha" type="date" required><small class="field-error" data-animal-error="fecha"></small></label><label class="field"><span>Dosis <span class="label">opcional</span></span><input name="dosis" maxlength="50"><small class="field-error" data-animal-error="dosis"></small></label><label class="field"><span>Próxima dosis <span class="label">opcional</span></span><input name="proximaDosis" type="date"><small class="field-error" data-animal-error="proximaDosis"></small></label></div><p id="animal-vaccine-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button class="activity-button" type="button" data-animal-cerrar>Cerrar</button><button class="activity-button activity-button--primary" type="submit">Agregar vacuna</button></div></form></dialog>
    <dialog id="farm-modal" class="activity-dialog activity-dialog--farm" aria-labelledby="farm-modal-title"><form id="farm-form" method="dialog" novalidate><div class="activity-dialog__header"><div><p class="section-kicker">Mi finca</p><h2 id="farm-modal-title">Agregar finca</h2></div><button id="farm-close" class="dialog-close" type="button" aria-label="Cerrar">×</button></div><p id="farm-form-help" class="fieldset-help">Agrega solo los datos de esta finca. La dirección y el punto exacto son opcionales.</p><div class="farm-form-grid"><label class="field field--full"><span>Nombre de finca</span><input id="farm-name" name="nombreFinca" maxlength="150" autocomplete="off" required aria-describedby="farm-name-error"><small id="farm-name-error" class="field-error" data-farm-error="nombreFinca"></small></label><details id="farm-address" class="farm-address-editor field--full"><summary>Dirección y punto exacto <span class="label">opcional</span></summary><div class="farm-address-editor__grid"><label class="field"><span>Provincia</span><select name="provincia" data-farm-province></select><small class="field-error" data-farm-error="provincia"></small></label><label class="field"><span>Cantón</span><select name="canton" data-farm-canton></select><small class="field-error" data-farm-error="canton"></small></label><label class="field"><span>Distrito</span><select name="distrito" data-farm-district disabled><option value="">Seleccione un distrito</option></select><small class="field-error" data-farm-error="distrito"></small></label><label class="field"><span>Pueblo</span><input name="pueblo" data-farm-town maxlength="150" list="farm-town-list" autocomplete="off" disabled><datalist id="farm-town-list" data-farm-town-list></datalist><small class="field-error" data-farm-error="pueblo"></small></label><label class="field field--full"><span>Señas</span><textarea name="senas" data-farm-directions maxlength="500" rows="2" aria-describedby="farm-directions-error"></textarea><small id="farm-directions-error" class="field-error" data-farm-error="senas"></small></label></div><div data-farm-map></div><small class="field-error" data-farm-error="direccionFinca"></small></details></div><p id="farm-form-status" class="auth-status" role="status" aria-live="polite"></p><div class="activity-dialog__actions"><button id="farm-deactivate" class="activity-button activity-button--text activity-dialog__danger" type="button" hidden>Desactivar finca</button><button id="farm-cancel" class="activity-button" type="button">Cancelar</button><button id="farm-save" class="activity-button activity-button--primary" type="submit">Guardar finca</button></div></form></dialog>
</body>
</html>
