# Guía de defensa

1. **¿Cuántas tablas hay?** Cuatro, todas con nombre singular.
2. **¿Hay PRIMARY KEY?** No, el esquema tiene cero PK.
3. **¿Hay FOREIGN KEY?** No, las asociaciones son lógicas.
4. **¿Hay CHECK o UNIQUE?** No existe ninguna de esas restricciones.
5. **¿Qué es `tbproductorid`?** Un `INT NOT NULL` ordinario.
6. **¿Cómo obtiene su valor?** PHP mantiene un `GET_LOCK`, consulta `MAX(id)+1`, inserta y libera después de finalizar la transacción.
7. **¿Es AUTO_INCREMENT?** No en MySQL.
8. **¿Cómo se asocian dirección y finca?** Guardan `tbproductorid` sin FK.
9. **¿Cómo se conserva una dirección?** POST crea una y PUT exige exactamente una como política de aplicación.
10. **¿Cómo se evitan fincas duplicadas?** El modelo consulta, actualiza o inserta mediante sentencias preparadas.
11. **¿La identificación es una PK?** No. Es inmutable por contrato del CRUD.
12. **¿Cómo se evita inyección SQL?** Los modelos usan `PDO::prepare()`, valores enlazados y preparadas nativas.
13. **¿Qué valida MySQL?** Tipos y nulabilidad; no aplica PK, FK, UNIQUE ni CHECK.
14. **¿Qué riesgo queda?** SQL directo puede insertar duplicados, huérfanos y dominio inválido.
15. **¿Cómo se audita?** La bitácora permanece dentro de la transacción y registra las cuatro acciones.
16. **¿Cómo se comprobó?** `information_schema`, pruebas PHP/Node/PDF, semillas repetidas y dos restauraciones comparadas.
