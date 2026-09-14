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

function hasNot(path, pattern, message) {
    const source = read(path);
    const ok = pattern instanceof RegExp ? pattern.test(source) : source.includes(pattern);
    assert(!ok, `${path}: ${message}`);
}

// Borradores temporales de CRUD administrativos.
has('Public/js/shared/form-draft.js', 'sessionStorage',
    'los borradores deben vivir en sessionStorage, no ser permanentes');
has('Public/js/shared/form-draft.js', "const PREFIX = 'tindercows:draft';",
    'las claves de borrador deben tener namespace propio');
hasNot('Public/js/shared/form-draft.js', 'localStorage',
    'un borrador temporal no debe sobrevivir como almacenamiento permanente');
has('Public/js/shared/form.js', "from './form-draft.js';",
    'los CRUD ligados deben activar la capa compartida de borradores');
has('Public/js/shared/form.js', 'enableFormDraft(form);',
    'bindFormErrors debe activar el borrador de los CRUD');

// Productores: identidad compartida, dirección principal y fincas independientes.
has('Public/js/productores.js', "const API_URL = 'api/productores.php';", 'endpoint de productores incorrecto');
has('Public/js/productores.js', 'direccionPrincipal: {', 'el payload debe incluir direccionPrincipal');
has('Public/js/productores.js', 'identificacionNumeroOriginal', 'PUT debe conservar la identificación original');
has('Public/js/productores.js', "const FINCAS_DIRECCION_URL = 'api/fincas-direccion.php';",
    'la edición puntual de una finca existente debe conservar su endpoint aprobado');
hasNot('Public/js/productores.js', 'fincaId', 'el frontend no debe inventar un id de finca');
has('Application/View/productores/index.php', 'id="fincas-cards"',
    'las fincas deben editarse como elementos independientes');
has('Application/View/productores/index.php', 'id="agregar-finca-admin"',
    'debe existir una acción explícita para agregar finca');
has('Application/View/productores/index.php', 'id="fincas-nombres"',
    'se conserva el control puente por nombre para no romper el contrato PHP');
has('Public/js/productores-fincas-ui.js', 'syncHidden()',
    'las tarjetas deben sincronizar el payload existente');
has('Public/js/productores-fincas-ui.js', 'Dirección y punto exacto',
    'cada finca debe exponer su propia dirección y el punto exacto opcional desde Agregar finca');
has('Public/js/productores-fincas-ui.js', 'crearSelectorPuntoFinca',
    'el admin debe reutilizar el selector cartográfico compartido de finca');
has('Public/js/productores.js', 'fincas: parseFincaDrafts(fincas).map(buildFincaPayload)',
    'el mismo comando de Productor debe transportar cada dirección opcional de finca');
hasNot('Public/js/productores.js', 'persistirDireccionesFinca',
    'no debe existir una segunda fase HTTP que pueda dejar Productor y direcciones parcialmente guardados');
has('Application/Controller/ProductorController.php', 'private FincaDireccion $direccionFinca;',
    'ProductorController debe coordinar la dirección de finca dentro de su unidad de trabajo');
has('Application/Controller/ProductorController.php', '$this->sincronizarDireccionesFinca(',
    'POST/PUT de Productor deben persistir las direcciones incluidas en el mismo payload');
has('Application/Controller/ProductorController.php', 'ejecutarConBloqueoEnlaceAlta',
    'los locks de enlace de finca deben envolver la transacción coordinada');
has('Application/Service/ValidacionService.php', "'fincasDetalle' => $fincasDetalle",
    'la validación debe conservar el detalle estructurado de las fincas para la transacción');
has(
    'Application/Service/ValidacionService.php',
    "$permitidos = ['identificacion', 'nombre', 'alias', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'];",
    'Productor debe reconocer alias, dirección y fincas'
);
has(
    'Application/Controller/FincaController.php',
    "['identificacionNumero', 'nombreFinca', 'direccionFinca']",
    'POST/PUT de dirección de finca deben identificar Persona, finca y dirección'
);
has('Application/Controller/FincaController.php', "'latitud' => null, 'longitud' => null",
    'el punto exacto de finca debe ser opcional y validado por PHP');

