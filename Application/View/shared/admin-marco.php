<?php
// Ícono y ayuda de cada pantalla admin, renderizados en el servidor para que el marco no salte (CLS) cuando
// shared/admin-ui.js se carga. Misma información que MODULES de admin-ui.js (admin_shell_ux.test.mjs lo compara).

if (!function_exists('admin_modulo')) {
    function admin_modulo(string $ruta): array
    {
        $modulos = [
            'admin/dashboard' => ['icon' => 'fa-gauge-high', 'hint' => 'Resumen general de la red ganadera.'],
            'admin/productores' => ['icon' => 'fa-cow', 'hint' => 'Busca en nombre, correo e identificación.'],
            'admin/compradores' => ['icon' => 'fa-handshake', 'hint' => 'Consulta las clasificaciones disponibles sin cambiar su política.'],
            'admin/transportistas' => ['icon' => 'fa-truck-fast', 'hint' => 'Busca en nombre, correo e identificación.'],
            'admin/vehiculos' => ['icon' => 'fa-truck-pickup', 'hint' => 'Busca en placa, VIN y modelo.'],
            'admin/publicaciones' => ['icon' => 'fa-clipboard-list', 'hint' => 'Busca en título, raza, vendedor, finca y zona.'],
            'admin/fletes' => ['icon' => 'fa-truck-ramp-box', 'hint' => 'Busca en transportista, vehículo y zona, o en publicación, comprador y vendedor.'],
            'admin/documentos' => ['icon' => 'fa-id-card', 'hint' => 'Documentos de identidad por revisar. Las pendientes, de la más antigua a la más nueva.'],
            'admin/metodos-pago' => ['icon' => 'fa-wallet', 'hint' => 'Busca en nombre y descripción.'],
            'admin/administradores' => ['icon' => 'fa-user-shield', 'hint' => 'Quién puede entrar al panel de administración.'],
            'admin/catalogos' => ['icon' => 'fa-tags', 'hint' => 'Especies, tipos, razas y vacunas de los formularios.'],
            'admin/subastas' => ['icon' => 'fa-gavel', 'hint' => 'Subastas en vivo y su historial. Solo lectura: los administradores no pujan.'],
            'admin/bitacora' => ['icon' => 'fa-clock-rotate-left', 'hint' => 'Busca por nombre, identificación o correo de quien hizo el cambio, o por el registro afectado.'],
        ];
        return $modulos[$ruta] ?? ['icon' => 'fa-table-list', 'hint' => 'Los filtros actualizan el listado sin recargar la página.'];
    }

    function admin_encabezado_icono(string $ruta): string
    {
        return '<span class="admin-page-header-icon" aria-hidden="true"><i class="fa-solid ' . htmlspecialchars(admin_modulo($ruta)['icon'], ENT_QUOTES) . '" aria-hidden="true"></i></span>';
    }

    function admin_filtros_cabeza(string $ruta): string
    {
        return '<div class="admin-filter-heading"><span class="admin-filter-heading__title"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>Filtros</span></span>'
            . '<span class="admin-filter-heading__hint">' . htmlspecialchars(admin_modulo($ruta)['hint'], ENT_QUOTES) . '</span></div>';
    }

    function admin_filtros_limpiar(): string
    {
        return '<button type="button" class="admin-clear-filters"><i class="fa-solid fa-eraser" aria-hidden="true"></i><span>Limpiar</span></button>';
    }
}
