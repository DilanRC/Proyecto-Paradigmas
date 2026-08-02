# Respaldo de línea base

- Proyecto: TinderCows
- Base de datos: `tinder_cows`
- Estado: anterior al refactor de participante y roles
- Fecha y hora: 2026-08-01 18:38 -06:00
- Motor de origen: MySQL 8.0.46
- Rama de origen: `main`
- Commit de origen: `e40dedc9a63123245e187654d2d26f76b4e08e8c`
- Responsable de exportación: equipo TinderCows
- Archivo completo: `tinder_cows_linea_base_completo.sql`
- Archivo de estructura: `tinder_cows_linea_base_estructura.sql`
- Archivo de datos: `tinder_cows_linea_base_datos.sql`
- Restauración probada: Sí
- Base temporal utilizada: `tinder_cows_restore_test`
- Resultado de integridad: correcto, 1 tabla y 2 productores tanto en origen como en restauración
- Copia física preventiva del volumen: `/tmp/tindercows-linea-base/mysql-data-before-refactor.tar.gz`
- SHA-256 de la copia física: `a6930caabc943a01ad548a31132cefd206befc34b88fd0c012b59f23001072eb`
- Datos: académicos y ficticios con dominio reservado `example.test`

La copia física en `/tmp` no se versiona. Los tres SQL y sus sumas sí forman la evidencia restaurable de la línea base. El volumen original `proyecto-paradigmas_mysql_data` no fue eliminado.
