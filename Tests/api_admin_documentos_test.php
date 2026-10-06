<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Model/Persona.php';
require_once dirname(__DIR__) . '/Application/Controller/AnimalPublicacionController.php';
require_once dirname(__DIR__) . '/Application/Controller/MiPerfilController.php';
require_once dirname(__DIR__) . '/Application/Controller/AdminDocumentoController.php';

use Application\Auth\ActorContext;
use Application\Controller\AdminDocumentoController;
use Application\Controller\MiPerfilController;
use Application\Service\SupabaseStorage;

/**
 * P2-6: verificación de documentos por el administrador. Cubre: lista de
 * pendientes, enlace firmado (con transporte falso) y su registro en la
 * bitácora, rechazar exige motivo y la persona lo ve, no se decide dos veces,
 * un documento nuevo limpia el motivo y vuelve a PENDIENTE, y sin clave 503.
 */

$db = test_db();
$identificaciones = [];
$claveOriginal = getenv('SUPABASE_SECRET_KEY');
$urlOriginal = getenv('SUPABASE_URL');

try {
    putenv('SUPABASE_URL=https://proyecto.example.supabase.co');
    putenv('SUPABASE_SECRET_KEY=sb_secret_de_prueba');

    $persona = test_create_completo(['fincas' => [['nombre' => 'Finca Documento']]]);
    $identificaciones[] = $persona['identificacionNumero'];
    $buscar = $db->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :i');
    $buscar->execute(['i' => $persona['identificacionNumero']]);
    $fila = $buscar->fetch();
    $personaId = (int) $fila['tbpersonaid'];
    $sujeto = 'test-doc-' . test_token('sub');
    $uuid = '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d';
    $propia = new MiPerfilController($db, ActorContext::usuarioVerificado($personaId, $sujeto, $fila['tbpersonacorreoelectronico'], null), test_token('perfil'));
    test_same(200, $propia->procesar('PATCH', ['documentoRuta' => "{$sujeto}/{$uuid}.jpg"])['status'], 'La persona sube su documento');

    $llamadas = [];
    $transporte = function (string $url, string $clave, string $cuerpo) use (&$llamadas): array {
        $llamadas[] = compact('url', 'clave', 'cuerpo');
        return ['status' => 200, 'body' => json_encode(['signedURL' => '/object/sign/documentos/x.jpg?token=abc'])];
    };
    $admin = ActorContext::usuarioVerificado(null, 'admin-doc', 'admin-doc@example.test', 'authenticated');
    $docs = new AdminDocumentoController($db, test_token('docs'), $admin, new SupabaseStorage($transporte));

    $pendientes = $docs->procesar('GET', ['q' => $persona['identificacionNumero']], []);
    test_same(200, $pendientes['status'], 'Se listan los documentos');
    test_same(1, $pendientes['body']['data']['total'], 'Por defecto se ven los pendientes');
    test_same('PENDIENTE', $pendientes['body']['data']['personas'][0]['documento']['estado'], 'El documento está pendiente');
    test_assert(!str_contains(json_encode($pendientes['body']), $uuid), 'La lista no expone la ruta del archivo');
    test_same(422, $docs->procesar('GET', ['estado' => 'OTRO'], [])['status'], 'Un filtro de estado inválido es 422');

    $enlace = $docs->procesar('POST', [], ['personaId' => $personaId]);
    test_same(200, $enlace['status'], 'El admin pide el enlace del documento');
    test_same('https://proyecto.example.supabase.co/storage/v1/object/sign/documentos/x.jpg?token=abc', $enlace['body']['data']['url'],
        'El enlace firmado se arma con la URL del proyecto');
    test_same("https://proyecto.example.supabase.co/storage/v1/object/sign/documentos/{$sujeto}/{$uuid}.jpg", $llamadas[0]['url'],
        'Se firma la ruta guardada en la base, no una que mande el navegador');
    test_same(['expiresIn' => SupabaseStorage::SEGUNDOS_ENLACE], json_decode($llamadas[0]['cuerpo'], true), 'El enlace caduca');
    test_same(422, $docs->procesar('POST', [], ['personaId' => $personaId, 'ruta' => 'otra/cosa.jpg'])['status'], 'No se acepta una ruta desde el navegador');
    $accesos = $db->prepare("SELECT COUNT(*) FROM tbbitacora WHERE tbbitacoraaccion = 'VER_DOCUMENTO' AND tbbitacoraregistroidentificacionnumero = :r");
    $accesos->execute(['r' => 'PERSONA:' . $personaId]);
    test_same(1, (int) $accesos->fetchColumn(), 'Ver un documento queda en la bitácora');

    $sinMotivo = $docs->procesar('PATCH', [], ['personaId' => $personaId, 'estado' => 'RECHAZADO']);
    test_same(422, $sinMotivo['status'], 'Rechazar exige motivo');
    test_assert(isset($sinMotivo['body']['errors']['motivo']), 'El error va en motivo');
    $rechazo = $docs->procesar('PATCH', [], ['personaId' => $personaId, 'estado' => 'RECHAZADO', 'motivo' => '  La foto está borrosa ']);
    test_same(200, $rechazo['status'], 'El admin rechaza con motivo');
    test_same(['estado' => 'RECHAZADO', 'fecha' => $rechazo['body']['data']['documento']['fecha'], 'motivo' => 'La foto está borrosa', 'lectura' => null],
        $rechazo['body']['data']['documento'], 'El motivo queda guardado y recortado');
    test_same(409, $docs->procesar('PATCH', [], ['personaId' => $personaId, 'estado' => 'VERIFICADO'])['status'],
        'Un documento ya revisado no se decide otra vez');

    $actividad = $db->prepare('SELECT * FROM tbpersona WHERE tbpersonaid = :id');
    $actividad->execute(['id' => $personaId]);
    test_same('La foto está borrosa', \Application\Model\Persona::documentoPublico($actividad->fetch())['motivo'],
        'La persona ve el motivo del rechazo');

    $nuevo = $propia->procesar('PATCH', ['documentoRuta' => "{$sujeto}/{$uuid}.png"]);
    test_same(['estado' => 'PENDIENTE', 'motivo' => null], array_intersect_key($nuevo['body']['data']['persona']['documento'], ['estado' => 1, 'motivo' => 1]),
        'Un documento nuevo vuelve a PENDIENTE y sin el motivo anterior');
    $verificado = $docs->procesar('PATCH', [], ['personaId' => $personaId, 'estado' => 'VERIFICADO', 'motivo' => 'ignorado']);
    test_same('VERIFICADO', $verificado['body']['data']['documento']['estado'], 'El admin verifica el documento');
    test_same(null, $verificado['body']['data']['documento']['motivo'], 'Un documento verificado no lleva motivo');

    $decisiones = $db->prepare("SELECT tbbitacoraaccion, tbbitacoradatosnuevos FROM tbbitacora
        WHERE tbbitacoraorigen = 'API_ADMIN_DOCUMENTOS' AND tbbitacoraregistroidentificacionnumero = :r ORDER BY tbbitacoraid");
    $decisiones->execute(['r' => 'PERSONA:' . $personaId]);
    $eventos = $decisiones->fetchAll();
    test_same(['VER_DOCUMENTO', 'RECHAZAR_DOCUMENTO', 'VERIFICAR_DOCUMENTO'], array_column($eventos, 'tbbitacoraaccion'), 'Cada paso queda en la bitácora');
    test_assert(str_contains((string) $eventos[1]['tbbitacoradatosnuevos'], 'admin-doc@example.test'), 'Con el correo de quien decidió');

    test_same(404, $docs->procesar('PATCH', [], ['personaId' => 999999999, 'estado' => 'VERIFICADO'])['status'], 'Sin documento es 404');
    test_same(404, $docs->procesar('POST', [], ['personaId' => 999999999])['status'], 'Sin documento no hay enlace');
    $ausente = new AdminDocumentoController($db, null, $admin, new SupabaseStorage(fn () => ['status' => 404, 'body' => '{}']));
    test_same(404, $ausente->procesar('POST', [], ['personaId' => $personaId])['status'], 'Si el archivo ya no está, 404');
    putenv('SUPABASE_SECRET_KEY=');
    test_same(503, $docs->procesar('POST', [], ['personaId' => $personaId])['status'], 'Sin clave secreta en el servidor, 503');

    echo "OK api_admin_documentos_test: pendientes, enlace firmado, motivo visible, una sola decisión, reenvío y bitácora.\n";
} finally {
    // El id de la persona de prueba se reutiliza en la próxima corrida: sus eventos se borran.
    if (isset($personaId)) {
        $db->prepare("DELETE FROM tbbitacora WHERE tbbitacoraregistroidentificacionnumero = :r
            AND tbbitacoraorigen IN ('API_ADMIN_DOCUMENTOS', 'API_MI_PERFIL')")->execute(['r' => 'PERSONA:' . $personaId]);
    }
    putenv($claveOriginal === false ? 'SUPABASE_SECRET_KEY' : "SUPABASE_SECRET_KEY={$claveOriginal}");
    putenv($urlOriginal === false ? 'SUPABASE_URL' : "SUPABASE_URL={$urlOriginal}");
    test_cleanup_productores($identificaciones);
}
