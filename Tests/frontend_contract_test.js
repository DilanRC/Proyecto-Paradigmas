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
has('Public/js/productores.js', "const API_URL = 'api/productores.php';", 'endpoint de productores incorrecto');
has('Public/js/productores.js', 'direccionPrincipal: {', 'el payload debe incluir direccionPrincipal');
has('Public/js/productores.js', 'identificacionNumeroOriginal', 'PUT debe conservar la identificación original');
has(
    'Application/Service/ValidacionService.php',
    /\$permitidos = \['identificacion', 'nombre', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'\];/,
    'POST/PUT deben reconocer direccionPrincipal (contrato de validación unificado)'
);
has(
    'Application/Controller/ProductorController.php',
    "$this->direccion->crear($productorId, $datos['direccion']);",
    'el alta debe persistir la dirección recibida'
);

// Compradores: vista de solo lectura desde el paso (d) (DEC-DBREADY-008).
// Comprador se deriva del comportamiento del Productor; no existe CRUD manual.
for (const panel of ['productores', 'compradores', 'transportistas', 'vehiculos', 'pagometodos']) {
    has(`Application/View/${panel}/index.php`, 'compradores.php', 'el menú perdió el enlace a Compradores');
}
assert.equal(fs.existsSync('Application/Model/Comprador.php'), false,
    'reapareció Application/Model/Comprador.php');
assert.equal(fs.existsSync('Application/Controller/CompradorController.php'), false,
    'reapareció Application/Controller/CompradorController.php');
has('Application/Controller/CompradorConsultaController.php', 'listarClasificados',
    'la consulta debe leer periodos de clasificación');
has('Public/js/compradores.js', "const API_URL = 'api/compradores.php';", 'endpoint de compradores incorrecto');
has('Public/js/compradores.js', 'clasificadoDesde', 'la lista debe mostrar desde cuándo está clasificado');
has('Public/js/compradores.js', 'personaEstado', 'la lista debe separar clasificación de disponibilidad de Persona');
hasNot('Public/js/compradores.js', 'buildCompradorPayload', 'el panel no debe volver a construir cuerpos');
hasNot('Application/View/compradores/index.php', 'id="crear-comprador"',
    'la vista no debe ofrecer alta de comprador');
hasNot('Application/View/compradores/index.php', 'id="formulario-comprador"',
    'la vista no debe tener formulario de comprador');
hasNot('Application/View/compradores/index.php', 'id="modal-desactivar"',
    'la vista no debe ofrecer desactivar la clasificación');
has('Public/api/compradores.php', "header('Allow: GET, OPTIONS');",
    'el endpoint de compradores debe declararse de solo lectura');
has('Public/api/compradores.php', "if ($metodo !== 'GET')",
    'la API debe rechazar escrituras antes de abrir la base');
has('Public/js/compradores.js', 'consultarCapacidades',
    'la ficha debe consultar las relaciones de la misma Persona');
for (const api of ['api/productores.php', 'api/compradores.php', 'api/transportistas.php']) {
    has('Public/js/shared/capacidades.js', api, `el catálogo de relaciones no apunta a ${api}`);
}
has('Public/js/shared/capacidades.js', 'derivada: true',
    'Comprador debe estar marcado como clasificación derivada');
hasNot('Public/js/shared/capacidades.js', "alias: 'vendedor'",
    'Productor no puede volver a usarse como alias de Vendedor');

// Transportistas y asignación de vehículos.
has('Public/js/transportistas.js', "const API_URL = 'api/transportistas.php';", 'endpoint de transportistas incorrecto');
has('Public/js/transportistas.js', "const ASIGNACION_URL = 'api/transportistas-vehiculos.php';", 'endpoint de asignación incorrecto');
has('Public/js/transportistas.js', 'identificacionNumeroOriginal', 'PUT debe enviar identificación original');
has(
    'Application/Service/ValidacionService.php',
    "$permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico'];",
    'contrato de campos de transportista cambió'
);
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
has('Public/js/vehiculos.js', "const API_URL = 'api/vehiculos.php';", 'endpoint de vehículos incorrecto');
has('Public/js/vehiculos.js', 'data.vehiculoId = Number(id);', 'PUT debe enviar vehiculoId');
has(
    'Application/Controller/VehiculoController.php',
    "$permitidos = ['placa', 'vin', 'modelo'];",
    'contrato de campos de vehículo cambió'
);

// Métodos de pago.
has('Public/js/pagometodos.js', "const API_URL = 'api/pagometodos.php';", 'endpoint de métodos de pago incorrecto');
has('Public/js/pagometodos.js', 'activo: true', 'el alta debe declarar el estado inicial esperado por la API');
has('Public/js/pagometodos.js', 'data.id = Number(id);', 'PUT debe enviar id');
has(
    'Application/Controller/PagoMetodoController.php',
    "$permitidos = ['nombre', 'descripcion', 'activo'];",
    'contrato de campos de método de pago cambió'
);

// Dirección de finca.
has('Public/js/productores.js', "const FINCAS_DIRECCION_URL = 'api/fincas-direccion.php';", 'endpoint de dirección de finca incorrecto');
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

console.log('OK frontend_contract_test: contratos UI/API alineados; borradores temporales activos y Comprador sigue read-only.');
