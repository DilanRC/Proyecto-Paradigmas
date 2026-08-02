# Diagrama de aplicación

```text
Vista de productores
  -> JavaScript (Public/js/productores.js)
  -> fetch/AJAX con JSON
  -> Public/api/productores.php
  -> ProductorController
  -> Productor + ProductorDireccion + ProductorFinca + Bitacora
  -> MySQL (dbtindercows)
  -> respuesta HTTP JSON
  -> JavaScript actualiza el DOM sin recargar la página
```

La vista captura y presenta datos. JavaScript serializa la solicitud y usa
`fetch()`. El endpoint exige JSON, entrega el trabajo al controlador y siempre
responde con JSON, incluidos los errores. El controlador valida y abre una
transacción; los modelos usan `PDO::prepare()` y parámetros enlazados contra
las cuatro tablas. Ningún valor recibido por HTTP se concatena al SQL. Tras
la respuesta, JavaScript actualiza los nodos del DOM con `textContent` sin una
recarga completa.
