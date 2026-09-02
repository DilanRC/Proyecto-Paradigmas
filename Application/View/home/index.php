<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows — experiencia ganadera académica para explorar y administrar una red en un solo lugar.">
    <meta name="theme-color" content="#151a18">
    <title>TinderCows — Red ganadera</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-3">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <link rel="stylesheet" href="css/public-v3.css?v=public-3">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
</head>
<body class="public-home">
    <div class="public-shell" id="inicio">
        <header class="public-header">
            <a class="public-brand" href="#inicio" aria-label="TinderCows, inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>

            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="#buscar-ganado"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar ganado</span></a>
                <a href="#nosotros"><i class="fa-solid fa-people-group" aria-hidden="true"></i><span>Nosotros</span></a>
                <a href="#como-funciona"><i class="fa-solid fa-route" aria-hidden="true"></i><span>Cómo funciona</span></a>
            </nav>

            <div class="public-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true">
                    <i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i>
                    <span class="theme-toggle__label">Claro</span>
                </button>
                <a class="public-header__login" href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main>
            <section class="public-hero public-hero--clean" aria-labelledby="public-title">
                <div class="public-hero__copy">
                    <p class="public-eyebrow">EIF400 · Proyecto académico</p>
                    <h1 id="public-title">Encuentra. Conecta. Gestiona.</h1>
                    <p class="public-hero__lead">Una experiencia digital para recorrer la red ganadera con información clara, acciones predecibles y una identidad construida alrededor de TinderCows.</p>
                    <div class="public-hero__actions">
                        <a class="public-cta" href="#buscar-ganado"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Explorar búsqueda</span></a>
                        <a class="public-secondary" href="#como-funciona"><i class="fa-solid fa-circle-play" aria-hidden="true"></i><span>Cómo funciona</span></a>
                    </div>
                    <p class="public-demo-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>El acceso administrativo actual es una demostración local mientras se integra autenticación de servidor.</span></p>
                </div>

                <div class="public-visual public-visual--brand" aria-label="Identidad visual de TinderCows">
                    <div class="public-logo-stage" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="430" height="430">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="430" height="430">
                    </div>
                    <div class="brand-orbit brand-orbit--one" aria-hidden="true"></div>
                    <div class="brand-orbit brand-orbit--two" aria-hidden="true"></div>
                </div>
            </section>

            <section class="public-section cattle-search-section" id="buscar-ganado" aria-labelledby="cattle-search-title">
                <div class="section-heading section-heading--split">
                    <div>
                        <p class="section-kicker">Buscar ganado</p>
                        <h2 id="cattle-search-title">Una búsqueda que se entiende antes de usarla.</h2>
                    </div>
                    <span class="preview-badge"><i class="fa-solid fa-eye" aria-hidden="true"></i> Vista previa</span>
                </div>

                <div class="cattle-search-preview" aria-label="Vista previa de búsqueda de ganado">
                    <div class="cattle-search-preview__toolbar">
                        <label class="cattle-search-preview__search">
                            <span class="screen-reader-only">Buscar ganado</span>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" value="" placeholder="Buscar ganado por los datos disponibles…" readonly aria-describedby="cattle-preview-note">
                        </label>
                        <button class="preview-filter-button" type="button" disabled><i class="fa-solid fa-sliders" aria-hidden="true"></i><span>Filtros</span></button>
                    </div>
                    <div class="cattle-search-preview__body">
                        <div class="cattle-search-preview__visual" aria-hidden="true">
                            <i class="fa-solid fa-cow"></i>
                        </div>
                        <div>
                            <strong>El patrón visual está listo; los datos todavía no.</strong>
                            <p id="cattle-preview-note">El repositorio actual no expone aún una entidad ni un endpoint de ganado. Por eso esta sección no inventa animales, razas ni resultados: muestra cómo será la experiencia cuando ese contrato exista.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="public-section public-section--about" id="nosotros" aria-labelledby="about-title">
                <div class="section-heading">
                    <p class="section-kicker">Nosotros</p>
                    <h2 id="about-title">Tecnología pensada para que la operación se sienta más simple.</h2>
                </div>
                <div class="about-grid about-grid--product">
                    <article><i class="fa-solid fa-seedling" aria-hidden="true"></i><h3>Contexto ganadero</h3><p>La interfaz usa el lenguaje y la identidad visual de TinderCows para que las acciones se reconozcan sin depender de memorizar rutas o símbolos aislados.</p></article>
                    <article><i class="fa-solid fa-compass" aria-hidden="true"></i><h3>Orientación clara</h3><p>Navegación visible, estados explícitos y opciones consistentes reducen dudas y ayudan a recuperar el flujo cuando algo falla.</p></article>
                    <article><i class="fa-solid fa-code-branch" aria-hidden="true"></i><h3>Proyecto académico</h3><p>EIF400 combina PHP MVC, MySQL y comunicación AJAX/JSON. El diseño acompaña esa arquitectura en vez de esconderla detrás de una maqueta.</p></article>
                </div>
            </section>

            <section class="public-section how-section" id="como-funciona" aria-labelledby="how-title">
                <div class="section-heading">
                    <p class="section-kicker">Cómo funciona</p>
                    <h2 id="how-title">Tres pasos, sin navegación escondida.</h2>
                </div>
                <ol class="use-steps use-steps--compact">
                    <li><span>01</span><div><strong>Explora</strong><p>Recorre la portada y la vista previa de búsqueda para entender el producto antes de entrar.</p></div></li>
                    <li><span>02</span><div><strong>Accede</strong><p>Usa el acceso de demostración. Mientras no exista autenticación backend, la interfaz lo indica de forma visible.</p></div></li>
                    <li><span>03</span><div><strong>Gestiona</strong><p>En administración, cada listado conserva búsqueda, filtros, estados, acciones y recuperación ante errores dentro del mismo lenguaje visual.</p></div></li>
                </ol>
            </section>

            <section class="public-section public-section--cta" aria-labelledby="cta-title">
                <div>
                    <p class="section-kicker">TinderCows</p>
                    <h2 id="cta-title">¿Listo para entrar al entorno de demostración?</h2>
                </div>
                <a class="public-cta" href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar al panel</span></a>
            </section>
        </main>

        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand">
                <a class="public-brand public-brand--footer" href="#inicio">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40">
                    </span>
                    <span>Tinder<strong>Cows</strong></span>
                </a>
                <p>Proyecto académico EIF400 · 2026.</p>
            </div>
            <div class="public-footer__links">
                <div><strong>Explorar</strong><a href="#inicio">Inicio</a><a href="#buscar-ganado">Buscar ganado</a><a href="#nosotros">Nosotros</a><a href="#como-funciona">Cómo funciona</a></div>
                <div><strong>Acceso</strong><a href="login.php">Entrar al panel</a><a href="sobre-nosotros.php">Sobre el proyecto</a><a href="como-usar.php">Ayuda de uso</a></div>
                <div class="public-footer__legal"><strong>Legal</strong><a href="privacidad.php">Privacidad</a><a href="terminos.php">Términos</a><a href="legal.php">Información legal</a></div>
            </div>
        </footer>
    </div>
</body>
</html>