// Comprador: contexto independiente sobre Persona, pero sin CRUD administrativo manual.
assert(fs.existsSync('Application/Model/Comprador.php'), 'debe existir el contexto Comprador');
has('Application/Controller/CompradorConsultaController.php', 'use Application\\Model\\Comprador;',
    'la consulta debe usar el modelo Comprador');
has('Application/Controller/CompradorConsultaController.php', "'tbcomprador + tbpersona'",
    'la fuente de Comprador debe ser su contexto más Persona');
has('Public/js/compradores.js', "const API_URL = 'api/compradores.php';", 'endpoint de compradores incorrecto');
hasNot('Public/js/compradores.js', 'buildCompradorPayload', 'el panel administrativo no debe construir altas manuales');
hasNot('Application/View/compradores/index.php', 'id="crear-comprador"',
    'la vista administrativa no debe ofrecer alta manual de Comprador');
has('Public/js/shared/capacidades.js', /clave:\s*'comprador'[\s\S]{0,300}derivada:\s*false/,
    'Comprador debe ser un contexto independiente, no derivado de Productor');
hasNot('Public/js/shared/capacidades.js', 'derivada: true',
    'ningún contexto de negocio debe presentarse como derivado');

// Transportistas, vehículos y métodos de pago mantienen sus contratos existentes.
has('Public/js/transportistas.js', "const API_URL = 'api/transportistas.php';", 'endpoint de transportistas incorrecto');
has('Public/js/transportistas.js', "const ASIGNACION_URL = 'api/transportistas-vehiculos.php';", 'endpoint de asignación incorrecto');
has('Public/js/transportistas.js', 'identificacionNumeroOriginal', 'PUT debe enviar identificación original');
has(
    'Application/Service/ValidacionService.php',
    "$permitidos = ['identificacion', 'nombre', 'alias', 'telefono', 'correoElectronico'];",
    'el contrato compartido de Persona debe incluir alias'
);
has('Public/js/vehiculos.js', "const API_URL = 'api/vehiculos.php';", 'endpoint de vehículos incorrecto');
has('Public/js/vehiculos.js', 'data.vehiculoId = Number(id);', 'PUT debe enviar vehiculoId');
has('Public/js/pagometodos.js', "const API_URL = 'api/pagometodos.php';", 'endpoint de métodos de pago incorrecto');

// Front 2.0: registro guiado y retorno al proceso que originó el onboarding.
has('Public/js/shared/business-rules.js', "COMPRADOR: Object.freeze({", 'falta la regla de Comprador');
has('Public/js/shared/business-rules.js', "PRODUCTOR: Object.freeze({", 'falta la regla de Productor');
has('Public/js/shared/business-rules.js', "TRANSPORTISTA: Object.freeze({", 'falta la regla de Transportista');
has('Public/js/shared/business-rules.js', 'requiresAtLeastOneFinca: true',
    'Productor debe pedir al menos una finca en onboarding');
has('Public/js/shared/business-rules.js', 'vehicleRequiredAtRegistration: false',
    'Transportista no debe exigir vehículo durante registro inicial');
has('Public/js/registro.js', "const SAFE_NEXT = new Set(['explorar.php', 'mi-actividad.php', 'fletes.php', 'publicar.php']);",
    'el onboarding debe volver únicamente a destinos públicos aprobados');
has('Public/js/registro.js', 'existingProfile?.persona',
    'al ampliar una actividad debe reutilizarse la misma Persona');
has('Public/js/registro.js', "base.filter((step) => step !== 'persona')",
    'una Persona existente no debe repetir la captura de identidad');
has('Public/js/registro.js', 'crearSelectorPuntoFinca',
    'el usuario debe poder marcar opcionalmente el punto exacto al agregar una finca');

// Ubicación automática y recomendación por cercanía.
has('Public/js/shared/ubicacion-sesion.js', "const UBICACION_USUARIO_KEY = 'tindercows:ubicacion-usuario'",
    'la posición del visitante debe tener almacenamiento temporal propio');
has('Public/js/explore.js', 'leerUbicacionUsuario()',
    'Explorar debe reutilizar la posición temporal del visitante');
