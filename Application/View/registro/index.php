<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Crea tu cuenta de TinderCows y configura cómo deseas participar.">
    <meta name="theme-color" content="#151a18">
    <title>Crear cuenta | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/onboarding.css?v=front-2">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/password-toggle.js?v=password-1"></script>
    <script type="module" src="js/registro.js?v=front-4"></script>
</head>
<body class="auth-page onboarding-page">
<main class="onboarding-shell" aria-labelledby="registro-title">
    <header class="auth-header onboarding-header">
        <a class="public-brand" href="./" aria-label="TinderCows, inicio">
            <span class="public-brand__logo" aria-hidden="true">
                <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
            </span>
            <span>Tinder<strong>Cows</strong></span>
        </a>
        <div class="auth-header__actions">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
            <a class="auth-back" href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Ya tengo cuenta</span></a>
        </div>
    </header>

    <section class="onboarding-intro">
        <div>
            <p class="section-kicker">Registro guiado</p>
            <h1 id="registro-title">Cuéntanos cómo quieres usar TinderCows.</h1>
            <p>No vamos a obligarte a ser productor, comprador o transportista. Primero registramos tu identidad una sola vez y después preguntamos únicamente lo necesario para las actividades que elijas.</p>
        </div>
        <ol class="onboarding-progress" aria-label="Progreso de registro">
            <li data-progress="persona" class="is-active"><span>1</span>Tu información</li>
            <li data-progress="intereses"><span>2</span>Qué quieres hacer</li>
            <li data-progress="fincas"><span>3</span>Fincas</li>
            <li data-progress="revision"><span>4</span>Revisión</li>
        </ol>
    </section>

    <section class="onboarding-card">
        <form id="registro-form" novalidate aria-busy="false">
            <section class="onboarding-step" data-step="persona">
                <div class="step-heading"><span class="step-number">01</span><div><h2>Tu información</h2><p>Estos datos identifican a la persona. No se volverán a pedir por cada actividad.</p></div></div>
                <div class="form-grid onboarding-grid">
                    <label class="auth-field"><span>Tipo de identificación *</span><select name="identificacionTipo" required><option value="">Seleccione</option><option value="CEDULA_FISICA">Cédula física</option><option value="CEDULA_JURIDICA">Cédula jurídica</option><option value="DIMEX">DIMEX</option><option value="NITE">NITE</option><option value="PASAPORTE">Pasaporte</option></select><small class="auth-error" data-error-for="identificacionTipo"></small></label>
                    <label class="auth-field"><span>Número de identificación *</span><input name="identificacionNumero" type="text" maxlength="250" autocomplete="off" required><small class="auth-error" data-error-for="identificacionNumero"></small></label>
                    <label class="auth-field auth-field--wide"><span>Nombre completo o razón social *</span><input name="nombre" type="text" minlength="3" maxlength="150" autocomplete="name" required><small class="auth-error" data-error-for="nombre"></small></label>
                    <label class="auth-field"><span>Alias <em>opcional</em></span><input name="alias" type="text" maxlength="150"><small class="auth-error" data-error-for="alias"></small></label>
                    <label class="auth-field"><span>Teléfono *</span><input name="telefono" type="tel" maxlength="20" autocomplete="tel" placeholder="+506 8888 8888" required><small class="auth-error" data-error-for="telefono"></small></label>
                    <label class="auth-field"><span>Correo electrónico *</span><input name="correoElectronico" type="email" maxlength="150" autocomplete="email" required><small class="auth-error" data-error-for="correoElectronico"></small></label>
                    <label class="auth-field"><span>Contraseña *</span><span class="auth-password-control"><input id="registro-password" name="password" type="password" minlength="8" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle aria-controls="registro-password" aria-label="Mostrar contraseña" aria-pressed="false"><i class="fa-solid fa-eye" aria-hidden="true"></i></button></span><small class="auth-error" data-error-for="password"></small></label>
                    <label class="auth-field"><span>Confirmar contraseña *</span><span class="auth-password-control"><input id="registro-password-confirmacion" name="passwordConfirmacion" type="password" minlength="8" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle aria-controls="registro-password-confirmacion" aria-label="Mostrar contraseña" aria-pressed="false"><i class="fa-solid fa-eye" aria-hidden="true"></i></button></span><small class="auth-error" data-error-for="passwordConfirmacion"></small></label>
                </div>
            </section>

            <section class="onboarding-step" data-step="intereses" hidden>
                <div class="step-heading"><span class="step-number">02</span><div><h2>¿Qué quieres hacer?</h2><p>Puedes elegir una, varias o todas. Estas opciones describen actividades de negocio, no roles administrativos.</p></div></div>
                <div class="capability-grid">
                    <label class="capability-card"><input type="checkbox" name="capacidades" value="COMPRADOR"><span class="capability-card__icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span><strong>Explorar como comprador</strong><span>Guardar oportunidades y registrar interés sobre publicaciones activas.</span></label>
                    <label class="capability-card"><input type="checkbox" name="capacidades" value="PRODUCTOR"><span class="capability-card__icon"><i class="fa-solid fa-cow" aria-hidden="true"></i></span><strong>Vender o publicar</strong><span>Registrar tus fincas y publicar ganado cuando lo necesites.</span></label>
                    <label class="capability-card"><input type="checkbox" name="capacidades" value="TRANSPORTISTA"><span class="capability-card__icon"><i class="fa-solid fa-truck" aria-hidden="true"></i></span><strong>Ofrecer fletes</strong><span>Ofrecer transporte; los vehículos se pueden asociar después.</span></label>
                </div>
                <p class="auth-error onboarding-error" data-error-for="capacidades"></p>
                <div class="business-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Podrás activar o desactivar estas actividades más adelante sin borrar tu cuenta ni duplicar tu identidad.</p></div>
            </section>

            <section class="onboarding-step" data-step="fincas" hidden>
                <div class="step-heading"><span class="step-number">03</span><div><h2>Tus fincas</h2><p>Como elegiste vender o publicar, necesitamos al menos una finca. Puedes agregar varias por separado.</p></div></div>
                <div id="fincas-list" class="fincas-list"></div>
                <button class="onboarding-add" id="agregar-finca" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i>Agregar otra finca</button>
                <p class="auth-error onboarding-error" data-error-for="fincas"></p>
            </section>

            <section class="onboarding-step" data-step="revision" hidden>
                <div class="step-heading"><span class="step-number">04</span><div><h2>Revisa antes de continuar</h2><p>Te mostramos qué información se guardaría y por qué se pidió.</p></div></div>
                <div id="registro-resumen" class="registration-summary"></div>
                <div class="business-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><p>Tu cuenta se valida con Supabase y esta revisión se guarda en el servidor en una sola operación. Si eliges vender o publicar, tus fincas quedan ligadas a la misma identidad.</p></div>
            </section>

            <p id="registro-status" class="auth-status" role="status" aria-live="polite"></p>
            <div class="onboarding-actions">
                <button class="auth-back onboarding-back" id="registro-anterior" type="button" hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Anterior</button>
                <button class="auth-submit onboarding-next" id="registro-siguiente" type="button">Continuar<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                <button class="auth-submit onboarding-next" id="registro-finalizar" type="submit" hidden>Terminar registro<i class="fa-solid fa-check" aria-hidden="true"></i></button>
            </div>
        </form>
    </section>
</main>
<template id="finca-template">
    <article class="finca-card" data-finca>
        <div class="finca-card__heading"><strong>Finca</strong><button type="button" data-remove-finca aria-label="Eliminar finca"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>
        <label class="auth-field"><span>Nombre de la finca *</span><input type="text" data-finca-nombre maxlength="150" required placeholder="Ej. Finca El Roble"></label>
        <details class="finca-address"><summary>Agregar dirección ahora <span>opcional</span></summary><p>La dirección se podrá completar o modificar desde la finca. En este registro inicial no es obligatoria.</p></details>
    </article>
</template>
</body>
</html>
