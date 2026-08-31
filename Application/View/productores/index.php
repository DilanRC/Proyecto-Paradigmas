<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Administración de productores de TinderCows">
    <title>Productores | TinderCows</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/red-ganadera.css">
    <script src="js/productores.js" defer></script>
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
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="productores.php">Productores<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
                <a class="rural-panel__nav-item" href="transportistas.php">Transportistas</a>
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
                <div><span class="label">Registro de productores</span><h1 id="page-title">Productores</h1><p>Administre productores identificados por su número, contacto, dirección y fincas.</p></div>
                <button class="button button--primary" id="crear-productor" type="button"><span aria-hidden="true">＋</span>Crear productor</button>
            </section>

            <section class="panel" aria-label="Lista de productores" aria-busy="true" id="panel-productores">
                <div class="tools">
                    <label class="search"><span class="screen-reader-only">Buscar productor</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="busqueda-productor" type="search" autocomplete="off" placeholder="Buscar por nombre o identificación"></label>
                    <label class="filter"><span>Estado</span><select id="filtro-estado"><option value="TODOS">Todos</option><option value="ACTIVO">Activos</option><option value="INACTIVO">Inactivos</option></select></label>
                </div>
                <div class="list-summary">
                    <p id="total-productores" aria-live="polite">Cargando productores…</p>
                    <div class="pagination" aria-label="Paginación de productores">
                        <button class="link-button" id="pagina-anterior" type="button">Anterior</button>
                        <span id="pagina-actual" aria-live="polite">Página 1</span>
                        <button class="link-button" id="pagina-siguiente" type="button">Siguiente</button>
                        <button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button>
                    </div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Productor</th><th>Identificación</th><th>Contacto</th><th>Dirección principal</th><th>Fincas</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-productores"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No se encontraron productores</h2><p>Modifique la búsqueda o cree el primer productor.</p></div>
                    <div class="loading-state" id="estado-carga" aria-live="polite"><span class="loader" aria-hidden="true"></span>Cargando información…</div>
                </div>
            </section>

            <p class="rural-panel__footnote">Directorio de productores · Datos protegidos por TinderCows</p>
        </div>
    </main>

    <dialog class="modal" id="modal-productor" aria-labelledby="titulo-modal">
        <form id="formulario-productor" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label" id="subtitulo-modal">Nuevo registro</span><h2 id="titulo-modal">Crear productor</h2></div><button class="close-button" id="cerrar-modal" type="button" aria-label="Cerrar formulario">×</button></div>
            <div class="modal__content">
                <input type="hidden" id="identificacion-original" name="identificacionNumeroOriginal">
                <fieldset><legend>Identificación</legend><div class="form-grid">
                    <label class="field"><span>Tipo <b aria-hidden="true">*</b></span><select id="identificacion-tipo" name="identificacion.tipoCodigo" required aria-describedby="error-identificacion-tipo"><option value="">Seleccione un tipo</option></select><small class="field__error" id="error-identificacion-tipo" data-error-for="identificacion.tipoCodigo"></small></label>
                    <label class="field"><span>Número <b aria-hidden="true">*</b></span><input id="identificacion-numero" name="identificacion.numero" type="text" maxlength="250" autocomplete="off" required aria-describedby="error-identificacion-numero"><small class="field__error" id="error-identificacion-numero" data-error-for="identificacion.numero"></small></label>
                </div></fieldset>
                <fieldset><legend>Datos de contacto</legend><div class="form-grid">
                    <label class="field field--full"><span>Nombre completo o razón social <b aria-hidden="true">*</b></span><input id="nombre" name="nombre" type="text" minlength="3" maxlength="150" autocomplete="name" required aria-describedby="error-nombre"><small class="field__error" id="error-nombre" data-error-for="nombre"></small></label>
                    <label class="field"><span>Teléfono <b aria-hidden="true">*</b></span><input id="telefono" name="telefono" type="tel" maxlength="20" autocomplete="tel" placeholder="+506 8888 8888" required aria-describedby="error-telefono"><small class="field__error" id="error-telefono" data-error-for="telefono"></small></label>
                    <label class="field"><span>Correo electrónico <b aria-hidden="true">*</b></span><input id="correo-electronico" name="correoElectronico" type="email" maxlength="150" autocomplete="email" required aria-describedby="error-correo"><small class="field__error" id="error-correo" data-error-for="correoElectronico"></small></label>
                </div></fieldset>
                <fieldset><legend>Dirección principal</legend><div class="form-grid">
                    <label class="field"><span>Provincia <b aria-hidden="true">*</b></span><input id="direccion-provincia" name="direccionPrincipal.provincia" maxlength="100" required aria-describedby="error-direccion-provincia"><small class="field__error" id="error-direccion-provincia" data-error-for="direccionPrincipal.provincia"></small></label>
                    <label class="field"><span>Cantón <b aria-hidden="true">*</b></span><input id="direccion-canton" name="direccionPrincipal.canton" maxlength="100" required aria-describedby="error-direccion-canton"><small class="field__error" id="error-direccion-canton" data-error-for="direccionPrincipal.canton"></small></label>
                    <label class="field"><span>Distrito <b aria-hidden="true">*</b></span><input id="direccion-distrito" name="direccionPrincipal.distrito" maxlength="100" required aria-describedby="error-direccion-distrito"><small class="field__error" id="error-direccion-distrito" data-error-for="direccionPrincipal.distrito"></small></label>
                    <label class="field"><span>Pueblo</span><input id="direccion-pueblo" name="direccionPrincipal.pueblo" maxlength="150" aria-describedby="error-direccion-pueblo"><small class="field__error" id="error-direccion-pueblo" data-error-for="direccionPrincipal.pueblo"></small></label>
                    <label class="field field--full"><span>Señas</span><textarea id="direccion-senas" name="direccionPrincipal.senas" maxlength="500" rows="3" aria-describedby="error-direccion-senas"></textarea><small class="field__error" id="error-direccion-senas" data-error-for="direccionPrincipal.senas"></small></label>
                </div></fieldset>
                <fieldset><legend>Fincas del productor</legend><p class="fieldset-help" id="ayuda-fincas">Escriba una finca por línea. Puede dejar el campo vacío.</p><label class="field field--full"><span>Nombres de fincas</span><textarea id="fincas-nombres" name="fincas" maxlength="2000" rows="4" aria-describedby="ayuda-fincas error-fincas"></textarea><small class="field__error" id="error-fincas" data-error-for="fincas"></small></label></fieldset>
                <p class="form-note"><b aria-hidden="true">*</b> Campos obligatorios</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-formulario" type="button">Cancelar</button><button class="button button--reactivate" id="reactivar-existente" type="button" hidden>Reactivar registro existente</button><button class="button button--primary" id="guardar-productor" type="submit">Guardar productor</button></div>
        </form>
    </dialog>

    <dialog class="modal modal--confirmation" id="modal-desactivar" aria-labelledby="titulo-desactivar"><div class="confirmation__icon" aria-hidden="true">!</div><h2 id="titulo-desactivar">Desactivar productor</h2><p id="mensaje-desactivar">El productor dejará de estar activo, pero conservará su dirección, fincas y bitácora.</p><div class="modal__actions"><button class="button button--secondary" id="cancelar-desactivacion" type="button">Cancelar</button><button class="button button--danger" id="confirmar-desactivacion" type="button">Desactivar</button></div></dialog>

    <dialog class="modal" id="modal-detalle" aria-labelledby="titulo-detalle">
        <div class="modal__header"><div><span class="label">Ficha del productor</span><h2 id="titulo-detalle">Detalle</h2></div><button class="close-button" id="cerrar-detalle" type="button" aria-label="Cerrar detalle">×</button></div>
        <div class="modal__content"><dl class="detail-grid" id="detalle-contenido"></dl></div>
        <div class="modal__actions"><button class="button button--secondary" id="cerrar-detalle-secundario" type="button">Cerrar</button><button class="button button--primary" id="editar-desde-detalle" type="button">Editar</button></div>
    </dialog>

    <dialog class="modal" id="modal-direccion-finca" aria-labelledby="titulo-direccion-finca">
        <form id="formulario-direccion-finca" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label" id="subtitulo-direccion-finca">Finca</span><h2 id="titulo-direccion-finca">Dirección de la finca</h2></div><button class="close-button" id="cerrar-direccion-finca" type="button" aria-label="Cerrar">×</button></div>
            <div class="modal__content">
                <fieldset><legend>Dirección</legend><div class="form-grid">
                    <label class="field"><span>Provincia <b aria-hidden="true">*</b></span><input id="finca-direccion-provincia" name="direccionFinca.provincia" maxlength="100" required aria-describedby="error-finca-direccion-provincia"><small class="field__error" id="error-finca-direccion-provincia" data-error-for="direccionFinca.provincia"></small></label>
                    <label class="field"><span>Cantón <b aria-hidden="true">*</b></span><input id="finca-direccion-canton" name="direccionFinca.canton" maxlength="100" required aria-describedby="error-finca-direccion-canton"><small class="field__error" id="error-finca-direccion-canton" data-error-for="direccionFinca.canton"></small></label>
                    <label class="field"><span>Distrito <b aria-hidden="true">*</b></span><input id="finca-direccion-distrito" name="direccionFinca.distrito" maxlength="100" required aria-describedby="error-finca-direccion-distrito"><small class="field__error" id="error-finca-direccion-distrito" data-error-for="direccionFinca.distrito"></small></label>
                    <label class="field"><span>Pueblo</span><input id="finca-direccion-pueblo" name="direccionFinca.pueblo" maxlength="150" aria-describedby="error-finca-direccion-pueblo"><small class="field__error" id="error-finca-direccion-pueblo" data-error-for="direccionFinca.pueblo"></small></label>
                    <label class="field field--full"><span>Señas</span><textarea id="finca-direccion-senas" name="direccionFinca.senas" maxlength="500" rows="3" aria-describedby="error-finca-direccion-senas"></textarea><small class="field__error" id="error-finca-direccion-senas" data-error-for="direccionFinca.senas"></small></label>
                </div></fieldset>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-direccion-finca" type="button">Cancelar</button><button class="button button--danger" id="vaciar-direccion-finca" type="button" hidden>Vaciar dirección</button><button class="button button--primary" id="guardar-direccion-finca" type="submit">Guardar dirección</button></div>
        </form>
    </dialog>
    <div class="notification" id="notificacion" role="status" aria-live="polite" hidden></div>
</body>
</html>
