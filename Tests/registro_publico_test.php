<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/RegistroPublicoService.php';
require_once dirname(__DIR__) . '/Application/Controller/RegistroPublicoController.php';

use Application\Auth\ActorContext;
use Application\Controller\RegistroPublicoController;

$identificaciones = [];
$periodosBasura = [];

function registro_payload(string $identificacion, string $correo, array $capacidades, array $fincas = []): array
{
    return [
        'persona' => [
            'identificacionTipo' => 'PASAPORTE',
            'identificacionNumero' => $identificacion,
            'nombre' => 'Persona Registro Público',
            'alias' => 'Registro ' . substr($identificacion, -4),
            'telefono' => '+506 8777-6655',
            'correoElectronico' => $correo,
        ],
        'capacidades' => $capacidades,
        'fincas' => $fincas,
    ];
}

function registro_actor(?int $personaId, string $correo): ActorContext
{
    return ActorContext::usuarioVerificado(
        $personaId,
        'supabase-' . test_token('registro'),
        $correo,
        'authenticated',
    );
}

function registro_controlador(?int $personaId, string $correo): RegistroPublicoController
{
    return new RegistroPublicoController(
        test_db(),
        registro_actor($personaId, $correo),
        test_token('registro-publico'),
    );
}

function registro_persona_id(string $identificacion): ?int
{
    $sentencia = test_db()->prepare(
        'SELECT tbpersonaid FROM tbpersona WHERE tbpersonaidentificacionnumero = :identificacion'
    );
    $sentencia->execute(['identificacion' => $identificacion]);
    $valor = $sentencia->fetchColumn();
    return $valor === false ? null : (int) $valor;
}

function registro_contar_contexto(string $tabla, string $columnaPersona, int $personaId): int
{
    $permitidas = ['tbproductor' => 'tbpersonaid', 'tbcomprador' => 'tbpersonaid', 'tbtransportista' => 'tbpersonaid'];
    if (($permitidas[$tabla] ?? null) !== $columnaPersona) {
        throw new RuntimeException('Tabla de contexto no admitida en fixture.');
    }
    $sentencia = test_db()->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$columnaPersona} = :personaId");
    $sentencia->execute(['personaId' => $personaId]);
    return (int) $sentencia->fetchColumn();
}

function registro_cleanup_contextos(array $identificaciones): void
{
    $ids = array_values(array_unique(array_filter(array_map('strval', $identificaciones))));
    if ($ids === []) return;
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $db = test_db();

    // Estos contextos no son eliminados por el helper histórico de Productor.
    $db->prepare(
        "DELETE c FROM tbcomprador c INNER JOIN tbpersona p ON p.tbpersonaid = c.tbpersonaid
         WHERE p.tbpersonaidentificacionnumero IN ({$marcadores})"
    )->execute($ids);
    $db->prepare(
        "DELETE tv FROM tbtransportistavehiculo tv
         INNER JOIN tbtransportista t ON t.tbtransportistaid = tv.tbtransportistaid
         INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid
         WHERE p.tbpersonaidentificacionnumero IN ({$marcadores})"
    )->execute($ids);
    $db->prepare(
        "DELETE t FROM tbtransportista t INNER JOIN tbpersona p ON p.tbpersonaid = t.tbpersonaid
         WHERE p.tbpersonaidentificacionnumero IN ({$marcadores})"
    )->execute($ids);

    test_cleanup_productores($ids);
}

