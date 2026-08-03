# 📦 Pull Request — TinderVacas

## 📋 Resumen del cambio

Describa concretamente qué modifica este Pull Request y qué problema resuelve dentro de TinderVacas.

**Ejemplo:**

> Implementa el registro, consulta, actualización y eliminación lógica de productores.  
> El cambio permite administrar las personas o empresas propietarias del ganado que participará en las subastas.

---

## 🎯 Trabajo realizado

**Módulo:** Productores / Ganado / Subastas / Pujas / Logística / Reportes

### Descripción

> Como **[programador]**, realice **[funcionalidad]**, para **[beneficio o necesidad que se satisface]**.


---

## 🧩 Cambios realizados

### ✨ Nueva funcionalidad

- 
- 

### 🐛 Corrección de errores

- 

### ♻️ Refactorización

- 

### 🗄️ Cambios en base de datos

- 
- 

### 🧪 Pruebas agregadas o actualizadas

- 
- 

### 📚 Documentación actualizada

- 
- 

---

## 🧑‍💼 Actor y problema resuelto

**Actor principal:**

- Administrador
- Productor
- Comprador
- Encargado de subasta
- Otro: __________

**Problema que existía:**

> Describa la limitación, error o necesidad anterior.

**Resultado después del cambio:**

> Explique qué puede hacer ahora el actor y qué dato se registra, consulta o modifica.

---

## 🔄 Flujo de funcionamiento

Explique el recorrido completo de los datos.

1. El usuario realiza una acción desde la vista.
2. El archivo JavaScript captura y valida los datos.
3. JavaScript envía una solicitud AJAX en formato JSON.
4. El controlador recibe la solicitud.
5. El controlador valida la operación solicitada.
6. El modelo ejecuta la consulta o modificación en MySQL.
7. La base de datos devuelve el resultado.
8. El controlador construye una respuesta JSON.
9. JavaScript procesa la respuesta.
10. La vista se actualiza sin recargar completamente la página.

### Ejemplo del dato enviado

```json
{
  "nombre": "Productor de ejemplo",
  "identificacion": "1-1111-1111",
  "telefono": "8888-8888"
}
```

### Ejemplo de respuesta esperada

```json
{
  "success": true,
  "message": "Productor registrado correctamente",
  "data": {
    "id": 15
  }
}
```

---

## 🏗 Módulos funcionales afectados

| Módulo | Afectado | Descripción |
|---|:---:|---|
| Usuarios y autenticación | ☐ | |
| Empresas | ☐ | |
| Productores | ☐ | |
| Ganado | ☐ | |
| Subastas | ☐ | |
| Pujas | ☐ | |
| Adjudicaciones | ☐ | |
| Logística | ☐ | |
| Reportes e historial | ☐ | |
| Seguridad y permisos | ☐ | |
| Otro | ☐ | |

---

## ⚙️ Capas técnicas afectadas

| Área | Afectada | Descripción |
|---|:---:|---|
| Controlador PHP | ☐ | Recibe solicitudes y coordina la operación |
| Modelo PHP | ☐ | Accede y modifica los datos |
| Vista | ☐ | Presenta la interfaz al usuario |
| JavaScript / AJAX | ☐ | Envía solicitudes y actualiza la interfaz |
| Base de datos MySQL | ☐ | Tablas, relaciones, restricciones o datos |
| Configuración | ☐ | Variables de entorno, conexión o parámetros |
| Docker | ☐ | Contenedores, puertos o servicios |
| Documentación | ☐ | Manuales, diagramas o instrucciones |
| Pruebas | ☐ | Pruebas manuales o automatizadas |

---

## 📂 Archivos relevantes

Indique solamente los archivos que realmente fueron modificados.

### Controladores

- `Aplicacion/Controlador/...`

### Modelos

- `Aplicacion/Modelo/...`

### Vistas

- `Aplicacion/Vista/...`

### JavaScript

- `Publico/js/...`

### Base de datos

- `BaseDatos/ScriptsSQL/...`
- `BaseDatos/DatosIniciales/...`

### Configuración

- `Configuracion/BaseDatos.php`
- `Configuracion/Configuracion.php`

### Pruebas

- `Pruebas/...`

### Documentación

- `Documentacion/...`

> Ajustar las rutas anteriores a los nombres exactos utilizados en el repositorio.

---

## 🗄️ Cambios en la base de datos

**¿Este PR modifica la base de datos?**

- [ ] Sí
- [ ] No

### Scripts incluidos

- Script de creación o modificación: `BaseDatos/ScriptsSQL/...`
- Datos iniciales: `BaseDatos/DatosIniciales/...`
- Respaldo utilizado: `BaseDatos/Respaldos/...`

### Tablas afectadas

| Tabla | Operación | Motivo |
|---|---|---|
| | Crear / modificar / eliminar | |
| | Crear / modificar / eliminar | |

### Relaciones afectadas

| Tabla origen | Tabla relacionada | Tipo de relación | Clave foránea |
|---|---|---|---|
| | | 1:1 / 1:N / N:M | |

### Integridad de datos

- [ ] Se definieron claves primarias.
- [ ] Se definieron claves foráneas.
- [ ] Se revisaron campos obligatorios.
- [ ] Se revisaron restricciones de unicidad.
- [ ] Se contempló eliminación lógica o física.
- [ ] Se verificó el comportamiento al actualizar registros relacionados.
- [ ] Se incluyó un procedimiento de reversión o restauración.

---

## 🌐 Solicitudes AJAX o endpoints modificados

