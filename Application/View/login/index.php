<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Entra a TinderCows para explorar publicaciones y oportunidades ganaderas.">
    <meta name="theme-color" content="#151a18">
    <title>Entrar | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
    <script type="module" src="js/login.js?v=public-login-2"></script>
</head>
<body class="auth-page">
    <main class="auth-stage" aria-labelledby="login-title">
        <header class="auth-header">
            <a class="public-brand" href="./" aria-label="TinderCows, inicio">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Tinder<strong>Cows</strong></span>
            </a>
            <div class="auth-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="auth-back" href="explorar.php"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
            </div>
        </header>

        <div class="auth-layout">
            <section class="auth-context" aria-label="Beneficios de la cuenta">
                <span class="auth-context__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="176" height="176">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="176" height="176">
                </span>
                <p class="section-kicker">Tu red ganadera</p>
                <h2>Vuelve a lo que te interesa.</h2>
                <p>Una cuenta te permite mantener el contexto de tus publicaciones favoritas, contactos y oportunidades mientras recorres TinderCows.</p>
            </section>

            <section class="auth-card">
                <div class="auth-card__brand">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="54" height="54">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="54" height="54">
                    </span>
                    <div><p class="section-kicker">Bienvenido de nuevo</p><h1 id="login-title">Entrar a TinderCows</h1></div>
                </div>
                <p class="auth-card__copy">Continúa para volver a Explorar. Si llegaste desde una herramienta interna, TinderCows conserva ese destino de forma segura.</p>
                <p class="auth-demo-banner"><strong>Estado actual:</strong> este acceso guarda una sesión local en el navegador y todavía no valida credenciales contra un servidor.</p>

                <form class="auth-form" id="formulario-login" novalidate>
                    <label class="auth-field">
                        <span>Correo electrónico</span>
                        <input id="login-email" name="email" type="email" autocomplete="email" required placeholder="correo@ejemplo.com" aria-describedby="login-email-error">
                        <small class="auth-error" id="login-email-error" data-error-for="email"></small>
                    </label>
                    <label class="auth-field">
                        <span>Contraseña</span>
                        <input id="login-password" name="password" type="password" autocomplete="current-password" required minlength="8" placeholder="Mínimo 8 caracteres" aria-describedby="login-password-error">
                        <small class="auth-error" id="login-password-error" data-error-for="password"></small>
                    </label>
                    <p class="auth-status" id="login-status" role="status" aria-live="polite"></p>
                    <button class="auth-submit" type="submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar a TinderCows</span></button>
                </form>

                <p class="auth-card__legal">Al continuar acepta los <a href="terminos.php">Términos</a> y puede consultar la <a href="privacidad.php">Política de privacidad</a>.</p>
            </section>
        </div>
    </main>
</body>
</html>
