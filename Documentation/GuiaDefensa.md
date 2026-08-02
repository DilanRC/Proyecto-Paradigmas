# Guía de defensa

1. **¿Cuál es la única PRIMARY KEY?** `tbproductores.tbproductoresIdentificacionNumero`.
2. **¿Hay otras PRIMARY KEY?** No. Dirección, finca y bitácora no tienen PK.
3. **¿Hay FOREIGN KEY?** No existe ninguna FK en el esquema.
4. **¿Por qué se repite la identificación?** Es una referencia lógica usada por la aplicación, no una restricción MySQL.
5. **¿Cómo se mantiene una dirección?** POST y PUT verifican el conteo dentro de la transacción.
6. **¿Cómo admite varias fincas?** Varias filas comparten la identificación; no existe PK compuesta.
7. **¿Cómo evita fincas duplicadas la aplicación?** Valida nombres y sincroniza mediante SELECT, UPDATE e INSERT.
8. **¿`tbbitacoraId` es PK?** No. Es AUTO_INCREMENT con un índice ordinario requerido por MySQL.
9. **¿Puede cambiar la identificación?** No; PUT la rechaza.
10. **¿Cómo se elimina un productor?** Cambiando su estado, sin borrado físico.
11. **¿Cómo se reactiva?** PATCH reutiliza la misma identificación.
12. **¿Quién registra la bitácora?** `NO_AUTENTICADO`, con `tbusuarioId = NULL`.
13. **¿Qué pasa si falla la bitácora?** La transacción revierte toda la operación.
14. **¿Cuántas tablas hay?** Exactamente cuatro.
15. **¿Qué intercalación usan?** `utf8mb4_unicode_ci`.
16. **¿Qué riesgo existe sin FK/PK adicionales?** SQL directo puede crear huérfanos o duplicados; la aplicación mantiene sus propias políticas.
