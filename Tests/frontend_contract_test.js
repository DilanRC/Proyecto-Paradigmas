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
hasNot('Public/js/shared/form-draft.js', 'localStorage',
    'un borrador temporal no debe sobrevivir como almacenamiento permanente');
has('Public/js/shared/form.js', 'enableFormDraft(form);',
    'bindFormErrors debe activar el borrador de los CRUD');

// Productores: Persona + fincas + direcciones forman una sola unidad de trabajo.
has('Public/js/productores.js', "const API_URL = 'api/productores.php';", 'endpoint de productores incorrecto');
has('Public/js/productores.js', 'fincas: parseFincaDrafts(fincas).map(buildFincaPayload)',
    'el mismo comando Productor debe transportar cada dirección opcional de finca');
hasNot('Public/js/productores.js', 'persistirDireccionesFinca',
    'no debe reaparecer una segunda fase HTTP que deje escrituras parciales');
has('Application/View/productores/index.php', 'id="fincas-cards"',
    'las fincas deben editarse como elementos independientes');
has('Public/js/productores-fincas-ui.js', 'crearSelectorPuntoFinca',
    'cada finca puede administrar su punto exacto opcional');
has('Application/Controller/ProductorController.php', 'private FincaDireccion $direccionFinca;',
    'ProductorController debe coordinar la dirección de finca');
has('Application/Controller/ProductorController.php', '$this->sincronizarDireccionesFinca(',
    'POST/PUT deben persistir las direcciones en la misma operación');
has('Application/Controller/ProductorController.php', 'ejecutarConBloqueoEnlaceAlta',
    'el lock de enlace debe envolver la transacción coordinada');
has('Application/Service/ValidacionService.php', "'fincasDetalle' => $fincasDetalle",
    'la validación debe conservar el detalle estructurado de las fincas');
has('Application/Controller/FincaController.php', "'latitud' => null, 'longitud' => null",
    'latitud/longitud deben ser opcionales y validadas por PHP');

// Comprador: contexto independiente, sin CRUD administrativo manual.
assert(fs.existsSync('Application/Model/Comprador.php'), 'debe existir el contexto Comprador');
has('Application/Controller/CompradorConsultaController.php', 'use Application\\Model\\Comprador;',
    'la consulta debe usar el modelo Comprador');
hasNot('Public/js/compradores.js', 'buildCompradorPayload',
    'el panel administrativo no debe construir altas manuales de Comprador');
hasNot('Application/View/compradores/index.php', 'id="crear-comprador"',
    'la vista administrativa no debe ofrecer alta manual de Comprador');
has('Public/js/shared/capacidades.js', /clave:\s*'comprador'[\s\S]{0,300}derivada:\s*false/,
    'Comprador debe ser un contexto independiente');
hasNot('Public/js/shared/capacidades.js', 'derivada: true',
    'ningún contexto debe presentarse como derivado de otro');

// Front 2.0: onboarding y geolocalización.
has('Public/js/shared/business-rules.js', "COMPRADOR: Object.freeze({", 'falta la regla de Comprador');
has('Public/js/shared/business-rules.js', "PRODUCTOR: Object.freeze({", 'falta la regla de Productor');
has('Public/js/shared/business-rules.js', "TRANSPORTISTA: Object.freeze({", 'falta la regla de Transportista');
has('Public/js/shared/business-rules.js', 'requiresAtLeastOneFinca: true',
    'Productor debe pedir al menos una finca en onboarding');
has('Public/js/shared/business-rules.js', 'vehicleRequiredAtRegistration: false',
    'Transportista no debe exigir vehículo en registro inicial');
has('Public/js/registro.js', "const SAFE_NEXT = new Set(['explorar.php', 'mi-actividad.php', 'fletes.php', 'publicar.php']);",
    'el onboarding solo debe volver a destinos públicos');
has('Public/js/registro.js', 'crearSelectorPuntoFinca',
    'el onboarding Productor debe permitir punto exacto opcional');
has('Public/js/shared/ubicacion-sesion.js', "const UBICACION_USUARIO_KEY = 'tindercows:ubicacion-usuario'",
    'la ubicación del visitante debe ser temporal');
has('Public/js/explore.js', 'leerUbicacionUsuario()',
    'Explorar debe reutilizar ubicación temporal');
has('Application/Service/PublicacionCercaniaService.php', 'calcularDistanciaKm',
    'la cercanía debe calcularse en PHP');

// Publicar: no simular un POST comercial inexistente.
assert(fs.existsSync('Public/publicar.php'), 'falta la ruta pública Publicar');
has('Public/js/publicar.js', "capabilityState(profile, 'PRODUCTOR')", 'Publicar debe exigir Productor');
has('Public/js/publicar.js', "const DRAFT_KEY = 'tindercows:publish-draft';",
    'la publicación debe conservarse como borrador temporal');
has('Public/js/publicar.js', 'el backend actual expone publicaciones solo por GET',
    'la UI debe admitir que todavía no existe escritura comercial');
hasNot('Public/js/publicar.js', "method: 'POST'", 'Publicar no debe inventar un POST');

// Comprar/Pujar: identidad estable de publicación y puja no inventada.
assert(fs.existsSync('Public/js/explore-actions.js'), 'falta el flujo Comprar / pujar');
has('Public/js/explore.js', 'article.dataset.publicacionId = String(publicacionId);',
    'la tarjeta debe conservar publicacionId');
