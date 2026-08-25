<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\Productor;
use Application\Model\ProductorDireccion;
use Application\Model\ProductorEstadoPeriodo;
use Application\Model\ProductorFinca;
use Application\Model\Direccion;
use PDO;
use Throwable;

final class ProductorHttpException extends \RuntimeException
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

final class ProductorController
{
    private const TIPOS_IDENTIFICACION = [
        'CEDULA_FISICA' => 'Cédula física',
        'CEDULA_JURIDICA' => 'Cédula jurídica',
        'DIMEX' => 'DIMEX',
        'NITE' => 'NITE',
        'PASAPORTE' => 'Pasaporte',
    ];

    private Productor $productor;
    private ProductorDireccion $direccion;
    private ProductorEstadoPeriodo $estadoPeriodos;
    private ProductorFinca $fincas;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->fincas = new ProductorFinca($conexion);
        $this->productor = new Productor($conexion, $this->fincas);
        $this->direccion = new ProductorDireccion($conexion, new Direccion($conexion));
        $this->estadoPeriodos = new ProductorEstadoPeriodo($conexion);
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
        } catch (\Application\Model\PersonaConflictException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), null, 409, [
                'identificacion.numero' => $excepcion->getMessage(),
            ]);
        } catch (ProductorHttpException $excepcion) {
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
                throw new ProductorHttpException('La identificación no es válida.', 422);
            }
            $productor = $this->productor->buscar($identificacion);
            if ($productor === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            return $this->respuesta(true, 'Productor consultado correctamente.', $productor);
        }

        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'TODOS', 10), 'UTF-8');
        if (!in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO'], true)) {
            throw new ProductorHttpException('El filtro de estado no es válido.', 422, null, [
                'estado' => 'Use TODOS, ACTIVO o INACTIVO.',
            ]);
        }
        $pagina = array_key_exists('pagina', $consulta) ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina') : 25;
        if ($tamano > 100) {
            throw new ProductorHttpException('El tamaño de página no es válido.', 422, null, [
                'tamanoPagina' => 'Debe estar entre 1 y 100.',
            ]);
        }
        $resultado = $this->productor->listar($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['catalogos'] = ['tiposIdentificacion' => $this->tiposIdentificacion()];

        return $this->respuesta(true, 'Productores consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarProductor($cuerpo, false);
        $nuevo = $this->productor->ejecutarConBloqueoAlta(
            fn (): array => $this->direccion->ejecutarConBloqueoAlta(
                fn (): array => $this->fincas->ejecutarConBloqueoAlta(
                    fn (): array => $this->transaccion(function () use ($datos): array {
                        $existente = $this->productor->buscar($datos['identificacionNumero']);
                        if ($existente !== null) {
                            $inactivo = $existente['estado'] === 'INACTIVO';
                            throw new ProductorHttpException(
                                $inactivo
                                    ? 'La identificación pertenece a un productor inactivo.'
                                    : 'La identificación ya está registrada.',
                                409,
                                $inactivo
                                    ? ['reactivacion' => ['identificacionNumero' => $datos['identificacionNumero']]]
                                    : null,
                                ['identificacion.numero' => $inactivo
                                    ? 'Debe reactivarse el productor existente.'
                                    : 'El número de identificación ya existe.'],
                            );
                        }
                        $productorId = $this->productor->crear($datos);
                        if ($datos['direccion'] === null) {
                            $this->direccion->crearVacia($productorId);
                        } else {
                            $this->direccion->crear($productorId, $datos['direccion']);
                        }
                        $this->fincas->sincronizar($productorId, $datos['fincas']);
                        $this->estadoPeriodos->ejecutarConBloqueo(
                            $productorId,
                            fn (): int => $this->estadoPeriodos->abrir($productorId, 1, 'Alta del productor'),
                        );
                        $nuevo = $this->productor->buscar($datos['identificacionNumero']);
                        if ($nuevo === null) {
                            throw new \RuntimeException('No fue posible leer el productor recién creado.');
                        }
                        $this->bitacora->registrar(
                            'CREAR',
                            $datos['identificacionNumero'],
                            null,
                            $nuevo,
                            $this->solicitudId,
                        );
                        return $nuevo;
                    }),
                ),
            ),
        );

        return $this->respuesta(true, 'Productor creado correctamente.', $nuevo, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarProductor($cuerpo, true);
        $identificacion = $datos['identificacionNumeroOriginal'];
        if ($datos['identificacionNumero'] !== $identificacion) {
            throw new ProductorHttpException('La identificación es inmutable y no puede modificarse.', 422, null, [
                'identificacion.numero' => 'Cree otro registro si la identificación fue digitada incorrectamente.',
            ]);
        }

        $nuevo = $this->fincas->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($datos, $identificacion): array {
                $bloqueado = $this->productor->bloquear($identificacion);
                if ($bloqueado === null) {
                    throw new ProductorHttpException('Productor no encontrado.', 404);
                }
                if ((int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                    throw new ProductorHttpException(
                        'El productor está inactivo. Debe reactivarlo antes de actualizarlo.',
                        409,
                    );
                }
                $anterior = $this->productor->buscar($identificacion);
                if ($anterior === null) {
                    throw new ProductorHttpException('El productor no conserva su dirección obligatoria.', 409);
                }
                $this->productor->actualizar($identificacion, $datos);
                $productorId = (int) $bloqueado['tbproductorid'];
                $this->direccion->actualizar($productorId, $datos['direccion']);
                $this->fincas->sincronizar($productorId, $datos['fincas']);
                $nuevo = $this->productor->buscar($identificacion);
                if ($nuevo === null) {
                    throw new \RuntimeException('No fue posible leer el productor actualizado.');
                }
                $this->bitacora->registrar('ACTUALIZAR', $identificacion, $anterior, $nuevo, $this->solicitudId);
                return $nuevo;
            }),
        );

        return $this->respuesta(true, 'Productor actualizado correctamente.', $nuevo);
    }

    /**
     * Ruta de reparación: crea la dirección de un productor que quedó sin fila
     * por datos heredados o corrupción previa. El alta normal siempre crea el
     * enlace de dirección: con los datos recibidos en POST o vacío para clientes
     * heredados que todavía no envían direccionPrincipal.
     */
    public function crearDireccion(array $cuerpo): array
    {
        try {
            $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', 'direccionPrincipal']);
            $errores = [];
            $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
                ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
            if ($identificacion === '') {
                $errores['identificacionNumero'] = 'La identificación es obligatoria.';
            }
            $direccion = $this->validarDireccion($cuerpo['direccionPrincipal'] ?? null, $errores);
            if ($errores !== []) {
                throw new ProductorHttpException('Revise los campos indicados.', 422, null, $errores);
            }

            $nuevo = $this->direccion->ejecutarConBloqueoAlta(
                fn (): array => $this->transaccion(function () use ($identificacion, $direccion): array {
                    $bloqueado = $this->productor->bloquear($identificacion);
                    if ($bloqueado === null) {
                        throw new ProductorHttpException('Productor no encontrado.', 404);
                    }
                    $productorId = (int) $bloqueado['tbproductorid'];
                    try {
                        $this->direccion->crear($productorId, $direccion);
                    } catch (\RuntimeException $excepcion) {
                        throw new ProductorHttpException($excepcion->getMessage(), 409);
                    }
                    $nuevo = $this->productor->buscar($identificacion);
                    $this->bitacora->registrar('CREAR_DIRECCION', $identificacion, null, $nuevo, $this->solicitudId);
                    return $nuevo ?? throw new \RuntimeException('No fue posible leer el productor tras crear la dirección.');
                }),
            );

            return $this->respuesta(true, 'Dirección creada correctamente.', $nuevo, 201);
        } catch (ProductorHttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    public function procesarDireccion(string $metodo, array $consulta, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultarDireccion($consulta),
                'POST' => $this->crearDireccion($cuerpo),
                'PUT' => $this->actualizarDireccion($cuerpo),
                'DELETE' => $this->eliminarDireccion($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (ProductorHttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores
            );
        }
    }

    private function desactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            $anterior = $this->productor->buscar($identificacion);
            if ($bloqueado === null || $anterior === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new ProductorHttpException('La persona está inactiva y no puede operar capacidades.', 409);
            }
            $productorId = (int) $bloqueado['tbproductorid'];
            $transicionOcurrida = $this->estadoPeriodos->ejecutarConBloqueo(
                $productorId,
                function () use ($productorId): bool {
                    $abierto = $this->estadoPeriodos->consultarAbierto($productorId);
                    if ($abierto !== null && (int) $abierto['tbproductorestadoperiodoestado'] === 0) {
                        return false;
                    }
                    if ($abierto !== null) {
                        $this->estadoPeriodos->cerrar($productorId);
                    }
                    $this->estadoPeriodos->abrir($productorId, 0, 'Desactivación');
                    return true;
                },
            );
            if (!$transicionOcurrida) {
                return $anterior;
            }
            $nuevo = $this->productor->buscar($identificacion);
            $this->bitacora->registrar('DESACTIVAR', $identificacion, $anterior, $nuevo, $this->solicitudId);
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el productor desactivado.');
        });

        return $this->respuesta(true, 'Productor desactivado correctamente.', $nuevo);
    }

    private function reactivar(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);
        $nuevo = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            $anterior = $this->productor->buscar($identificacion);
            if ($bloqueado === null || $anterior === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new ProductorHttpException('La persona está inactiva y no puede reactivar capacidades.', 409);
            }
            $productorId = (int) $bloqueado['tbproductorid'];
            $transicionOcurrida = $this->estadoPeriodos->ejecutarConBloqueo(
                $productorId,
                function () use ($productorId): bool {
                    $abierto = $this->estadoPeriodos->consultarAbierto($productorId);
                    if ($abierto !== null && (int) $abierto['tbproductorestadoperiodoestado'] === 1) {
                        return false;
                    }
                    if ($abierto !== null) {
                        $this->estadoPeriodos->cerrar($productorId);
                    }
                    $this->estadoPeriodos->abrir($productorId, 1, 'Reactivación');
                    return true;
                },
            );
            if (!$transicionOcurrida) {
                return $anterior;
            }
            $nuevo = $this->productor->buscar($identificacion);
            $this->bitacora->registrar('REACTIVAR', $identificacion, $anterior, $nuevo, $this->solicitudId);
            return $nuevo ?? throw new \RuntimeException('No fue posible leer el productor reactivado.');
        });

        return $this->respuesta(true, 'Productor reactivado correctamente.', $nuevo);
    }

    private function validarProductor(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'];
        if ($actualizacion) {
            $permitidos[] = 'identificacionNumeroOriginal';
        }
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];
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
            throw new ProductorHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return [
            'identificacionNumero' => $identificacion['numero'],
            'identificacionNumeroOriginal' => $original,
            'identificacionTipo' => $identificacion['tipoCodigo'],
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correoElectronico' => $correo,
            'direccion' => $direccion,
            'fincas' => $fincas,
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
        } catch (ProductorHttpException $excepcion) {
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

    private function validarDireccion(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores['direccionPrincipal'] = 'La dirección debe ser un objeto.';
            return ['provincia' => '', 'canton' => '', 'distrito' => '', 'pueblo' => null, 'senas' => null];
        }
        try {
            $this->rechazarCamposDesconocidos($valor, ['provincia', 'canton', 'distrito', 'pueblo', 'senas'], 'direccionPrincipal.');
        } catch (ProductorHttpException $excepcion) {
            $errores += $excepcion->errores;
        }
        return [
            'provincia' => $this->textoCampo($valor['provincia'] ?? null, 'direccionPrincipal.provincia', 100, $errores, 1),
            'canton' => $this->textoCampo($valor['canton'] ?? null, 'direccionPrincipal.canton', 100, $errores, 1),
            'distrito' => $this->textoCampo($valor['distrito'] ?? null, 'direccionPrincipal.distrito', 100, $errores, 1),
            'pueblo' => $this->textoOpcional($valor['pueblo'] ?? null, 'direccionPrincipal.pueblo', 150, $errores),
            'senas' => $this->textoOpcional($valor['senas'] ?? null, 'direccionPrincipal.senas', 500, $errores),
        ];
    }

    private function consultarDireccion(array $consulta): array
    {
        if (!array_key_exists('identificacionNumero', $consulta)) {
            throw new ProductorHttpException('Debe indicar identificacionNumero.', 422, null, [
                'identificacionNumero' => 'El parámetro es obligatorio.',
            ]);
        }
        $identificacion = $this->normalizarIdentificacion(
            $this->textoConsulta($consulta['identificacionNumero'], 250)
        );
        if ($identificacion === '') {
            throw new ProductorHttpException('La identificación no es válida.', 422);
        }
        $productor = $this->productor->buscar($identificacion);
        if ($productor === null) {
            throw new ProductorHttpException('Productor no encontrado.', 404);
        }
        $productorId = (int) $productor['productorId'];
        $direccion = $this->direccion->buscar($productorId);
        if ($direccion === null) {
            throw new ProductorHttpException('El productor no tiene una dirección registrada.', 404);
        }

        return $this->respuesta(true, 'Dirección consultada correctamente.', [
            'identificacionNumero' => $identificacion,
            'direccionPrincipal' => $direccion,
        ]);
    }

    private function actualizarDireccion(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['identificacionNumero', 'direccionPrincipal']);
        $errores = [];
        $identificacion = is_string($cuerpo['identificacionNumero'] ?? null)
            ? $this->normalizarIdentificacion($cuerpo['identificacionNumero']) : '';
        if ($identificacion === '') {
            $errores['identificacionNumero'] = 'La identificación es obligatoria.';
        }
        $direccion = $this->validarDireccion($cuerpo['direccionPrincipal'] ?? null, $errores);
        if ($errores !== []) {
            throw new ProductorHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = $this->transaccion(function () use ($identificacion, $direccion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new ProductorHttpException(
                    'El productor está inactivo. Debe reactivarlo antes de actualizar su dirección.',
                    409,
                );
            }
            $productorId = (int) $bloqueado['tbproductorid'];
            $anterior = $this->direccion->buscar($productorId);
            if ($anterior === null) {
                throw new ProductorHttpException(
                    'El productor no tiene una dirección registrada; use POST para crearla.',
                    404,
                );
            }
            try {
                $this->direccion->actualizar($productorId, $direccion);
            } catch (\RuntimeException $excepcion) {
                throw new ProductorHttpException($excepcion->getMessage(), 409);
            }
            $nueva = $this->direccion->buscar($productorId);
            $this->bitacora->registrar(
                'ACTUALIZAR_DIRECCION',
                $identificacion,
                ['direccionPrincipal' => $anterior],
                ['direccionPrincipal' => $nueva],
                $this->solicitudId,
            );

            return ['identificacionNumero' => $identificacion, 'direccionPrincipal' => $nueva];
        });

        return $this->respuesta(true, 'Dirección actualizada correctamente.', $resultado);
    }

    private function eliminarDireccion(array $cuerpo): array
    {
        $identificacion = $this->validarIdentificacionUnica($cuerpo);

        $resultado = $this->transaccion(function () use ($identificacion): array {
            $bloqueado = $this->productor->bloquear($identificacion);
            if ($bloqueado === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbproductorestado'] !== 1 || (int) $bloqueado['tbpersonaestado'] !== 1) {
                throw new ProductorHttpException(
                    'El productor está inactivo. Debe reactivarlo antes de modificar su dirección.',
                    409,
                );
            }
            $productorId = (int) $bloqueado['tbproductorid'];
            $anterior = $this->direccion->buscar($productorId);
            if ($anterior === null) {
                throw new ProductorHttpException(
                    'El productor no tiene una dirección registrada; no hay nada que eliminar.',
                    404,
                );
            }
            try {
                $this->direccion->vaciar($productorId);
            } catch (\RuntimeException $excepcion) {
                throw new ProductorHttpException($excepcion->getMessage(), 409);
            }
            $nueva = $this->direccion->buscar($productorId);
            $this->bitacora->registrar(
                'VACIAR_DIRECCION',
                $identificacion,
                ['direccionPrincipal' => $anterior],
                ['direccionPrincipal' => $nueva],
                $this->solicitudId,
            );

            return ['identificacionNumero' => $identificacion, 'direccionPrincipal' => $nueva];
        });

        return $this->respuesta(true, 'Dirección vaciada correctamente.', $resultado);
    }

    private function validarFincas(mixed $valor, array &$errores): array
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
            throw new ProductorHttpException('Revise los campos indicados.', 422, null, [
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
            throw new ProductorHttpException('La consulta no es válida.', 422);
        }
        return trim($valor);
    }

    private function enteroConsulta(mixed $valor, string $campo): int
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            throw new ProductorHttpException("{$campo} debe ser un entero positivo.", 422);
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
        throw new ProductorHttpException('Revise los campos indicados.', 422, null, $errores);
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
