<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows: encuentra la pareja perfecta para tu ganado">
    <title>TinderCows — Match ganadero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,600&display=swap">
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/home.js" defer></script>
</head>
<body class="rural">
    <aside class="rural__aside">
        <div class="rural__brand">
            <span class="brand__icon" aria-hidden="true"><svg viewBox="0 0 48 48"><path d="M13 12 6 7c-1 7 2 10 7 11m22-6 7-5c1 7-2 10-7 11"/><path d="M11 24c0-10 5-16 13-16s13 6 13 16v7c0 7-5 11-13 11S11 38 11 31Z"/><path d="M16 29c0-4 3-6 8-6s8 2 8 6-3 7-8 7-8-3-8-7Z"/><circle cx="18" cy="20" r="2"/><circle cx="30" cy="20" r="2"/><circle cx="21" cy="29" r="1.5"/><circle cx="27" cy="29" r="1.5"/></svg></span>
            <span class="rural__brand-name">Tinder<strong>Cows</strong></span>
        </div>

        <div class="rural__tabs" role="tablist" aria-label="Secciones">
            <button class="rural__tab rural__tab--active" type="button" role="tab" aria-selected="true">Matches</button>
            <button class="rural__tab" type="button" role="tab" aria-selected="false">Mensajes</button>
        </div>

        <h3 class="rural__label">Mi manada</h3>
        <div class="rural__herd">
            <div class="rural__avatar" title="Trueno"><img src="https://images.pexels.com/photos/11556841/pexels-photo-11556841.jpeg?auto=compress&cs=tinysrgb&w=150&q=70" alt="Trueno" loading="lazy" decoding="async"><i class="rural__avatar-dot" aria-hidden="true"></i></div>
            <div class="rural__avatar rural__avatar--muted" title="Bella"><img src="https://images.pexels.com/photos/27207635/pexels-photo-27207635.jpeg?auto=compress&cs=tinysrgb&w=150&q=70" alt="Bella" loading="lazy" decoding="async"></div>
            <div class="rural__avatar rural__avatar--muted" title="Luna"><img src="https://images.unsplash.com/photo-1624210681638-9ecd10500b05?auto=format&w=150&q=70&fit=crop" alt="Luna" loading="lazy" decoding="async"></div>
            <button class="rural__avatar rural__avatar--add" type="button" aria-label="Agregar a la manada">+</button>
        </div>

        <div class="rural__messages">
            <h3 class="rural__label">Mensajes directos</h3>
            <div class="rural__thread">
                <div class="rural__avatar rural__avatar--sm" aria-hidden="true"><img src="https://i.pravatar.cc/100?u=mateo-rivera-tindercows" alt="" loading="lazy" decoding="async"></div>
                <div class="rural__thread-body">
                    <div class="rural__thread-head"><b>Mateo Rivera</b><time>Ahora</time></div>
                    <p>"Trueno es un ejemplar excepcional, ¿coordinamos una visita al rancho?"</p>
                </div>
            </div>
            <div class="rural__thread rural__thread--muted">
                <div class="rural__avatar rural__avatar--sm" aria-hidden="true"><img src="https://i.pravatar.cc/100?u=rancho-la-gloria-tindercows" alt="" loading="lazy" decoding="async"></div>
                <div class="rural__thread-body">
                    <div class="rural__thread-head"><b>Rancho La Gloria</b><time>Ayer</time></div>
                    <p>"El lote de novillas ya está verificado."</p>
                </div>
            </div>
        </div>

        <a class="rural__panel-link" href="productores.php">Panel de productores →</a>
        <a class="rural__panel-link" href="compradores.php">Panel de compradores →</a>
    </aside>

    <main class="rural__main">
        <article class="rural__card">
            <nav class="rural__card-nav">
                <button class="rural__card-tab rural__card-tab--active" type="button">Galería</button>
                <button class="rural__card-tab" type="button">Ficha técnica</button>
                <button class="rural__card-tab" type="button">Producción</button>
            </nav>

            <div class="rural__media">
                <img src="https://images.unsplash.com/photo-1598974357035-0d439e28ccaf?auto=format&w=1200&q=75&fit=crop" alt="Lucero, toro Brahman de Finca El Roble" loading="lazy" decoding="async">
                <div class="rural__media-badges">
                    <span class="rural__badge">Brahman Puro</span>
                    <span class="rural__badge rural__badge--accent">Semental A+</span>
                </div>
            </div>

            <div class="rural__body">
                <div class="rural__heading">
                    <div>
                        <h1>Lucero</h1>
                        <p class="rural__sub-identity"><span data-finca>Finca El Roble</span> <span aria-hidden="true">•</span> 480 kg</p>
                    </div>
                    <div class="rural__rating" aria-label="5 de 5 estrellas">
                        <span aria-hidden="true">★★★★★</span>
                        <p>Rating del productor</p>
                    </div>
                </div>

                <p class="rural__quote">"Ejemplar Brahman de alta pureza genética, criado en libertad en las colinas de Heredia. Estructura ósea imponente y temperamento noble, ideal para mejora de hato."</p>

                <div class="rural__producer">
                    <div class="rural__producer-profile">
                        <div class="rural__avatar rural__avatar--lg"><img src="https://images.unsplash.com/photo-1622834613016-eb976dc462bd?auto=format&w=200&q=75&fit=crop" alt="María Fernández Solano" loading="lazy" decoding="async"><i class="rural__avatar-check" aria-hidden="true">✓</i></div>
                        <div>
                            <p class="rural__producer-title">Ganadero principal</p>
                            <p class="rural__producer-name">María Fernández Solano</p>
                            <p class="rural__producer-meta"><span class="rural__cert">Certificado TinderCows</span> <span aria-hidden="true">•</span> 12 lotes vendidos</p>
                        </div>
                    </div>
                    <a class="button button--primary" href="productores.php">Ver catálogo</a>
                </div>
            </div>
        </article>

        <div class="rural__dock">
            <button class="rural__dock-btn" type="button" aria-label="Pasar">✕</button>
            <button class="rural__dock-btn" type="button" aria-label="Guardar">♥</button>
            <button class="rural__dock-btn rural__dock-btn--primary" type="button">Contactar</button>
        </div>
    </main>
</body>
</html>
