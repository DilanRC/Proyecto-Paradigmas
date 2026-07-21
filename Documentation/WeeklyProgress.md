# Avance semanal - CRUD de productores

El módulo de productores entregado permite crear, buscar, filtrar por estado, actualizar, desactivar y reactivar registros mediante una interfaz AJAX adaptable. Las capas MVC son `Application/Model/Producer.php`, `Application/Controller/ProducerController.php` y `Application/View/producers/index.php`. El punto de entrada HTTP es `Public/api/producers.php`.

La eliminación se implementa como una desactivación lógica, lo que conserva los datos para futuras relaciones con ganado, lotes, subastas y pujas. Las consultas preparadas de PDO protegen las operaciones de base de datos y los errores internos no se exponen al navegador.
