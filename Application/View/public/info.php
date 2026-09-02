<?php

declare(strict_types=1);

$pageKey = isset($publicPage) && is_string($publicPage) ? $publicPage : 'about';

$pages = [
    'about' => [
        'title' => 'Sobre TinderCows',
        'kicker' => 'Una experiencia para descubrir y conectar',
        'lead' => 'TinderCows reúne publicaciones de ganado, cercanía, subastas y contacto en un recorrido simple para encontrar oportunidades relevantes.',
    ],
    'guide' => [
        'title' => 'Cómo usar TinderCows',
        'kicker' => 'Ayuda de uso',
        'lead' => 'Explora publicaciones, usa la búsqueda cuando necesites precisión y abre contacto o puja desde la oportunidad que te interese.',
    ],
    'privacy' => [
        'title' => 'Política de privacidad',
        'kicker' => 'Privacidad y datos',
        'lead' => 'Esta página resume el comportamiento actual del sitio respecto a la información de sesión y deja claros los puntos que todavía deben formalizarse.',
    ],
    'terms' => [
        'title' => 'Términos de uso',
        'kicker' => 'Condiciones de uso',
        'lead' => 'Estos términos describen el alcance actual de TinderCows y las condiciones mínimas para utilizar sus funciones disponibles.',
    ],
    'legal' => [
        'title' => 'Información legal',
        'kicker' => 'Transparencia',
        'lead' => 'TinderCows identifica de forma explícita qué capacidades están disponibles y qué asuntos todavía requieren definición antes de operar con datos y transacciones reales.',
    ],
];

