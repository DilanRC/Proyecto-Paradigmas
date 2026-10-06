<!DOCTYPE html>
<html lang="es">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Verificación de documentos de identidad de TinderCows">
    <title>Documentos | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/documentos.js?v=documentos-1"></script>
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
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="admin/documentos">Documentos<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="admin/metodos-pago">Métodos de pago</a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Landing</a><a class="rural-panel__admin-link" href="entrar">Login</a></div>

            <section class="page-header" aria-labelledby="page-title">
                <div><span class="label">Verificación</span><h1 id="page-title">Documentos de identidad</h1><p>Revise el documento que subió cada persona y verifíquelo o recházelo con un motivo.</p></div>
            </section>

            <section class="panel" aria-label="Documentos por revisar" aria-busy="true" id="panel-documentos">
                <div class="tools">
                    <label class="search"><span class="screen-reader-only">Buscar persona</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="busqueda-documento" type="search" maxlength="150" autocomplete="off" placeholder="Buscar por nombre, identificación o correo"></label>
                    <label class="filter"><span>Estado</span><select id="filtro-estado"><option value="PENDIENTE">Pendientes</option><option value="VERIFICADO">Verificados</option><option value="RECHAZADO">Rechazados</option><option value="TODOS">Todos</option></select></label>
                </div>
                <div class="list-summary">
                    <p id="total-documentos" aria-live="polite">Cargando documentos…</p>
                    <div class="pagination" aria-label="Paginación de documentos">
                        <button class="link-button" id="pagina-anterior" type="button">Anterior</button>
                        <span id="pagina-actual" aria-live="polite">Página 1</span>
                        <button class="link-button" id="pagina-siguiente" type="button">Siguiente</button>
                        <button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button>
                    </div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Persona</th><th>Identificación</th><th>Correo</th><th>Enviado</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-documentos"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No hay documentos en este estado</h2><p>Cambie el estado o la búsqueda.</p></div>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar los documentos</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Verificación · El documento se abre con un enlace que caduca en minutos · Cada acceso y cada decisión quedan en la bitácora</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-rechazar" aria-labelledby="titulo-rechazar">
        <form id="formulario-rechazar" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label">Verificación</span><h2 id="titulo-rechazar">Rechazar documento</h2></div><button class="close-button" id="cerrar-rechazar" type="button" aria-label="Cerrar formulario">×</button></div>
            <div class="modal__content">
                <p id="mensaje-rechazar"></p>
                <fieldset><legend>Motivo</legend><div class="form-grid">
                    <label class="field field--full"><span>Motivo <b aria-hidden="true">*</b></span><textarea id="motivo" name="motivo" maxlength="250" rows="3" required aria-describedby="error-motivo"></textarea><small class="field__error" id="error-motivo" data-error-for="motivo"></small></label>
                </div></fieldset>
                <p class="form-note"><b aria-hidden="true">*</b> Campo obligatorio. La persona lo verá en Ajustes para saber qué corregir; no incluya datos sensibles.</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-rechazar" type="button">Cancelar</button><button class="button button--danger" id="confirmar-rechazar" type="submit">Rechazar</button></div>
        </form>
    </dialog>
    <dialog class="modal modal--confirmation" role="dialog" aria-modal="true" id="modal-verificar" aria-labelledby="titulo-verificar"><div class="confirmation__icon" aria-hidden="true">✓</div><h2 id="titulo-verificar">Verificar documento</h2><p id="mensaje-verificar"></p><div class="modal__actions"><button class="button button--secondary" id="cancelar-verificar" type="button">Cancelar</button><button class="button button--primary" id="confirmar-verificar" type="button">Verificar</button></div></dialog>
    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
