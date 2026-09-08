# DEC-CALIDAD-HISTORICOS-2026-09-07

**Estado:** vigente para `dev` durante Avance 2  
**Evidencia:** reunión con Cristian Brenes G. sobre históricos de entidades madre y confiabilidad.

## Conclusión

Para cada entidad madre se evalúa atributo por atributo qué cambio tiene relevancia de negocio. Cada atributo aprobado como histórico obtiene su propia tabla histórica. No se historiza por costumbre ni se sustituye la bitácora por el histórico.

## Persona, Productor y Comprador

`tbpersona` conserva la identidad única y el contacto compartido. `tbproductor` y `tbcomprador` relacionan esa Persona con su contexto de negocio.

Calidad confirmó para Productor y Comprador:

- identificación: sin histórico;
- tipo de identificación: sin histórico;
- nombre: sin histórico;
- teléfono: **sí histórico**;
- correo electrónico: sin histórico para esta regla;
- `alias`: atributo relevante de Persona y debe almacenarse.

## Histórico de teléfono

Se usan tablas separadas:

- `tbproductorpersonatelefonohistorico`;
- `tbcompradorpersonatelefonohistorico`.

Cada una contiene solamente:

1. ID ordinario calculado por PHP;
2. ID de la entidad relacionada, como relación conceptual sin FOREIGN KEY del motor;
3. número nuevo;
4. fecha DATETIME calculada por PHP.

No contienen estado, fecha fin, trigger, DEFAULT ni lógica automática del motor.

## Mecanismo

Cuando cambia `tbpersonatelefono`, PHP bloquea la Persona y ejecuta una transacción. Antes de modificar el valor vigente registra el número nuevo en cada contexto que la Persona posea: Productor y/o Comprador. Si falla cualquiera de las escrituras, la transacción debe revertirse completa.

La implementación está centralizada en `Application/Model/PersonaTelefonoHistorico.php` y es invocada por `Application/Model/Persona.php`.

## Confiabilidad

El histórico se conserva para poder derivar información como la frecuencia de cambios de teléfono. La política que traduzca esa frecuencia a un índice de confianza o recomendación sigue pendiente; no se inventan umbrales en MySQL ni en PHP sin aprobación.

## Clasificaciones comerciales existentes

`tbproductorclasificacionperiodo` permanece temporalmente porque `dev` ya tiene consumidores comerciales construidos sobre ella. Desde esta decisión no debe presentarse como sustituto de `tbcomprador` ni como identidad de la Persona. Su retiro o reinterpretación completa exige migrar primero todos los consumidores, pruebas y datos sin eliminar el único productor de un hecho necesario.

## Base de datos

Se mantiene la regla docente del proyecto: cero PRIMARY KEY, FOREIGN KEY, UNIQUE, CHECK, AUTO_INCREMENT, índices, triggers, procedimientos, eventos y DEFAULT automáticos. Relaciones, unicidad, IDs, validación, concurrencia y políticas se resuelven en PHP mediante sentencias preparadas, locks y transacciones cuando correspondan.

## Pendientes

- evaluar uno por uno los atributos de las demás entidades madre antes de crear nuevos históricos;
- definir con Calidad la política concreta del índice de confiabilidad;
- migrar de forma controlada los consumidores que todavía interpretan COMPRADOR únicamente desde `tbproductorclasificacionperiodo`;
- mantener DER y diccionario de datos sincronizados con esta decisión.
