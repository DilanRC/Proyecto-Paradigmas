const fs = require('node:fs');
const assert = require('node:assert');

function read(path) {
    return fs.readFileSync(path, 'utf8');
}

function has(path, pattern, message) {
    const source = read(path);
    const ok = pattern instanceof RegExp ? pattern.test(source) : source.includes(pattern);
    assert(ok, `${path}: ${message}`);
}

/** Lo contrario de has(): el archivo NO debe contener el patrón. */
function hasNot(path, pattern, message) {
    const source = read(path);
    const ok = pattern instanceof RegExp ? pattern.test(source) : source.includes(pattern);
    assert(!ok, `${path}: ${message}`);
}

// Borradores temporales pedidos por Calidad: los CRUD con identidad estable se
// conectan desde form.js, usan sessionStorage y solo limpian al cerrar tras 2xx.
has('Public/js/shared/form-draft.js', 'sessionStorage',
    'los borradores deben vivir en sessionStorage, no ser permanentes');
has('Public/js/shared/form-draft.js', "const PREFIX = 'tindercows:draft';",
    'las claves de borrador deben tener namespace propio');
has('Public/js/shared/form-draft.js', "IDENTITY_FIELDS = ['identificacionNumeroOriginal', 'vehiculoId', 'id']",
    'crear/editar deben separarse por la identidad estable del recurso');
hasNot('Public/js/shared/form-draft.js', 'localStorage',
    'un borrador temporal no debe sobrevivir como almacenamiento permanente');
has('Public/js/shared/form.js', "from './form-draft.js';",
    'todos los formularios ligados deben activar la capa compartida de borradores');
has('Public/js/shared/form.js', 'enableFormDraft(form);',
    'bindFormErrors debe activar el borrador de los CRUD');
has('Public/js/shared/form.js', 'clearFormDraftAfterSuccessfulClose(form);',
    'el borrador debe borrarse únicamente al terminar un guardado exitoso');
for (const [vista, campo] of [
    ['productores', 'identificacionNumeroOriginal'],
    ['transportistas', 'identificacionNumeroOriginal'],
    ['vehiculos', 'vehiculoId'],
    ['pagometodos', 'name="id"'],
]) {
    has(`Application/View/${vista}/index.php`, campo,
        `${vista}: falta el contexto estable que separa borrador crear/editar`);
}

