# Guía de defensa del CRUD de Productor

## Modelo e identidad

1. **¿Qué representa `tbparticipante`?** La persona física o jurídica común que
   puede asumir uno o varios roles. Solo guarda nombre, teléfono, correo de
   contacto y estado.
2. **¿Por qué productor no repite esos datos?** Productor es un rol. Copiarlos
   crearía identidades contradictorias si la misma persona compra y vende.
3. **¿Cómo compra y vende una misma persona?** El mismo `tbparticipanteId` tiene
   filas activas `PRODUCTOR` y `COMPRADOR` en `tbparticipanterol`.
4. **¿Participante y rol son lo mismo?** No. Participante es quién interviene;
   rol es qué papel desempeña.
5. **¿Qué pasa al agregar un rol?** Se agrega una asociación, no otra persona.
6. **¿Por qué identificación tiene tabla propia?** Tiene tipo, valor visible,
   valor normalizado, estado y condición de principal; además permite crecer a
   varios documentos.
7. **¿Por qué dos valores del número?** El visible conserva formato y ceros; el
   normalizado permite comparación estable.
8. **¿Por qué la unicidad incluye tipo?** El mismo texto puede pertenecer a
   sistemas documentales distintos; la identidad de negocio es tipo + número.
9. **¿Qué ocurre si el participante está inactivo?** La UK sigue reservando su
   documento y el API exige reactivar el mismo ID.
10. **¿Por qué el correo no es único?** Es contacto administrativo compartible,
    no identidad ni credencial.
11. **¿Dónde irá el correo de acceso?** En una futura `tbusuario`, con reglas de
    autenticación propias.

## Dirección y finca

12. **¿Por qué dirección está separada?** Es una entidad repetible y no un
    atributo atómico de la persona.
13. **¿Cómo se impide dos principales?** Una columna generada e índice único
    garantizan como máximo una; la transacción comprueba exactamente una.
14. **¿Dirección personal equivale a ubicación de finca?** No. Tienen semántica
    distinta y no se usa automáticamente para transporte.
15. **¿Por qué finca es independiente?** Puede relacionarse con varias personas
    y tendrá evolución propia.
16. **¿Qué cardinalidad existe?** N:M mediante `tbproductorfinca`.
17. **¿Por qué “asociación” y no “propiedad”?** No existe confirmación jurídica
    que permita afirmar titularidad.
18. **¿Por qué no IDs separados por comas?** Perderían FK, unicidad, consultas e
    integridad. Cada relación es una fila.
19. **¿Puede el CRUD crear una finca por nombre?** No. Recibe únicamente
    `fincaId` existente y activo; finca es otro catálogo/entidad.

## Transacción, concurrencia y estados

20. **¿Quién genera `tbparticipanteId`?** MySQL mediante `AUTO_INCREMENT`; las
    tablas relacionadas reutilizan ese ID como FK.
21. **¿Quién administra la transacción?** `ProductorController`, porque coordina
    varios modelos en una sola operación.
22. **¿Qué pasa si falla rol o dirección después del participante?** Se ejecuta
    `ROLLBACK`; no queda una persona parcial ni bitácora falsa.
23. **¿Cómo se evita duplicado concurrente?** La consulta previa mejora el
    mensaje y la UK MySQL resuelve la carrera como garantía final.
24. **¿Desactivar es eliminar?** No. Solo cambia el estado a `0`.
25. **¿Qué conserva un inactivo?** ID, contacto, identificación, dirección,
    roles, fincas y bitácora.
26. **¿Cómo se reactiva?** `PATCH` por ID o identificación, validando que conserve
    rol y principales; se reutiliza la fila.

## Bitácora, API e interfaz

27. **¿Por qué actor `NO_AUTENTICADO`?** No existe sesión que permita atribuir la
    acción a una persona verificable.
28. **¿Qué se conoce?** Acción, entidad, ID, fecha, datos, origen y solicitud. No
    se conoce la identidad humana.
29. **¿Una IP identifica a la persona?** No. Puede compartirse, traducirse o
    cambiar; sería solo telemetría técnica.
30. **¿Por qué no hay FK desde `tbbitacoraRegistroId`?** Es una referencia lógica
    polimórfica cuyo destino depende de `tbbitacoraEntidad`. En este avance
    apunta lógicamente a participante.
31. **¿Por qué el API no devuelve `tb...`?** El contrato de dominio permanece
    estable aunque cambie el esquema físico.
32. **¿Cómo se comprueba AJAX?** DevTools debe mostrar solicitudes `fetch` y
    ninguna navegación/document request al crear, editar, desactivar o reactivar.
33. **¿Cómo evita XSS la interfaz?** Inserta datos externos con `textContent`, no
    con HTML interpretado.
34. **¿Qué responde un error inesperado?** JSON `500` genérico; el detalle queda
    en `error_log`.

## Instalación, pruebas y respaldos

35. **¿Cómo se instala?** Copiar `.env.example`, ejecutar `docker compose up
    --build -d` y verificar `docker compose ps`.
36. **¿Dónde está cada cosa?** Esquema en `Database/SqlScripts`, semillas en
    `Database/SeedData`, respaldos en `Database/Backups`, código MVC en
    `Application`, API/JS en `Public`, pruebas en `Tests`.
37. **¿Script, semilla y respaldo son iguales?** No. El script define estructura,
    la semilla crea datos iniciales repetibles y el respaldo captura un estado de
    entrega.
38. **¿Git reemplaza al respaldo?** No. Git conserva archivos; el dump conserva
    estructura y filas del estado persistente.
39. **¿Qué contienen los tres dumps?** Completo: ambos; estructura: DDL y objetos;
    datos: filas sin creación.
40. **¿Cómo se prueba integridad del archivo?** SHA-256 detecta alteración y la
    restauración demuestra que MySQL puede leerlo.
41. **¿Por qué base temporal?** Evita destruir o mezclar la base origen y permite
    comparación lado a lado.
42. **¿Cómo se vincula con código?** El manifiesto guarda el SHA candidato y la
    etiqueta `avance-NN` apunta al paquete final.
43. **¿Qué pasa después de etiquetar?** No se modifica la entrega; una corrección
    usa otro commit, carpeta y etiqueta.
44. **¿Qué no se respalda públicamente?** Credenciales, `.env`, tokens, usuarios
    internos, datos personales reales ni futuros hashes de contraseña en semillas.

## Límites que deben declararse

No existe todavía autenticación, comprador especializado, significado jurídico
de la asociación, origen de transporte, catálogo territorial completo ni
atributos adicionales confirmados de finca. Declarar el límite es correcto;
inventar una regla no lo es.

