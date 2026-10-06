<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Compra y vende ganado cerca de ti. Ganado Cerca conecta ganaderos, compradores y transportistas.">
    <meta name="theme-color" content="#151a18">
    <title>Ganado Cerca — Compra y vende ganado cerca de ti</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-9">
    <link rel="stylesheet" href="css/explore.css?v=explore-9">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-14"></script>
    <script type="module" src="js/home.js?v=home-7"></script>
</head>
<body class="public-home" data-portada>
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="#inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Ganado<strong>Cerca</strong></span>
            </a>

            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a class="is-active" href="#inicio"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            </nav>

            <div class="public-header__actions">
                <form class="public-search" action="explorar" method="get" role="search" data-public-search data-open="false">
                    <button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Buscar">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span>
                    </button>
                    <div class="public-search__field">
                        <label class="screen-reader-only" for="busqueda-publica-home">Buscar publicaciones</label>
                        <input id="busqueda-publica-home" name="q" type="search" autocomplete="off" placeholder="Ganado, zona…">
                        <button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    </div>
                </form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true">
                    <i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i>
                    <span class="theme-toggle__label">Claro</span>
                </button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main>
            <section class="public-hero public-hero--clean public-hero--landing" aria-labelledby="public-title">
                <div class="public-hero__copy">
                    <p class="public-eyebrow">Ganaderos · Compradores · Transportistas</p>
                    <h1 id="public-title">Compra y vende ganado cerca de ti.</h1>
                    <p class="public-hero__lead">El mercado ganadero de tu zona: publica tus animales, encuentra lo que buscas y coordina el flete con transportistas cercanos.</p>
                    <div class="public-hero__signals" aria-label="Funciones principales">
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Cerca de ti</span>
                        <span><i class="fa-solid fa-message" aria-hidden="true"></i> Contacto directo</span>
                        <span><i class="fa-solid fa-truck" aria-hidden="true"></i> Fletes en la zona</span>
                    </div>
                </div>

                <div class="public-visual public-visual--hero-photo">
                    <img class="public-hero-photo" src="assets/hero-ganado-cerca.png" alt="Ganado recorriendo un camino empedrado entre potreros de Costa Rica">
                </div>

                <form class="hero-search" action="explorar" method="get" role="search" aria-labelledby="hero-search-title">
                    <h2 id="hero-search-title">¿Qué ganado buscas?</h2>
                    <div class="hero-search__field">
                        <label for="hero-search-ubicacion">Ubicación</label>
                        <input id="hero-search-ubicacion" name="ubicacion" type="text" autocomplete="address-level2" placeholder="Provincia, cantón o pueblo">
                    </div>
                    <div class="hero-search__field">
                        <label for="hero-search-tipo">Tipo o raza de ganado</label>
                        <input id="hero-search-tipo" name="tipo" type="text" autocomplete="off" placeholder="Engorde, leche, Brahman…">
                    </div>
                    <button class="public-cta" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button>
                    <a class="text-link" href="explorar">Ver todas las publicaciones</a>
                </form>
            </section>

            <section class="public-section featured" id="destacadas" aria-labelledby="featured-title" data-featured>
                <div class="section-heading section-heading--split">
                    <div><p class="section-kicker">Publicaciones destacadas</p><h2 id="featured-title">Ganado publicado cerca de ti</h2></div>
                    <a class="public-secondary" href="explorar" data-featured-more hidden><span>Ver todas</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                </div>
                <p class="featured__loading" data-featured-loading><i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> Cargando publicaciones…</p>
                <div class="public-carousel featured-carousel" data-featured-carousel role="region" aria-roledescription="carrusel" aria-label="Publicaciones destacadas" hidden>
                    <div class="public-carousel__viewport">
                        <button class="public-carousel__arrow public-carousel__arrow--prev" type="button" data-carousel-prev aria-label="Publicaciones anteriores"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                        <div class="public-carousel__track" data-carousel-track tabindex="0" aria-label="Publicaciones; usa las flechas para moverte"></div>
                        <button class="public-carousel__arrow public-carousel__arrow--next" type="button" data-carousel-next aria-label="Publicaciones siguientes"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                    </div>
                    <div class="public-carousel__footer"><p data-carousel-status></p><div class="public-carousel__dots" data-carousel-dots role="group" aria-label="Posiciones del carrusel"></div></div>
                </div>
                <div class="featured__empty" data-featured-empty hidden>
                    <i class="fa-solid fa-cow" aria-hidden="true"></i>
                    <h3>Pronto verás aquí ganado cerca de ti</h3>
                    <p>Las publicaciones de tu zona aparecerán en esta sección.</p>
                    <a class="public-cta" href="registro" data-featured-empty-action><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>Crear cuenta para publicar</span></a>
                </div>
            </section>


            <section class="public-section how-section" id="como-funciona" aria-labelledby="how-title">
                <div class="section-heading">
                    <p class="section-kicker">Cómo funciona</p>
                    <h2 id="how-title">Del potrero al trato en tres pasos.</h2>
                </div>
                <ol class="use-steps use-steps--compact">
                    <li><span>01</span><div><strong>Publica o busca</strong><p>Publica tus animales o busca por zona, tipo y precio.</p></div></li>
                    <li><span>02</span><div><strong>Contacta y negocia</strong><p>Habla directo con el vendedor o comprador y acuerden condiciones.</p></div></li>
                    <li><span>03</span><div><strong>Cierra el trato y coordina el flete</strong><p>Confirma la compra y elige un transportista cercano para el traslado.</p></div></li>
                </ol>
            </section>
        </main>

        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand">
                <a class="public-brand public-brand--footer" href="#inicio">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40">
                    </span>
                    <span>Ganado<strong>Cerca</strong></span>
                </a>
                <p>Descubre ganado y oportunidades cerca de ti.</p>
            </div>
            <div class="public-footer__links">
                <div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar">Explorar</a><a href="sobre-nosotros">Nosotros</a><a href="#como-funciona">Cómo funciona</a></div>
                <div><strong>Cuenta</strong><a href="entrar">Entrar</a><a href="como-usar">Ayuda de uso</a><a href="sobre-nosotros">Sobre Ganado Cerca</a></div>
                <div class="public-footer__legal"><strong>Legal</strong><a href="privacidad">Privacidad</a><a href="terminos">Términos</a><a href="legal">Información legal</a></div>
            </div>
        </footer>
    </div>
</body>
</html>
