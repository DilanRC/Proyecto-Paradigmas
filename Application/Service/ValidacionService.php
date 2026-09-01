<?php

declare(strict_types=1);

namespace Application\Service;

/**
 * Excepción del contrato de validación unificado: lleva la lista de errores por
 * campo para responder 422 + errors. Es independiente del controlador para
 * poder ejecutar la validación sin el controlador gigante.
 */
final class ValidacionException extends \RuntimeException
{
    /**
     * @param array<string,string> $errores
     */
    public function __construct(string $message, public readonly array $errores = [])
    {
        parent::__construct($message);
    }
}

/**
 * Contrato de validación unificado y reutilizable por toda la API. Identificación,
 * teléfono, correo, dirección y fincas se validan sobre el valor normalizado y
 * devuelven errores por campo con el mismo contrato 422 que usa toda la API. La
 * máscara visual es del frontend; este servicio trabaja con el valor normalizado.
 */
final class ValidacionService
{
    public const TIPOS_IDENTIFICACION = [
        'CEDULA_FISICA' => 'Cédula física',
        'CEDULA_JURIDICA' => 'Cédula jurídica',
        'DIMEX' => 'DIMEX',
        'NITE' => 'NITE',
        'PASAPORTE' => 'Pasaporte',
    ];

    public function tiposIdentificacion(): array
    {
        $resultado = [];
        foreach (self::TIPOS_IDENTIFICACION as $codigo => $nombre) {
            $resultado[] = ['codigo' => $codigo, 'nombre' => $nombre];
        }

        return $resultado;
    }

    /**
     * Valida el payload completo de productor (POST/PUT) y devuelve los datos
     * normalizados junto a los errores por campo.
     *
     * @return array{datos: array, errores: array<string,string>}
     */
    public function validarProductor(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'];
        if ($actualizacion) {
            $permitidos[] = 'identificacionNumeroOriginal';
        }
        $errores = $this->rechazarCamposDesconocidos($cuerpo, $permitidos);

        $identificacion = $this->validarIdentificacion($cuerpo['identificacion'] ?? null, $errores);
        $nombre = $this->textoCampo($cuerpo['nombre'] ?? null, 'nombre', 150, $errores, 3);
        $telefono = $this->validarTelefono($cuerpo['telefono'] ?? null, $errores);
        $correo = $this->validarCorreo($cuerpo['correoElectronico'] ?? null, $errores);
        $direccion = array_key_exists('direccionPrincipal', $cuerpo)
            ? $this->validarDireccion($cuerpo['direccionPrincipal'], $errores)
            : null;
        if ($actualizacion && $direccion === null) {
            $errores['direccionPrincipal'] = 'La dirección es obligatoria.';
        }
        $fincas = $this->validarFincas($cuerpo['fincas'] ?? [], $errores);

        $original = null;
        if ($actualizacion) {
            $originalTexto = is_string($cuerpo['identificacionNumeroOriginal'] ?? null)
                ? $cuerpo['identificacionNumeroOriginal'] : '';
            $original = $this->normalizarIdentificacion($originalTexto);
            if ($original === '') {
                $errores['identificacionNumeroOriginal'] = 'La identificación original es obligatoria.';
            }
        }

        if ($errores !== []) {
            throw new ValidacionException('Revise los campos indicados.', $errores);
        }

        return [
            'datos' => [
                'identificacionNumero' => $identificacion['numero'],
                'identificacionNumeroOriginal' => $original,
                'identificacionTipo' => $identificacion['tipoCodigo'],
                'nombre' => $nombre,
                'telefono' => $telefono,
                'correoElectronico' => $correo,
                'direccion' => $direccion,
                'fincas' => $fincas,
            ],
            'errores' => [],
        ];
    }

