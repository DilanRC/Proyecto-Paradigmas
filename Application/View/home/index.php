<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows: red ganadera de productores activos">
    <title>TinderCows — Red ganadera</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/panel.css">
    <link rel="stylesheet" href="css/red-ganadera.css">
    <script type="module" src="js/home.js"></script>
</head>
<body class="rural">
    <div class="rural__admin">
        <a class="rural__admin-toggle" href="productores.php">Admin</a>
    </div>

    <aside class="rural__aside">
        <div class="rural__brand">
            <span class="brand__icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M13 12 6 7c-1 7 2 10 7 11m22-6 7-5c1 7-2 10-7 11"/><path d="M11 24c0-10 5-16 13-16s13 6 13 16v7c0 7-5 11-13 11S11 38 11 31Z"/><path d="M16 29c0-4 3-6 8-6s8 2 8 6-3 7-8 7-8-3-8-7Z"/><circle cx="18" cy="20" r="2"/><circle cx="30" cy="20" r="2"/><circle cx="21" cy="29" r="1.5"/><circle cx="27" cy="29" r="1.5"/></svg></span>
            <span class="rural__brand-name">Tinder<strong>Cows</strong></span>
        </div>

        <h3 class="rural__label">Sesión actual</h3>
        <dl class="rural__counters">
            <div class="rural__counter">
                <dt>Guardados</dt>
                <dd id="contador-guardados">0</dd>
            </div>
            <div class="rural__counter">
                <dt>Revisados</dt>
                <dd id="contador-revisados">0</dd>
            </div>
        </dl>
        <p class="rural__counters-note">
            Los contadores viven solo en esta pestaña. No existe todavía un
            endpoint para registrar el interés de una persona, así que no se
            inventa una persistencia que el sistema no tiene.
        </p>

        <div class="rural__messages">
            <h3 class="rural__label">Cómo funciona</h3>
            <p class="rural__hint">
                Se recorren los productores <strong>activos</strong> en el orden
                que devuelve la API. Ese orden es una decisión del backend: esta
                vista solamente lo representa.
            </p>
        </div>
    </aside>

    <main class="rural__main">
        <article class="rural__card" id="estado-carga" aria-hidden="true">
            <div class="rural__media rural__media--placeholder"></div>
            <div class="rural__body">
                <div class="skeleton">
                    <div class="skeleton__row"></div>
                    <div class="skeleton__row"></div>
                    <div class="skeleton__row"></div>
                </div>
            </div>
        </article>

        <article class="rural__card" id="tarjeta-productor" hidden>
            <div class="rural__media rural__media--placeholder">
                <span class="rural__media-initials" id="productor-iniciales" aria-hidden="true"></span>
                <div class="rural__media-badges">
                    <span class="rural__badge" id="productor-estado">Activo</span>
                </div>
            </div>

            <div class="rural__body">
                <div class="rural__heading">
                    <div>
                        <h1 id="productor-nombre">—</h1>
                        <p class="rural__sub-identity">
                            <span id="productor-finca">Sin fincas</span>
                            <span aria-hidden="true">•</span>
                            <span id="productor-ubicacion">Sin dirección</span>
                        </p>
                    </div>
                    <div class="rural__rating">
                        <p id="productor-posicion">—</p>
                    </div>
                </div>

                <dl class="detail-grid">
                    <dt>Identificación</dt><dd id="productor-identificacion">—</dd>
                    <dt>Teléfono</dt><dd id="productor-telefono">—</dd>
                    <dt>Correo electrónico</dt><dd id="productor-correo">—</dd>
                    <dt>Fincas</dt><dd id="productor-fincas" class="detail--full">—</dd>
                </dl>
            </div>
        </article>

        <div class="empty-state" id="estado-vacio" hidden>
            <span class="empty-state__icon" aria-hidden="true">♧</span>
            <h2>No hay productores activos</h2>
            <p>Cuando se registre un productor activo aparecerá aquí.</p>
            <a class="button button--primary" href="productores.php">Ir a administración</a>
        </div>

        <div class="error-state" id="estado-error" hidden>
            <span class="error-state__icon" aria-hidden="true">!</span>
            <h2>No fue posible cargar la red ganadera</h2>
            <p id="mensaje-error"></p>
            <button class="button button--secondary" id="reintentar" type="button">Reintentar</button>
        </div>

        <div class="rural__dock" id="acciones" hidden>
            <button class="rural__dock-btn" id="accion-pasar" type="button" aria-label="Pasar al siguiente productor">✕</button>
            <button class="rural__dock-btn" id="accion-guardar" type="button" aria-label="Guardar en esta sesión">♥</button>
            <a class="rural__dock-btn rural__dock-btn--primary" id="accion-contactar" href="productores.php">Ver en administración</a>
        </div>
    </main>

    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