| Método | Ruta o acción | Controlador | Entrada JSON | Respuesta |
|---|---|---|---|---|
| GET / POST / PUT / DELETE | | | | |
| GET / POST / PUT / DELETE | | | | |

### Validaciones realizadas

- [ ] Campos obligatorios.
- [ ] Tipo y formato de los datos.
- [ ] Existencia de registros relacionados.
- [ ] Duplicidad de datos.
- [ ] Autorización del usuario.
- [ ] Manejo de valores vacíos o nulos.
- [ ] Manejo de solicitudes inválidas.

---

## ⚙️ Instrucciones para probar el cambio

### Requisitos previos

- PHP configurado.
- MySQL disponible.
- Base de datos creada.
- Scripts SQL ejecutados.
- Variables de configuración definidas.
- Contenedores Docker levantados, cuando corresponda.
- Usuario de prueba disponible.

### Comandos necesarios

```bash
# Ejemplo: levantar el entorno
docker compose up -d

# Ejemplo: verificar contenedores
docker compose ps
```

### Datos de prueba

**Usuario:**

```text
Correo:
Contraseña:
Rol:
```

**Registros requeridos:**

```text
Productor:
Ganado:
Subasta:
Otro:
```

### Pasos de prueba

1. Iniciar el proyecto.
2. Ingresar con el usuario de prueba.
3. Acceder al módulo afectado.
4. Ejecutar la operación implementada.
5. Verificar la respuesta enviada por el servidor.
6. Confirmar que la interfaz se actualiza sin recargar la página.
7. Revisar el registro correspondiente en MySQL.
8. Intentar la operación con datos inválidos.
9. Verificar el comportamiento cuando el registro no existe.
10. Confirmar que no aparezcan errores en la consola del navegador.

---

## ✅ Resultado esperado

### Caso normal

- 
- 

### Caso límite

- 
- 

### Caso de error

- 
- 

### Verificación en base de datos

```sql
-- Consulta utilizada para comprobar el resultado
SELECT *
FROM nombre_tabla
WHERE id = 0;
```

---

## 📸 Evidencias

| # | Descripción de la evidencia | Imagen o enlace |
|---:|---|---|
| 1 | Vista inicial del módulo | [Ver captura](./Documentacion/Evidencias/01-vista-inicial.png) |
| 2 | Datos enviados mediante AJAX | [Ver captura](./Documentacion/Evidencias/02-solicitud-json.png) |
| 3 | Respuesta JSON del controlador | [Ver captura](./Documentacion/Evidencias/03-respuesta-json.png) |
| 4 | Resultado mostrado sin recargar la página | [Ver captura](./Documentacion/Evidencias/04-resultado.png) |
| 5 | Registro almacenado en MySQL | [Ver captura](./Documentacion/Evidencias/05-base-datos.png) |
| 6 | Validación o manejo de error | [Ver captura](./Documentacion/Evidencias/06-validacion.png) |

---

## 🧪 Pruebas realizadas

- [ ] Prueba manual del flujo completo.
- [ ] Prueba de creación.
- [ ] Prueba de consulta.
- [ ] Prueba de actualización.
- [ ] Prueba de eliminación.
- [ ] Prueba con campos vacíos.
- [ ] Prueba con datos duplicados.
- [ ] Prueba con identificador inexistente.
- [ ] Prueba de permisos o sesión.
- [ ] Prueba de respuesta JSON.
- [ ] Prueba sin recarga completa de página.
- [ ] Prueba de integración con MySQL.
- [ ] Prueba en el entorno Docker.
- [ ] Prueba realizada por otro integrante.

---

## 🔐 Seguridad

- [ ] Las consultas utilizan parámetros preparados.
- [ ] No se concatenan directamente valores recibidos en SQL.
- [ ] Las entradas son validadas en el servidor.
- [ ] Las salidas visibles se escapan cuando corresponde.
- [ ] Se verifica la sesión del usuario.
- [ ] Se verifica el rol o permiso requerido.
- [ ] No se exponen contraseñas ni credenciales.
- [ ] No se incluyeron secretos en el repositorio.
- [ ] Los mensajes de error no revelan información sensible.

---

## ⚠️ Riesgos o impactos

- [ ] Modifica la estructura de la base de datos.
- [ ] Requiere ejecutar un script SQL.
- [ ] Puede afectar otros módulos.
- [ ] Modifica contratos JSON existentes.
- [ ] Cambia rutas o acciones utilizadas por JavaScript.
- [ ] Puede afectar datos existentes.
- [ ] Puede generar problemas de concurrencia.
- [ ] Puede afectar el rendimiento.
- [ ] Requiere actualizar Docker.
- [ ] No se identificaron impactos relevantes.

### Descripción del riesgo

> Explique qué podría fallar, qué componente sería afectado y bajo qué condición.

### Medida de mitigación

> Explique cómo se previene, detecta o revierte el problema.

---

## 🔗 Dependencias con otros cambios

**Este PR depende de:**

- PR:
- Historia de usuario:
- Script SQL:
- Configuración:

**Otros módulos que deben probarse:**

- 
- 

---


## 👀 Revisor sugerido

@nombre-del-integrante

**Aspectos que debe revisar especialmente:**

- 
- 

---

## 📅 Avance

**Avance:** Avance N  
**Trabajo de usuario:** TV-XX  
**Responsable:** @usuario  
**Fecha de inicio:** DD/MM/AAAA  
**Fecha prevista de cierre:** DD/MM/AAAA
