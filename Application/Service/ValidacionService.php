<?php

declare(strict_types=1);

namespace Application\Service;

final class ValidacionException extends \RuntimeException
{
    public function __construct(string $message, public readonly array $errores = [])
    {
        parent::__construct($message);
    }
}

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

    public function validarProductor(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'alias', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'];
        if ($actualizacion) {
            $permitidos[] = 'identificacionNumeroOriginal';
        }
        $errores = $this->rechazarCamposDesconocidos($cuerpo, $permitidos);

        $identificacion = $this->validarIdentificacion($cuerpo['identificacion'] ?? null, $errores);
        $nombre = $this->textoCampo($cuerpo['nombre'] ?? null, 'nombre', 150, $errores, 3);
        $alias = $this->textoOpcional($cuerpo['alias'] ?? null, 'alias', 150, $errores);
        $telefono = $this->validarTelefono($cuerpo['telefono'] ?? null, $errores);
        $correo = $this->validarCorreo($cuerpo['correoElectronico'] ?? null, $errores);
        $direccion = array_key_exists('direccionPrincipal', $cuerpo)
            ? $this->validarDireccion($cuerpo['direccionPrincipal'], $errores)
            : null;
        if ($actualizacion && $direccion === null) {
            $errores['direccionPrincipal'] = 'La dirección es obligatoria.';
        }
        $fincasDetalle = $this->validarFincas($cuerpo['fincas'] ?? [], $errores);

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
                'alias' => $alias,
                'telefono' => $telefono,
                'correoElectronico' => $correo,
                'direccion' => $direccion,
                // ProductorFinca conserva el contrato de nombres. El detalle se
                // mantiene aparte para que ProductorController pueda persistir
                // cada dirección de finca dentro de la MISMA transacción.
                'fincas' => array_map(
                    static fn (array $finca): string => $finca['nombre'],
                    $fincasDetalle,
                ),
                'fincasDetalle' => $fincasDetalle,
            ],
            'errores' => [],
        ];
    }

    public function validarPersona(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'alias', 'telefono', 'correoElectronico'];
        if ($actualizacion) {
            $permitidos[] = 'identificacionNumeroOriginal';
        }
        $errores = $this->rechazarCamposDesconocidos($cuerpo, $permitidos);

        $identificacion = $this->validarIdentificacion($cuerpo['identificacion'] ?? null, $errores);
        $nombre = $this->textoCampo($cuerpo['nombre'] ?? null, 'nombre', 150, $errores, 3);
        $alias = $this->textoOpcional($cuerpo['alias'] ?? null, 'alias', 150, $errores);
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
                'alias' => $alias,
                'telefono' => $telefono,
                'correoElectronico' => $correo,
            ],
            'errores' => [],
        ];
    }

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

    /**
     * Dirección de finca: comparte los campos manuales de Dirección y agrega un
     * punto geográfico opcional. La base solo almacena tipos/atributos; paridad
     * y rangos de coordenadas son política de PHP.
     */
    public function validarDireccionFincaEnCampo(mixed $valor, string $campo, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores[$campo] = 'La dirección debe ser un objeto.';
            return [
                'provincia' => '', 'canton' => '', 'distrito' => '',
                'pueblo' => null, 'senas' => null, 'latitud' => null, 'longitud' => null,
            ];
        }

        $base = $valor;
        unset($base['latitud'], $base['longitud']);
        $direccion = $this->validarDireccionEnCampo($base, $campo, $errores);
        $punto = $this->validarPuntoDireccion($valor, $campo, $errores);

        return [...$direccion, ...$punto];
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

    /**
     * Valida fincas como objetos independientes. La dirección es opcional, pero
     * cuando se envía debe ser completa y su punto geográfico debe venir en par.
     * La política actual de nombre único se conserva porque ProductorFinca aún
     * usa (productor, nombre) para resolver una finca; cambiarla requiere otra
     * decisión de identidad, no solo relajar este validador.
     *
     * @return array<int,array{nombre:string,direccion?:array<string,mixed>}>
     */
    public function validarFincas(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || !array_is_list($valor)) {
            $errores['fincas'] = 'Las fincas deben ser una lista.';
            return [];
        }

        $fincas = [];
        foreach ($valor as $indice => $finca) {
            if (!is_array($finca) || array_is_list($finca)) {
                $errores["fincas.{$indice}"] = 'Cada finca debe ser un objeto.';
                continue;
            }
            $desconocidos = array_diff(array_keys($finca), ['nombre', 'direccion']);
            if ($desconocidos !== []) {
                foreach ($desconocidos as $campo) {
                    $errores["fincas.{$indice}.{$campo}"] = 'Campo no permitido.';
                }
            }

            $nombre = trim((string) ($finca['nombre'] ?? ''));
            if ($nombre === '' || mb_strlen($nombre) > 150) {
                $errores["fincas.{$indice}.nombre"] = 'El nombre debe contener entre 1 y 150 caracteres.';
                continue;
            }

            $clave = mb_strtoupper($nombre, 'UTF-8');
            if (isset($fincas[$clave])) {
                $errores['fincas'] = 'No repita la misma finca.';
                continue;
            }

            $validada = ['nombre' => $nombre];
            if (array_key_exists('direccion', $finca)) {
                $validada['direccion'] = $this->validarDireccionFincaEnCampo(
                    $finca['direccion'],
                    "fincas.{$indice}.direccion",
                    $errores,
                );
            }
            $fincas[$clave] = $validada;
        }

        return array_values($fincas);
    }

    private function validarPuntoDireccion(array $direccion, string $campo, array &$errores): array
    {
        $latitudCruda = $direccion['latitud'] ?? null;
        $longitudCruda = $direccion['longitud'] ?? null;
        $latitudVacia = $latitudCruda === null || (is_string($latitudCruda) && trim($latitudCruda) === '');
        $longitudVacia = $longitudCruda === null || (is_string($longitudCruda) && trim($longitudCruda) === '');

        if ($latitudVacia && $longitudVacia) {
            return ['latitud' => null, 'longitud' => null];
        }
        if ($latitudVacia || $longitudVacia) {
            $mensaje = 'Latitud y longitud deben enviarse juntas o ambas quedar vacías.';
            $errores[$campo . '.latitud'] = $mensaje;
            $errores[$campo . '.longitud'] = $mensaje;
            return ['latitud' => null, 'longitud' => null];
        }
        if (!is_numeric($latitudCruda) || !is_numeric($longitudCruda)) {
            $errores[$campo . '.latitud'] = 'La latitud debe ser numérica.';
            $errores[$campo . '.longitud'] = 'La longitud debe ser numérica.';
            return ['latitud' => null, 'longitud' => null];
        }

        $latitud = (float) $latitudCruda;
        $longitud = (float) $longitudCruda;
        $valido = true;
        if (!is_finite($latitud) || $latitud < -90 || $latitud > 90) {
            $errores[$campo . '.latitud'] = 'La latitud debe estar entre -90 y 90.';
            $valido = false;
        }
        if (!is_finite($longitud) || $longitud < -180 || $longitud > 180) {
            $errores[$campo . '.longitud'] = 'La longitud debe estar entre -180 y 180.';
            $valido = false;
        }
        if (!$valido) {
            return ['latitud' => null, 'longitud' => null];
        }

        return [
            'latitud' => number_format($latitud, 7, '.', ''),
            'longitud' => number_format($longitud, 7, '.', ''),
        ];
    }

    public function normalizarIdentificacion(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[ -]+/u', '', trim($valor)) ?? '', 'UTF-8');
    }

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
