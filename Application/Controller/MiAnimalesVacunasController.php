<?php

declare(strict_types=1);

namespace Application\Controller;

// Mismo patrón que AnimalPublicacionController: el controlador carga lo que usa y no depende de la lista del endpoint.
require_once dirname(__DIR__) . '/Service/AnimalValidacionService.php';
require_once dirname(__DIR__) . '/Model/AnimalCatalogo.php';

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalCatalogo;
use Application\Model\AnimalVacunacion;
use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Service\AnimalValidacionService;
use PDO;
use Throwable;

/**
 * Historial de vacunación de un animal PROPIO (P2-3): listar, registrar y corregir. Un animal ajeno responde 404.
 * No hay DELETE: un registro equivocado se corrige. Cada cambio queda en la bitácora.
 */
final class MiAnimalesVacunasController
{
    private const ORIGEN = 'API_MI_ANIMALES_VACUNAS';
    private const CAMPOS_EDITABLES = ['vacunaId', 'fecha', 'dosis', 'lote', 'aplicadaPor', 'proximaDosis', 'observaciones'];

    private AnimalVacunacion $vacunaciones;
    private AnimalCatalogo $catalogo;
    private Productor $productor;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->vacunaciones = new AnimalVacunacion($conexion);
        $this->catalogo = new AnimalCatalogo($conexion);
        $this->productor = new Productor($conexion, new ProductorFinca($conexion));
        $this->bitacora = new Bitacora($conexion, $actor);
        $solicitudId = trim((string) $solicitudId);
        $this->solicitudId = $solicitudId !== '' && strlen($solicitudId) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $solicitudId)
            ? $solicitudId : 'REQ-' . bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $consulta = [], array $cuerpo = []): array
    {
        try {
            if (!$this->actor->tienePersona()) {
                throw new HttpException('Debe iniciar sesión para ver el historial de vacunación.', 401);
            }
            return match ($metodo) {
                'GET' => $this->listar($consulta),
                'POST' => $this->registrar($cuerpo),
                'PATCH' => $this->corregir($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function listar(array $consulta): array
    {
        $errores = [];
        $animalId = $this->entero($consulta['animalId'] ?? null, 'animalId', $errores, true);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $this->exigirAnimalPropio($animalId, $this->productorId(false));

        return $this->respuesta(true, 'Historial de vacunación consultado correctamente.', [
            'animalId' => $animalId,
            'vacunas' => $this->vacunaciones->listar($animalId),
        ]);
    }

    private function registrar(array $cuerpo): array
    {
        $this->rechazarDesconocidos($cuerpo, array_merge(['animalId'], self::CAMPOS_EDITABLES));
        $errores = [];
        $animalId = $this->entero($cuerpo['animalId'] ?? null, 'animalId', $errores, true);
        $datos = $this->campos($cuerpo, $errores, true);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $productorId = $this->productorId(true);

        $nueva = $this->ejecutarConBloqueos(function () use ($animalId, $productorId, $datos): array {
            $animal = $this->exigirAnimalPropio($animalId, $productorId);
            $this->exigirAnimalVigente($animal);
            $this->exigirVacunaActiva($datos['vacunaId']);
            $id = $this->vacunaciones->crear(['animalId' => $animalId] + $datos);
            $nueva = $this->vacunaciones->buscar($id);
            $this->bitacora->registrar('CREAR', (string) $id, null, $nueva, $this->solicitudId,
                entidad: 'ANIMAL_VACUNACION', origen: self::ORIGEN);
            return $nueva;
        });

        return $this->respuesta(true, 'Vacuna registrada correctamente.', [
            'vacunacion' => $nueva,
            'vacunas' => $this->vacunaciones->listar($animalId),
        ], 201);
    }

    /** PATCH { vacunacionId, ...campos }: solo cambian las claves presentes. */
    private function corregir(array $cuerpo): array
    {
        $this->rechazarDesconocidos($cuerpo, array_merge(['vacunacionId'], self::CAMPOS_EDITABLES));
        $errores = [];
        $id = $this->entero($cuerpo['vacunacionId'] ?? null, 'vacunacionId', $errores, true);
        $cambios = $this->campos($cuerpo, $errores, false);
        if ($errores === [] && $cambios === []) $errores['vacunacionId'] = 'Indica qué deseas corregir.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        $productorId = $this->productorId(true);

        $nueva = $this->ejecutarConBloqueos(function () use ($id, $productorId, $cambios): array {
            $anterior = $this->vacunaciones->buscar($id);
            // Un registro de un animal ajeno responde igual que uno inexistente.
            if ($anterior === null) throw new HttpException('El registro de vacunación no existe.', 404);
            $animal = $this->vacunaciones->animalPropio($anterior['animalId'], $productorId);
            if ($animal === null) throw new HttpException('El registro de vacunación no existe.', 404);
            $this->exigirAnimalVigente($animal);
            if (isset($cambios['vacunaId']) && $cambios['vacunaId'] !== $anterior['vacunaId']) {
                $this->exigirVacunaActiva($cambios['vacunaId']);
            }
            $fecha = $cambios['fecha'] ?? $anterior['fecha'];
            $proxima = array_key_exists('proximaDosis', $cambios) ? $cambios['proximaDosis'] : $anterior['proximaDosis'];
            if ($proxima !== null && $proxima < $fecha) {
                throw new HttpException('Revise los campos indicados.', 422, null, ['proximaDosis' => 'La próxima dosis no puede ser anterior a la aplicación.']);
            }
            $this->vacunaciones->actualizar($id, $cambios);
            $nueva = $this->vacunaciones->buscar($id);
            $this->bitacora->registrar('ACTUALIZAR', (string) $id, $anterior, $nueva, $this->solicitudId,
                entidad: 'ANIMAL_VACUNACION', origen: self::ORIGEN);
            return $nueva;
        });

        return $this->respuesta(true, 'Registro de vacunación corregido.', [
            'vacunacion' => $nueva,
            'vacunas' => $this->vacunaciones->listar($nueva['animalId']),
        ]);
    }

    /** Valida los campos presentes. Con $completo (alta) vacunaId y fecha son obligatorios. */
    private function campos(array $cuerpo, array &$errores, bool $completo): array
    {
        $datos = [];
        if ($completo || array_key_exists('vacunaId', $cuerpo)) {
            $datos['vacunaId'] = $this->entero($cuerpo['vacunaId'] ?? null, 'vacunaId', $errores, true);
        }
        if ($completo || array_key_exists('fecha', $cuerpo)) {
            $datos['fecha'] = $this->fecha($cuerpo['fecha'] ?? null, 'fecha', $errores, true, true);
        }
        foreach ([['dosis', 50], ['lote', 50], ['aplicadaPor', 150], ['observaciones', 500]] as [$campo, $maximo]) {
            if (array_key_exists($campo, $cuerpo)) $datos[$campo] = $this->texto($cuerpo[$campo], $campo, $maximo, $errores);
        }
        if (array_key_exists('proximaDosis', $cuerpo)) {
            $datos['proximaDosis'] = $this->fecha($cuerpo['proximaDosis'], 'proximaDosis', $errores, false, false);
            if (($datos['proximaDosis'] ?? null) !== null && isset($datos['fecha']) && $datos['proximaDosis'] < $datos['fecha']) {
                $errores['proximaDosis'] = 'La próxima dosis no puede ser anterior a la aplicación.';
            }
        }

        return $completo ? $datos + ['dosis' => null, 'lote' => null, 'aplicadaPor' => null, 'proximaDosis' => null, 'observaciones' => null] : $datos;
    }

    private function productorId(bool $escritura): int
    {
        $productor = $this->productor->buscarPorPersonaId((int) $this->actor->personaId);
        if ($productor === null) {
            throw new HttpException('La cuenta no tiene la actividad Vendedor configurada.', 409);
        }
        if ($escritura && ((int) ($productor['tbproductorestado'] ?? 0) !== 1 || (int) ($productor['tbpersonaestado'] ?? 0) !== 1)) {
            throw new HttpException('La actividad Vendedor está inactiva.', 409);
        }

        return (int) $productor['tbproductorid'];
    }

    private function exigirAnimalPropio(int $animalId, int $productorId): array
    {
        $animal = $this->vacunaciones->animalPropio($animalId, $productorId);
        if ($animal === null) throw new HttpException('El animal no existe en tu cuenta.', 404);

        return $animal;
    }

    private function exigirAnimalVigente(array $animal): void
    {
        if (in_array($animal['estado'], ['VENDIDO', 'INACTIVO'], true)) {
            throw new HttpException('El animal ya no está en tu inventario.', 409);
        }
    }

    private function exigirVacunaActiva(int $vacunaId): void
    {
        $vacuna = $this->catalogo->vacuna($vacunaId);
        if ($vacuna === null || !$vacuna['activo']) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['vacunaId' => 'La vacuna no existe o no está disponible.']);
        }
    }

    private function entero(mixed $valor, string $campo, array &$errores, bool $obligatorio): ?int
    {
        if ($valor === null || $valor === '') {
            if ($obligatorio) $errores[$campo] = 'Este campo es obligatorio.';
            return null;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $errores[$campo] = 'Debe ser un entero positivo.';
            return null;
        }

        return $entero;
    }

    private function texto(mixed $valor, string $campo, int $maximo, array &$errores): ?string
    {
        if ($valor === null) return null;
        if (!is_string($valor)) {
            $errores[$campo] = 'Debe ser texto.';
            return null;
        }
        $texto = trim($valor);
        if (mb_strlen($texto) > $maximo) {
            $errores[$campo] = "Debe tener hasta {$maximo} caracteres.";
            return null;
        }

        return $texto === '' ? null : $texto;
    }

    /** AAAA-MM-DD válida; la de aplicación no puede ser futura (hora de Costa Rica). */
    private function fecha(mixed $valor, string $campo, array &$errores, bool $obligatoria, bool $noFutura): ?string
    {
        $texto = is_string($valor) ? trim($valor) : '';
        if ($texto === '') {
            if ($obligatoria) $errores[$campo] = 'Este campo es obligatorio.';
            return null;
        }
        $partes = explode('-', $texto);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) !== 1 || !checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0])) {
            $errores[$campo] = 'Usa una fecha válida (AAAA-MM-DD).';
            return null;
        }
        if ($noFutura && $texto > AnimalValidacionService::hoy()) {
            $errores[$campo] = 'La fecha de aplicación no puede ser futura.';
            return null;
        }

        return $texto;
    }

    private function rechazarDesconocidos(array $cuerpo, array $permitidos): void
    {
        $errores = [];
        foreach (array_diff(array_keys($cuerpo), $permitidos) as $campo) $errores[$campo] = 'Campo no permitido.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);
    }

    /** Orden de locks: vacunación -> bitácora, dentro de una transacción. */
    private function ejecutarConBloqueos(callable $operacion): array
    {
        return $this->vacunaciones->ejecutarConBloqueoAlta(
            fn (): array => $this->bitacora->ejecutarConBloqueoAlta(function () use ($operacion): array {
                $this->conexion->beginTransaction();
                try {
                    $resultado = $operacion();
                    $this->conexion->commit();
                    return $resultado;
                } catch (Throwable $excepcion) {
                    if ($this->conexion->inTransaction()) $this->conexion->rollBack();
                    throw $excepcion;
                }
            }),
        );
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
