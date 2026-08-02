# Guía de defensa

1. **¿Qué identifica al productor?** `tbproductoresIdentificacionNumero`, PK textual canónica.
2. **¿Por qué no existe participante?** La instrucción docente delimitó este avance únicamente a productores.
3. **¿Por qué no existen roles?** No forman parte del modelo simplificado solicitado.
4. **¿Dónde está el tipo?** Como columna de `tbproductores`, sin tabla catálogo.
5. **¿Cómo se relaciona la dirección?** 1:1 mediante la misma columna como PK y FK.
6. **¿Por qué dirección no tiene ID?** La identificación del productor ya distingue la única dirección.
7. **¿Dónde está finca?** En `tbproductoresfinca`; no existe `tbfinca` separada.
8. **¿Cómo admite varias fincas?** PK compuesta por identificación y nombre.
9. **¿Por qué hay FOREIGN KEY?** Evita direcciones o fincas sin productor.
10. **¿Puede cambiar la identificación?** No; es la clave primaria natural.
11. **¿Cómo se evitan variantes duplicadas?** El servidor elimina espacios y guiones y convierte letras a mayúsculas antes del INSERT.
12. **¿Qué pasa si falla la bitácora?** La transacción revierte productor, dirección y fincas.
13. **¿Cómo se elimina?** Solo se cambia estado a inactivo.
14. **¿Cómo vuelve?** PATCH reactiva la misma PK.
15. **¿Quién es el actor?** `NO_AUTENTICADO`; no se inventa un usuario.
16. **¿Cómo funciona sin recarga?** JavaScript usa `fetch()` y actualiza nodos con `textContent`.
17. **¿Cuántas tablas existen?** Exactamente cuatro.
18. **¿Qué reglas tienen las FK?** `ON UPDATE RESTRICT` y `ON DELETE RESTRICT`, porque la PK natural es inmutable y no se permiten huérfanos.
19. **¿Qué pasa si la identificación está mal digitada?** Se desactiva el registro incorrecto, se conserva su bitácora y se crea el correcto; no se cambia la PK.
20. **¿Qué intercalación usa el modelo?** La base y las cuatro tablas usan `utf8mb4_unicode_ci`, comprobado en `information_schema`.
