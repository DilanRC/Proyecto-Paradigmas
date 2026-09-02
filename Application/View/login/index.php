<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acceso de demostración a TinderCows">
    <meta name="theme-color" content="#151a18">
    <title>Acceso demo | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <script type="module" src="js/public-theme.js?v=brand-2"></script>
    <script type="module" src="js/login.js"></script>
</head>
<body class="auth-page">
    <main class="auth-stage" aria-labelledby="login-title">
        <header class="auth-header">
            <a class="public-brand" href="./" aria-label="TinderCows, sitio público">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>
            <div class="auth-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar tema" aria-pressed="true"><span class="theme-toggle__icon" aria-hidden="true">☀</span><span class="theme-toggle__label">Claro</span></button>
                <a class="auth-back" href="./">Sitio público</a>
            </div>
        </header>

        <div class="auth-layout">
            <section class="auth-context" aria-label="Contexto de acceso">
                <span class="auth-context__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="176" height="176">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="176" height="176">
                </span>
                <p class="section-kicker">Administración TinderCows</p>
                <h2>Entre al panel para recorrer los módulos del proyecto.</h2>
                <p>La autenticación de servidor todavía no está implementada. Esta pantalla existe para probar navegación, validaciones y la experiencia del frontend sin presentarla como un control de seguridad real.</p>
            </section>

            <section class="auth-card">
                <div class="auth-card__brand">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="54" height="54">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="54" height="54">
                    </span>
                    <div><p class="section-kicker">Acceso de demostración</p><h1 id="login-title">Entrar al panel</h1></div>
                </div>
                <p class="auth-card__copy">Complete campos válidos para abrir la administración. Por defecto se entra a Productores; los enlaces del sitio público pueden indicar otro módulo mediante <code>?next=</code>.</p>
                <p class="auth-demo-banner"><strong>Importante:</strong> este formulario guarda una sesión únicamente en <code>sessionStorage</code>. No valida credenciales contra un backend.</p>

                <form class="auth-form" id="formulario-login" novalidate>
                    <label class="auth-field">
                        <span>Correo electrónico</span>
                        <input id="login-email" name="email" type="email" autocomplete="email" required placeholder="correo@ejemplo.com" aria-describedby="login-email-error">
                        <small class="auth-error" id="login-email-error" data-error-for="email"></small>
                    </label>
                    <label class="auth-field">
                        <span>Contraseña de demostración</span>
                        <input id="login-password" name="password" type="password" autocomplete="current-password" required minlength="8" placeholder="Mínimo 8 caracteres" aria-describedby="login-password-error">
                        <small class="auth-error" id="login-password-error" data-error-for="password"></small>
                    </label>
                    <p class="auth-status" id="login-status" role="status" aria-live="polite"></p>
                    <button class="auth-submit" type="submit">Continuar a administración</button>
                </form>

                <p class="auth-card__legal">Al continuar acepta el uso académico descrito en <a href="terminos.php">Términos</a> y puede consultar <a href="privacidad.php">Privacidad</a>.</p>
            </section>
        </div>
    </main>
</body>
</html>
