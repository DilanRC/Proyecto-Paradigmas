<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="/">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Entra a Ganado Cerca para explorar publicaciones y oportunidades ganaderas.">
    <meta name="theme-color" content="#151a18">
    <title>Entrar | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/password-toggle.js?v=password-1"></script>
    <script type="module" src="js/login.js?v=front-3"></script>
</head>
<body class="auth-page">
    <main class="auth-stage" aria-labelledby="login-title">
        <header class="auth-header">
            <a class="public-brand" href="./">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Ganado<strong>Cerca</strong></span>
            </a>
            <div class="auth-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="auth-back" href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
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
                <p>Una cuenta te permite mantener una sola identidad y participar como comprador, productor, transportista o en varias actividades a la vez.</p>
            </section>

            <section class="auth-card">
                <div class="auth-card__brand">
                    <span class="public-brand__logo" aria-hidden="true">
                        <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="54" height="54">
                        <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="54" height="54">
                    </span>
                    <div><p class="section-kicker">Bienvenido de nuevo</p><h1 id="login-title">Entrar a Ganado Cerca</h1></div>
                </div>
                <p class="auth-card__copy">Continúa para volver a Explorar o administrar tus actividades.</p>
                <form class="auth-form" id="formulario-login" novalidate>
                    <label class="auth-field">
                        <span>Correo electrónico</span>
                        <input id="login-email" name="email" type="email" autocomplete="email" required placeholder="correo@ejemplo.com" aria-describedby="login-email-error">
                        <small class="auth-error" id="login-email-error" data-error-for="email"></small>
                    </label>
                    <label class="auth-field">
                        <span>Contraseña</span>
                        <span class="auth-password-control"><input id="login-password" name="password" type="password" autocomplete="current-password" required minlength="8" placeholder="Mínimo 8 caracteres" aria-describedby="login-password-error"><button class="auth-password-toggle" type="button" data-password-toggle aria-controls="login-password" aria-label="Mostrar contraseña" aria-pressed="false"><i class="fa-solid fa-eye" aria-hidden="true"></i></button></span>
                        <small class="auth-error" id="login-password-error" data-error-for="password"></small>
                    </label>
                    <p class="auth-status" id="login-status" role="status" aria-live="polite"></p>
                    <button class="auth-submit" type="submit"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar a Ganado Cerca</span></button>
                </form>

                <p class="auth-card__copy">¿Primera vez aquí? <a href="registro"><strong>Crear cuenta con registro guiado</strong></a></p>
                <p class="auth-card__legal">Al continuar acepta los <a href="terminos">Términos</a> y puede consultar la <a href="privacidad">Política de privacidad</a>.</p>
            </section>
        </div>
    </main>
</body>
</html>
