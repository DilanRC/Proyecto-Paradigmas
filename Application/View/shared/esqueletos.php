<?php

declare(strict_types=1);

/*
 * Esqueletos de carga de las páginas públicas.
 *
 * Skeleton = contenido que todavía no llegó. Cada esqueleto copia la geometría
 * del componente real (mismas clases de tarjeta, fila o panel) para que nada
 * salte cuando JavaScript cambia el esqueleto por los datos. Todo va con
 * aria-hidden: el anuncio "Cargando…" lo hace el texto de estado que lo
 * acompaña (role="status"), una sola vez.
 */

function tc_esqueleto_linea(string $variantes = 'texto'): string
{
    $clases = 'skeleton__row';
    foreach (preg_split('/\s+/', trim($variantes)) ?: [] as $variante) {
        if ($variante !== '') {
            $clases .= ' skeleton__row--' . $variante;
        }
    }

    return '<span class="' . $clases . '"></span>';
}

/** Tarjetas de publicación (Explorar, Inicio, Me interesa): foto, datos y precio. */
function tc_esqueleto_tarjetas_publicacion(int $cantidad, bool $compacta = false): string
{
    $tarjeta = '<article class="explore-card explore-card--skeleton' . ($compacta ? ' explore-card--compacta' : '') . '">'
        . '<div class="explore-card__visual skeleton__row"></div>'
        . '<div class="explore-card__body">'
        . tc_esqueleto_linea('texto corta')
        . tc_esqueleto_linea('titulo')
        . tc_esqueleto_linea('texto media');
    if (!$compacta) {
        $tarjeta .= tc_esqueleto_linea('texto') . tc_esqueleto_linea('texto media')
            . '<span class="explore-card__skeleton-acciones">' . tc_esqueleto_linea() . tc_esqueleto_linea() . '</span>';
    }
    $tarjeta .= '</div></article>';

    return str_repeat($tarjeta, max(1, $cantidad));
}

/** Tarjetas del listado de subastas: foto 4:3, título, precio, reloj y botón. */
function tc_esqueleto_tarjetas_subasta(int $cantidad): string
{
    $tarjeta = '<article class="subasta-card subasta-card--skeleton">'
        . '<div class="subasta-card__foto skeleton__row"></div>'
        . '<div class="subasta-card__cuerpo">'
        . tc_esqueleto_linea('titulo') . tc_esqueleto_linea('texto corta')
        . tc_esqueleto_linea('cifra') . tc_esqueleto_linea('texto media') . tc_esqueleto_linea()
        . '</div></article>';

    return str_repeat($tarjeta, max(1, $cantidad));
}

/** Detalle de una subasta: foto y ficha a la izquierda; precio, puja y pujas a la derecha. */
function tc_esqueleto_detalle_subasta(): string
{
    $lista = str_repeat('<li>' . tc_esqueleto_linea('texto') . '</li>', 4);

    return '<div class="subasta-layout subasta-layout--skeleton">'
        . '<div class="subasta-principal">'
        . '<div class="subasta-foto skeleton__row"></div>'
        . '<div class="activity-panel">' . tc_esqueleto_linea('texto corta') . tc_esqueleto_linea('titulo')
        . tc_esqueleto_linea('texto') . tc_esqueleto_linea('texto media') . '</div>'
        . '</div>'
        . '<aside class="subasta-lateral">'
        . '<div class="activity-panel">' . tc_esqueleto_linea('texto corta') . tc_esqueleto_linea('cifra')
        . tc_esqueleto_linea('texto media') . tc_esqueleto_linea() . '</div>'
        . '<div class="activity-panel">' . tc_esqueleto_linea('titulo corta') . '<ul class="skeleton-lista">' . $lista . '</ul></div>'
        . '</aside></div>';
}

/** Filas de las secciones de Mi panel (fincas, publicaciones, animales, vehículos, solicitudes). */
function tc_esqueleto_filas(int $cantidad, bool $conFoto = false): string
{
    $fila = '<div class="panel-row panel-row--skeleton' . ($conFoto ? ' panel-row--media' : '') . '">'
        . ($conFoto ? '<span class="panel-thumb skeleton__row"></span>' : '')
        . '<div>' . tc_esqueleto_linea('titulo') . tc_esqueleto_linea('texto media') . '</div>'
        . '<span class="panel-row__skeleton-estado">' . tc_esqueleto_linea('texto') . '</span>'
        . '</div>';

    return str_repeat($fila, max(1, $cantidad));
}

/** Saludo y tarjeta de perfil de Mi panel y Ajustes. */
function tc_esqueleto_perfil(): string
{
    return '<div class="perfil-skeleton">'
        . '<span class="perfil-skeleton__avatar skeleton__row"></span>'
        . '<div>' . tc_esqueleto_linea('titulo') . tc_esqueleto_linea('texto media') . tc_esqueleto_linea('texto corta') . '</div>'
        . '</div>';
}

/** Mi dashboard: 4 indicadores, barra de estados, dos gráficos, actividad y tabla de ejemplares. */
function tc_esqueleto_dashboard(): string
{
    $kpi = '<div class="dash-skeleton__kpi">' . tc_esqueleto_linea('texto media') . tc_esqueleto_linea('cifra') . tc_esqueleto_linea('texto corta') . '</div>';
    $grafico = '<div class="dash-skeleton__grafico">' . tc_esqueleto_linea('titulo corta')
        . '<div class="dash-skeleton__barras">' . str_repeat('<span class="skeleton__row"></span>', 6) . '</div></div>';
    $filas = str_repeat('<div class="dash-skeleton__fila">' . tc_esqueleto_linea('texto') . tc_esqueleto_linea('texto') . tc_esqueleto_linea('texto') . '</div>', 4);

    $titulo = '<span class="dash-skeleton__titulo">' . tc_esqueleto_linea('titulo corta') . '</span>';
    $estado = '<div class="dash-skeleton__kpi">' . tc_esqueleto_linea('texto media') . tc_esqueleto_linea('cifra corta') . tc_esqueleto_linea('texto') . '</div>';

    return '<div class="dash-skeleton">'
        . $titulo . '<div class="dash-skeleton__kpis">' . str_repeat($kpi, 4) . '</div>'
        . $titulo . '<div class="dash-skeleton__estados"><div class="dash-skeleton__kpis">' . str_repeat($estado, 4) . '</div></div>'
        . '<div class="dash-skeleton__graficos">' . $grafico . $grafico . '</div>'
        . '<div class="dash-skeleton__bloque">' . tc_esqueleto_linea('titulo corta') . tc_esqueleto_linea('texto') . tc_esqueleto_linea('texto media') . '</div>'
        . '<div class="dash-skeleton__bloque">' . tc_esqueleto_linea('titulo corta') . $filas . '</div>'
        . '</div>';
}
