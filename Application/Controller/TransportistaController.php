<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use PDO;
use Throwable;

final class TransportistaHttpException extends \RuntimeException
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

final class TransportistaController
{
    private const TIPOS_IDENTIFICACION = [
        'CEDULA_FISICA' => 'Cédula física',
        'CEDULA_JURIDICA' => 'Cédula jurídica',
        'DIMEX' => 'DIMEX',
        'NITE' => 'NITE',
        'PASAPORTE' => 'Pasaporte',
    ];

    private Transportista $transportista;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $vehiculos = new TransportistaVehiculo($conexion);
        $this->transportista = new Transportista($conexion, $vehiculos);
        $this->bitacora = new Bitacora($conexion);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesar(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar($consulta),
                'POST' => $this->crear($cuerpo),
                'PUT' => $this->actualizar($cuerpo),
                'DELETE' => $this->desactivar($cuerpo),
                'PATCH' => $this->reactivar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (TransportistaHttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    private function consultar(array $consulta): array
    {
        if (array_key_exists('identificacionNumero', $consulta)) {
            $identificacion = $this->normalizarIdentificacion(
                $this->textoConsulta($consulta['identificacionNumero'], 250)
            );
            if ($identificacion === '') {
                throw new TransportistaHttpException('La identificación no es válida.', 422);
            }
            $transportista = $this->transportista->buscar($identificacion);
            if ($transportista === null) {
                throw new TransportistaHttpException('Transportista no encontrado.', 404);
            }
            return $this->respuesta(true, 'Transportista consultado correctamente.', $transportista);
        }

        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'TODOS', 10), 'UTF-8');
        if (!in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO'], true)) {
            throw new TransportistaHttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use TODOS, ACTIVO o INACTIVO.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta) ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new TransportistaHttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->transportista->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['catalogos'] = ['tiposIdentificacion' => $this->tiposIdentificacion()];

        return $this->respuesta(true, 'Transportistas consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarTransportista($cuerpo, false);
        $nuevo = $this->transportista->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos): array {
                $existente = $this->transportista->buscar($datos['identificacionNumero']);
                if ($existente !== null) {
                    $inactivo = $existente['estado'] === 'INACTIVO';
                    throw new TransportistaHttpException(
                        $inactivo
                            ? 'La identificación pertenece a un transportista inactivo.'
                            : 'La identificación ya está registrada.',
                        409,
                        $inactivo
                            ? ['reactivacion' => ['identificacionNumero' => $datos['identificacionNumero']]]
                            : null,
                        ['identificacion.numero' => $inactivo
                            ? 'Debe reactivarse el transportista existente.'
                            : 'El número de identificación ya existe.'],
                    );
                }
                $this->transportista->crear($datos);
                $nuevo = $this->transportista->buscar($datos['identificacionNumero']);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el transportista recién creado.');
                }
                $this->bitacora->registrar(
                    'CREAR',
                    $datos['identificacionNumero'],
                    null,
                    $nuevo,
                    $this->solicitudId,
                    entidad: 'TRANSPORTISTA',
                    origen: 'API_TRANSPORTISTAS',
                );
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Transportista creado correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarTransportista($cuerpo, true);
        $identificacion = $datos['identificacionNumeroOriginal'];
        if ($datos['identificacionNumero'] !== $identificacion) {
            throw new TransportistaHttpException('La identificación es inmutable y no puede modificarse.', 422, null, [
                'identificacion.numero' => 'Cree otro registro si la identificación fue digitada incorrectamente.',
            ]);
        }

        $nuevo = $this->transaccion(function () use ($datos, $identificacion): array {
            $bloqueado = $this->transportista->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new TransportistaHttpException('Transportista no encontrado.', 404);
            }
            if ((int) $bloqueado['tbtransportistaestado'] !== 1) {
                throw new TransportistaHttpException(
                    'El transportista está inactivo. Debe reactivarlo antes de actualizarlo.',
                    409,
                );
            }
            $anterior = $this->transportista->buscar($identificacion);
            $this->transportista->actualizar($identificacion, $datos);
            $nuevo = $this->transportista->buscar($identificacion);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el transportista actualizado.');
            }
            $this->bitacora->registrar(
                'ACTUALIZAR', $identificacion, $anterior, $nuevo, $this->solicitudId,
                entidad: 'TRANSPORTISTA', origen: 'API_TRANSPORTISTAS',
            );
            return $nuevo;
        });

        return $this->respuesta(true, 'Transportista actualizado correctamente.', $nuevo);
    }

    private function desactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->transportista->bloquear($identificacion);
            $anterior = $this->transportista->buscar($identificacion);
            if ($bloqueado === null || $anterior === null) {
                throw new TransportistaHttpException('Transportista no encontrado.', 404);
            }
            if ((int) $bloqueado['tbtransportistaestado'] === 0) {
                return $anterior;
            }
            $this->transportista->cambiarEstado($identificacion, false);
            $nuevo = $this->transportista->buscar($identificacion);
            $this->bitacora->registrar(
                'DESACTIVAR', $identificacion, $anterior, $nuevo, $this->solicitudId,
                entidad: 'TRANSPORTISTA', origen: 'API_TRANSPORTISTAS',
            );
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el transportista desactivado.');
        });

        return $this->respuesta(true, 'Transportista desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->transportista->bloquear($identificacion);
            $anterior = $this->transportista->buscar($identificacion);
            if ($bloqueado === null || $anterior === null) {
                throw new TransportistaHttpException('Transportista no encontrado.', 404);
            }
            if ((int) $bloqueado['tbtransportistaestado'] === 1) {
                return $anterior;
            }
            $this->transportista->cambiarEstado($identificacion, true);
            $nuevo = $this->transportista->buscar($identificacion);
            $this->bitacora->registrar(
                'REACTIVAR', $identificacion, $anterior, $nuevo, $this->solicitudId,
                entidad: 'TRANSPORTISTA', origen: 'API_TRANSPORTISTAS',
            );
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el transportista reactivado.');
        });

        return $this->respuesta(true, 'Transportista reactivado correctamente.', $nuevo);
    }

    private function validarTransportista(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico'];
        if ($actualizacion) {
            $permitidos[] = 'identificacionNumeroOriginal';
        }
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];
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
            throw new TransportistaHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return [
            'identificacionNumero' => $identificacion['numero'],
            'identificacionNumeroOriginal' => $original,
            'identificacionTipo' => $identificacion['tipoCodigo'],
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correoElectronico' => $correo,
        ];
    }

    private function validarIdentificacion(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores['identificacion'] = 'La identificación debe ser un objeto.';
            return ['tipoCodigo' => '', 'numero' => ''];
        }
        try {
            $this->rechazarCamposDesconocidos($valor, ['tipoCodigo', 'numero'], 'identificacion.');
        } catch (TransportistaHttpException $excepcion) {
            $errores += $excepcion->errores;
        }
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
        $numero = $this->normalizarIdentificacion($visible);
        return ['tipoCodigo' => $tipo, 'numero' => $numero];
    }

    private function validarTelefono(mixed $valor, array &$errores): string
    {
        $telefono = $this->textoCampo($valor, 'telefono', 20, $errores, 1, false);
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';
        if ($telefono !== '' && (!preg_match('/^\+?[0-9 ()-]+$/', $telefono) || strlen($digitos) < 8 || strlen($digitos) > 15)) {
            $errores['telefono'] = 'Use un prefijo opcional y entre 8 y 15 dígitos.';
        }
        return preg_replace('/[ ()-]+/', '', $telefono) ?? $telefono;
    }

    private function validarCorreo(mixed $valor, array &$errores): string
    {
        $correo = mb_strtolower($this->textoCampo($valor, 'correoElectronico', 150, $errores, 1, false), 'UTF-8');
        if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $errores['correoElectronico'] = 'Ingrese un correo electrónico válido.';
        }
        return $correo;
    }

    private function validarIdentificacionUnica(array $cuerpo): string
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero']);
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
            ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
        if ($identificacion === '') {
            throw new TransportistaHttpException('Revise los campos indicados.', 422, null, [
                'identificacionNumero' => 'La identificación es obligatoria.',
            ]);
        }
        return $identificacion;
    }

    private function normalizarIdentificacion(string $valor): string
    {
        return mb_strtoupper(preg_replace('/[ -]+/u', '', trim($valor)) ?? '', 'UTF-8');
    }

    private function tiposIdentificacion(): array
    {
        $resultado = [];
        foreach (self::TIPOS_IDENTIFICACION as $codigo => $nombre) {
            $resultado[] = ['codigo' => $codigo, 'nombre' => $nombre];
        }
        return $resultado;
    }

    private function textoCampo(mixed $valor, string $campo, int $maximo, array &$errores, int $minimo = 0, bool $compactar = true): string
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

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor) || mb_strlen($valor) > $maximo) {
            throw new TransportistaHttpException('La consulta no es válida.', 422);
        }
        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new TransportistaHttpException("{$campo} debe ser un entero positivo.", 422);
        }
        return $entero;
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
        throw new TransportistaHttpException('Revise los campos indicados.', 422, null, $errores);
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