// Productores: el formulario exige dirección y el POST debe aceptarla/persistirla.
has('Public/js/productores.js', "const API_URL = 'api/v1/productores';", 'endpoint de productores incorrecto');
has('Public/js/productores.js', 'direccionPrincipal: {', 'el payload debe incluir direccionPrincipal');
has('Public/js/productores.js', 'identificacionNumeroOriginal', 'PUT debe conservar la identificación original');
has(
    'Application/Service/ValidacionService.php',
    /\$permitidos = \['identificacion', 'nombre', 'alias', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'\];/,
    'POST/PUT deben reconocer dirección y alias (contrato de validación unificado)'
);
has(
    'Application/Controller/ProductorController.php',
    "$this->direccion->crear($productorId, $datos['direccion']);",
    'el alta debe persistir la dirección recibida'
);

// Compradores: contexto de Persona con escritura idempotente (DEC-28/29).
// El panel conserva su vista de solo lectura (no construye cuerpos); la
// persistencia vuelve a la API con POST inscribir, DELETE desactivar y PATCH
// reactivar.
for (const panel of ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos']) {
    has(`Application/View/${panel}/index.php`, 'admin/compradores', 'el menú perdió el enlace a Compradores');
}
has('Application/Model/Comprador.php', 'tbcomprador', 'el modelo de contexto Comprador debe existir');
has('Application/Controller/CompradorController.php', 'class CompradorController', 'el controlador de Comprador debe existir');
assert.throws(
    () => fs.readFileSync('Application/Controller/CompradorConsultaController.php', 'utf8'),
    'no debe quedar el controlador de consulta de la etapa de solo lectura'
);
hasNot('Application/View/compradores/index.php', 'id="crear-comprador"',
    'la vista no debe ofrecer alta manual de comprador');
hasNot('Application/View/compradores/index.php', 'id="formulario-comprador"',
    'la vista no debe tener formulario de comprador (solo lectura)');
hasNot('Application/View/compradores/index.php', 'id="modal-desactivar"',
    'la vista no debe ofrecer desactivar desde el panel');
has('Public/js/compradores.js', "const API_URL = 'api/v1/compradores';", 'endpoint de compradores incorrecto');
hasNot('Public/js/compradores.js', 'buildCompradorPayload', 'el panel conserva la lectura; no construye cuerpos');
has('Public/js/compradores.js', 'consultarCapacidades',
    'la ficha debe consultar las relaciones de la misma Persona');
has('Public/api/compradores.php', "\$permitidos = ['GET', 'POST', 'DELETE', 'PATCH'];",
    'la API debe permitir inscribir, desactivar y reactivar el contexto');
has('Public/api/compradores.php', "header('Allow: GET, POST, DELETE, PATCH, OPTIONS');",
    'el Allow debe declarar los verbos del contexto');
has('Public/api/compradores.php', 'readJsonBody()',
    'POST/DELETE/PATCH deben leer cuerpo JSON');
has('Public/api/compradores.php', 'CompradorController',
    'el endpoint debe delegar en el controlador del contexto');
for (const api of ['api/v1/productores', 'api/v1/compradores', 'api/v1/transportistas']) {
    has('Public/js/shared/capacidades.js', api, `el catálogo de relaciones no apunta a ${api}`);
}
has('Public/js/shared/capacidades.js', 'derivada: false',
    'los tres contextos son capacidades registrables; ninguno es derivado');
hasNot('Public/js/shared/capacidades.js', 'derivada: true',
    'Comprador no puede revertirse a clasificación derivada');
hasNot('Public/js/shared/capacidades.js', "alias: 'vendedor'",
    'Productor no puede volver a usarse como alias de Vendedor');

// Transportistas y asignación de vehículos.
has('Public/js/transportistas.js', "const API_URL = 'api/v1/transportistas';", 'endpoint de transportistas incorrecto');
has('Public/js/transportistas.js', "const ASIGNACION_URL = 'api/v1/transportistas/vehiculos';", 'endpoint de asignación incorrecto');
has('Public/js/transportistas.js', 'identificacionNumeroOriginal', 'PUT debe enviar identificación original');
has('Application/Service/ValidacionService.php', 'public function validarPersona(',
    'transportista debe reutilizar la validación común de Persona');
has(
    'Application/Controller/TransportistaVehiculoController.php',
    "['identificacionNumero', 'vehiculoId']",
    'asignar/reasignar debe recibir identificación y vehículo'
);
has(
    'Application/Controller/TransportistaVehiculoController.php',
    "$this->rechazarCamposDesconocidos($cuerpo, ['vehiculoId']);",
    'desasignar debe recibir solo vehiculoId'
);

// Vehículos.
has('Public/js/vehiculos.js', "const API_URL = 'api/v1/vehiculos';", 'endpoint de vehículos incorrecto');
has('Public/js/vehiculos.js', 'data.vehiculoId = Number(id);', 'PUT debe enviar vehiculoId');
has(
    'Application/Controller/VehiculoController.php',
    "$permitidos = ['placa', 'vin', 'modelo'];",
    'contrato de campos de vehículo cambió'
);

// Métodos de pago.
has('Public/js/pagometodos.js', "const API_URL = 'api/v1/metodos-pago';", 'endpoint de métodos de pago incorrecto');
has('Public/js/pagometodos.js', 'activo: true', 'el alta debe declarar el estado inicial esperado por la API');
has('Public/js/pagometodos.js', 'data.id = Number(id);', 'PUT debe enviar id');
has(
    'Application/Controller/PagoMetodoController.php',
    "$permitidos = ['nombre', 'descripcion', 'activo'];",
    'contrato de campos de método de pago cambió'
);

// Dirección de finca.
has('Public/js/productores.js', "const FINCAS_DIRECCION_URL = 'api/v1/fincas/direccion';", 'endpoint de dirección de finca incorrecto');
has('Public/js/productores.js', 'direccionFinca: {', 'el payload de finca debe incluir direccionFinca');
has(
    'Application/Controller/FincaController.php',
    "['identificacionNumero', 'nombreFinca', 'direccionFinca']",
    'POST/PUT de dirección de finca deben compartir los mismos campos que la UI'
);
has(
    'Application/Controller/FincaController.php',
    "['identificacionNumero', 'nombreFinca']",
    'DELETE de dirección de finca debe identificar productor y finca'
);

console.log('OK frontend_contract_test: contratos UI/API alineados; borradores temporales activos; capacidades de Persona y paneles administrativos coherentes.');
