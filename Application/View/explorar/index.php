<?php

declare(strict_types=1);

$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explora ganado, subastas y oportunidades cercanas en TinderCows.">
    <meta name="theme-color" content="#151a18">
    <title>Explorar | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/public-product.css?v=product-1">
    <link rel="stylesheet" href="css/explore.css?v=explore-1">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
    <script type="module" src="js/public-ui.js?v=public-1"></script>
    <script type="module" src="js/explore.js?v=explore-1"></script>
</head>
<body class="public-home explore-page">
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="./" aria-label="TinderCows, inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>

            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a class="is-active" href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
                <a href="./#nosotros"><i class="fa-solid fa-people-group" aria-hidden="true"></i><span>Nosotros</span></a>
                <a href="./#como-funciona"><i class="fa-solid fa-route" aria-hidden="true"></i><span>Cómo funciona</span></a>
            </nav>

            <div class="public-header__actions">
                <form class="public-search" action="explorar.php" method="get" role="search" data-public-search data-open="<?= $query !== '' ? 'true' : 'false' ?>">
                    <button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="<?= $query !== '' ? 'true' : 'false' ?>" aria-label="Abrir búsqueda">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span>
                    </button>
                    <div class="public-search__field">
                        <label class="screen-reader-only" for="busqueda-publica-explorar">Buscar publicaciones</label>
                        <input id="busqueda-publica-explorar" name="q" type="search" autocomplete="off" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ganado, subastas, zona…" data-explore-search>
                        <button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    </div>
                </form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true">
                    <i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span>
                </button>
                <a class="public-header__login" href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="explore-main">
            <section class="explore-heading" aria-labelledby="explore-title">
                <div>
                    <p class="section-kicker">Explorar</p>
                    <h1 id="explore-title">Oportunidades para descubrir, comparar y decidir.</h1>
                    <p>Desliza entre publicaciones de ganado y subastas, guarda lo que te interesa y abre contacto o puja cuando corresponda.</p>
                </div>
                <div class="explore-heading__controls" aria-label="Filtros rápidos">
                    <button class="explore-chip is-active" type="button" data-explore-filter="all"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span>Todo</span></button>
                    <button class="explore-chip" type="button" data-explore-filter="ganado"><i class="fa-solid fa-cow" aria-hidden="true"></i><span>Ganado</span></button>
                    <button class="explore-chip" type="button" data-explore-filter="subasta"><i class="fa-solid fa-gavel" aria-hidden="true"></i><span>Subastas</span></button>
                    <button class="explore-chip" type="button" data-explore-filter="cerca"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Cerca de mí</span></button>
                </div>
            </section>

            <p class="explore-sample-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Las tarjetas usan contenido de muestra mientras se conecta el catálogo real de publicaciones.</span></p>

            <section class="explore-deck" aria-label="Publicaciones para explorar">
                <div class="explore-deck__viewport" data-explore-deck tabindex="0">
                    <article class="explore-card" data-type="ganado cerca" data-searchable="novillas engorde san carlos alajuela ganado cerca brahman engorde finca la esperanza mariela solano aguas zarcas 950000 colones">
                        <div class="explore-card__visual explore-card__visual--green" aria-hidden="true"><i class="fa-solid fa-cow"></i></div>
                        <div class="explore-card__body">
                            <div class="explore-card__meta"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Aguas Zarcas, San Carlos, Alajuela</span><span>8 km</span></div>
                            <span class="explore-card__type">Ganado · Muestra</span>
                            <h2>Novillas de engorde</h2>
                            <p class="explore-card__price"><strong>₡950 000</strong><span>por animal · 6 disponibles</span></p>
                            <p>Una publicación pensada para revisar rápidamente ubicación, categoría y acciones antes de abrir el detalle.</p>
                            <dl class="explore-card__specs">
                                <div><dt><i class="fa-solid fa-dna" aria-hidden="true"></i> Raza</dt><dd>Brahman</dd></div>
                                <div><dt><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Edad</dt><dd>18 meses</dd></div>
                                <div><dt><i class="fa-solid fa-weight-scale" aria-hidden="true"></i> Peso</dt><dd>320 kg</dd></div>
                                <div><dt><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Propósito</dt><dd>Engorde</dd></div>
                            </dl>
                            <p class="explore-card__seller"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span>Finca La Esperanza · Mariela Solano</span></p>
                            <div class="explore-card__tags"><span>Disponibles</span><span>Cercano</span><span>Contacto directo</span></div>
                            <div class="explore-card__actions">
                                <button type="button" data-explore-action="Pasar"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Pasar</span></button>
                                <button type="button" data-explore-action="Me interesa"><i class="fa-solid fa-heart" aria-hidden="true"></i><span>Me interesa</span></button>
                                <button type="button" data-explore-action="Contactar"><i class="fa-solid fa-message" aria-hidden="true"></i><span>Contactar</span></button>
                            </div>
                        </div>
                    </article>

                    <article class="explore-card" data-type="subasta cerca" data-searchable="subasta ganado grecia alajuela puja cerca jersey doble proposito finca el alto carlos venegas san roque 1200000 colones">
                        <div class="explore-card__visual explore-card__visual--orange" aria-hidden="true"><i class="fa-solid fa-gavel"></i></div>
                        <div class="explore-card__body">
                            <div class="explore-card__meta"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> San Roque, Grecia, Alajuela</span><span>12 km</span></div>
                            <span class="explore-card__type">Subasta · Muestra</span>
                            <h2>Subasta de ganado</h2>
                            <p class="explore-card__price"><strong>₡1 200 000</strong><span>puja actual · cierra hoy 6:00 p. m.</span></p>
                            <p>La tarjeta destaca que existe una puja activa y mantiene contacto, interés y descarte dentro del mismo flujo.</p>
                            <dl class="explore-card__specs">
                                <div><dt><i class="fa-solid fa-dna" aria-hidden="true"></i> Raza</dt><dd>Jersey</dd></div>
                                <div><dt><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Edad</dt><dd>36 meses</dd></div>
                                <div><dt><i class="fa-solid fa-weight-scale" aria-hidden="true"></i> Peso</dt><dd>410 kg</dd></div>
                                <div><dt><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Propósito</dt><dd>Doble propósito</dd></div>
                            </dl>
                            <p class="explore-card__seller"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span>Finca El Alto · Carlos Venegas</span></p>
                            <div class="explore-card__tags"><span>Puja activa</span><span>Cercano</span><span>Hoy</span></div>
                            <div class="explore-card__actions">
                                <button type="button" data-explore-action="Pasar"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Pasar</span></button>
                                <button type="button" data-explore-action="Me interesa"><i class="fa-solid fa-heart" aria-hidden="true"></i><span>Me interesa</span></button>
                                <button type="button" data-explore-action="Contactar"><i class="fa-solid fa-message" aria-hidden="true"></i><span>Contactar</span></button>
                                <button class="is-primary" type="button" data-explore-action="Pujar"><i class="fa-solid fa-gavel" aria-hidden="true"></i><span>Pujar</span></button>
                            </div>
                        </div>
                    </article>

                    <article class="explore-card" data-type="ganado" data-searchable="toro reproductor tilaran guanacaste ganado gyr cria finca los robles jose quiros quebrada grande 2450000 colones">
                        <div class="explore-card__visual explore-card__visual--cream" aria-hidden="true"><i class="fa-solid fa-cow"></i></div>
                        <div class="explore-card__body">
                            <div class="explore-card__meta"><span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Quebrada Grande, Tilarán, Guanacaste</span><span>16 km</span></div>
                            <span class="explore-card__type">Ganado · Muestra</span>
                            <h2>Toro reproductor</h2>
                            <p class="explore-card__price"><strong>₡2 450 000</strong><span>precio único · 1 disponible</span></p>
                            <p>El formato permite decidir si seguir explorando, guardar la publicación o iniciar una conversación con el publicador.</p>
                            <dl class="explore-card__specs">
                                <div><dt><i class="fa-solid fa-dna" aria-hidden="true"></i> Raza</dt><dd>Gyr</dd></div>
                                <div><dt><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Edad</dt><dd>48 meses</dd></div>
                                <div><dt><i class="fa-solid fa-weight-scale" aria-hidden="true"></i> Peso</dt><dd>780 kg</dd></div>
                                <div><dt><i class="fa-solid fa-bullseye" aria-hidden="true"></i> Propósito</dt><dd>Cría</dd></div>
                            </dl>
                            <p class="explore-card__seller"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span>Finca Los Robles · José Quirós</span></p>
                            <div class="explore-card__tags"><span>Disponible</span><span>Reproductor</span><span>Contacto</span></div>
                            <div class="explore-card__actions">
                                <button type="button" data-explore-action="Pasar"><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Pasar</span></button>
                                <button type="button" data-explore-action="Me interesa"><i class="fa-solid fa-heart" aria-hidden="true"></i><span>Me interesa</span></button>
                                <button type="button" data-explore-action="Contactar"><i class="fa-solid fa-message" aria-hidden="true"></i><span>Contactar</span></button>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="explore-deck__navigation">
                    <button type="button" data-explore-prev><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Anterior</span></button>
                    <span data-explore-position aria-live="polite">1 de 3</span>
                    <button type="button" data-explore-next><span>Siguiente</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>

                <div class="explore-empty" data-explore-empty hidden>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <h2>No encontramos coincidencias en esta muestra.</h2>
                    <p>Prueba otra búsqueda o vuelve a mostrar todas las publicaciones.</p>
                    <button type="button" data-explore-reset><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Restablecer</span></button>
                </div>
            </section>
        </main>

        <div class="explore-toast" role="status" aria-live="polite" data-explore-toast hidden></div>

        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand">
                <a class="public-brand public-brand--footer" href="./">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40">
                    </span>
                    <span>Tinder<strong>Cows</strong></span>
                </a>
                <p>Descubre ganado y oportunidades cerca de ti.</p>
            </div>
            <div class="public-footer__links">
                <div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar.php">Explorar</a><a href="./#nosotros">Nosotros</a><a href="./#como-funciona">Cómo funciona</a></div>
                <div><strong>Cuenta</strong><a href="login.php">Entrar</a><a href="como-usar.php">Ayuda de uso</a><a href="sobre-nosotros.php">Sobre TinderCows</a></div>
                <div class="public-footer__legal"><strong>Legal</strong><a href="privacidad.php">Privacidad</a><a href="terminos.php">Términos</a><a href="legal.php">Información legal</a></div>
            </div>
        </footer>
    </div>
</body>
</html>
