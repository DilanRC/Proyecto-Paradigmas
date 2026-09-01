<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Inicio de sesión de TinderCows">
    <title>Iniciar sesión | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=official-shell-2">
    <link rel="stylesheet" href="css/components.css?v=official-shell-2">
    <link rel="stylesheet" href="css/panel.css?v=official-shell-2">
    <link rel="stylesheet" href="css/red-ganadera.css?v=official-shell-2">
    <script type="module" src="js/login.js"></script>
</head>
<body class="login-page">
    <main class="login-shell" aria-labelledby="login-title">
        <section class="login-panel">
            <a class="login-brand" href="./" aria-label="Volver a TinderCows">
                <span class="brand__logo brand__logo--dark"><img src="assets/logo_dark.png" alt="" width="44" height="44"></span>
                <span>Tinder<strong>Cows</strong></span>
            </a>
            <p class="label">Acceso administrativo</p>
            <h1 id="login-title">Iniciar sesión</h1>
            <p class="login-copy">Entre al panel para administrar productores, compradores, transportistas, vehículos y métodos de pago.</p>
            <form class="login-form" id="formulario-login" novalidate>
                <label class="field">
                    <span>Correo electrónico</span>
                    <input id="login-email" name="email" type="email" autocomplete="email" required placeholder="usuario@tindercows.local" aria-describedby="login-email-error">
                    <small class="field__error" id="login-email-error" data-error-for="email"></small>
                </label>
                <label class="field">
                    <span>Contraseña</span>
                    <input id="login-password" name="password" type="password" autocomplete="current-password" required minlength="8" aria-describedby="login-password-error">
                    <small class="field__error" id="login-password-error" data-error-for="password"></small>
                </label>
                <p class="login-status" id="login-status" role="status" aria-live="polite"></p>
                <button class="button button--primary" type="submit">Entrar al panel</button>
            </form>
        </section>
        <section class="login-context" aria-label="Resumen del sistema">
            <span class="brand__logo brand__logo--light"><img src="assets/logo_light.png" alt="TinderCows" width="78" height="78"></span>
            <h2>Panel único para la operación ganadera</h2>
            <p>La sesión local habilita la navegación de la entrega web. Cuando exista un proveedor Supabase configurado para el navegador, el contrato actual ya espera tokens Bearer en las API.</p>
            <a class="button button--secondary" href="./">Ver landing</a>
        </section>
    </main>
</body>
</html>
