<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= tc_public_base_attribute() ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Publica tu ganado en Ganado Cerca con foto, precio y datos del animal.">
    <meta name="theme-color" content="#151a18">
    <title>Publicar ganado | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-7">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-3">
    <link rel="stylesheet" href="css/public-v3.css?v=public-10">
    <link rel="stylesheet" href="css/public-product.css?v=product-7">
    <link rel="stylesheet" href="css/onboarding.css?v=publish-3">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
    <script type="module" src="js/public-ui.js?v=public-11"></script>
    <script type="module" src="js/publicar.js?v=publish-3"></script>
</head>
<body class="public-home publish-page">
    <div class="public-shell" id="inicio">
        <header class="public-header public-header--product">
            <a class="public-brand" href="./">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Ganado<strong>Cerca</strong></span>
            </a>
            <nav class="public-nav public-nav--primary" aria-label="Navegación principal">
                <a href="./"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Inicio</span></a>
                <a href="explorar"><i class="fa-solid fa-compass" aria-hidden="true"></i><span>Explorar</span></a>
                <a class="is-active" href="publicar"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i><span>Publicar</span></a>
            </nav>
            <div class="public-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="entrar"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="publish-main">
            <section class="publish-intro" aria-labelledby="publish-title">
                <p class="section-kicker">Vender ganado</p>
                <h1 id="publish-title">Publica tu ganado.</h1>
                <p>Una buena foto, un título claro y el precio ayudan a que te contacten más rápido.</p>
            </section>

            <section class="onboarding-card publish-gate" id="publish-gate" hidden aria-live="polite"></section>

            <section class="onboarding-card" id="publish-workspace" hidden>
                <form id="publish-form" novalidate aria-busy="false">
                    <div class="onboarding-step signup">
                        <div class="signup-section signup-section--first">
                            <h2 class="signup-title">¿Qué vas a publicar?</h2>
                            <p class="signup-required"><span aria-hidden="true">*</span> campos obligatorios</p>
                            <div class="signup-grid">
                                <div class="auth-field auth-field--wide"><label for="publish-titulo">Título *</label><input id="publish-titulo" name="titulo" maxlength="150" required autocomplete="off" placeholder="Ej. Novilla Brahman lista para cría"></div>
                                <div class="auth-field"><label for="publish-finca">Finca *</label><select name="fincaNombre" id="publish-finca" required><option value="">Seleccione una finca</option></select><small class="field-help">Solo aparecen tus fincas activas.</small></div>
                                <div class="auth-field"><label for="publish-precio">Precio de referencia (₡)</label><input id="publish-precio" name="precio" type="number" min="0" step="1" inputmode="numeric" placeholder="Ej. 950000"></div>
                            </div>
                        </div>

                        <fieldset class="signup-type publish-photo">
                            <legend class="signup-type__title">Foto del animal</legend>
                            <p class="signup-type__question" id="publish-foto-ayuda">Opcional, pero las publicaciones con foto reciben más interés.</p>
                            <div class="signup-type__options" aria-describedby="publish-foto-ayuda">
                                <label class="signup-radio"><input type="radio" name="imagenModo" value="archivo" checked><span>Subir desde el dispositivo</span></label>
                                <label class="signup-radio"><input type="radio" name="imagenModo" value="url"><span>Usar una URL</span></label>
                            </div>

                            <div class="publish-dropzone" data-imagen-panel="archivo">
                                <input class="screen-reader-only" id="publish-imagen-archivo" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="publish-imagen-formatos">
                                <label class="public-secondary publish-dropzone__button" for="publish-imagen-archivo"><i class="fa-solid fa-upload" aria-hidden="true"></i><span>Elegir imagen</span></label>
                                <span class="publish-dropzone__text">o arrástrala aquí</span>
                                <small id="publish-imagen-formatos" class="field-help">JPG, PNG o WebP, hasta 5 MB.</small>
                            </div>

                            <div class="auth-field publish-url" data-imagen-panel="url" hidden>
                                <label for="publish-imagen-url">Dirección de la imagen</label>
                                <input id="publish-imagen-url" name="imagenUrl" type="url" inputmode="url" maxlength="500" autocomplete="off" placeholder="https://…">
                                <small class="field-help">Pega el enlace directo a la imagen (debe empezar con https://).</small>
                            </div>

                            <div class="publish-preview" data-imagen-preview hidden>
                                <img alt="Vista previa de la foto" data-imagen-preview-img>
                                <button class="public-secondary" type="button" data-imagen-quitar><i class="fa-solid fa-xmark" aria-hidden="true"></i><span>Quitar foto</span></button>
                            </div>
                            <small class="auth-error" data-error-for="imagenUrl" role="alert"></small>
                        </fieldset>

                        <div class="signup-section">
                            <h2 class="signup-title signup-title--sm">Datos del animal</h2>
                            <div class="signup-grid">
                                <div class="auth-field"><label for="publish-identificacion">Identificación del animal</label><input id="publish-identificacion" name="animalIdentificacion" maxlength="100" autocomplete="off" placeholder="Ej. arete 0457"></div>
                                <div class="auth-field"><label for="publish-raza">Raza</label><input id="publish-raza" name="raza" maxlength="120" autocomplete="off" placeholder="Ej. Brahman"></div>
                                <div class="auth-field"><label for="publish-sexo">Sexo</label><select id="publish-sexo" name="sexo"><option value="">Sin indicar</option><option value="HEMBRA">Hembra</option><option value="MACHO">Macho</option></select></div>
                                <div class="auth-field"><label for="publish-proposito">Propósito</label><select id="publish-proposito" name="proposito"><option value="">Sin indicar</option><option value="CRIA">Cría</option><option value="ENGORDE">Engorde</option><option value="LECHE">Leche</option><option value="DOBLE PROPOSITO">Doble propósito</option></select></div>
                                <div class="auth-field"><label for="publish-edad">Edad aproximada (meses)</label><input id="publish-edad" name="edadMeses" type="number" min="0" step="1" inputmode="numeric"></div>
                                <div class="auth-field"><label for="publish-peso">Peso aproximado (kg)</label><input id="publish-peso" name="peso" type="number" min="0" step="0.1" inputmode="decimal"></div>
                                <div class="auth-field auth-field--wide"><label for="publish-descripcion">Descripción</label><textarea id="publish-descripcion" name="descripcion" maxlength="500" placeholder="Describe características relevantes para la persona compradora."></textarea><small class="field-help">Hasta 500 caracteres.</small></div>
                            </div>
                        </div>
                    </div>

                    <div class="publish-status" id="publish-status" role="status" aria-live="polite" hidden></div>
                    <div class="onboarding-actions">
                        <a class="auth-back onboarding-back" href="mi-actividad"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Mi panel</span></a>
                        <button class="auth-submit onboarding-next signup-submit" id="publish-submit" type="submit"><span>Publicar ganado</span></button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
