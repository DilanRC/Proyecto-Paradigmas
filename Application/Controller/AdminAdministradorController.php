<?php

declare(strict_types=1);

namespace Application\Controller;

require_once dirname(__DIR__) . '/Service/ValidacionService.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Administrador;
use Application\Model\Bitacora;
use Application\Service\ValidacionService;
use PDO;
use Throwable;

/**
 * Gestión de administradores desde el panel (P3-4): listar, agregar por correo
 * y desactivar o reactivar. Antes se editaba la base a mano. La autorización
 * de administrador la exige el endpoint antes de llegar aquí.
 *
 * Nadie puede desactivarse a sí mismo ni desactivar al último administrador
 * activo: el panel quedaría sin nadie que pudiera entrar.
 */
final class AdminAdministradorController
{
    private const ORIGEN = 'API_ADMIN_ADMINISTRADORES';

    private readonly Administrador $administradores;
    private readonly Bitacora $bitacora;
    private readonly ActorContext $actor;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null,
        ?ActorContext $actor = null)
    {
        $this->actor = $actor ?? ActorContext::noAutenticado();
        $this->administradores = new Administrador($conexion);
        $this->bitacora = new Bitacora($conexion, $this->actor);
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== ''
            ? trim($solicitudId)
            : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->listar(),
                'POST' => $this->agregar($cuerpo),
                'PATCH' => $this->cambiarEstado($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function listar(): array
    {
        return $this->respuesta(true, 'Administradores consultados correctamente.', [
            'administradores' => array_map(fn (array $admin): array => $admin + [
                'esUsted' => $this->esUsted($admin),
            ], $this->administradores->listar()),
        ]);
    }

    private function agregar(array $cuerpo): array
    {
        $this->rechazarCampos($cuerpo, ['correoElectronico']);
        $errores = [];
        $correo = (new ValidacionService())->validarCorreo($cuerpo['correoElectronico'] ?? null, $errores);
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        [$admin, $estado, $mensaje] = $this->administradores->ejecutarConBloqueoAlta(
            fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                fn (): array => $this->transaccion(function () use ($correo): array {
                    $existente = $this->administradores->buscarPorCorreo($correo);
                    if ($existente !== null && $existente['estado'] === 'ACTIVO') {
                        throw new HttpException('Ese correo ya es administrador.', 409, null, [
                            'correoElectronico' => 'Ese correo ya es administrador.',
                        ]);
                    }
                    if ($existente !== null) {
                        // Ya existió: se reactiva la misma fila en vez de duplicar el correo.
                        $this->administradores->cambiarEstado($existente['administradorId'], true);
                        $nuevo = $this->administradores->bloquearPorId($existente['administradorId']);
                        $this->registrar('REACTIVAR', $existente, $nuevo);
                        return [$nuevo, 200, 'El correo ya había sido administrador: se reactivó.'];
                    }
                    $nuevo = $this->administradores->bloquearPorId($this->administradores->crear($correo));
                    $this->registrar('CREAR', null, $nuevo);
                    return [$nuevo, 201, 'Administrador agregado correctamente.'];
                }),
            ),
        );

        return $this->respuesta(true, $mensaje, ['administrador' => $admin + ['esUsted' => $this->esUsted($admin)]], $estado);
    }

    private function cambiarEstado(array $cuerpo): array
    {
        $this->rechazarCampos($cuerpo, ['administradorId', 'activo']);
        $id = filter_var($cuerpo['administradorId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $activo = $cuerpo['activo'] ?? null;
        if ($id === false || !is_bool($activo)) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_filter([
                'administradorId' => $id === false ? 'Debe ser un entero positivo.' : null,
                'activo' => !is_bool($activo) ? 'Debe ser true o false.' : null,
            ]));
        }

        $admin = $this->administradores->ejecutarConBloqueoAlta(
            fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                fn (): array => $this->transaccion(function () use ($id, $activo): array {
                    $anterior = $this->administradores->bloquearPorId($id);
                    if ($anterior === null) {
                        throw new HttpException('El administrador no existe.', 404);
                    }
                    if (($anterior['estado'] === 'ACTIVO') === $activo) {
                        return $anterior;
                    }
                    if (!$activo && $this->esUsted($anterior)) {
                        throw new HttpException('No puedes desactivar tu propio acceso de administrador.', 409);
                    }
                    if (!$activo && $this->administradores->contarActivos() <= 1) {
                        throw new HttpException('Debe quedar al menos un administrador activo.', 409);
                    }
                    $this->administradores->cambiarEstado($id, $activo);
                    $nuevo = $this->administradores->bloquearPorId($id);
                    $this->registrar($activo ? 'REACTIVAR' : 'DESACTIVAR', $anterior, $nuevo);
                    return $nuevo;
                }),
            ),
        );

        return $this->respuesta(true, $activo ? 'Administrador reactivado.' : 'Administrador desactivado.', [
            'administrador' => $admin + ['esUsted' => $this->esUsted($admin)],
        ]);
    }

    private function esUsted(array $admin): bool
    {
        $correo = mb_strtolower(trim((string) $this->actor->correoElectronico), 'UTF-8');
        return $correo !== '' && mb_strtolower($admin['correoElectronico'], 'UTF-8') === $correo;
    }

    /** Un admin puede no tener Persona: la bitácora guarda también su correo. */
    private function registrar(string $accion, ?array $anterior, ?array $nuevo): void
    {
        $this->bitacora->registrar(
            $accion,
            'ADMINISTRADOR:' . ($nuevo['administradorId'] ?? $anterior['administradorId'] ?? ''),
            $anterior,
            ($nuevo ?? []) + ['realizadoPor' => $this->actor->correoElectronico],
            $this->solicitudId,
            entidad: 'ADMINISTRADOR',
            origen: self::ORIGEN,
        );
    }

    private function rechazarCampos(array $cuerpo, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($cuerpo), $permitidos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null,
                array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();
            return $resultado;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200,
        array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
