# Documentación del proyecto

Fuentes Markdown de los entregables del CRUD de Productor:

- `Decisiones.md`: DEC-001 a DEC-008 y puntos no confirmados.
- `DER.md`: modelo entidad-relación, claves, unicidad y cardinalidades.
- `DiccionarioDatos.md`: definición completa de tablas y columnas.
- `DAplicacion.md`: capas MVC, contrato y flujo transaccional.
- `AvanceSemanal.md`: alcance y estado comprobable del avance.
- `EvidenciasPruebas.md`: plantilla para registrar resultados reales.
- `Respaldos.md`: protocolo de exportación, integridad y restauración.
- `GuiaDefensa.md`: preguntas y respuestas para la defensa individual.

`WeeklyProgress.md` se conserva como referencia histórica del estado anterior y
no describe el modelo refactorizado.

## Exportación

Los PDF son artefactos derivados y no se generan ni versionan en este cambio.
Para una entrega que los exija:

```bash
pandoc Documentation/AvanceSemanal.md -o Documentation/AvanceSemanal.pdf
pandoc Documentation/DAplicacion.md -o Documentation/DAplicacion.pdf
pandoc Documentation/DER.md -o Documentation/DER.pdf
```

Revise los diagramas Mermaid en un renderizador compatible antes de exportar.