    /**
     * Valida el payload de una persona con capacidades (Comprador/Transportista)
     * que comparte el contrato de Productor sin dirección ni fincas.
     *
     * @return array{datos: array, errores: array<string,string>}
     */
    public function validarPersona(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico'];
        if ($actualizacion) {
            $permitidos[] = 'identificacionNumeroOriginal';
        }
        $errores = $this->rechazarCamposDesconocidos($cuerpo, $permitidos);

        $identificacion = $this->validarIdentificacion($cuerpo['identificacion'] ?? null, $errores);
        $nombre = $this->textoCampo($cuerpo['nombre'] ?? null, 'nombre', 150, $errores, 3);
        $telefono = $this->validarTelefono($cuerpo['telefono'] ?? null, $errores);
        $correo = $this->validarCorreo($cuerpo['correoElectronico'] ?? null, $errores);

        $original = null;
        if ($actualizacion) {
            $originalTexto = is_string($cuerpo['identificacionNumeroOriginal'] ?? null)
                ? $cuerpo['identificacionNumeroOriginal'] : '';
            $original = $this->normalizarIdentificacion($originalTexto);
            if ($original === '') {
                $errores['identificacionNumeroOriginal'] = 'La identificación original es obligatoria.';
            }
        }

        if ($errores !== []) {
            throw new ValidacionException('Revise los campos indicados.', $errores);
        }

        return [
            'datos' => [
                'identificacionNumero' => $identificacion['numero'],
                'identificacionNumeroOriginal' => $original,
                'identificacionTipo' => $identificacion['tipoCodigo'],
                'nombre' => $nombre,
                'telefono' => $telefono,
                'correoElectronico' => $correo,
            ],
            'errores' => [],
        ];
    }

    /**
     * Valida una identificación única de cuerpo (DELETE/PATCH).
     *
     * @throws ValidacionException
     */
    public function validarIdentificacionUnica(array $cuerpo): string
    {
        $errores = $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero']);
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
            ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
        if ($identificacion === '') {
            $errores['identificacionNumero'] = 'La identificación es obligatoria.';
        }
        if ($errores !== []) {
            throw new ValidacionException('Revise los campos indicados.', $errores);
        }

        return $identificacion;
    }

    /**
     * Valida una identificación + dirección (rutas de dirección del productor).
     *
     * @return array{identificacionNumero: string, direccion: array}
     * @throws ValidacionException
     */
    public function validarIdentificacionYDireccion(array $cuerpo, string $campoDireccion): array
    {
        $errores = $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', $campoDireccion]);
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
            ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
        if ($identificacion === '') {
            $errores['identificacionNumero'] = 'La identificación es obligatoria.';
        }
        $direccion = $this->validarDireccionEnCampo($cuerpo[$campoDireccion] ?? null, $campoDireccion, $errores);
        if ($errores !== []) {
            throw new ValidacionException('Revise los campos indicados.', $errores);
        }

        return ['identificacionNumero' => $identificacion, 'direccion' => $direccion];
    }

    /** Valida la dirección de productor con el campo por defecto direccionPrincipal. */
    public function validarDireccion(mixed $valor, array &$errores): array
    {
        return $this->validarDireccionEnCampo($valor, 'direccionPrincipal', $errores);
    }

    public function validarDireccionEnCampo(mixed $valor, string $campo, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores[$campo] = 'La dirección debe ser un objeto.';

            return ['provincia' => '', 'canton' => '', 'distrito' => '', 'pueblo' => null, 'senas' => null];
        }
        $errores += $this->rechazarCamposDesconocidos(
            $valor,
            ['provincia', 'canton', 'distrito', 'pueblo', 'senas'],
            $campo . '.',
        );

