<!DOCTYPE html>
<html lang="es">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Catálogos del animal en TinderCows">
    <title>Catálogos | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/catalogos.js?v=catalogos-1"></script>
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
                <a class="rural-panel__nav-item" href="admin/fletes">Fletes</a>
                <a class="rural-panel__nav-item" href="admin/documentos">Documentos</a>
                <a class="rural-panel__nav-item" href="admin/metodos-pago">Métodos de pago</a>
                <a class="rural-panel__nav-item rural-panel__nav-item--active" href="admin/catalogos">Catálogos<span class="rural-panel__nav-dot" aria-hidden="true"></span></a>
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
                <div><span class="label">Configuración</span><h1 id="page-title">Catálogos del animal</h1><p>Especies, tipos, razas y vacunas que aparecen en los formularios de publicar y de Mis animales.</p></div>
                <button class="button button--primary" id="crear-registro" type="button"><span aria-hidden="true">＋</span>Agregar</button>
            </section>

            <section class="panel" aria-label="Lista del catálogo" aria-busy="true" id="panel-catalogos">
                <div class="tools">
                    <label class="filter"><span>Catálogo</span><select id="filtro-catalogo"><option value="ESPECIE">Especies</option><option value="TIPO">Tipos de animal</option><option value="RAZA">Razas</option><option value="VACUNA">Vacunas</option></select></label>
                    <label class="filter"><span>Estado</span><select id="filtro-estado"><option value="TODOS">Todos</option><option value="ACTIVO">Activos</option><option value="INACTIVO">Inactivos</option></select></label>
                </div>
                <div class="list-summary">
                    <p id="total-catalogo" aria-live="polite">Cargando catálogos…</p>
                    <div class="pagination"><button class="link-button" id="actualizar-lista" type="button">Actualizar lista</button></div>
                </div>
                <div class="table-container">
                    <table><thead><tr><th>Nombre</th><th id="columna-especie">Especie</th><th id="columna-sexo">Sexo</th><th>Estado</th><th><span class="screen-reader-only">Acciones</span></th></tr></thead><tbody id="cuerpo-catalogo"></tbody></table>
                    <div class="empty-state" id="estado-vacio" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No hay registros</h2><p>Cambie el filtro o agregue el primero.</p></div>
                    <div class="error-state" id="estado-error" hidden><span class="error-state__icon" aria-hidden="true">!</span><h2>No fue posible cargar los catálogos</h2><p id="mensaje-error"></p><button class="button button--secondary" id="reintentar" type="button">Reintentar</button></div>
                    <div class="skeleton" id="estado-carga" aria-hidden="true">
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                        <div class="skeleton__row"></div>
                    </div>
                </div>
            </section>

            <p class="rural-panel__footnote">Configuración · Nada se borra: desactivar un registro lo quita de los formularios sin tocar los animales ya guardados · Cada cambio queda en la bitácora</p>
        </div>
    </main>

    <dialog class="modal" role="dialog" aria-modal="true" id="modal-registro" aria-labelledby="titulo-registro">
        <form id="formulario-registro" novalidate aria-busy="false">
            <div class="modal__header"><div><span class="label" id="subtitulo-registro">Nuevo registro</span><h2 id="titulo-registro">Agregar</h2></div><button class="close-button" id="cerrar-registro" type="button" aria-label="Cerrar formulario">×</button></div>
            <div class="modal__content">
                <fieldset><legend>Datos</legend><div class="form-grid">
                    <label class="field field--full"><span>Nombre <b aria-hidden="true">*</b></span><input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="off" required aria-describedby="error-nombre"><small class="field__error" id="error-nombre" data-error-for="nombre"></small></label>
                    <label class="field" id="campo-especie"><span>Especie <b aria-hidden="true">*</b></span><select id="especieId" name="especieId" aria-describedby="error-especieId"></select><small class="field__error" id="error-especieId" data-error-for="especieId"></small></label>
                    <label class="field" id="campo-sexo"><span>Sexo</span><select id="sexo" name="sexo" aria-describedby="error-sexo"><option value="">Cualquiera</option><option value="H">Hembra</option><option value="M">Macho</option></select><small class="field__error" id="error-sexo" data-error-for="sexo"></small></label>
                </div></fieldset>
                <p class="form-note" id="nota-registro"><b aria-hidden="true">*</b> Campos obligatorios. La especie y el sexo no se cambian después.</p>
            </div>
            <div class="modal__actions"><button class="button button--secondary" id="cancelar-registro" type="button">Cancelar</button><button class="button button--primary" id="guardar-registro" type="submit">Guardar</button></div>
        </form>
    </dialog>
    <dialog class="modal modal--confirmation" role="dialog" aria-modal="true" id="modal-desactivar" aria-labelledby="titulo-desactivar"><div class="confirmation__icon" aria-hidden="true">!</div><h2 id="titulo-desactivar">Desactivar registro</h2><p id="mensaje-desactivar"></p><div class="modal__actions"><button class="button button--secondary" id="cancelar-desactivacion" type="button">Cancelar</button><button class="button button--danger" id="confirmar-desactivacion" type="button">Desactivar</button></div></dialog>
    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