$page = $pages[$pageKey] ?? $pages['about'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($page['lead'], ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#151a18">
    <title><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?> | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
    <script type="module" src="js/public-ui.js?v=public-1"></script>
</head>
<body class="public-info-page">
    <div class="public-shell">
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
                <a href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
                <a href="./#nosotros"><i class="fa-solid fa-people-group" aria-hidden="true"></i><span>Nosotros</span></a>
                <a href="./#como-funciona"><i class="fa-solid fa-route" aria-hidden="true"></i><span>Cómo funciona</span></a>
            </nav>
            <div class="public-header__actions">
                <form class="public-search" action="explorar.php" method="get" role="search" data-public-search data-open="false">
                    <button class="public-search__toggle" type="button" data-public-search-toggle aria-expanded="false" aria-label="Abrir búsqueda"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span>Buscar</span></button>
                    <div class="public-search__field"><label class="screen-reader-only" for="busqueda-publica-info">Buscar publicaciones</label><input id="busqueda-publica-info" name="q" type="search" autocomplete="off" placeholder="Ganado, subastas, zona…"><button type="submit" aria-label="Buscar"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button></div>
                </form>
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="info-main">
            <header class="info-hero">
                <p class="section-kicker"><?= htmlspecialchars($page['kicker'], ENT_QUOTES, 'UTF-8') ?></p>
                <h1><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars($page['lead'], ENT_QUOTES, 'UTF-8') ?></p>
            </header>

            <div class="info-content">
                <?php if ($pageKey === 'about'): ?>
                    <section><h2>Qué ofrece</h2><div class="info-copy"><p>TinderCows está pensado para descubrir ganado y oportunidades sin obligar a recorrer catálogos interminables. La experiencia prioriza publicaciones relevantes, cercanía y acciones claras desde la misma tarjeta.</p><p>El producto combina exploración visual, búsqueda puntual, favoritos, contacto y participación en subastas cuando una publicación lo permita.</p></div></section>
                    <section><h2>Cómo se siente</h2><div class="info-copy"><p>La interfaz está diseñada para reconocer rápidamente qué está viendo, dónde se encuentra la publicación y qué puede hacer después. Los controles mantienen icono y texto, los estados son visibles y las acciones principales aparecen cerca del contenido que afectan.</p><p>El modo claro y oscuro conserva la misma identidad verde, naranja y crema de TinderCows.</p></div></section>
                    <section><h2>Qué sigue</h2><div class="info-copy"><p>La experiencia pública ya define la navegación y los patrones de exploración. Las publicaciones reales, subastas y acciones transaccionales deben conectarse a sus servicios correspondientes antes de considerarse operativas.</p></div></section>
                <?php elseif ($pageKey === 'guide'): ?>
                    <section><h2>Explorar</h2><div class="info-copy"><ol><li>Abra <strong>Explorar</strong> para recorrer tarjetas de ganado y subastas.</li><li>Deslice entre publicaciones o use <strong>Anterior</strong> y <strong>Siguiente</strong>.</li><li>Use <strong>Buscar</strong> cuando quiera encontrar algo por nombre, zona o tipo de oportunidad.</li><li>Marque <strong>Me interesa</strong>, use <strong>Pasar</strong>, abra <strong>Contactar</strong> o <strong>Pujar</strong> cuando la publicación lo permita.</li></ol></div></section>
                    <section><h2>Búsqueda</h2><div class="info-copy"><p>La búsqueda permanece compacta en la barra superior para no competir con la navegación. Al activarla se abre el campo completo y puede cerrarse con Escape cuando está vacío.</p></div></section>
                    <section><h2>Cuenta</h2><div class="info-copy"><p>El acceso actual mantiene una sesión local en el navegador. Mientras la autenticación de servidor esté pendiente, la interfaz no debe interpretarse como un control definitivo de identidad o permisos.</p></div></section>
                <?php elseif ($pageKey === 'privacy'): ?>
                    <section><h2>Datos de sesión</h2><div class="info-copy"><p>El acceso actual puede guardar en <code>sessionStorage</code> un indicador local de sesión, el correo escrito y la hora de inicio. Ese contenido permanece asociado a la pestaña del navegador y no constituye por sí mismo autenticación de servidor.</p></div></section>
                    <section><h2>Publicaciones y contacto</h2><div class="info-copy"><p>Cuando las publicaciones, favoritos, contacto y subastas se conecten a servicios reales, deberá informarse qué datos se recopilan, para qué se usan, durante cuánto tiempo se conservan y cómo puede una persona ejercer sus derechos.</p></div></section>
                    <section><h2>Retención</h2><div class="info-copy"><p class="info-note"><strong>Pendiente:</strong> TinderCows todavía no publica una política definitiva de retención, eliminación o exportación de datos para operación real. No debe inferirse un plazo ni una base jurídica que aún no hayan sido definidos.</p></div></section>
                <?php elseif ($pageKey === 'terms'): ?>
                    <section><h2>Uso del sitio</h2><div class="info-copy"><p>TinderCows puede utilizarse para recorrer la interfaz, explorar contenido disponible y usar las funciones que estén activas en cada publicación.</p></div></section>
                    <section><h2>Cuenta y acciones</h2><div class="info-copy"><p>El acceso actual mantiene una sesión local y todavía no valida credenciales contra un servidor. Las acciones de contacto, favoritos o puja solo deben considerarse efectivas cuando exista confirmación del servicio correspondiente.</p></div></section>
                    <section><h2>Límites</h2><div class="info-copy"><ul><li>No asumir que una acción visual equivale a una transacción confirmada.</li><li>No usar información de muestra como si representara una publicación real.</li><li>No interpretar la visibilidad de una pantalla como prueba de autorización.</li></ul></div></section>
                <?php elseif ($pageKey === 'legal'): ?>
                    <section><h2>Identidad</h2><div class="info-copy"><p>TinderCows utiliza sus logos verde/naranja y su favicon como identidad visual propia. La interfaz no utiliza el logotipo de Tinder ni presenta una relación oficial con esa marca.</p></div></section>
                    <section><h2>Servicios y terceros</h2><div class="info-copy"><p>Las dependencias, mapas, tipografías, iconos y otros servicios de terceros utilizados en una versión operativa deben mantener sus licencias y avisos correspondientes.</p></div></section>
                    <section><h2>Pendientes antes de operación real</h2><div class="info-copy"><ul><li>Autenticación y autorización de servidor.</li><li>Política formal de privacidad, retención y ejercicio de derechos.</li><li>Definición de responsables y canales de contacto.</li><li>Confirmación transaccional para contacto, favoritos y pujas.</li><li>Procedimientos de respaldo, recuperación, incidentes y disponibilidad.</li></ul></div></section>
                <?php endif; ?>
            </div>
        </main>

        <footer class="public-footer public-footer--complete">
            <div class="public-footer__brand"><a class="public-brand public-brand--footer" href="./"><span class="public-brand__logo" aria-hidden="true"><img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="40" height="40"><img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="40" height="40"></span><span>Tinder<strong>Cows</strong></span></a><p>Descubre ganado y oportunidades cerca de ti.</p></div>
            <div class="public-footer__links"><div><strong>Explorar</strong><a href="./">Inicio</a><a href="explorar.php">Explorar</a><a href="./#nosotros">Nosotros</a><a href="./#como-funciona">Cómo funciona</a></div><div><strong>Cuenta</strong><a href="login.php">Entrar</a><a href="como-usar.php">Ayuda de uso</a><a href="sobre-nosotros.php">Sobre TinderCows</a></div><div class="public-footer__legal"><strong>Legal</strong><a href="privacidad.php">Privacidad</a><a href="terminos.php">Términos</a><a href="legal.php">Información legal</a></div></div>
        </footer>
    </div>
</body>
</html>