has('Public/js/explore.js', "parametros.set('latitud'",
    'Explorar debe enviar latitud al ranking cuando exista');
has('Application/Service/PublicacionCercaniaService.php', 'calcularDistanciaKm',
    'el backend debe calcular la cercanía en PHP');
hasNot('Public/js/shared/auth-gate.js', 'api/productores-ubicacion.php',
    'la ubicación automática de sesión no debe crear históricos de Productor');

// Publicar: gate Productor y borrador honesto mientras no exista POST aprobado.
assert(fs.existsSync('Public/publicar.php'), 'falta la ruta pública Publicar');
assert(fs.existsSync('Application/View/publicar/index.php'), 'falta la vista pública Publicar');
has('Public/js/publicar.js', "capabilityState(profile, 'PRODUCTOR')", 'Publicar debe exigir contexto Productor');
has('Public/js/publicar.js', 'registro.php?capacidad=PRODUCTOR&next=publicar.php',
    'el onboarding Productor debe regresar a Publicar');
has('Public/js/publicar.js', "const DRAFT_KEY = 'tindercows:publish-draft';",
    'la publicación incompleta debe preservarse como borrador temporal');
has('Public/js/publicar.js', 'el backend actual expone publicaciones solo por GET',
    'la UI no debe anunciar persistencia que el backend todavía no ofrece');
hasNot('Public/js/publicar.js', "method: 'POST'", 'Publicar no debe inventar un POST inexistente');

// Comprar/Pujar: disponibilidad real por GET, gate Comprador y puja no inventada.
assert(fs.existsSync('Public/js/explore-actions.js'), 'falta el flujo Comprar / pujar');
has('Public/js/explore.js', 'article.dataset.publicacionId = String(publicacionId);',
    'la tarjeta debe conservar el publicacionId que ya entrega el catálogo');
has('Public/js/explore.js', 'article.dataset.animalId = String(animalId);',
    'la tarjeta debe conservar también el animalId asociado');
has('Public/js/explore-actions.js', "const API_URL = 'api/publicaciones.php';", 'debe verificar el catálogo vigente');
has('Public/js/explore-actions.js', "estado: 'ACTIVO'", 'solo debe continuar con publicaciones activas');
has('Public/js/explore-actions.js', 'publicacionId: positiveInt(card.dataset.publicacionId)',
    'Comprar/Pujar debe transportar el id estable de la publicación, no reconstruir identidad desde el texto visible');
has('Public/js/explore-actions.js', 'positiveInt(item?.publicacionId) !== expectedPublicationId',
    'la revalidación debe comparar por publicacionId');
has('Public/js/explore-actions.js', "profile?.capacidadesEstado?.COMPRADOR", 'la compra debe exigir contexto Comprador');
has('Public/js/explore-actions.js', 'registro.php?capacidad=COMPRADOR&next=explorar.php',
    'el onboarding Comprador debe volver a la publicación');
has('Public/js/explore-actions.js', 'bid.disabled = true',
    'la puja debe quedar deshabilitada mientras el API no informe subasta activa');
has('Public/js/explore-actions.js', 'Reintentar', 'el modal debe ofrecer recuperación ante error');
has('Public/js/explore-actions.js', 'aria-busy', 'el modal debe exponer estado ocupado');
hasNot('Public/js/explore-actions.js', "method: 'POST'", 'no debe inventarse una escritura de compra');

// La experiencia pública no debe mandar a los usuarios a los CRUD administrativos.
hasNot('Public/js/mi-actividad.js', 'href="productores.php"', 'Mi actividad no debe enviar al CRUD de Productores');
hasNot('Public/js/mi-actividad.js', 'href="transportistas.php"', 'Mi actividad no debe enviar al CRUD de Transportistas');
has('Public/js/mi-actividad.js', 'href="publicar.php"', 'Productor activo debe continuar por el flujo Publicar');
has('Public/js/mi-actividad.js', 'href="fletes.php"', 'Transportista activo debe continuar por Fletes');

console.log('OK frontend_contract_test: contratos administrativos, transacción de fincas y Front 2.0 alineados.');
