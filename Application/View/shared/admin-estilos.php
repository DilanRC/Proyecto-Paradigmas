<?php require_once __DIR__ . '/admin-marco.php'; ?>
<?php
// Estilos del marco admin desde el primer pintado (antes los inyectaba shared/admin-ui.js al cargar el módulo y el
// panel saltaba: CLS 0,2-0,8). Las mismas URL que admin-ui.js, que solo los agrega si faltan (marcas data-tc-admin-*).
// El estado del sidebar colapsado se aplica aquí, antes del primer pintado, para que no se anime al entrar.
?>
    <link rel="stylesheet" href="css/admin-v3.css?v=admin-10" data-tc-admin-v3>
    <link rel="stylesheet" href="css/admin-sidebar-collapse.css?v=sidebar-1" data-tc-admin-sidebar>
    <link rel="stylesheet" href="css/admin-refinements.css?v=admin-18" data-tc-admin-refinements>
    <script>try{document.documentElement.dataset.tcSidebar=localStorage.getItem('tindercows:admin-sidebar-collapsed')==='true'?'collapsed':'expanded'}catch(e){}</script>
