<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HttpException;
use Application\Model\AnimalCatalogo;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Reglas del modelo de animal (P2-2, DEC-ANIMAL-001). Todo vive en PHP: el esquema no tiene llaves ni restricciones.
 */
final class AnimalValidacionService
{
    public const LOTE_MAXIMO = 100;
    public const PARTOS_MAXIMO = 30;
    /** Provincias del arete SENASA: 01 San José … 07 Limón (supuesto del equipo, ver MEMORIA). */
    private const PROVINCIAS_ARETE = ['01', '02', '03', '04', '05', '06', '07'];

    /**
     * Arete SENASA (DIIO), 13 dígitos: 188 (Costa Rica) + dígito de control + provincia (2) + correlativo (7).
     * Acepta espacios y guiones al escribirlo y devuelve solo los dígitos. Vacío -> null (el arete es opcional).
     * Devuelve un mensaje de error (o null si es válido) en la clave 'error'.
     *
     * @return array{arete:?string,error:?string}
     */
    public static function arete(mixed $valor): array
    {
        if ($valor === null || trim((string) $valor) === '') {
            return ['arete' => null, 'error' => null];
        }
        $digitos = preg_replace('/[\s-]+/', '', trim((string) $valor)) ?? '';
        $valido = preg_match('/^\d{13}$/', $digitos) === 1
            && str_starts_with($digitos, '188')
            && in_array(substr($digitos, 4, 2), self::PROVINCIAS_ARETE, true);

        return $valido
            ? ['arete' => $digitos, 'error' => null]
            : ['arete' => null, 'error' => 'El arete tiene 13 dígitos: 188, un dígito de control, la provincia (01 a 07) y 7 dígitos del correlativo.'];
    }

    /** Hoy en Costa Rica (UTC-6, sin horario de verano): una fecha de nacimiento no puede ser posterior. */
    public static function hoy(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Costa_Rica')))->format('Y-m-d');
    }

    /** 'HEMBRA'/'H' -> 'H', 'MACHO'/'M' -> 'M', otro texto -> '?', vacío -> null. */
    public static function sexoCodigo(mixed $sexo): ?string
    {
        $texto = mb_strtoupper(trim((string) ($sexo ?? '')), 'UTF-8');
        if ($texto === '') return null;

        return match ($texto) { 'HEMBRA', 'H' => 'H', 'MACHO', 'M' => 'M', default => '?' };
    }

