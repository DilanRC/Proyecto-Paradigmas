# Guía de defensa

1. **¿Cuántas tablas hay?** La instalación MySQL vigente tiene 48 tablas, y el espejo PostgreSQL/Supabase conserva las mismas 48 tablas.
2. **¿Hay PRIMARY KEY?** Sí, una por tabla: las 48 tablas declaran `PRIMARY KEY (<tabla>id)`. Es la única restricción del esquema.
3. **¿Hay FOREIGN KEY?** No, las asociaciones son lógicas.
4. **¿Hay CHECK o UNIQUE?** No existe ninguna de esas restricciones.
5. **¿Qué es `tbproductorid`?** La llave primaria de `tbproductor`: un `INT NOT NULL` que PHP calcula.
6. **¿Cómo obtiene su valor?** PHP mantiene un `GET_LOCK`, consulta `MAX(id)+1`, inserta y libera después de finalizar la transacción.
7. **¿Es AUTO_INCREMENT?** No en MySQL.
8. **¿Cómo se asocian dirección y finca?** Guardan `tbproductorid` sin FK.
9. **¿Cómo se conserva una dirección?** POST crea una y PUT exige exactamente una como política de aplicación.
10. **¿Cómo se evitan fincas duplicadas?** El modelo consulta, actualiza o inserta mediante sentencias preparadas.
11. **¿La identificación es una PK?** No. La PK es `tbpersonaid`; la identificación es inmutable por contrato del CRUD.
12. **¿Cómo se evita inyección SQL?** Los modelos usan `PDO::prepare()`, valores enlazados y preparadas nativas.
13. **¿Qué valida MySQL?** Tipos, nulabilidad y que no se repita la llave primaria; no aplica FK, UNIQUE ni CHECK.
14. **¿Qué riesgo queda?** SQL directo puede insertar duplicados lógicos (misma cédula con otro id), huérfanos y dominio inválido.
15. **¿Cómo se audita?** La bitácora permanece dentro de la transacción y registra las cuatro acciones.
16. **¿Cómo se protege la administración?** El frontend solo controla la navegación. Las escrituras exigen un Bearer validado por Supabase y una cuenta activa en `tbadministrador`, comprobada por PHP con una consulta preparada.
17. **¿Cómo se comprobó?** `information_schema`, pruebas PHP/Node/PDF, semillas repetidas y dos restauraciones comparadas.
