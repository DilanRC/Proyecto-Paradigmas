<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorUbicacion;
use DateTime;
use PDO;
use Throwable;

final class ProductorUbicacionHttpException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $estadoHttp = 400,
        public readonly array $errores = [],
        public readonly ?array $datos = null,
    ) {
        parent::__construct($message);
    }
}

/**
 * API append-only de ubicaciones GPS del productor (plan §9, §14-16).
 * Solo admite POST (nueva lectura) y GET (histórico); cualquier método
 * destructivo se rechaza con 405 porque la tabla no se actualiza ni borra.
 */
final class ProductorUbicacionController
{
    private const ORIGENES_PERMITIDOS = ['NAVEGADOR', 'MANUAL'];

    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly Productor $productor,
        private readonly ProductorUbicacion $ubicacion,
        private readonly Bitacora $bitacora,
        ?string $solicitudId = null,
    ) {
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->listar($consulta),
                'POST' => $this->registrar($cuerpo),
                'PUT', 'PATCH', 'DELETE' => throw new ProductorUbicacionHttpException(
                    'Las ubicaciones son append-only: no se permite modificar ni eliminar registros.',
                    405,
                ),
                default => throw new ProductorUbicacionHttpException('Método no permitido.', 405),
            };
        } catch (ProductorUbicacionHttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    private function registrar(array $cuerpo): array
    {
        $datos = $this->validarRegistro($cuerpo);
        $productor = $this->productor->buscarPorId($datos['productorId']);
        if ($productor === null) {
            throw new ProductorUbicacionHttpException('Productor no encontrado.', 404, [
                'productorId' => 'No existe un productor con ese identificador.',
            ]);
        }
        if ((int) $productor['tbproductorestado'] !== 1) {
            throw new ProductorUbicacionHttpException('El productor está inactivo.', 409, [
                'productorId' => 'Solo productores activos pueden registrar ubicaciones.',
            ]);
        }

        $ubicacionId = $this->ubicacion->ejecutarConBloqueoAlta(
            fn (): int => $this->transaccion(function () use ($datos, $productor): int {
                $nuevoId = $this->ubicacion->registrar(
                    $datos['productorId'],
                    $datos['latitud'],
                    $datos['longitud'],
                    $datos['precisionMetros'],
                    $datos['origen'],
                );
                $this->bitacora->registrar(
                    'REGISTRAR_UBICACION',
                    (string) $productor['tbproductoridentificacionnumero'],
                    null,
                    [
                        'tbproductorubicacionid' => $nuevoId,
                        'tbproductorid' => $datos['productorId'],
                        'tbproductorubicacionlatitud' => $datos['latitud'],
                        'tbproductorubicacionlongitud' => $datos['longitud'],
                        'tbproductorubicacionprecision' => $datos['precisionMetros'],
                        'tbproductorubicacionorigen' => $datos['origen'],
                    ],
                    $this->solicitudId,
                    entidad: 'PRODUCTORUBICACION',
                    origen: 'API_PRODUCTORES_UBICACION',
                );

                return $nuevoId;
            }),
        );

        return $this->respuesta(true, 'Ubicación registrada correctamente.', [
            'tbproductorubicacionid' => $ubicacionId,
            'tbproductorid' => $datos['productorId'],
            'tbproductorubicacionlatitud' => $datos['latitud'],
            'tbproductorubicacionlongitud' => $datos['longitud'],
            'tbproductorubicacionprecision' => $datos['precisionMetros'],
            'tbproductorubicacionfecha' => date('Y-m-d H:i:s'),
            'tbproductorubicacionorigen' => $datos['origen'],
        ]);
    }

    private function listar(array $consulta): array
    {
        $errores = [];
        $productorId = $this->enteroConsulta($consulta['productorId'] ?? null, 'productorId', $errores);
        if ($errores !== []) {
            throw new ProductorUbicacionHttpException('Revise los campos indicados.', 400, $errores);
        }

        if (isset($consulta['desde']) || isset($consulta['hasta'])) {
            $rango = $this->validarRangoFechas($consulta['desde'] ?? null, $consulta['hasta'] ?? null);

            return $this->respuesta(true, 'Ubicaciones consultadas correctamente.',
                $this->ubicacion->listarPorPeriodo($productorId, $rango['desde'], $rango['hasta']));
        }

        $pagina = array_key_exists('pagina', $consulta)
            ? $this->enteroConsulta($consulta['pagina'], 'pagina', $errores) : 1;
        $tamano = array_key_exists('tamano', $consulta)
            ? $this->enteroConsulta($consulta['tamano'], 'tamano', $errores) : 25;
        if ($tamano > 100) {
            $errores['tamano'] = 'Debe estar entre 1 y 100.';
        }
        if ($errores !== []) {
            throw new ProductorUbicacionHttpException('Revise los campos indicados.', 400, $errores);
        }

        return $this->respuesta(true, 'Ubicaciones consultadas correctamente.',
            $this->ubicacion->listarPorProductor($productorId, $pagina, $tamano));
    }

    private function validarRegistro(array $cuerpo): array
    {
        // "fecha" se acepta en el cuerpo pero se descarta: la fecha la asigna
        // siempre PHP con el reloj del servidor y el cliente no puede falsearla.
        $permitidos = ['productorId', 'latitud', 'longitud', 'precisionMetros', 'origen', 'fecha'];
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);

        $errores = [];
        $productorId = $this->enteroCampo($cuerpo['productorId'] ?? null, 'productorId', $errores);
        $latitud = $this->coordenadaCampo($cuerpo['latitud'] ?? null, 'latitud', -90.0, 90.0, $errores);
        $longitud = $this->coordenadaCampo($cuerpo['longitud'] ?? null, 'longitud', -180.0, 180.0, $errores);
        $precision = $this->precisionCampo($cuerpo['precisionMetros'] ?? null, $errores);
        $origen = $cuerpo['origen'] ?? null;
        if (!is_string($origen) || !in_array(mb_strtoupper(trim($origen), 'UTF-8'), self::ORIGENES_PERMITIDOS, true)) {
            $errores['origen'] = 'Debe ser NAVEGADOR o MANUAL.';
        } else {
            $origen = mb_strtoupper(trim($origen), 'UTF-8');
        }

        if ($errores !== []) {
            throw new ProductorUbicacionHttpException('Revise los campos indicados.', 400, $errores);
        }

        return [
            'productorId' => $productorId,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'precisionMetros' => $precision,
            'origen' => $origen,
        ];
    }

    private function coordenadaCampo(mixed $valor, string $campo, float $minimo, float $maximo, array &$errores): string
    {
        if ($valor === null) {
            $errores[$campo] = 'El campo es obligatorio.';

            return '';
        }
        if (!is_numeric($valor)) {
            $errores[$campo] = 'Debe ser un valor numérico.';

            return '';
        }
        $numero = (float) $valor;
        if (!is_finite($numero) || $numero < $minimo || $numero > $maximo) {
            $errores[$campo] = sprintf('Debe estar entre %s y %s.', $minimo, $maximo);

            return '';
        }

        // Se conserva como texto: DECIMAL(10,7) guarda el dígito exacto que
        // envió el cliente sin redondeos de punto flotante.
        return (string) $valor;
    }

    private function precisionCampo(mixed $valor, array &$errores): ?string
    {
        if ($valor === null) {
            return null;
        }
        if (!is_numeric($valor)) {
            $errores['precisionMetros'] = 'Debe ser un valor numérico.';

            return null;
        }
        $numero = (float) $valor;
        if (!is_finite($numero) || $numero < 0) {
            $errores['precisionMetros'] = 'Debe ser un número mayor o igual a cero.';

            return null;
        }

        return (string) $valor;
    }

    private function validarRangoFechas(mixed $desde, mixed $hasta): array
    {
        $formatos = ['Y-m-d H:i:s', 'Y-m-d'];
        $inicio = $this->fechaConsulta($desde, 'desde', $formatos, '00:00:00');
        $fin = $this->fechaConsulta($hasta, 'hasta', $formatos, '23:59:59');

        if ($inicio > $fin) {
            throw new ProductorUbicacionHttpException('Revise los campos indicados.', 400, [
                'desde' => 'La fecha inicial debe ser anterior o igual a la final.',
            ]);
        }

        return ['desde' => $inicio, 'hasta' => $fin];
    }

    /** @param non-empty-array<int, string> $formatos */
    private function fechaConsulta(mixed $valor, string $campo, array $formatos, string $horaPorDefecto): string
    {
        foreach ($formatos as $formato) {
            $fecha = is_string($valor) ? DateTime::createFromFormat($formato, $valor) : false;
            if ($fecha === false) {
                continue;
            }
            $erroresFormato = $fecha->getLastErrors();
            if (($erroresFormato['warning_count'] ?? 0) === 0 && ($erroresFormato['error_count'] ?? 0) === 0) {
                return $fecha->format($formato === 'Y-m-d' ? "Y-m-d {$horaPorDefecto}" : 'Y-m-d H:i:s');
            }
        }

        throw new ProductorUbicacionHttpException('Revise los campos indicados.', 400, [
            $campo => 'Debe usar el formato Y-m-d H:i:s o Y-m-d.',
        ]);
    }

    private function enteroCampo(mixed $valor, string $campo, array &$errores): int
    {
        if ($valor === null) {
            $errores[$campo] = 'El campo es obligatorio.';

            return 0;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $errores[$campo] = 'Debe ser un entero positivo.';

            return 0;
        }

        return $entero;
    }

    private function enteroConsulta(mixed $valor, string $campo, array &$errores): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $errores[$campo] = "{$campo} debe ser un entero positivo.";

            return 0;
        }

        return $entero;
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) {
            return;
        }
        $errores = [];
        foreach ($desconocidos as $campo) {
            $errores[$campo] = 'Campo no permitido.';
        }
        throw new ProductorUbicacionHttpException('Revise los campos indicados.', 400, $errores);
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
