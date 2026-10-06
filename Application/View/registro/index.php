<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Crea tu cuenta de Ganado Cerca y configura cómo deseas participar.">
    <meta name="theme-color" content="#151a18">
    <title>Crear cuenta | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/onboarding.css?v=publish-3">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/password-toggle.js?v=password-1"></script>
    <script type="module" src="js/registro.js?v=signup-8"></script>
</head>
<body class="auth-page onboarding-page">
<main class="onboarding-shell" aria-labelledby="registro-title">
    <header class="auth-header onboarding-header">
            <a class="public-brand" href="./">
            <span class="public-brand__logo" aria-hidden="true">
                <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
            </span>
            <span>Ganado<strong>Cerca</strong></span>
        </a>
        <div class="auth-header__actions">
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
            <a class="auth-back" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Ya tengo cuenta</span></a>
        </div>
    </header>

    <section class="onboarding-intro">
        <div>
            <p class="section-kicker">Crear cuenta</p>
            <h1 id="registro-title">Crea tu cuenta en Ganado Cerca.</h1>
            <p>Te registras como comprador para explorar ganado cerca de ti. Si después quieres vender u ofrecer fletes, lo activas desde tu panel sin volver a registrarte.</p>
        </div>
        <ol class="onboarding-progress" aria-label="Progreso de registro">
            <li data-progress="persona" class="is-active"><span>1</span>Tus datos</li>
            <li data-progress="intereses"><span>2</span>Tu actividad</li>
            <li data-progress="fincas"><span>3</span>Tus fincas</li>
            <li data-progress="revision"><span>4</span>Listo</li>
        </ol>
    </section>

    <section class="onboarding-card">
        <form id="registro-form" novalidate aria-busy="false">
            <section class="onboarding-step signup" data-step="persona">
                <fieldset class="signup-type">
                    <legend class="signup-type__title">Tipo de identificación</legend>
                    <p class="signup-type__question" id="signup-type-question">¿Con qué documento te identificas?</p>
                    <div class="signup-type__options" aria-describedby="signup-type-question">
                        <label class="signup-radio"><input type="radio" name="identificacionTipo" value="CEDULA_FISICA" checked><span>Cédula física</span></label>
                        <label class="signup-radio"><input type="radio" name="identificacionTipo" value="CEDULA_JURIDICA"><span>Cédula jurídica</span></label>
                        <label class="signup-radio"><input type="radio" name="identificacionTipo" value="DIMEX"><span>DIMEX</span></label>
                        <label class="signup-radio"><input type="radio" name="identificacionTipo" value="NITE"><span>NITE</span></label>
                        <label class="signup-radio"><input type="radio" name="identificacionTipo" value="PASAPORTE"><span>Pasaporte</span></label>
                    </div>
                    <small class="auth-error" data-error-for="identificacionTipo"></small>
                </fieldset>

                <div class="signup-section">
                    <h2 class="signup-title">¿Quién va a usar la cuenta?</h2>
                    <p class="signup-required"><span aria-hidden="true">*</span> campos obligatorios</p>
                    <div class="signup-grid">
                        <div class="auth-field"><label for="registro-identificacion-numero">Número de identificación *</label><input id="registro-identificacion-numero" name="identificacionNumero" type="text" maxlength="12" autocomplete="off" required aria-describedby="registro-identificacion-hint registro-identificacion-status"><small id="registro-identificacion-hint" class="field-help" data-identificacion-hint>Elige el tipo para conocer el formato.</small><small id="registro-identificacion-status" class="identity-check-status" data-identificacion-status data-state="idle" role="status" aria-live="polite"></small><button class="identity-check-retry" data-identificacion-retry type="button" hidden>Reintentar verificación</button><small class="auth-error" data-error-for="identificacionNumero"></small></div>
                        <div class="auth-field"><label for="registro-correo-electronico">Correo electrónico *</label><input id="registro-correo-electronico" name="correoElectronico" type="email" maxlength="150" autocomplete="email" required aria-describedby="registro-correo-status registro-correo-error"><small id="registro-correo-status" class="identity-check-status" data-correo-status data-state="idle" role="status" aria-live="polite"></small><small id="registro-correo-error" class="auth-error" data-error-for="correoElectronico"></small></div>
                        <div class="auth-field"><label for="registro-nombres">Nombres *</label><input id="registro-nombres" name="nombres" type="text" minlength="2" maxlength="75" autocomplete="given-name" required placeholder="Ej. María Fernanda"><small class="auth-error" data-error-for="nombres"></small></div>
                        <div class="auth-field"><label for="registro-apellidos">Apellidos *</label><input id="registro-apellidos" name="apellidos" type="text" minlength="2" maxlength="75" autocomplete="family-name" required placeholder="Ej. Solano Vargas"><small class="auth-error" data-error-for="apellidos"></small></div>
                        <div class="auth-field"><label for="registro-telefono">Teléfono *</label><input id="registro-telefono" name="telefono" type="tel" maxlength="20" autocomplete="tel" placeholder="+506 8888 8888" required><small class="auth-error" data-error-for="telefono"></small></div>
                        <div class="auth-field"><label for="registro-alias">Alias <em>(opcional)</em></label><input id="registro-alias" name="alias" type="text" maxlength="150" aria-describedby="registro-alias-help"><small id="registro-alias-help" class="field-help">Así te saludaremos en tu panel.</small><small class="auth-error" data-error-for="alias"></small></div>
                        <label class="auth-field"><span>Contraseña *</span><span class="auth-password-control"><input id="registro-password" name="password" type="password" minlength="8" autocomplete="new-password" required aria-describedby="registro-password-reglas"><button class="auth-password-toggle" type="button" data-password-toggle aria-controls="registro-password" aria-label="Mostrar contraseña" aria-pressed="false"><i class="fa-solid fa-eye" aria-hidden="true"></i></button></span><ul class="password-rules" id="registro-password-reglas" data-password-rules aria-live="polite"><li data-rule="letter">Al menos una letra</li><li data-rule="uppercase">Al menos una letra en mayúscula</li><li data-rule="number">Al menos un número</li><li data-rule="length">Al menos 8 caracteres</li></ul><small class="auth-error" data-error-for="password"></small></label>
                        <label class="auth-field"><span>Confirmar contraseña *</span><span class="auth-password-control"><input id="registro-password-confirmacion" name="passwordConfirmacion" type="password" minlength="8" autocomplete="new-password" required><button class="auth-password-toggle" type="button" data-password-toggle aria-controls="registro-password-confirmacion" aria-label="Mostrar contraseña" aria-pressed="false"><i class="fa-solid fa-eye" aria-hidden="true"></i></button></span><small class="auth-error" data-error-for="passwordConfirmacion"></small></label>
                    </div>
                </div>

                <div class="signup-accordions">
                    <details class="signup-accordion">
                        <summary>¿Qué puedo hacer con mi cuenta?</summary>
                        <div class="signup-accordion__body">
                            <ul>
                                <li>Explorar ganado cerca de ti y guardar lo que te interesa.</li>
                                <li>Contactar a quien vende y coordinar el flete.</li>
                                <li>Si después quieres vender u ofrecer fletes, lo activas desde tu panel sin volver a registrarte.</li>
                            </ul>
                        </div>
                    </details>
                    <details class="signup-accordion">
                        <summary>Cómo usamos tus datos</summary>
                        <div class="signup-accordion__body">
                            <p>Tu identidad se registra una sola vez y se comparte entre todas tus actividades. Tu contraseña la guarda solo el servicio de autenticación; nosotros nunca la vemos.</p>
                            <p>Consulta la <a href="privacidad">política de privacidad</a> y los <a href="terminos">términos de uso</a>.</p>
                        </div>
                    </details>
                </div>
            </section>

            <section class="onboarding-step" data-step="intereses" hidden>
                <div class="step-heading"><span class="step-number">02</span><div><h2>¿Qué quieres hacer?</h2><p>Puedes elegir una, varias o todas. Más adelante podrás cambiar estas opciones desde tu perfil.</p></div></div>
                <div class="capability-grid">
                    <label class="capability-card"><input type="checkbox" name="capacidades" value="COMPRADOR"><span class="capability-card__icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></span><strong>Explorar como comprador</strong><span>Guardar oportunidades y registrar interés sobre publicaciones activas.</span></label>
                    <label class="capability-card"><input type="checkbox" name="capacidades" value="PRODUCTOR"><span class="capability-card__icon"><i class="fa-solid fa-cow" aria-hidden="true"></i></span><strong>Vender o publicar</strong><span>Registrar tus fincas y publicar ganado cuando lo necesites.</span></label>
                    <label class="capability-card"><input type="checkbox" name="capacidades" value="TRANSPORTISTA"><span class="capability-card__icon"><i class="fa-solid fa-truck" aria-hidden="true"></i></span><strong>Ofrecer fletes</strong><span>Ofrecer transporte; los vehículos se pueden asociar después.</span></label>
                </div>
                <p class="auth-error onboarding-error" data-error-for="capacidades"></p>
                <div class="business-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Podrás activar o desactivar estas actividades más adelante sin borrar tu cuenta ni duplicar tu identidad.</p></div>
            </section>

            <section class="onboarding-step signup" data-step="fincas" hidden>
                <div class="signup-section signup-section--first">
                    <h2 class="signup-title">Tus fincas</h2>
                    <p class="signup-required"><span aria-hidden="true">*</span> campos obligatorios · el punto exacto en el mapa es opcional</p>
                </div>
                <div id="fincas-list" class="fincas-list"></div>
                <button class="onboarding-add" id="agregar-finca" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i>Agregar otra finca</button>
                <p class="auth-error onboarding-error" data-error-for="fincas"></p>
            </section>

            <section class="onboarding-step" data-step="revision" hidden>
                <div class="step-heading"><span class="step-number">04</span><div><h2>Todo listo</h2><p>Terminaremos de guardar tus datos y podrás entrar a tu cuenta.</p></div></div>
                <div class="registration-ready"><i class="fa-solid fa-check" aria-hidden="true"></i><strong>Tu cuenta quedará lista para usar.</strong><span>Al terminar podrás completar o cambiar tus actividades desde tu perfil.</span></div>
            </section>

            <p id="registro-status" class="auth-status" role="status" aria-live="polite"></p>
            <div class="onboarding-actions">
                <button class="auth-back onboarding-back" id="registro-anterior" type="button" hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Anterior</button>
                <button class="auth-submit onboarding-next" id="registro-siguiente" type="button">Continuar<i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                <button class="auth-submit onboarding-next signup-submit" id="registro-finalizar" type="submit" hidden><span data-finish-label>Registrar</span></button>
            </div>
        </form>
    </section>
</main>
<template id="finca-template">
    <article class="finca-card" data-finca>
        <div class="finca-card__heading"><strong>Finca</strong><button type="button" data-remove-finca aria-label="Eliminar finca"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>
        <label class="auth-field"><span>Nombre de la finca *</span><input type="text" data-finca-nombre maxlength="150" required placeholder="Ej. Finca El Roble"></label>
        <div class="finca-address"></div>
        <small class="auth-error" data-finca-error></small>
    </article>
</template>
</body>
</html>
