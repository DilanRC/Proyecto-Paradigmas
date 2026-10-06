# P3-1 · Comerciante: investigación y propuesta

Fecha: 2026-10-05 · Responsable: Jeremi · Estado: **propuesta, sin implementar** (así lo pide el plan).

El issue de la reunión del 29/09 menciona un "comerciante" que hoy no existe en la aplicación. Este documento
resume qué dice la normativa de Costa Rica, qué ya cubre el modelo actual y qué decisión hace falta antes de
programar algo.

## 1. Qué hay hoy en Ganado Cerca

- Una **Persona** puede tener tres actividades a la vez: **Comprador**, **Vendedor** (`tbproductor`, con fincas) y
  **Transportista**. Cualquier persona puede comprar y también vender.
- Para publicar un animal hace falta una **finca** propia (`tbanimalpublicacion` guarda la finca).
- "Comerciante" no aparece en el código, la base de datos ni la documentación (revisado).

## 2. Qué dice la normativa

**Ley 8799, Control de ganado bovino, prevención y sanción de su robo, hurto y receptación (2010):**

- No define una figura de "comerciante" ni de "intermediario". Define la actividad: *"Negociación y
  comercialización de ganado: toda forma de compra, venta, remate, subasta, cesión o negocio con el ganado"*
  (art. 3 h). Quien compra para revender hace esa actividad, con las mismas obligaciones que cualquier dueño.
- Sí define los **establecimientos mercantiles**: *"todo tipo de establecimiento que recepte, reciba, adquiera,
  negocie, comercialice, subaste, mate, sacrifique o desmiembre ganado"* (art. 3 i). Las subastas, plazas de
  ganado, ferias y plantas de matanza deben tener un **responsable de recibo de ganado** inscrito (art. 3 j) y
  sus datos se actualizan en el registro del certificado veterinario de operación.
- Todo movimiento por caminos públicos exige la **guía oficial de movilización** de SENASA, que es una
  declaración jurada del propietario o del responsable de los animales. También la necesita *"toda persona que
  adquiera, negocie o ingrese ganado, previamente movilizado, a un sitio de sacrificio o comercialización"*.
- SENASA debe mantener las bases de datos y los permisos *"de los actores de la cadena de movilización y
  comercialización"* (art. 4).

**Decreto 44336-MAG-S-SP-MOPT, trazabilidad bovina (SENASA):**

- Solo se pueden tener y comercializar bovinos **identificados individualmente y registrados**, en
  establecimientos registrados. Cada venta genera una guía de movilización que actualiza el inventario del
  establecimiento de origen y del de destino. Según las noticias, el sistema entra en vigencia el 26/04/2026.
- El dispositivo oficial (**DIIO**) son dos aretes: uno visual en la oreja izquierda y uno con chip RFID en la
  derecha, y su numeración lleva el **188** (código ISO de Costa Rica). Se colocan antes de los 6 meses o en el
  primer movimiento. *(Esto responde también a la pregunta abierta de P2-2 sobre el formato del arete.)*

**Conclusión:** para la ley, un comerciante de ganado **no es un tipo de actor distinto**: es alguien que compra
y vende ganado, con las mismas obligaciones de guía y trazabilidad que cualquier dueño. Lo que sí es distinto son
los **establecimientos mercantiles** (subastas, ferias, plazas de ganado), que tienen un registro y un responsable
propios.

## 3. Tres formas de entender "comerciante" en la app

| | A. Vendedor con más volumen | B. Cuarta actividad | C. Establecimiento (subasta o feria) |
|---|---|---|---|
| Qué es | Una persona que compra y revende, con fincas propias | Una actividad nueva junto a Comprador, Vendedor y Transportista | Una empresa que recibe y remata ganado de otros |
| Qué cambia en el modelo | **Nada**: ya puede ser Comprador + Vendedor | Tabla de actividad nueva (4 lugares), registro, Ajustes, admin | Entidad nueva (establecimiento con responsable de recibo), lotes, calendario de remates |
| Qué resuelve | Nada nuevo, pero no hace falta | Distinguirlo (por ejemplo, con una etiqueta en sus publicaciones) | Publicar subastas y sus lotes |
| Esfuerzo | Ninguno | Medio | Alto |

## 4. Recomendación

**A por ahora, sin programar nada.** El modelo actual ya permite que una persona compre y venda; la ley no le pide
nada distinto; y una cuarta actividad sin una regla de negocio propia sería una tabla más sin uso, como las que
P3-3 propone limpiar.

Pasar a **B** solo si el cliente define algo que el comerciante tenga y el vendedor no. Pasar a **C** solo si se
quieren publicar subastas: es otro producto y conviene tratarlo como una tarea aparte.

## 5. Preguntas para el cliente (para el issue)

1. ¿"Comerciante" es una **persona** que compra y revende, o un **establecimiento** (subasta o feria)?
2. ¿Necesita algo que un vendedor no tenga? Por ejemplo: publicar animales que no están en una finca suya,
   vender por **lotes** (ver la pregunta de lotes de P2-2), una etiqueta visible, comisiones o una verificación
   distinta.
3. ¿Hay que pedirle algún registro (el certificado veterinario de operación del establecimiento o el registro en
   SENASA) antes de dejarlo publicar?

## Fuentes

- Ley 8799 (texto en FAOLEX): https://faolex.fao.org/docs/pdf/cos94343.pdf
- Ficha de la Ley 8799 (UNEP LEAP): https://leap.unep.org/en/countries/cr/national-legislation/ley-no-8799-ley-de-control-de-ganado-bovino-prevencion-y-sancion
- Sistema Nacional de Trazabilidad Bovina y Bufalina (MAG): https://mag.go.cr/sistema-nacional-de-trazabilidad-bovina-y-bufalina-en-costa-rica/
- Ganadería y comercialización (CORFOGA, subastas), revista E-Agronegocios del TEC: https://revistas.tec.ac.cr/index.php/eagronegocios/article/view/4940/4695
