# Comprobaciones SQL del modelo

Scripts de solo base de datos. No sustituyen a las pruebas de `Tests/`: aquí no
hay PHP, únicamente SQL que se ejecuta contra `bdmercadoganadero`.

## Orden de ejecución

Sobre una base limpia:

```bash
docker compose up -d db
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" < Database/Tests/comprobacionestructura.sql
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" < Database/Tests/comprobaciondatosiniciales.sql
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" < Database/Tests/comprobacionrelaciones.sql
docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" < Database/Tests/diagnostico.sql
```

| Script | Qué comprueba | Resultado esperado |
|---|---|---|
| `comprobacionestructura.sql` | `DESCRIBE` de las siete tablas del avance y ausencia de llaves, índices, valores automáticos y objetos programables | estructura declarada y cero filas en las consultas de metadatos |
| `comprobaciondatosiniciales.sql` | contenido de `tbpagometodo` | una fila: `1`, `Efectivo`, `Pago realizado en efectivo`, `1` |
| `comprobacionrelaciones.sql` | productor y finca compartiendo ubicación, productor y finca en ubicaciones distintas, un productor con varias fincas, un transportista con varios vehículos | cinco resultados descritos en el script y limpieza en ceros |
| `diagnostico.sql` | duplicados, cardinalidades fuera de política y asociaciones huérfanas | cero filas en D-01 a D-09 sobre datos válidos |

`comprobacionrelaciones.sql` inserta filas con identificadores negativos y las
borra al final, de modo que no compite con los consecutivos que calcula la
aplicación ni deja datos de prueba en la base.

## Alcance

`diagnostico.sql` **detecta**, no **impide**. El esquema no declara llaves ni
restricciones, así que el motor acepta duplicados y huérfanos. Rechazarlos es
responsabilidad de la capa de aplicación y queda fuera de este avance de base
de datos.
