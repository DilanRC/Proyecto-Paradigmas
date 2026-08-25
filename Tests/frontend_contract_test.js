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

function lacks(path, pattern, message) {
    const source = read(path);
    const found = pattern instanceof RegExp ? pattern.test(source) : source.includes(pattern);
    assert(!found, `${path}: ${message}`);
}

function missing(path, message) {
    assert(!fs.existsSync(path), `${path}: ${message}`);
}

// Productores: el formulario exige dirección y el POST debe aceptarla/persistirla.
has('Public/js/productores.js', "const API_URL = 'api/productores.php';", 'endpoint de productores incorrecto');
has('Public/js/productores.js', 'direccionPrincipal: {', 'el payload debe incluir direccionPrincipal');
has('Public/js/productores.js', 'identificacionNumeroOriginal', 'PUT debe conservar la identificación original');
has(
    'Application/Controller/ProductorController.php',
    /\$permitidos = \['identificacion', 'nombre', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'\];/,
    'POST/PUT deben reconocer direccionPrincipal'
);
has(
    'Application/Controller/ProductorController.php',
    "$this->direccion->crear($productorId, $datos['direccion']);",
    'el alta debe persistir la dirección recibida'
);

// Tramo 7: el módulo retirado no debe reaparecer en los paneles activos.
for (const panel of ['productores', 'transportistas', 'vehiculos', 'pagometodos']) {
    lacks(`Application/View/${panel}/index.php`, 'compradores.php', 'el menú conserva el enlace retirado');
}
missing('Application/View/compradores', 'la vista retirada volvió a existir');
missing('Public/compradores.php', 'la ruta pública retirada volvió a existir');
missing('Public/js/compradores.js', 'el JavaScript retirado volvió a existir');

// Transportistas y asignación de vehículos.
has('Public/js/transportistas.js', "const API_URL = 'api/transportistas.php';", 'endpoint de transportistas incorrecto');
has('Public/js/transportistas.js', "const ASIGNACION_URL = 'api/transportistas-vehiculos.php';", 'endpoint de asignación incorrecto');
has('Public/js/transportistas.js', 'identificacionNumeroOriginal', 'PUT debe enviar identificación original');
has(
    'Application/Controller/TransportistaController.php',
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

console.log('OK frontend_contract_test: contratos UI/API alineados en productores, pagos, transportistas, vehículos y direcciones de finca; Compradores continúa retirado.');
