<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Administración de transportistas de TinderCows">
    <title>Transportistas | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/red-ganadera.css">
    <script type="module" src="js/transportistas.js"></script>
</head>
<body class="rural-panel">
    <aside class="rural-panel__sidebar">
        <div class="rural-panel__sidebar-brand">
            <span class="brand__icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M13 12 6 7c-1 7 2 10 7 11m22-6 7-5c1 7-2 10-7 11"/><path d="M11 24c0-10 5-16 13-16s13 6 13 16v7c0 7-5 11-13 11S11 38 11 31Z"/><path d="M16 29c0-4 3-6 8-6s8 2 8 6-3 7-8 7-8-3-8-7Z"/><circle cx="18" cy="20" r="2"/><circle cx="30" cy="20" r="2"/><circle cx="21" cy="29" r="1.5"/><circle cx="27" cy="29" r="1.5"/></svg></span>
            <span class="rural-panel__sidebar-brand-name">Tinder<strong>Cows</strong></span>
        </div>
        <nav class="rural-panel__nav" aria-label="Administración">
            <p class="rural-panel__nav-label">Administración</p>
            <div class="rural-panel__nav-list">
                <a class="rural-panel__nav-item" href="productores.php">Productores</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="transportistas.php">Transportistas<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="vehiculos.php">Vehículos</a>
                <a class="rural-panel__nav-item" href="pagometodos.php">Métodos de pago</a>
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
            <div class="rural-panel__admin-row"><a class="rural-panel__admin-link" href="./">Regresar</a></div>

            <section class="page-header" aria-labelledby="page-title">
                <div><span class="label">Registro de transportistas</span><h1 id="page-title">Transportistas</h1><p>Administre transportistas identificados por su número, contacto y vehículos asignados.</p></div>
                <button class="button button--primary" id="crear-transportista" type="button"><span aria-hidden="true">＋</span>Crear transportista</button>
            </section>

            <section class="panel" aria-label="Lista de transportistas" aria-busy="true" id="panel-transportistas">
                <div class="tools">
                    <label class="search"><span class="screen-reader-only">Buscar transportista</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="busqueda-transportista" type="search" autocomplete="off" placeholder="Buscar por nombre o identificación"></label>
                    <label class="filter"><span>Estado</span><select id="filtro-estado"><option value="TODOS">Todos</option><option value="ACTIVO">Activos</option><option value="INACTIVO">Inactivos</option></select></label>
                </div>
                <div class="list-summary">
                    <p id="total-transportistas" aria-live="polite">Cargando transportistas…</p>
                    <div class="pagination" aria-label="Paginación de transportistas">
                        <button class="link-button" id="pagina-anterior" type="button">Anterior</button>
                        <span id="pagina-actual" aria-live="polite">Página 1</span>
                        <button class="link-button" id="pagina-siguiente" type="button">Siguiente</button>
                        <button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button>
                    </div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Transportista</th><th>Identificación</th><th>Contacto</th><th>Vehículos</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-transportistas"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No se encontraron transportistas</h2><p>Modifique la búsqueda o cree el primero.</p></div>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar los transportistas</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Directorio de transportistas · Datos protegidos por TinderCows</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-transportista" aria-labelledby="titulo-modal">
        <form id="formulario-transportista" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label" id="subtitulo-modal">Nuevo registro</span><h2 id="titulo-modal">Crear transportista</h2></div><button class="close-button" id="cerrar-modal" type="button" aria-label="Cerrar formulario">×</button></div>
            <div class="modal__content">
                <input type="hidden" id="identificacion-original" name="identificacionNumeroOriginal">
                <fieldset><legend>Identificación</legend><div class="form-grid">
                    <label class="field"><span>Tipo <b aria-hidden="true">*</b></span><select id="identificacion-tipo" name="identificacion.tipoCodigo" required aria-describedby="error-identificacion-tipo"><option value="">Seleccione un tipo</option></select><small class="field__error" id="error-identificacion-tipo" data-error-for="identificacion.tipoCodigo"></small></label>
                    <label class="field"><span>Número <b aria-hidden="true">*</b></span><input id="identificacion-numero" name="identificacion.numero" type="text" maxlength="250" autocomplete="off" required aria-describedby="ayuda-identificacion-numero error-identificacion-numero"><small class="field__hint" id="ayuda-identificacion-numero"></small><small class="field__error" id="error-identificacion-numero" data-error-for="identificacion.numero"></small></label>
                </div></fieldset>
                <fieldset><legend>Datos de contacto</legend><div class="form-grid">
                    <label class="field field--full"><span>Nombre completo o razón social <b aria-hidden="true">*</b></span><input id="nombre" name="nombre" type="text" minlength="3" maxlength="150" autocomplete="name" required aria-describedby="error-nombre"><small class="field__error" id="error-nombre" data-error-for="nombre"></small></label>
                    <label class="field"><span>Teléfono <b aria-hidden="true">*</b></span><input id="telefono" name="telefono" type="tel" maxlength="20" autocomplete="tel" placeholder="+506 8888 8888" required aria-describedby="error-telefono"><small class="field__error" id="error-telefono" data-error-for="telefono"></small></label>
                    <label class="field"><span>Correo electrónico <b aria-hidden="true">*</b></span><input id="correo-electronico" name="correoElectronico" type="email" maxlength="150" autocomplete="email" required aria-describedby="error-correo"><small class="field__error" id="error-correo" data-error-for="correoElectronico"></small></label>
                </div></fieldset>
                <p class="form-note"><b aria-hidden="true">*</b> Campos obligatorios</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-formulario" type="button">Cancelar</button><button class="button button--reactivate" id="reactivar-existente" type="button" hidden>Reactivar registro existente</button><button class="button button--primary" id="guardar-transportista" type="submit">Guardar transportista</button></div>
        </form>
    </dialog>

    <dialog class="modal modal--confirmation" role="dialog" aria-modal="true" id="modal-desactivar" aria-labelledby="titulo-desactivar"><div class="confirmation__icon" aria-hidden="true">!</div><h2 id="titulo-desactivar">Desactivar transportista</h2><p id="mensaje-desactivar">El transportista dejará de estar activo, pero conservará sus vehículos asignados y su bitácora.</p><div class="modal__actions"><button class="button button--secondary" id="cancelar-desactivacion" type="button">Cancelar</button><button class="button button--danger" id="confirmar-desactivacion" type="button">Desactivar</button></div></dialog>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-detalle" aria-labelledby="titulo-detalle">
        <div class="modal__header"><div><span class="label">Ficha del transportista</span><h2 id="titulo-detalle">Detalle</h2></div><button class="close-button" id="cerrar-detalle" type="button" aria-label="Cerrar detalle">×</button></div>
        <div class="modal__content">
            <dl class="detail-grid" id="detalle-contenido"></dl>
            <button class="button button--secondary" id="abrir-asignar-vehiculo" type="button">+ Asignar vehículo</button>
        </div>
        <div class="modal__actions"><button class="button button--secondary" id="cerrar-detalle-secundario" type="button">Cerrar</button><button class="button button--primary" id="editar-desde-detalle" type="button">Editar</button></div>
    </dialog>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-asignar-vehiculo" aria-labelledby="titulo-asignar">
        <form id="formulario-asignar-vehiculo" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label">Vehículos</span><h2 id="titulo-asignar">Asignar vehículo</h2></div><button class="close-button" id="cerrar-asignar" type="button" aria-label="Cerrar">×</button></div>
            <div class="modal__content">
                <label class="field field--full"><span>Vehículo activo <b aria-hidden="true">*</b></span><select id="asignar-vehiculo-select" name="vehiculoId" required aria-describedby="error-asignar-vehiculo"><option value="">Cargando vehículos…</option></select><small class="field__error" id="error-asignar-vehiculo" data-error-for="vehiculoId"></small></label>
                <p class="form-note">Si el vehículo ya está asignado a otro transportista, se moverá a este.</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-asignacion" type="button">Cancelar</button><button class="button button--primary" id="confirmar-asignacion" type="submit">Asignar</button></div>
        </form>
    </dialog>
    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