has('Public/js/explore-actions.js', 'publicacionId: positiveInt(card.dataset.publicacionId)',
    'Comprar/Pujar debe transportar el id estable');
has('Public/js/explore-actions.js', 'positiveInt(item?.publicacionId) !== expectedPublicationId',
    'la revalidación debe comparar por publicacionId');
has('Public/js/explore-actions.js', 'bid.disabled = true',
    'la puja debe seguir deshabilitada sin contrato de subasta');
hasNot('Public/js/explore-actions.js', "method: 'POST'", 'no debe inventarse una compra');

// Autenticación pública real: Supabase identifica usuario; PHP vincula Persona.
assert(fs.existsSync('Public/js/shared/supabase-auth.js'), 'falta el cliente de autenticación Supabase');
assert(fs.existsSync('Public/api/auth-config.php'), 'falta el endpoint de configuración pública de Auth');
has('Public/js/shared/supabase-auth.js', "token?grant_type=${encodeURIComponent(grantType)}",
    'el login/refresh debe usar el endpoint token de Supabase');
has('Public/js/shared/supabase-auth.js', "tokenRequest('password'", 'el login debe usar password grant');
has('Public/js/shared/supabase-auth.js', "tokenRequest('refresh_token'", 'la sesión debe poder renovar JWT');
has('Public/js/shared/supabase-auth.js', '/auth/v1/logout?scope=local',
    'salir debe intentar revocar la sesión local en Supabase');
hasNot('Public/js/shared/supabase-auth.js', 'SUPABASE_SECRET_KEY',
    'el navegador nunca debe conocer la clave secreta');
has('Public/api/auth-config.php', "getenv('SUPABASE_PUBLISHABLE_KEY')",
    'el navegador solo debe recibir la publishable key');
hasNot('Public/api/auth-config.php', 'SUPABASE_SECRET_KEY',
    'auth-config no debe exponer la clave secreta');
has('Public/js/shared/api.js', 'Authorization: `Bearer ${bearer}`',
    'las llamadas PHP autenticadas deben llevar Bearer');
has('Public/js/shared/api.js', 'getAccessToken({ forceRefresh: true })',
    'un 401 debe renovar el JWT como máximo una vez');
has('Public/js/login.js', 'await signInWithPassword(email, password);',
    'Login no debe aceptar cualquier contraseña localmente');
has('Public/js/login.js', "await request('api/mi-actividad.php')",
    'Login debe comprobar que el JWT esté vinculado a una Persona');
hasNot('Public/js/login.js', 'local-browser-session',
    'Login no debe reconstruir la sesión demo anterior');
hasNot('Public/js/login.js', "'productores.php'", 'una cuenta pública no debe redirigir al CRUD admin');
has('Public/js/shared/auth-gate.js', "const SESSION_KEY = 'tindercows:admin-session';",
    'la sesión administrativa debe estar separada de la sesión pública');
has('Public/js/shared/auth-gate.js', "session?.mode !== 'admin-server-session'",
    'el gate admin debe denegar por defecto hasta tener autorización real');
hasNot('Public/js/shared/auth-gate.js', 'local-browser-session',
    'el gate admin no debe aceptar la antigua sesión simulada');

// Mi actividad: proceso público ligado al actor, no a una cédula elegida por JS.
assert(fs.existsSync('Application/Controller/MiActividadController.php'), 'falta el controlador Mi actividad');
assert(fs.existsSync('Public/api/mi-actividad.php'), 'falta el endpoint Mi actividad');
has('Public/api/mi-actividad.php', 'SupabaseActorResolver::fromGlobals($conexion)',
    'Mi actividad debe resolver el actor desde el Bearer');
has('Application/Controller/MiActividadController.php', "array_diff(array_keys($cuerpo), ['contexto', 'activo'])",
    'PATCH público solo debe aceptar contexto y estado');
has('Application/Controller/MiActividadController.php', '$this->persona->buscarPorId($this->actor->personaId)',
    'la Persona objetivo debe derivarse del actor autenticado');
has('Application/Controller/MiActividadController.php', "if ($contexto === 'COMPRADOR')",
    'Comprador debe permanecer bloqueado hasta aprobar su escritor de negocio');
has('Public/js/mi-actividad.js', "const API_URL = 'api/mi-actividad.php';",
    'Mi actividad no debe llamar los CRUD directamente');
has('Public/js/mi-actividad.js', "method: 'PATCH'", 'los cambios deben persistirse por JSON');
has('Public/js/mi-actividad.js', 'JSON.stringify({ contexto: id, activo: nextActive })',
    'el navegador no debe enviar una identificación objetivo');
has('Public/js/mi-actividad.js', 'await loadActivity({ quiet: true })',
    'tras cambiar estado debe releerse la fuente de verdad');
hasNot('Public/js/mi-actividad.js', 'api/productores.php',
    'Mi actividad no debe saltarse el proceso público seguro');
hasNot('Public/js/mi-actividad.js', 'api/transportistas.php',
    'Mi actividad no debe saltarse el proceso público seguro');
hasNot('Public/js/mi-actividad.js', 'href="productores.php"',
    'Mi actividad no debe enviar al CRUD de Productores');
hasNot('Public/js/mi-actividad.js', 'href="transportistas.php"',
    'Mi actividad no debe enviar al CRUD de Transportistas');

console.log('OK frontend_contract_test: atomicidad, Front 2.0, auth real y Mi actividad alineados.');
