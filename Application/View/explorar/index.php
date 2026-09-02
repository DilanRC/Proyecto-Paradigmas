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
                <div class="explore-heading__controls" data-explore-filters aria-label="Filtrar por propósito">
                    <button class="explore-chip is-active" type="button" data-explore-filter="todos"><span>Todo</span></button>
                </div>
            </section>

            <section class="explore-deck" aria-label="Publicaciones para explorar">
                <p class="explore-state" data-explore-loading hidden>
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i><span>Cargando publicaciones…</span>
                </p>

                <div class="explore-empty" data-explore-error hidden>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <h2>No pudimos cargar las publicaciones.</h2>
                    <p data-explore-error-message></p>
                    <button type="button" data-explore-retry><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Reintentar</span></button>
                </div>

                <div class="explore-deck__viewport" data-explore-deck tabindex="0"></div>

                <div class="explore-deck__navigation">
                    <button type="button" data-explore-prev><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Anterior</span></button>
                    <span data-explore-position aria-live="polite">0 de 0</span>
                    <button type="button" data-explore-next><span>Siguiente</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>

                <div class="explore-empty" data-explore-empty hidden>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <h2>No encontramos publicaciones.</h2>
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
