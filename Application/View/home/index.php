<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows te ayuda a descubrir ganado, subastas y oportunidades cerca de ti.">
    <meta name="theme-color" content="#151a18">
    <title>TinderCows — Ganado y oportunidades cerca de ti</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/public-product.css?v=product-1">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
    <script type="module" src="js/public-ui.js?v=public-1"></script>
</head>
<body class="public-home">
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="#inicio" aria-label="TinderCows, inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>

            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a class="is-active" href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
                <a href="#nosotros"><i class="fa-solid fa-people-group" aria-hidden="true"></i><span>Nosotros</span></a>
                <a href="#como-funciona"><i class="fa-solid fa-route" aria-hidden="true"></i><span>Cómo funciona</span></a>
            </nav>

            <div class="public-header__actions">
                <form class="public-search" action="explorar.php" method="get" role="search" data-public-search data-open="false">
                    <button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Abrir búsqueda">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span>
                    </button>
                    <div class="public-search__field">
                        <label class="screen-reader-only" for="busqueda-publica-home">Buscar publicaciones</label>
                        <input id="busqueda-publica-home" name="q" type="search" autocomplete="off" placeholder="Ganado, subastas, zona…">
                        <button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    </div>
                </form>
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
                    <p class="public-eyebrow">Ganado · Subastas · Cercanía</p>
                    <h1 id="public-title">El ganado que buscas, más cerca de ti.</h1>
                    <p class="public-hero__lead">Explora publicaciones, descubre subastas cercanas, guarda lo que te interesa y conecta directamente con quienes están detrás de cada oportunidad.</p>
                    <div class="public-hero__actions">
                        <a class="public-cta" href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar publicaciones</span></a>
                        <a class="public-secondary" href="#como-funciona"><i class="fa-solid fa-circle-play" aria-hidden="true"></i><span>Cómo funciona</span></a>
                    </div>
                    <div class="public-hero__signals" aria-label="Funciones principales">
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Cerca de ti</span>
                        <span><i class="fa-solid fa-gavel" aria-hidden="true"></i> Subastas</span>
                        <span><i class="fa-solid fa-message" aria-hidden="true"></i> Contacto directo</span>
                    </div>
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

            <section class="public-section public-section--about" id="nosotros" aria-labelledby="about-title">
                <div class="section-heading">
                    <p class="section-kicker">Nosotros</p>
                    <h2 id="about-title">TinderCows convierte descubrir ganado en una experiencia simple y cercana.</h2>
                </div>
                <p class="section-lead">Reunimos publicaciones, ubicación, subastas y contacto en un solo recorrido para que comparar opciones y tomar una decisión requiera menos pasos.</p>
                <div class="about-grid about-grid--product">
                    <article><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i><h3>Descubre por cercanía</h3><p>Prioriza oportunidades relevantes por zona y encuentra ganado o subastas que realmente puedas considerar.</p></article>
                    <article><i class="fa-solid fa-layer-group" aria-hidden="true"></i><h3>Decide con contexto</h3><p>Compara publicaciones desde tarjetas claras, guarda favoritas y pasa de largo cuando una opción no encaja contigo.</p></article>
                    <article><i class="fa-solid fa-handshake" aria-hidden="true"></i><h3>Conecta y negocia</h3><p>Contacta a la persona responsable de una publicación o participa en una puja cuando la oportunidad lo permita.</p></article>
                </div>
            </section>

            <section class="public-section how-section" id="como-funciona" aria-labelledby="how-title">
                <div class="section-heading">
                    <p class="section-kicker">Cómo funciona</p>
                    <h2 id="how-title">Explora, decide y conecta sin perder el contexto.</h2>
                </div>
                <ol class="use-steps use-steps--compact">
                    <li><span>01</span><div><strong>Explora</strong><p>Desliza publicaciones de ganado y subastas, usa búsqueda cuando la necesites y filtra por lo que te interesa.</p></div></li>
                    <li><span>02</span><div><strong>Decide</strong><p>Marca una publicación, descártala o abre sus detalles sin salir del flujo de exploración.</p></div></li>
                    <li><span>03</span><div><strong>Conecta</strong><p>Contacta al publicador o participa en una puja cuando la publicación tenga una subasta activa.</p></div></li>
                </ol>
            </section>

            <section class="public-section public-section--cta" aria-labelledby="cta-title">
                <div>
                    <p class="section-kicker">Empieza a explorar</p>
                    <h2 id="cta-title">Tu próxima oportunidad puede estar a pocos kilómetros.</h2>
                </div>
                <a class="public-cta" href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Ir a Explorar</span></a>
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
                <p>Descubre ganado y oportunidades cerca de ti.</p>
            </div>
            <div class="public-footer__links">
                <div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar.php">Explorar</a><a href="#nosotros">Nosotros</a><a href="#como-funciona">Cómo funciona</a></div>
                <div><strong>Cuenta</strong><a href="login.php">Entrar</a><a href="como-usar.php">Ayuda de uso</a><a href="sobre-nosotros.php">Sobre TinderCows</a></div>
                <div class="public-footer__legal"><strong>Legal</strong><a href="privacidad.php">Privacidad</a><a href="terminos.php">Términos</a><a href="legal.php">Información legal</a></div>
            </div>
        </footer>
    </div>
</body>
</html>
