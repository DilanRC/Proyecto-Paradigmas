<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\Direccion;
use Application\Model\FincaDireccion;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use PDO;
use Throwable;

final class FincaHttpException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $estadoHttp,
        public readonly ?array $datos = null,
        public readonly array $errores = [],
    ) {
        parent::__construct($message);
    }
}

final class FincaController
{
    private ProductorFinca $fincas;
    private Productor $productor;
    private FincaDireccion $direccionFinca;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);
        $this->direccionFinca = new FincaDireccion($conexion, new Direccion($conexion));
        $this->bitacora = new Bitacora($conexion);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesarDireccion(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultarDireccion($consulta),
                'POST' => $this->crearDireccion($cuerpo),
                'PUT' => $this->actualizarDireccion($cuerpo),
                'DELETE' => $this->vaciarDireccion($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (FincaHttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    private function consultarDireccion(array $consulta): array
    {
        [$identificacion, $nombreFinca] = $this->identificarFincaDesdeConsulta($consulta);
        $productor = $this->productor->buscar($identificacion);
        if ($productor === null) {
            throw new FincaHttpException('Productor no encontrado.', 404);
        }
        $productorId = (int) $productor['productorId'];
        $fincaId = $this->fincas->buscarIdActivo($productorId, $nombreFinca);
        if ($fincaId === null) {
            throw new FincaHttpException('Finca no encontrada o inactiva.', 404);
        }
        $direccion = $this->direccionFinca->buscar($fincaId);
        if ($direccion === null) {
            throw new FincaHttpException('La finca no tiene una dirección registrada.', 404);
        }

        return $this->respuesta(true, 'Dirección de finca consultada correctamente.', [
            'identificacionNumero' => $identificacion,
            'nombreFinca' => $nombreFinca,
            'direccionFinca' => $direccion,
        ]);
    }

    private function crearDireccion(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', 'nombreFinca', 'direccionFinca']);
        $errores = [];
        $identificacion = $this->campoIdentificacion($cuerpo, $errores);
        $nombreFinca = $this->campoNombreFinca($cuerpo, $errores);
        $direccion = $this->validarDireccion($cuerpo['direccionFinca'] ?? null, $errores);
        if ($errores !== []) {
            throw new FincaHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = $this->direccionFinca->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($identificacion, $nombreFinca, $direccion): array {
                [$productorId, $fincaId] = $this->resolverFincaActiva($identificacion, $nombreFinca, true);
                try {
                    $this->direccionFinca->crear($fincaId, $direccion);
                } catch (\RuntimeException $excepcion) {
                    throw new FincaHttpException($excepcion->getMessage(), 409);
                }
                $nueva = $this->direccionFinca->buscar($fincaId);
                $this->bitacora->registrar(
                    'CREAR_DIRECCION_FINCA',
                    "{$identificacion}:{$nombreFinca}",
                    null,
                    ['direccionFinca' => $nueva],
                    $this->solicitudId,
                    'FINCA',
                    'API_FINCAS',
                );

                return [
                    'identificacionNumero' => $identificacion,
                    'nombreFinca' => $nombreFinca,
                    'direccionFinca' => $nueva,
                ];
            }),
        );

        return $this->respuesta(true, 'Dirección de finca creada correctamente.', $resultado, 201);
    }

    private function actualizarDireccion(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', 'nombreFinca', 'direccionFinca']);
        $errores = [];
        $identificacion = $this->campoIdentificacion($cuerpo, $errores);
        $nombreFinca = $this->campoNombreFinca($cuerpo, $errores);
        $direccion = $this->validarDireccion($cuerpo['direccionFinca'] ?? null, $errores);
        if ($errores !== []) {
            throw new FincaHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = $this->transaccion(function () use ($identificacion, $nombreFinca, $direccion): array {
            [, $fincaId] = $this->resolverFincaActiva($identificacion, $nombreFinca, true);
            $anterior = $this->direccionFinca->buscar($fincaId);
            if ($anterior === null) {
                throw new FincaHttpException(
                    'La finca no tiene una dirección registrada; use POST para crearla.',
                    404,
                );
            }
            try {
                $this->direccionFinca->actualizar($fincaId, $direccion);
            } catch (\RuntimeException $excepcion) {
                throw new FincaHttpException($excepcion->getMessage(), 409);
            }
            $nueva = $this->direccionFinca->buscar($fincaId);
            $this->bitacora->registrar(
                'ACTUALIZAR_DIRECCION_FINCA',
                "{$identificacion}:{$nombreFinca}",
                ['direccionFinca' => $anterior],
                ['direccionFinca' => $nueva],
                $this->solicitudId,
                'FINCA',
                'API_FINCAS',
            );

            return [
                'identificacionNumero' => $identificacion,
                'nombreFinca' => $nombreFinca,
                'direccionFinca' => $nueva,
            ];
        });

        return $this->respuesta(true, 'Dirección de finca actualizada correctamente.', $resultado);
    }

    private function vaciarDireccion(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', 'nombreFinca']);
        $errores = [];
        $identificacion = $this->campoIdentificacion($cuerpo, $errores);
        $nombreFinca = $this->campoNombreFinca($cuerpo, $errores);
        if ($errores !== []) {
            throw new FincaHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = $this->transaccion(function () use ($identificacion, $nombreFinca): array {
            [, $fincaId] = $this->resolverFincaActiva($identificacion, $nombreFinca, true);
            $anterior = $this->direccionFinca->buscar($fincaId);
            if ($anterior === null) {
                throw new FincaHttpException(
                    'La finca no tiene una dirección registrada; no hay nada que vaciar.',
                    404,
                );
            }
            try {
                $this->direccionFinca->vaciar($fincaId);
            } catch (\RuntimeException $excepcion) {
                throw new FincaHttpException($excepcion->getMessage(), 409);
            }
            $nueva = $this->direccionFinca->buscar($fincaId);
            $this->bitacora->registrar(
                'VACIAR_DIRECCION_FINCA',
                "{$identificacion}:{$nombreFinca}",
                ['direccionFinca' => $anterior],
                ['direccionFinca' => $nueva],
                $this->solicitudId,
                'FINCA',
                'API_FINCAS',
            );

            return [
                'identificacionNumero' => $identificacion,
                'nombreFinca' => $nombreFinca,
                'direccionFinca' => $nueva,
            ];
        });

        return $this->respuesta(true, 'Dirección de finca vaciada correctamente.', $resultado);
    }

    /**
     * Bloquea al productor (misma disciplina que ProductorController antes de
     * tocar datos hijos), valida que esté activo, y resuelve el tbfincaid
     * activo para el nombre dado.
     */
    private function resolverFincaActiva(string $identificacion, string $nombreFinca, bool $exigirProductorActivo): array
    {
        $bloqueado = $this->productor->bloquear($identificacion);
        if ($bloqueado === null) {
            throw new FincaHttpException('Productor no encontrado.', 404);
        }
        if ($exigirProductorActivo && (int) $bloqueado['tbproductorestado'] !== 1) {
            throw new FincaHttpException(
                'El productor está inactivo. Debe reactivarlo antes de modificar sus fincas.',
                409,
            );
        }
        $productorId = (int) $bloqueado['tbproductorid'];
        $fincaId = $this->fincas->buscarIdActivo($productorId, $nombreFinca);
        if ($fincaId === null) {
            throw new FincaHttpException('Finca no encontrada o inactiva.', 404);
        }

        return [$productorId, $fincaId];
    }

    private function identificarFincaDesdeConsulta(array $consulta): array
    {
        if (!array_key_exists('identificacionNumero', $consulta) || !array_key_exists('nombreFinca', $consulta)) {
            throw new FincaHttpException('Debe indicar identificacionNumero y nombreFinca.', 422, null, [
                'identificacionNumero' => 'El parámetro es obligatorio.',
                'nombreFinca' => 'El parámetro es obligatorio.',
            ]);
        }
        $identificacion = $this->normalizarIdentificacion($this->textoConsulta($consulta['identificacionNumero'], 250));
        $nombreFinca = trim($this->textoConsulta($consulta['nombreFinca'], 150));
        if ($identificacion === '' || $nombreFinca === '') {
            throw new FincaHttpException('Los parámetros no son válidos.', 422);
        }

        return [$identificacion, $nombreFinca];
    }

    private function campoIdentificacion(array $cuerpo, array &$errores): string
    {
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
            ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
        if ($identificacion === '') {
            $errores['identificacionNumero'] = 'La identificación es obligatoria.';
        }

        return $identificacion;
    }

    private function campoNombreFinca(array $cuerpo, array &$errores): string
    {
        if (!is_string($cuerpo['nombreFinca'] ?? null)) {
            $errores['nombreFinca'] = 'El nombre de la finca es obligatorio.';
            return '';
        }
        $nombre = trim($cuerpo['nombreFinca']);
        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $errores['nombreFinca'] = 'Debe contener entre 1 y 150 caracteres.';
        }

        return $nombre;
    }

    private function validarDireccion(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores['direccionFinca'] = 'La dirección debe ser un objeto.';
            return ['provincia' => '', 'canton' => '', 'distrito' => '', 'pueblo' => null, 'senas' => null];
        }
        try {
            $this->rechazarCamposDesconocidos($valor, ['provincia', 'canton', 'distrito', 'pueblo', 'senas'], 'direccionFinca.');
        } catch (FincaHttpException $excepcion) {
            $errores += $excepcion->errores;
        }

        return [
            'provincia' => $this->textoCampo($valor['provincia'] ?? null, 'direccionFinca.provincia', 100, $errores, 1),
            'canton' => $this->textoCampo($valor['canton'] ?? null, 'direccionFinca.canton', 100, $errores, 1),
            'distrito' => $this->textoCampo($valor['distrito'] ?? null, 'direccionFinca.distrito', 100, $errores, 1),
            'pueblo' => $this->textoOpcional($valor['pueblo'] ?? null, 'direccionFinca.pueblo', 150, $errores),
            'senas' => $this->textoOpcional($valor['senas'] ?? null, 'direccionFinca.senas', 500, $errores),
        ];
    }

    private function normalizarIdentificacion(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[ -]+/u', '', trim($valor)) ?? '', 'UTF-8');
    }

    private function textoCampo(mixed $valor, string $campo, int $maximo, array &$errores, int $minimo = 0): string
    {
        if (!is_string($valor)) {
            $errores[$campo] = 'El campo es obligatorio.';
            return '';
        }
        $texto = trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);
        $longitud = mb_strlen($texto);
        if ($longitud < $minimo || $longitud > $maximo) {
            $errores[$campo] = "Debe contener entre {$minimo} y {$maximo} caracteres.";
        }

        return $texto;
    }

    private function textoOpcional(mixed $valor, string $campo, int $maximo, array &$errores): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_string($valor) || mb_strlen(trim($valor)) > $maximo) {
            $errores[$campo] = "No puede superar {$maximo} caracteres.";
            return null;
        }

        return trim($valor);
    }

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new FincaHttpException('La consulta no es válida.', 422);
        }

        return trim($valor);
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos, string $prefijo = ''): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) {
            return;
        }
        $errores = [];
        foreach ($desconocidos as $campo) {
            $errores[$prefijo . $campo] = 'Campo no permitido.';
        }
        throw new FincaHttpException('Revise los campos indicados.', 422, null, $errores);
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();
            return $resultado;
        } catch (Throwable $excepcion) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $excepcion;
        }
    }

    private function normalizarSolicitudId(?string $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor !== '' && strlen($valor) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $valor)) {
            return $valor;
        }

        return 'REQ-' . bin2hex(random_bytes(16));
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}