        return [
            'provincia' => $this->textoCampo($valor['provincia'] ?? null, $campo . '.provincia', 100, $errores, 1),
            'canton' => $this->textoCampo($valor['canton'] ?? null, $campo . '.canton', 100, $errores, 1),
            'distrito' => $this->textoCampo($valor['distrito'] ?? null, $campo . '.distrito', 100, $errores, 1),
            'pueblo' => $this->textoOpcional($valor['pueblo'] ?? null, $campo . '.pueblo', 150, $errores),
            'senas' => $this->textoOpcional($valor['senas'] ?? null, $campo . '.senas', 500, $errores),
        ];
    }

    public function validarIdentificacion(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores['identificacion'] = 'La identificación debe ser un objeto.';

            return ['tipoCodigo' => '', 'numero' => ''];
        }
        $errores += $this->rechazarCamposDesconocidos($valor, ['tipoCodigo', 'numero'], 'identificacion.');
        $tipo = is_string($valor['tipoCodigo'] ?? null)
            ? mb_strtoupper(trim($valor['tipoCodigo']), 'UTF-8') : '';
        if (!array_key_exists($tipo, self::TIPOS_IDENTIFICACION)) {
            $errores['identificacion.tipoCodigo'] = 'Seleccione un tipo de identificación válido.';
        }
        $visible = $this->textoCampo($valor['numero'] ?? null, 'identificacion.numero', 250, $errores, 1, false);
        $patron = in_array($tipo, ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'], true)
            ? '/^[0-9][0-9 -]*$/' : '/^[A-Za-z0-9][A-Za-z0-9 -]*$/';
        if ($visible !== '' && !preg_match($patron, $visible)) {
            $errores['identificacion.numero'] = 'Use únicamente letras, dígitos, espacios o guiones según el tipo.';
        }

        return ['tipoCodigo' => $tipo, 'numero' => $this->normalizarIdentificacion($visible)];
    }

    public function validarTelefono(mixed $valor, array &$errores): string
    {
        $telefono = $this->textoCampo($valor, 'telefono', 20, $errores, 1, false);
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';
        if ($telefono !== '' && (!preg_match('/^\+?[0-9 ()-]+$/', $telefono) || strlen($digitos) < 8 || strlen($digitos) > 15)) {
            $errores['telefono'] = 'Use un prefijo opcional y entre 8 y 15 dígitos.';
        }

        return preg_replace('/[ ()-]+/', '', $telefono) ?? $telefono;
    }

    public function validarCorreo(mixed $valor, array &$errores): string
    {
        $correo = mb_strtolower($this->textoCampo($valor, 'correoElectronico', 150, $errores, 1, false), 'UTF-8');
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $errores['correoElectronico'] = 'Ingrese un correo electrónico válido.';
        }

        return $correo;
    }

    public function validarFincas(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || !array_is_list($valor)) {
            $errores['fincas'] = 'Las fincas deben ser una lista.';

            return [];
        }
        $nombres = [];
        foreach ($valor as $indice => $finca) {
            if (!is_array($finca) || array_keys($finca) !== ['nombre']) {
                $errores["fincas.{$indice}"] = 'Cada finca debe contener únicamente nombre.';
                continue;
            }
            $nombre = trim((string) $finca['nombre']);
            if ($nombre === '' || mb_strlen($nombre) > 150) {
                $errores["fincas.{$indice}.nombre"] = 'El nombre debe contener entre 1 y 150 caracteres.';
                continue;
            }
            $clave = mb_strtoupper($nombre, 'UTF-8');
            if (isset($nombres[$clave])) {
                $errores['fincas'] = 'No repita la misma finca.';
            }
            $nombres[$clave] = $nombre;
        }

        return array_values($nombres);
    }

    public function normalizarIdentificacion(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[ -]+/u', '', trim($valor)) ?? '', 'UTF-8');
    }

    /**
     * @return array<string,string> errores de campos no permitidos (vacía si todo está bien)
     */
    public function rechazarCamposDesconocidos(array $datos, array $permitidos, string $prefijo = ''): array
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) {
            return [];
        }
        $errores = [];
        foreach ($desconocidos as $campo) {
            $errores[$prefijo . $campo] = 'Campo no permitido.';
        }

        return $errores;
    }

    public function textoCampo(mixed $valor, string $campo, int $maximo, array &$errores, int $minimo = 0, bool $compactar = true): string
    {
        if (!is_string($valor)) {
            $errores[$campo] = 'El campo es obligatorio.';

            return '';
        }
        $texto = trim($valor);
        if ($compactar) {
            $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
        }
        $longitud = mb_strlen($texto);
        if ($longitud < $minimo || $longitud > $maximo) {
            $errores[$campo] = "Debe contener entre {$minimo} y {$maximo} caracteres.";
        }

        return $texto;
    }

    public function textoOpcional(mixed $valor, string $campo, int $maximo, array &$errores): ?string
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
}
