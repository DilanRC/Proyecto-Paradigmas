<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Controller/MiPerfilController.php';

use Application\Auth\ActorContext;
use Application\Controller\MiPerfilController;
use Application\Service\SupabaseAuthAdmin;

final class PerfilCorreoCommitPDO extends PDO
{
    public string $fallo = '';
    public function commit(): bool
    {
        if ($this->fallo === 'antes') throw new PDOException('Fallo simulado antes de COMMIT');
        $resultado = parent::commit();
        if ($this->fallo === 'ambiguo') throw new PDOException('Respuesta de COMMIT perdida');
        return $resultado;
    }
}

$identificaciones = [];
$personaId = null;
try {
    $persona = test_create_completo(['fincas' => [['nombre' => 'Finca Recuperación Correo']]]);
    $identificaciones[] = $persona['identificacionNumero'];
    $buscar = test_db()->prepare('SELECT tbpersonaid, tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaidentificacionnumero = :id');
    $buscar->execute(['id' => $persona['identificacionNumero']]);
    $fila = $buscar->fetch();
    $personaId = (int) $fila['tbpersonaid'];
    $original = $fila['tbpersonacorreoelectronico'];
    $remoto = $original;
    $uuid = '11111111-2222-4333-8444-555555555555';
    $modo = 'timeout-aplicado';
    $puts = [];
    putenv('SUPABASE_URL=https://recuperacion.example.test');
    putenv('SUPABASE_SECRET_KEY=clave-falsa-solo-transporte-local');
    $transporte = function (string $url, string $clave, string $cuerpo, string $metodo = 'PUT') use (&$remoto, &$modo, &$puts, $uuid): array {
        if ($metodo === 'PUT') {
            $remoto = json_decode($cuerpo, true)['email'];
            $puts[] = $remoto;
            if ($modo === 'timeout-aplicado') return ['status' => 0, 'body' => ''];
        }
        return ['status' => 200, 'body' => json_encode(['id' => $uuid, 'email' => $remoto, 'email_confirmed_at' => '2026-01-01T00:00:00Z'])];
    };
    $db = new PerfilCorreoCommitPDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME')), getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $controlador = new MiPerfilController($db, ActorContext::usuarioVerificado($personaId, $uuid, $original, null), test_token('recuperar-correo'), new SupabaseAuthAdmin($transporte));
    $local = function () use ($personaId): string {
        $q = test_db()->prepare('SELECT tbpersonacorreoelectronico FROM tbpersona WHERE tbpersonaid = :id');
        $q->execute(['id' => $personaId]);
        return (string) $q->fetchColumn();
    };
    $nuevo = strtolower(test_token('correo-recuperacion')) . '@example.test';
    test_same(200, $controlador->procesar('PATCH', ['correoElectronico' => $nuevo])['status'], 'Un timeout aplicado se confirma leyendo la cuenta remota');
    test_same($nuevo, $local(), 'El correo local coincide después del timeout aplicado');
    test_same($nuevo, $remoto, 'El correo remoto coincide después del timeout aplicado');

    $modo = 'normal';
    $db->fallo = 'antes';
    $otro = strtolower(test_token('correo-rollback')) . '@example.test';
    test_same(503, $controlador->procesar('PATCH', ['correoElectronico' => $otro])['status'], 'El fallo antes de COMMIT no anuncia éxito');
    test_same($nuevo, $local(), 'El rollback conserva el correo local previo');
    test_same($nuevo, $remoto, 'La compensación verificada restaura el correo remoto previo');

    $db->fallo = 'ambiguo';
    $puts = [];
    test_same(503, $controlador->procesar('PATCH', ['correoElectronico' => $otro])['status'], 'COMMIT ambiguo responde recuperación pendiente');
    test_same($otro, $local(), 'El COMMIT simulado sí persistió');
    test_same($otro, $remoto, 'No se invierte el correo remoto tras un COMMIT ambiguo');
    test_same([$otro], $puts, 'No existe compensación ciega con resultado local desconocido');
    foreach ([
        '{}',
        json_encode(['id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'email' => $otro, 'email_confirmed_at' => '2026-01-01']),
        json_encode(['id' => $uuid, 'email' => $otro, 'email_confirmed_at' => null]),
    ] as $cuerpoInvalido) {
        $adminInvalido = new SupabaseAuthAdmin(static fn (): array => ['status' => 200, 'body' => $cuerpoInvalido]);
        try {
            $adminInvalido->cambiarCorreo($uuid, $otro);
            throw new RuntimeException('Un HTTP 200 sin identidad confirmada no puede anunciar éxito');
        } catch (Application\HttpException $error) {
            test_same(503, $error->estadoHttp, 'La identidad remota no verificable exige recuperación');
        }
    }
    $adminSinRespuesta = new SupabaseAuthAdmin(static fn (): array => ['status' => 0, 'body' => '']);
    try {
        $adminSinRespuesta->recuperarCorreo($uuid, $otro, $nuevo);
        throw new RuntimeException('No se puede restaurar sin conocer la cuenta remota');
    } catch (Application\HttpException $error) {
        test_same(503, $error->estadoHttp, 'La compensación sin evidencia se rechaza');
    }
    echo "OK perfil_correo_recuperacion_test: timeout aplicado, rollback y COMMIT ambiguo sin compensación ciega.\n";
} finally {
    if ($personaId !== null) {
        test_db()->prepare("DELETE FROM tbbitacora WHERE tbbitacoraentidad = 'PERSONA' AND tbbitacoraregistroidentificacionnumero = :id")->execute(['id' => 'PERSONA:' . $personaId]);
    }
    test_cleanup_productores($identificaciones);
}
