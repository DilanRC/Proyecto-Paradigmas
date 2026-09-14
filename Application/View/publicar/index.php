<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Prepara una publicación de ganado en TinderCows.">
    <meta name="theme-color" content="#151a18">
    <title>Publicar ganado | TinderCows</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=admin-public-4">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-2">
    <link rel="stylesheet" href="css/public-v3.css?v=public-4">
    <link rel="stylesheet" href="css/public-product.css?v=product-1">
    <link rel="stylesheet" href="css/front2-flow.css?v=front2-1">
    <script type="module" src="js/public-theme.js?v=brand-3"></script>
    <script type="module" src="js/public-ui.js?v=front2-2"></script>
    <script type="module" src="js/publicar.js?v=front2-1"></script>
</head>
<body class="public-home publish-page">
    <div class="public-shell" id="inicio">
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
                <a class="is-active" href="publicar.php"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i><span>Publicar</span></a>
            </nav>
            <div class="public-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="public-header__login" href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i><span>Entrar</span></a>
            </div>
        </header>

        <main class="flow-main">
            <section class="flow-heading" aria-labelledby="publish-title">
                <div>
                    <p class="section-kicker">Vender ganado</p>
                    <h1 id="publish-title">Prepara una publicación sin salir del flujo.</h1>
                    <p>Si todavía no participas como productor, TinderCows te pedirá únicamente los datos que faltan y luego volverá aquí.</p>
                </div>
                <span class="flow-status-chip"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Frontend conectado a reglas actuales</span>
            </section>

            <section class="flow-card" id="publish-gate" hidden aria-live="polite"></section>

            <section class="flow-card" id="publish-workspace" hidden>
                <form class="flow-form" id="publish-form" novalidate aria-busy="false">
                    <fieldset class="flow-section">
                        <legend>Origen de la publicación</legend>
                        <div class="flow-grid">
                            <label class="flow-field flow-field--full"><span>Finca <b aria-hidden="true">*</b></span><select name="fincaNombre" id="publish-finca" required><option value="">Seleccione una finca</option></select><small>Solo mostramos las fincas asociadas a tu perfil de productor.</small></label>
                        </div>
                    </fieldset>

                    <fieldset class="flow-section">
                        <legend>Datos del animal</legend>
                        <div class="flow-grid">
                            <label class="flow-field"><span>Identificación del animal</span><input name="animalIdentificacion" maxlength="100" autocomplete="off"></label>
                            <label class="flow-field"><span>Raza</span><input name="raza" maxlength="120" autocomplete="off"></label>
                            <label class="flow-field"><span>Sexo</span><input name="sexo" maxlength="40" autocomplete="off"></label>
                            <label class="flow-field"><span>Propósito</span><input name="proposito" maxlength="100" autocomplete="off" placeholder="Ej. Cría, leche, engorde"></label>
                            <label class="flow-field"><span>Edad aproximada (meses)</span><input name="edadMeses" type="number" min="0" step="1" inputmode="numeric"></label>
                            <label class="flow-field"><span>Peso aproximado (kg)</span><input name="peso" type="number" min="0" step="0.1" inputmode="decimal"></label>
                        </div>
                    </fieldset>

                    <fieldset class="flow-section">
                        <legend>Contenido comercial</legend>
                        <div class="flow-grid">
                            <label class="flow-field flow-field--full"><span>Título <b aria-hidden="true">*</b></span><input name="titulo" maxlength="150" required autocomplete="off" placeholder="Ej. Novilla Brahman lista para cría"></label>
                            <label class="flow-field"><span>Precio de referencia (₡)</span><input name="precio" type="number" min="0" step="1" inputmode="numeric"></label>
                            <label class="flow-field flow-field--full"><span>Descripción</span><textarea name="descripcion" maxlength="1200" placeholder="Describe características relevantes para la persona compradora."></textarea></label>
                        </div>
                    </fieldset>

                    <div class="flow-feedback" id="publish-status" role="status" aria-live="polite" hidden></div>
                    <div class="flow-actions">
                        <a class="flow-button" href="mi-actividad.php">Mi actividad</a>
                        <button class="flow-button flow-button--primary" id="publish-submit" type="submit">Preparar publicación</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