    /**
     * Valida y normaliza los campos del modelo de animal de un cuerpo de publicación.
     * Todo es opcional: sin ninguno de estos campos devuelve un resultado "vacío" y la publicación
     * se comporta como antes de P2-2.
     *
     * @return array{arete:?string,especieId:?int,tipoId:?int,razaId:?int,razaNombre:?string,sexo:?string,
     *               fechaNacimiento:?string,fechaNacimientoEstimada:?bool,partos:?int,loteCantidad:int}
     */
    public static function validar(AnimalCatalogo $catalogo, array $cuerpo): array
    {
        $errores = [];
        $entero = static function (string $campo, int $minimo, int $maximo) use ($cuerpo, &$errores): ?int {
            $valor = $cuerpo[$campo] ?? null;
            if ($valor === null || $valor === '') return null;
            $n = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimo, 'max_range' => $maximo]]);
            if ($n === false) {
                $errores[$campo] = $minimo === 1 && $maximo === PHP_INT_MAX
                    ? 'Debe ser un entero positivo.' : "Debe ser un entero entre {$minimo} y {$maximo}.";
                return null;
            }
            return $n;
        };

        $especieId = $entero('especieId', 1, PHP_INT_MAX);
        $tipoId = $entero('tipoId', 1, PHP_INT_MAX);
        $razaId = $entero('razaId', 1, PHP_INT_MAX);
        $partos = $entero('partos', 0, self::PARTOS_MAXIMO);
        $lote = $entero('loteCantidad', 1, self::LOTE_MAXIMO) ?? 1;

        // Catálogos: tipo y raza deben existir, estar activos y pertenecer a la especie.
        if (($tipoId !== null || $razaId !== null) && $especieId === null && !isset($errores['especieId'])) {
            $errores['especieId'] = 'Elige la especie.';
        }
        if ($especieId !== null) {
            $especie = $catalogo->especie($especieId);
            if ($especie === null || !$especie['activo']) $errores['especieId'] = 'La especie no existe o no está disponible.';
        }
        $tipo = null;
        if ($tipoId !== null) {
            $tipo = $catalogo->tipo($tipoId);
            if ($tipo === null || !$tipo['activo']) {
                $errores['tipoId'] = 'El tipo no existe o no está disponible.';
                $tipo = null;
            } elseif ($especieId !== null && $tipo['especieId'] !== $especieId) {
                $errores['tipoId'] = 'El tipo no pertenece a la especie elegida.';
                $tipo = null;
            }
        }
        $razaNombre = null;
        if ($razaId !== null) {
            $raza = $catalogo->raza($razaId);
            if ($raza === null || !$raza['activo']) {
                $errores['razaId'] = 'La raza no existe o no está disponible.';
            } elseif ($especieId !== null && $raza['especieId'] !== $especieId) {
                $errores['razaId'] = 'La raza no pertenece a la especie elegida.';
            } else {
                $razaNombre = $raza['nombre'];
            }
        }

        // Sexo: el tipo lo fija (M/H); si además se envía, debe coincidir.
        $sexoTexto = trim((string) ($cuerpo['sexo'] ?? ''));
        $sexoEnviado = self::sexoCodigo($sexoTexto);
        $sexo = $sexoTexto === '' ? null : $sexoTexto;
        if ($tipo !== null && $tipo['sexo'] !== null) {
            if ($sexoEnviado !== null && $sexoEnviado !== $tipo['sexo']) {
                $errores['sexo'] = 'El sexo no coincide con el tipo de animal.';
            }
            $sexo = $tipo['sexo'] === 'H' ? 'HEMBRA' : 'MACHO';
        }

        if ($partos !== null && self::sexoCodigo($sexo) !== 'H') {
            $errores['partos'] = 'Los partos solo aplican a hembras.';
        }

        // Fecha de nacimiento: real o estimada, nunca futura.
        $fecha = null;
        $texto = trim((string) ($cuerpo['fechaNacimiento'] ?? ''));
        if ($texto !== '') {
            $partes = explode('-', $texto);
            $esFecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) === 1
                && checkdate((int) $partes[1], (int) $partes[2], (int) $partes[0]);
            if (!$esFecha) {
                $errores['fechaNacimiento'] = 'Usa una fecha válida (AAAA-MM-DD).';
            } elseif ($texto > self::hoy()) {
                $errores['fechaNacimiento'] = 'La fecha de nacimiento no puede ser futura.';
            } else {
                $fecha = $texto;
            }
        }
        $estimada = null;
        if (($cuerpo['fechaNacimientoEstimada'] ?? null) !== null && $cuerpo['fechaNacimientoEstimada'] !== '') {
            $estimada = filter_var($cuerpo['fechaNacimientoEstimada'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($estimada === null) {
                $errores['fechaNacimientoEstimada'] = 'Debe ser verdadero o falso.';
            } elseif ($texto === '') {
                $errores['fechaNacimientoEstimada'] = 'Indica primero la fecha de nacimiento.';
            }
        }
        if ($fecha !== null && $estimada === null) $estimada = false;

        // Arete SENASA (opcional): solo dígitos. Es de un animal, no de un lote.
        $revision = self::arete($cuerpo['arete'] ?? null);
        if ($revision['error'] !== null) $errores['arete'] = $revision['error'];
        if ($lote > 1) {
            if ($revision['arete'] !== null) $errores['arete'] = 'El arete identifica a un solo animal: un lote no lleva arete.';
            if ($partos !== null) $errores['partos'] = 'Los partos son de un animal, no de un lote.';
        }

        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return [
            'arete' => $revision['arete'], 'especieId' => $especieId, 'tipoId' => $tipoId, 'razaId' => $razaId,
            'razaNombre' => $razaNombre, 'sexo' => $sexo, 'fechaNacimiento' => $fecha,
            'fechaNacimientoEstimada' => $estimada, 'partos' => $partos, 'loteCantidad' => $lote,
        ];
    }
}