try {
    // 1) Calidad: una Persona puede entrar únicamente queriendo comprar.
    $idComprador = test_document();
    $correoComprador = strtolower(test_token('buyer')) . '@example.test';
    $identificaciones[] = $idComprador;
    $soloComprador = registro_controlador(null, $correoComprador)->procesar(
        'POST',
        registro_payload($idComprador, $correoComprador, ['COMPRADOR']),
    );
    test_same(201, $soloComprador['status'], 'Registro solo Comprador debe crear el contexto');
    $personaComprador = registro_persona_id($idComprador);
    test_assert($personaComprador !== null, 'Registro solo Comprador debe crear una única Persona');
    test_same(1, registro_contar_contexto('tbcomprador', 'tbpersonaid', $personaComprador),
        'Comprador debe apuntar a la Persona creada');
    test_same(0, registro_contar_contexto('tbproductor', 'tbpersonaid', $personaComprador),
        'Registrarse para comprar no debe crear Productor');
    test_same(0, registro_contar_contexto('tbtransportista', 'tbpersonaid', $personaComprador),
        'Registrarse para comprar no debe crear Transportista');

    // 2) Ampliar la misma Persona: Comprador existente es idempotente y solo se
    // crea Transportista. No se duplica identidad ni se exige Productor.
    $ampliado = registro_controlador($personaComprador, $correoComprador)->procesar(
        'POST',
        registro_payload($idComprador, $correoComprador, ['COMPRADOR', 'TRANSPORTISTA']),
    );
    test_same(201, $ampliado['status'], 'Ampliar una Persona debe crear solo el contexto faltante');
    test_same(['TRANSPORTISTA'], $ampliado['body']['data']['capacidadesCreadas'],
        'El contexto Comprador activo debe tratarse como no-op, no duplicarse');
    test_same(1, registro_contar_contexto('tbcomprador', 'tbpersonaid', $personaComprador),
        'Ampliar actividades no debe duplicar Comprador');
    test_same(1, registro_contar_contexto('tbtransportista', 'tbpersonaid', $personaComprador),
        'Transportista debe reutilizar la misma Persona');

    // 3) Los tres contextos comparten exactamente una Persona y Productor crea
    // sus fincas/direcciones dentro de la misma operación.
    $idCompleto = test_document();
    $correoCompleto = strtolower(test_token('all')) . '@example.test';
    $identificaciones[] = $idCompleto;
    $todos = registro_controlador(null, $correoCompleto)->procesar(
        'POST',
        registro_payload($idCompleto, $correoCompleto, ['PRODUCTOR', 'COMPRADOR', 'TRANSPORTISTA'], [[
            'nombre' => 'Finca Registro Atómico',
            'direccion' => [
                'provincia' => 'Alajuela',
                'canton' => 'San Carlos',
                'distrito' => 'Quesada',
                'pueblo' => null,
                'senas' => 'Frente al salón comunal',
                'latitud' => '10.3239000',
                'longitud' => '-84.4309000',
            ],
        ]]),
    );
    test_same(201, $todos['status'], 'Registro combinado debe completar todos los contextos');
    $personaCompleta = registro_persona_id($idCompleto);
    test_assert($personaCompleta !== null, 'Registro combinado debe crear Persona');
    foreach (['tbproductor', 'tbcomprador', 'tbtransportista'] as $tabla) {
        test_same(1, registro_contar_contexto($tabla, 'tbpersonaid', $personaCompleta),
            "{$tabla} debe reutilizar la misma Persona");
    }
    $productor = test_controller()->procesar('GET', ['identificacionNumero' => $idCompleto], []);
    test_same([['nombre' => 'Finca Registro Atómico']], $productor['body']['data']['fincas'],
        'El Productor registrado debe conservar su finca');
    $direccionFinca = test_finca_controller()->procesarDireccion('GET', [
        'identificacionNumero' => $idCompleto,
        'nombreFinca' => 'Finca Registro Atómico',
    ], []);
    test_same(200, $direccionFinca['status'], 'La dirección de finca debe confirmarse junto con el registro');
    test_same('10.3239000', $direccionFinca['body']['data']['direccionFinca']['latitud'],
        'El punto exacto opcional debe conservarse');

    // 4) El JWT manda sobre el correo: el formulario no puede elegir otra cuenta.
    $idCorreo = test_document();
    $correoActor = strtolower(test_token('actor')) . '@example.test';
    $correoDistinto = strtolower(test_token('otro')) . '@example.test';
    $mismatch = registro_controlador(null, $correoActor)->procesar(
        'POST',
        registro_payload($idCorreo, $correoDistinto, ['COMPRADOR']),
    );
    test_same(422, $mismatch['status'], 'El correo del formulario debe coincidir con el JWT verificado');
    test_same(null, registro_persona_id($idCorreo), 'Un correo distinto no debe crear Persona parcial');

    // 5) Regresión de rollback tardío. Al no haber FK en Calidad podemos crear
    // un periodo huérfano para el próximo Productor y forzar que abrir el estado
    // falle DESPUÉS de Persona/Productor/dirección/finca. Todo debe revertirse.
    $idRollback = test_document();
    $correoRollback = strtolower(test_token('rollback')) . '@example.test';
    $identificaciones[] = $idRollback;
    $proximoProductorId = (int) test_db()->query(
        'SELECT COALESCE(MAX(tbproductorid), 0) + 1 FROM tbproductor'
    )->fetchColumn();
    $periodoId = (int) test_db()->query(
        'SELECT COALESCE(MAX(tbproductorestadoperiodoid), 0) + 1 FROM tbproductorestadoperiodo'
    )->fetchColumn();
    test_db()->prepare(
        'INSERT INTO tbproductorestadoperiodo
         (tbproductorestadoperiodoid, tbproductorid, tbproductorestadperiodoestado,
          tbproductorestadperiodofechainicio, tbproductorestadperiodofechafin, tbproductorestadperiodomotivo)
         VALUES (:periodoId, :productorId, 1, :inicio, NULL, :motivo)'
    )->execute([
        'periodoId' => $periodoId,
        'productorId' => $proximoProductorId,
        'inicio' => gmdate('Y-m-d H:i:s'),
        'motivo' => 'Fixture deliberada de rollback',
    ]);
    $periodosBasura[] = $periodoId;

    $falloTardio = false;
    try {
        registro_controlador(null, $correoRollback)->procesar(
            'POST',
            registro_payload($idRollback, $correoRollback, ['PRODUCTOR'], [[
                'nombre' => 'Finca Debe Revertirse',
            ]]),
        );
    } catch (Throwable) {
        $falloTardio = true;
    }
    test_assert($falloTardio, 'La fixture debe provocar un fallo tardío real');
    test_same(null, registro_persona_id($idRollback),
        'Un fallo tardío debe revertir también la Persona recién insertada');
    $conteoProductorRollback = test_db()->prepare(
        'SELECT COUNT(*) FROM tbproductor p INNER JOIN tbpersona pe ON pe.tbpersonaid = p.tbpersonaid
         WHERE pe.tbpersonaidentificacionnumero = :identificacion'
    );
    $conteoProductorRollback->execute(['identificacion' => $idRollback]);
    test_same(0, (int) $conteoProductorRollback->fetchColumn(),
        'Un fallo tardío no debe dejar Productor parcial');
} finally {
    foreach ($periodosBasura as $periodoId) {
        test_db()->prepare(
            'DELETE FROM tbproductorestadoperiodo WHERE tbproductorestadoperiodoid = :id'
        )->execute(['id' => $periodoId]);
    }
    registro_cleanup_contextos($identificaciones);
}

echo "OK registro_publico_test: Persona única, contextos independientes y rollback atómico.\n";
