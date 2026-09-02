<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Iniciar sesión en TinderCows">
    <meta name="theme-color" content="#15151c">
    <title>Iniciar sesión | TinderCows</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/public-auth.css?v=public-identity-1">
    <script type="module" src="js/login.js"></script>
</head>
<body class="auth-page">
    <main class="auth-stage" aria-labelledby="login-title">
        <header class="auth-header">
            <a class="public-brand" href="./" aria-label="TinderCows, inicio">
                <span class="public-mark" aria-hidden="true">TC</span>
                <span>TinderCows</span>
            </a>
            <a class="auth-back" href="./">Volver</a>
        </header>

        <div class="auth-media auth-media--left" data-placeholder="Imagen principal · placeholder" aria-hidden="true"></div>
        <div class="auth-media auth-media--right" data-placeholder="Imagen secundaria · placeholder" aria-hidden="true"></div>

        <section class="auth-card">
            <span class="auth-card__mark" aria-hidden="true"></span>
            <h1 id="login-title">Inicia sesión</h1>
            <p class="auth-card__copy">Continúa para entrar a TinderCows.</p>
            <div class="auth-divider"><span>Acceso privado</span></div>

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
                <button class="auth-submit" type="submit">Continuar</button>
            </form>

            <p class="auth-card__legal">El acceso al contenido del producto comienza después de completar esta pantalla.</p>
        </section>
    </main>
</body>
</html>
