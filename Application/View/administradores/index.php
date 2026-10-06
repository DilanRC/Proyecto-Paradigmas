<!DOCTYPE html>
<html lang="es">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Administradores del panel de TinderCows">
    <title>Administradores | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/administradores.js?v=administradores-2"></script>
</head>
<body class="rural-panel">
    <aside class="rural-panel__sidebar">
        <div class="rural-panel__sidebar-brand">
            <span class="brand__logo brand__logo--light" aria-hidden="true"><img src="assets/logo_light.png" alt="" width="44" height="44"></span>
            <span class="rural-panel__sidebar-brand-name">Tinder<strong>Cows</strong></span>
        </div>
        <nav class="rural-panel__nav" aria-label="Administración">
            <p class="rural-panel__nav-label">Administración</p>
            <div class="rural-panel__nav-list">
                <a class="rural-panel__nav-item" href="admin/dashboard">Dashboard</a>
                <a class="rural-panel__nav-item" href="admin/productores">Productores</a>
                <a class="rural-panel__nav-item" href="admin/compradores">Compradores</a>
                <a class="rural-panel__nav-item" href="admin/transportistas">Transportistas</a>
                <a class="rural-panel__nav-item" href="admin/vehiculos">Vehículos</a>
                <a class="rural-panel__nav-item" href="admin/publicaciones">Publicaciones</a>
                <a class="rural-panel__nav-item" href="admin/metodos-pago">Métodos de pago</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="admin/administradores">Administradores<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Landing</a><a class="rural-panel__admin-link" href="entrar">Login</a></div>

            <section class="page-header" aria-labelledby="page-title">
                <div><span class="label">Seguridad</span><h1 id="page-title">Administradores</h1><p>Decida qué correos pueden entrar al panel de administración.</p></div>
                <button class="button button--primary" id="agregar-administrador" type="button"><span aria-hidden="true">＋</span>Agregar administrador</button>
            </section>

            <section class="panel" aria-label="Lista de administradores" aria-busy="true" id="panel-administradores">
                <div class="list-summary">
                    <p id="total-administradores" aria-live="polite">Cargando administradores…</p>
                    <div class="pagination"><button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button></div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Correo</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-administradores"></tbody></table>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar los administradores</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Seguridad · Nadie puede quitarse su propio acceso ni dejar el panel sin administradores · Cada cambio queda en la bitácora</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-administrador" aria-labelledby="titulo-administrador">
        <form id="formulario-administrador" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label">Nuevo acceso</span><h2 id="titulo-administrador">Agregar administrador</h2></div><button class="close-button" id="cerrar-administrador" type="button" aria-label="Cerrar formulario">×</button></div>
            <div class="modal__content">
                <fieldset><legend>Cuenta</legend><div class="form-grid">
                    <label class="field field--full"><span>Correo electrónico <b aria-hidden="true">*</b></span><input id="correoElectronico" name="correoElectronico" type="email" maxlength="150" autocomplete="off" required aria-describedby="error-correoElectronico"><small class="field__error" id="error-correoElectronico" data-error-for="correoElectronico"></small></label>
                </div></fieldset>
                <p class="form-note"><b aria-hidden="true">*</b> Campo obligatorio. Entrará al panel con este correo en su cuenta de Ganado Cerca.</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-administrador" type="button">Cancelar</button><button class="button button--primary" id="guardar-administrador" type="submit">Agregar</button></div>
        </form>
    </dialog>
    <dialog class="modal modal--confirmation" role="dialog" aria-modal="true" id="modal-desactivar" aria-labelledby="titulo-desactivar"><div class="confirmation__icon" aria-hidden="true">!</div><h2 id="titulo-desactivar">Desactivar administrador</h2><p id="mensaje-desactivar"></p><div class="modal__actions"><button class="button button--secondary" id="cancelar-desactivacion" type="button">Cancelar</button><button class="button button--danger" id="confirmar-desactivacion" type="button">Desactivar</button></div></dialog>
    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
