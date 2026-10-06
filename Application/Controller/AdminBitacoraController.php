<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\HttpException;
use Application\Model\Bitacora;
use PDO;

/**
 * Visor de la bitácora para el administrador (P3-4): solo lectura. Hasta ahora
 * se escribía en cada cambio pero nadie la consultaba. La autorización de
 * administrador la exige el endpoint antes de llegar aquí.
 */
final class AdminBitacoraController
{
    private readonly Bitacora $bitacora;

    public function __construct(PDO $conexion)
    {
        $this->bitacora = new Bitacora($conexion);
    }

    public function procesar(string $metodo, array $consulta): array
    {
        try {
            return $metodo === 'GET'
                ? $this->listar($consulta)
                : $this->respuesta(false, 'Método no permitido.', null, 405);
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores
            );
        }
    }

    private function listar(array $consulta): array
    {
        $errores = [];
        $entidad = mb_strtoupper(trim((string) ($consulta['entidad'] ?? '')), 'UTF-8');
        if ($entidad !== '' && !preg_match('/^[A-Z0-9_]{1,80}$/', $entidad)) {
            $errores['entidad'] = 'Elija una entidad de la lista.';
        }
        $desde = $this->fecha($consulta['desde'] ?? '', 'desde', $errores);
        $hasta = $this->fecha($consulta['hasta'] ?? '', 'hasta', $errores);
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            $errores['hasta'] = 'Debe ser igual o posterior a la fecha inicial.';
        }
        $q = $consulta['q'] ?? '';
        if (!is_string($q) || mb_strlen($q) > 150) {
            $errores['q'] = 'Use hasta 150 caracteres.';
        }
        $pagina = filter_var($consulta['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $tamano = filter_var($consulta['tamanoPagina'] ?? 25, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($pagina === false) $errores['pagina'] = 'Debe ser un entero positivo.';
        if ($tamano === false) $errores['tamanoPagina'] = 'Debe estar entre 1 y 100.';
        if ($errores !== []) {
            throw new HttpException('Revise los filtros indicados.', 422, null, $errores);
        }

        $resultado = $this->bitacora->listar(
            ['entidad' => $entidad, 'desde' => $desde, 'hasta' => $hasta, 'q' => trim((string) $q)],
            (int) $pagina, (int) $tamano,
        );

        return $this->respuesta(true, 'Bitácora consultada correctamente.', $resultado + [
            'pagina' => (int) $pagina,
            'tamanoPagina' => (int) $tamano,
            'entidades' => $this->bitacora->entidades(),
        ]);
    }

    /** Fecha YYYY-MM-DD real (no 2026-02-31), o '' si no se envió. */
    private function fecha(mixed $valor, string $campo, array &$errores): string
    {
        $texto = is_string($valor) ? trim($valor) : '';
        if ($texto === '') {
            return '';
        }
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
            $errores[$campo] = 'Use una fecha con formato AAAA-MM-DD.';
            return '';
        }
        return $texto;
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
