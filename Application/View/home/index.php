<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows — proyecto académico para la gestión de una red ganadera.">
    <meta name="theme-color" content="#151a18">
    <title>TinderCows — Red ganadera</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <script type="module" src="js/public-theme.js?v=brand-2"></script>
</head>
<body class="public-home">
    <div class="public-shell">
        <header class="public-header">
            <a class="public-brand" href="./" aria-label="TinderCows, inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>

            <nav class="public-nav" aria-label="Navegación pública">
                <a href="#sobre">Sobre nosotros</a>
                <a href="#uso">Cómo usarlo</a>
                <a href="#modulos">Módulos</a>
                <a href="privacidad.php">Privacidad</a>
            </nav>

            <div class="public-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar tema" aria-pressed="true">
                    <span class="theme-toggle__icon" aria-hidden="true">◐</span>
                    <span class="theme-toggle__label">Claro</span>
                </button>
                <a class="public-header__login" href="login.php">Entrar al panel</a>
            </div>
        </header>

        <main>
            <section class="public-hero" aria-labelledby="public-title">
                <div class="public-hero__copy">
                    <p class="public-eyebrow">EIF400 · Proyecto académico</p>
                    <h1 id="public-title">La red ganadera, en un solo lugar.</h1>
                    <p class="public-hero__lead">TinderCows organiza productores, compradores derivados, transporte, vehículos y métodos de pago con una identidad visual propia de la operación ganadera.</p>
                    <div class="public-hero__actions">
                        <a class="public-cta" href="login.php">Entrar al panel de demostración</a>
                        <a class="public-secondary" href="#uso">Ver cómo funciona</a>
                    </div>
                    <p class="public-demo-note"><strong>Estado actual:</strong> el acceso es una sesión local de demostración. No existe todavía autenticación de backend.</p>
                </div>

                <div class="public-visual" aria-label="Vista conceptual de TinderCows">
                    <div class="public-logo-stage" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="420" height="420">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="420" height="420">
                    </div>
                    <article class="module-chip module-chip--producer"><span>Productores</span><strong>Identidad + fincas</strong></article>
                    <article class="module-chip module-chip--transport"><span>Transporte</span><strong>Vehículos + asignaciones</strong></article>
                    <article class="module-chip module-chip--buyer"><span>Compradores</span><strong>Clasificación derivada</strong></article>
                </div>
            </section>

            <section class="public-section public-section--about" id="sobre" aria-labelledby="about-title">
                <div class="section-heading">
                    <p class="section-kicker">Sobre nosotros</p>
                    <h2 id="about-title">Un proyecto centrado en procesos reales, no en roles artificiales.</h2>
                </div>
                <div class="about-grid">
                    <p>TinderCows es un proyecto académico de EIF400 orientado a modelar una red ganadera. La aplicación separa la identidad de las personas de los hechos que ocurren en el tiempo: actividad, direcciones, clasificaciones y relaciones operativas.</p>
                    <p>El objetivo del frontend es hacer visibles esos procesos sin ocultar su estado: búsquedas, filtros, carga, errores, recuperación y operaciones administrativas deben poder comprenderse y comprobarse.</p>
                </div>
                <a class="text-link" href="sobre-nosotros.php">Conocer el proyecto completo →</a>
            </section>

            <section class="public-section" id="uso" aria-labelledby="use-title">
                <div class="section-heading">
                    <p class="section-kicker">Cómo usarlo</p>
                    <h2 id="use-title">El recorrido actual es simple y explícito.</h2>
                </div>
                <ol class="use-steps">
                    <li><span>01</span><div><strong>Conozca el proyecto</strong><p>Revise esta portada, los módulos y las condiciones del entorno académico.</p></div></li>
                    <li><span>02</span><div><strong>Abra el acceso demo</strong><p>El formulario valida formato en el navegador, pero todavía no autentica contra un backend.</p></div></li>
                    <li><span>03</span><div><strong>Entre a administración</strong><p>Después de continuar se abre Productores, desde donde puede navegar por los CRUD disponibles.</p></div></li>
                    <li><span>04</span><div><strong>Pruebe los flujos</strong><p>Use búsqueda, filtros, creación, edición, desactivación, reactivación y consulta según el módulo.</p></div></li>
                </ol>
                <a class="text-link" href="como-usar.php">Ver guía y rutas →</a>
            </section>

            <section class="public-section" id="modulos" aria-labelledby="modules-title">
                <div class="section-heading">
                    <p class="section-kicker">Módulos</p>
                    <h2 id="modules-title">Una administración dividida por responsabilidad.</h2>
                </div>
                <div class="module-grid">
                    <article><span class="module-number">01</span><h3>Productores</h3><p>Identificación, contacto, dirección principal y fincas.</p><a href="login.php?next=productores.php">Abrir vía acceso demo</a></article>
                    <article><span class="module-number">02</span><h3>Compradores</h3><p>Consulta de la clasificación derivada del comportamiento del productor.</p><a href="login.php?next=compradores.php">Abrir vía acceso demo</a></article>
                    <article><span class="module-number">03</span><h3>Transportistas</h3><p>Personas dedicadas al transporte y sus relaciones operativas.</p><a href="login.php?next=transportistas.php">Abrir vía acceso demo</a></article>
                    <article><span class="module-number">04</span><h3>Vehículos</h3><p>Unidades disponibles y asignables a transportistas.</p><a href="login.php?next=vehiculos.php">Abrir vía acceso demo</a></article>
                    <article><span class="module-number">05</span><h3>Métodos de pago</h3><p>Configuración de opciones disponibles para la operación.</p><a href="login.php?next=pagometodos.php">Abrir vía acceso demo</a></article>
                </div>
            </section>

            <section class="public-section public-section--transparency" aria-labelledby="transparency-title">
                <div>
                    <p class="section-kicker">Transparencia del prototipo</p>
                    <h2 id="transparency-title">La interfaz no debe fingir capacidades que todavía no existen.</h2>
                </div>
                <p>El login actual crea una sesión de navegador y redirige al panel. Eso sirve para recorrer el frontend, pero no sustituye autenticación, autorización ni control de acceso de servidor. Las páginas legales describen el estado actual y separan lo implementado de lo pendiente.</p>
            </section>
        </main>

        <footer class="public-footer">
            <div class="public-footer__brand">
                <a class="public-brand public-brand--footer" href="./">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40">
                    </span>
                    <span>Tinder<strong>Cows</strong></span>
                </a>
                <p>Proyecto académico EIF400 · 2026.</p>
            </div>
            <div class="public-footer__links">
                <div><strong>Proyecto</strong><a href="sobre-nosotros.php">Sobre nosotros</a><a href="como-usar.php">Cómo usar</a><a href="login.php">Acceso demo</a></div>
                <div><strong>Información</strong><a href="privacidad.php">Privacidad</a><a href="terminos.php">Términos</a><a href="legal.php">Legal</a></div>
            </div>
        </footer>
    </div>
</body>
</html>
