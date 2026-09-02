<?php

declare(strict_types=1);

$pageKey = isset($publicPage) && is_string($publicPage) ? $publicPage : 'about';

$pages = [
    'about' => [
        'title' => 'Sobre nosotros',
        'kicker' => 'Proyecto TinderCows',
        'lead' => 'TinderCows es un proyecto académico de EIF400 que modela una red ganadera separando identidad, hechos históricos y operación administrativa.',
    ],
    'guide' => [
        'title' => 'Cómo usar TinderCows',
        'kicker' => 'Guía de navegación',
        'lead' => 'Esta guía distingue el sitio público, el acceso de demostración y los módulos administrativos para que sea claro qué ruta sirve para cada tarea.',
    ],
    'privacy' => [
        'title' => 'Política de privacidad',
        'kicker' => 'Estado actual del prototipo',
        'lead' => 'Esta página describe lo que el proyecto implementa hoy. No inventa una política de producción que todavía no ha sido definida.',
    ],
    'terms' => [
        'title' => 'Términos de uso',
        'kicker' => 'Entorno académico',
        'lead' => 'TinderCows se encuentra en desarrollo académico. Estos términos explican el alcance de la demostración y sus límites actuales.',
    ],
    'legal' => [
        'title' => 'Información legal',
        'kicker' => 'Transparencia del proyecto',
        'lead' => 'Resumen de responsabilidades, dependencias y asuntos pendientes antes de considerar un despliegue de producción.',
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
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <script type="module" src="js/public-theme.js?v=brand-2"></script>
</head>
<body class="public-info-page">
    <div class="public-shell">
        <header class="public-header">
            <a class="public-brand" href="./" aria-label="TinderCows, inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>
            <nav class="public-nav" aria-label="Información pública">
                <a href="sobre-nosotros.php">Sobre nosotros</a>
                <a href="como-usar.php">Cómo usar</a>
                <a href="privacidad.php">Privacidad</a>
                <a href="terminos.php">Términos</a>
                <a href="legal.php">Legal</a>
            </nav>
            <div class="public-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar tema" aria-pressed="true"><span class="theme-toggle__icon" aria-hidden="true">☀</span><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="login.php">Acceso demo</a>
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
                    <section>
                        <h2>Qué es</h2>
                        <div class="info-copy">
                            <p>TinderCows es una aplicación web académica para administrar una red ganadera. El frontend actual expone productores, compradores derivados, transportistas, vehículos y métodos de pago.</p>
                            <p>La arquitectura del proyecto evita tratar las capacidades de una persona como identidades duplicadas. El objetivo es conservar una persona o productor identificable y representar los cambios relevantes como hechos o periodos históricos cuando corresponde.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Cómo se diseña</h2>
                        <div class="info-copy">
                            <p>La interfaz prioriza estados verificables: carga, vacío, error, éxito, edición y recuperación. Una operación no se considera resuelta solo porque el código ejecute; debe poder comprobarse desde la experiencia y desde sus datos.</p>
                            <p>La identidad visual pública usa los logos oficiales de TinderCows y sus dos variantes cromáticas. El sitio público ofrece modo claro y oscuro sin modificar los módulos de negocio.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Estado actual</h2>
                        <div class="info-copy">
                            <p class="info-note"><strong>Hecho:</strong> el formulario de acceso actual crea una sesión local en el navegador y redirige a la administración. <strong>Pendiente:</strong> autenticación y autorización reales en servidor.</p>
                            <p>Los CRUD administrativos y sus APIs son una capa distinta del acceso demo. La ausencia de autenticación real no debe interpretarse como una garantía de seguridad o como un sistema terminado para producción.</p>
                        </div>
                    </section>
                <?php elseif ($pageKey === 'guide'): ?>
                    <section>
                        <h2>Recorrido</h2>
                        <div class="info-copy">
                            <ol>
                                <li>Abra <code>/</code> para volver siempre al sitio público.</li>
                                <li>Use <code>/login.php</code> para entrar al acceso de demostración.</li>
                                <li>Con campos válidos, el frontend crea una sesión local y abre <code>/productores.php</code> por defecto.</li>
                                <li>Desde la administración puede navegar entre los módulos CRUD disponibles.</li>
                                <li>Los enlaces públicos a módulos usan <code>?next=</code> para conservar el destino después del acceso demo.</li>
                            </ol>
                        </div>
                    </section>
                    <section>
                        <h2>Rutas públicas</h2>
                        <div class="info-copy">
                            <table class="route-table"><thead><tr><th>Ruta</th><th>Propósito</th></tr></thead><tbody>
                                <tr><td><code>/</code></td><td>Inicio público y presentación del proyecto.</td></tr>
                                <tr><td><code>/sobre-nosotros.php</code></td><td>Descripción del proyecto y su enfoque.</td></tr>
                                <tr><td><code>/como-usar.php</code></td><td>Guía de navegación y mapa de rutas.</td></tr>
                                <tr><td><code>/privacidad.php</code></td><td>Estado actual de privacidad y datos.</td></tr>
                                <tr><td><code>/terminos.php</code></td><td>Términos de uso académico.</td></tr>
                                <tr><td><code>/legal.php</code></td><td>Información legal y pendientes de producción.</td></tr>
                                <tr><td><code>/login.php</code></td><td>Acceso local de demostración.</td></tr>
                            </tbody></table>
                        </div>
                    </section>
                    <section>
                        <h2>Rutas administrativas</h2>
                        <div class="info-copy">
                            <table class="route-table"><thead><tr><th>Ruta</th><th>Módulo</th></tr></thead><tbody>
                                <tr><td><code>/productores.php</code></td><td>Productores.</td></tr>
                                <tr><td><code>/compradores.php</code></td><td>Compradores derivados.</td></tr>
                                <tr><td><code>/transportistas.php</code></td><td>Transportistas.</td></tr>
                                <tr><td><code>/vehiculos.php</code></td><td>Vehículos.</td></tr>
                                <tr><td><code>/pagometodos.php</code></td><td>Métodos de pago.</td></tr>
                            </tbody></table>
                            <p class="info-note">Estas páginas son administrativas. Mientras el backend de autenticación esté pendiente, el control de acceso mostrado en el navegador es demostrativo y no debe considerarse una barrera de seguridad.</p>
                        </div>
                    </section>
                <?php elseif ($pageKey === 'privacy'): ?>
                    <section>
                        <h2>Datos que maneja el sistema</h2>
                        <div class="info-copy">
                            <p>Los módulos administrativos pueden manejar datos como identificación, nombre o razón social, teléfono, correo electrónico, dirección, fincas, vehículos y relaciones de operación, según el módulo utilizado.</p>
                            <p>La pantalla de acceso demo guarda en <code>sessionStorage</code> un indicador local de sesión, el correo escrito y la hora de inicio. Ese mecanismo vive en la pestaña del navegador y actualmente no valida la identidad con un servidor.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Finalidad actual</h2>
                        <div class="info-copy">
                            <p>El tratamiento de datos existe para el desarrollo, prueba y evaluación académica de los flujos de TinderCows. El repositorio no define todavía una finalidad comercial ni una política de producción.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Retención y derechos</h2>
                        <div class="info-copy">
                            <p class="info-note"><strong>Pendiente:</strong> el proyecto no publica todavía una política formal de retención, eliminación, exportación o ejercicio de derechos de titulares para un entorno productivo. Esas reglas deben definirse antes de tratar datos reales fuera del contexto académico.</p>
                            <p>No se debe inferir de esta página un plazo de conservación ni una base jurídica que el proyecto todavía no haya definido.</p>
                        </div>
                    </section>
                <?php elseif ($pageKey === 'terms'): ?>
                    <section>
                        <h2>Uso permitido</h2>
                        <div class="info-copy">
                            <p>El sitio se presenta como proyecto académico y entorno de demostración. Puede utilizarse para revisar interfaz, navegación, procesos CRUD y comportamiento de los módulos implementados.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Acceso</h2>
                        <div class="info-copy">
                            <p>El formulario de login actual no es un sistema de autenticación real. Completar datos válidos permite crear una sesión local de navegador y entrar al panel. No debe utilizarse como evidencia de identidad, autorización o control de permisos.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Límites</h2>
                        <div class="info-copy">
                            <ul>
                                <li>No usar el prototipo como sistema productivo sin completar seguridad, privacidad, respaldo y operación.</li>
                                <li>No asumir que los datos de ejemplo representan personas o transacciones reales.</li>
                                <li>No considerar una interfaz visible como sustituto de controles de servidor.</li>
                            </ul>
                        </div>
                    </section>
                <?php elseif ($pageKey === 'legal'): ?>
                    <section>
                        <h2>Naturaleza</h2>
                        <div class="info-copy">
                            <p>TinderCows es un proyecto académico identificado dentro del curso EIF400. La interfaz pública y sus textos informativos existen para documentar el producto y facilitar su evaluación.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Activos de identidad</h2>
                        <div class="info-copy">
                            <p>El frontend usa los archivos <code>assets/logo_dark.png</code>, <code>assets/logo_light.png</code> y <code>favicon.svg</code> como identidad visual del proyecto. La portada no utiliza el logotipo de Tinder ni presenta una relación oficial con esa marca.</p>
                        </div>
                    </section>
                    <section>
                        <h2>Pendientes antes de producción</h2>
                        <div class="info-copy">
                            <ul>
                                <li>Autenticación y autorización de servidor.</li>
                                <li>Política formal de privacidad, retención y ejercicio de derechos.</li>
                                <li>Definición de responsables del tratamiento y canales de contacto.</li>
                                <li>Revisión de dependencias, licencias y avisos de terceros usados en el despliegue definitivo.</li>
                                <li>Procedimientos documentados de respaldo, recuperación, incidentes y disponibilidad.</li>
                            </ul>
                            <p class="info-note">La ausencia de estos puntos no se oculta: son requisitos pendientes para un escenario de producción y no forman parte del acceso demo actual.</p>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
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
                <div><strong>Proyecto</strong><a href="./">Inicio</a><a href="sobre-nosotros.php">Sobre nosotros</a><a href="como-usar.php">Cómo usar</a></div>
                <div><strong>Información</strong><a href="privacidad.php">Privacidad</a><a href="terminos.php">Términos</a><a href="legal.php">Legal</a></div>
            </div>
        </footer>
    </div>
</body>
</html>
