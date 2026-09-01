<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows: plataforma para administrar productores, compradores, transportistas, vehículos y pagos de la red ganadera">
    <title>TinderCows — Red ganadera oficial</title>
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
    <header class="landing-header">
        <div class="rural__brand">
            <span class="brand__logo brand__logo--light" aria-hidden="true"><img src="assets/logo_light.png" alt=""></span>
            <span class="rural__brand-name">Tinder<strong>Cows</strong></span>
        </div>
        <nav class="landing-nav" aria-label="Principal">
            <a href="#proyecto">Proyecto</a>
            <a href="#uso">Cómo usarlo</a>
            <a href="#red">Red activa</a>
            <a class="landing-nav__login" href="login.php">Iniciar sesión</a>
        </nav>
    </header>

    <main class="landing">
        <section class="landing-hero" aria-labelledby="landing-title">
            <div class="landing-hero__copy">
                <p class="label">Sistema ganadero académico</p>
                <h1 id="landing-title">TinderCows</h1>
                <p class="landing-hero__lead">Una aplicación web para registrar Productores, consultar Compradores derivados, administrar Transportistas, asignar vehículos y mantener Métodos de pago de una red ganadera.</p>
                <div class="landing-actions">
                    <a class="button button--primary" href="login.php">Entrar al sistema</a>
                    <a class="button button--secondary" href="#red">Ver red activa</a>
                </div>
            </div>
            <div class="landing-hero__product" aria-label="Módulos principales">
                <span class="brand__logo brand__logo--dark"><img src="assets/logo_dark.png" alt="TinderCows"></span>
                <dl class="landing-metrics">
                    <div><dt>Productores</dt><dd>Registro, contacto, dirección y fincas</dd></div>
                    <div><dt>Compradores</dt><dd>Clasificación histórica de solo lectura</dd></div>
                    <div><dt>Transporte</dt><dd>Transportistas y vehículos asignables</dd></div>
                </dl>
            </div>
        </section>

        <section class="landing-section" id="proyecto" aria-labelledby="proyecto-title">
            <div>
                <p class="label">Qué resuelve</p>
                <h2 id="proyecto-title">Gestión centralizada de la red</h2>
            </div>
            <div class="landing-feature-grid">
                <article>
                    <h3>Identidad única</h3>
                    <p>Productores y transportistas se administran por identificación de negocio. La misma persona puede participar en más de una capacidad sin duplicar su identidad.</p>
                </article>
                <article>
                    <h3>Operación diaria</h3>
                    <p>Los paneles permiten crear, editar, consultar, desactivar y reactivar registros, con búsqueda, filtros, paginación y mensajes de error distinguibles.</p>
                </article>
                <article>
                    <h3>Trazabilidad</h3>
                    <p>Las operaciones de escritura quedan preparadas para bitácora y actor autenticado cuando el sistema recibe un JWT válido de Supabase.</p>
                </article>
            </div>
        </section>

        <section class="landing-section landing-section--steps" id="uso" aria-labelledby="uso-title">
            <div>
                <p class="label">Flujo de uso</p>
                <h2 id="uso-title">Cómo usar TinderCows</h2>
            </div>
            <ol class="landing-steps">
                <li><strong>Inicie sesión</strong><span>Use la entrada del sistema para abrir el panel administrativo.</span></li>
                <li><strong>Registre productores</strong><span>Complete identificación, contacto, dirección principal y fincas.</span></li>
                <li><strong>Revise compradores</strong><span>Consulte productores clasificados como compradores sin crear clasificaciones manuales.</span></li>
                <li><strong>Coordine transporte</strong><span>Administre transportistas, vehículos y asignaciones activas.</span></li>
                <li><strong>Configure pagos</strong><span>Mantenga disponibles los métodos de pago usados por la operación.</span></li>
            </ol>
        </section>

        <section class="landing-deck" id="red" aria-labelledby="red-title">
            <aside class="rural__aside">
                <h2 id="red-title">Red activa</h2>
                <p class="rural__hint">Recorra los productores activos que devuelve la API. Guardar en esta vista es temporal y sólo vive en esta pestaña.</p>

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
            </aside>

            <div class="rural__main">
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
            </div>
        </section>
    </main>

    <div class="toast-region">
        <div class="toast" id="toast-status" role="status" aria-live="polite"></div>
        <div class="toast" id="toast-alert" role="alert" aria-live="assertive"></div>
    </div>
</body>
</html>
