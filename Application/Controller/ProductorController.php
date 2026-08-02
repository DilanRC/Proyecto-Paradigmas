<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Bitacora;
use Application\Model\Finca;
use Application\Model\Participante;
use Application\Model\ParticipanteDireccion;
use Application\Model\ParticipanteIdentificacion;
use Application\Model\ParticipanteRol;
use Application\Model\ProductorFinca;
use Application\Model\Rol;
use Application\Model\TipoIdentificacion;
use PDO;
use PDOException;
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
    private Participante $participante;
    private Rol $rol;
    private ParticipanteRol $participanteRol;
    private TipoIdentificacion $tipoIdentificacion;
    private ParticipanteIdentificacion $identificacion;
    private ParticipanteDireccion $direccion;
    private Finca $finca;
    private ProductorFinca $productorFinca;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null)
    {
        $this->productorFinca = new ProductorFinca($conexion);
        $this->participante = new Participante($conexion, $this->productorFinca);
        $this->rol = new Rol($conexion);
        $this->participanteRol = new ParticipanteRol($conexion);
        $this->tipoIdentificacion = new TipoIdentificacion($conexion);
        $this->identificacion = new ParticipanteIdentificacion($conexion);
        $this->direccion = new ParticipanteDireccion($conexion);
        $this->finca = new Finca($conexion);
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
        } catch (ProductorHttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores,
            );
        } catch (PDOException $excepcion) {
            $codigoMotor = (int) ($excepcion->errorInfo[1] ?? 0);
            if ($codigoMotor === 1062) {
                return $this->respuesta(
                    false,
                    'La identificación ya está registrada.',
                    null,
                    409,
                    ['identificacion.numero' => 'La combinación de tipo y número ya existe.'],
                );
            }
            if ($codigoMotor === 1452) {
                return $this->respuesta(false, 'Una referencia enviada no es válida.', null, 422);
            }
            throw $excepcion;
        }
    }

    private function consultar(array $consulta): array
    {
        if (array_key_exists('id', $consulta)) {
            $id = $this->enteroConsulta($consulta['id'], 'id');
            $productor = $this->participante->buscarPorId($id);
            if ($productor === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }

            return $this->respuesta(true, 'Productor consultado correctamente.', $productor);
        }

        $busqueda = $this->textoConsulta($consulta['q'] ?? '', 150);
        $estado = mb_strtoupper($this->textoConsulta($consulta['estado'] ?? 'TODOS', 10), 'UTF-8');
        if (!in_array($estado, ['TODOS', 'ACTIVO', 'INACTIVO'], true)) {
            throw new ProductorHttpException('El filtro de estado no es válido.', 422, null, ['estado' => 'Use TODOS, ACTIVO o INACTIVO.']);
        }
        $pagina = array_key_exists('pagina', $consulta) ? $this->enteroConsulta($consulta['pagina'], 'pagina') : 1;
        $tamano = array_key_exists('tamanoPagina', $consulta)
            ? $this->enteroConsulta($consulta['tamanoPagina'], 'tamanoPagina')
            : 25;
        if ($tamano > 100) {
            throw new ProductorHttpException('El tamaño de página no es válido.', 422, null, ['tamanoPagina' => 'Debe estar entre 1 y 100.']);
        }
        $resultado = $this->participante->listarProductores($busqueda, $estado, $pagina, $tamano);
        $resultado['pagina'] = $pagina;
        $resultado['tamanoPagina'] = $tamano;
        $resultado['catalogos'] = [
            'tiposIdentificacion' => $this->tipoIdentificacion->listarActivos(),
            'fincasDisponibles' => $this->finca->listarActivas(),
        ];

        return $this->respuesta(true, 'Productores consultados correctamente.', $resultado);
    }

    private function crear(array $cuerpo): array
    {
        $datos = $this->validarProductor($cuerpo, false);
        $duplicado = $this->identificacion->buscarPorTipoYNumero($datos['tipoId'], $datos['numeroNormalizado']);
        if ($duplicado !== null) {
            $inactivo = (int) $duplicado['tbparticipanteEstado'] === 0;
            throw new ProductorHttpException(
                $inactivo
                    ? 'La identificación pertenece a un participante inactivo.'
                    : 'La identificación ya está registrada.',
                409,
                $inactivo ? ['reactivacion' => ['participanteId' => (int) $duplicado['tbparticipanteId']]] : null,
                ['identificacion.numero' => $inactivo
                    ? 'Debe reactivarse el participante existente.'
                    : 'La combinación de tipo y número ya existe.'],
            );
        }

        $productor = $this->transaccion(function () use ($datos): array {
            $rol = $this->rol->buscarActivoPorCodigo('PRODUCTOR', true);
            if ($rol === null) {
                throw new ProductorHttpException('El rol PRODUCTOR no está disponible.', 409);
            }
            if ($this->tipoIdentificacion->buscarActivoPorId($datos['tipoId'], true) === null) {
                throw new ProductorHttpException('El tipo de identificación dejó de estar activo.', 409);
            }
            if (!$this->finca->bloquearActivas($datos['fincaIds'])) {
                throw new ProductorHttpException('Una de las fincas no existe o está inactiva.', 422, null, ['fincas' => 'Seleccione únicamente fincas activas.']);
            }
            $participanteId = $this->participante->crear($datos);
            $this->identificacion->crearPrincipal(
                $participanteId,
                $datos['tipoId'],
                $datos['numero'],
                $datos['numeroNormalizado'],
            );
            $this->direccion->crearPrincipal($participanteId, $datos['direccion']);
            $this->participanteRol->asignar($participanteId, (int) $rol['tbrolId']);
            $this->productorFinca->sincronizar($participanteId, $datos['fincaIds']);
            $this->comprobarPrincipales($participanteId);
            $nuevo = $this->participante->buscarPorId($participanteId);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el productor recién creado.');
            }
            $this->bitacora->registrar('CREAR', $participanteId, null, $nuevo, $this->solicitudId);

            return $nuevo;
        });

        return $this->respuesta(true, 'Productor creado correctamente.', $productor, 201);
    }

    private function actualizar(array $cuerpo): array
    {
        $datos = $this->validarProductor($cuerpo, true);
        $id = $datos['participanteId'];
        $productor = $this->transaccion(function () use ($datos, $id): array {
            $bloqueado = $this->participante->bloquearPorId($id);
            if ($bloqueado === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbparticipanteEstado'] !== 1) {
                throw new ProductorHttpException('El productor está inactivo. Debe reactivarlo antes de actualizarlo.', 409);
            }
            $rol = $this->rol->buscarActivoPorCodigo('PRODUCTOR', true);
            if ($rol === null || !$this->participanteRol->estaActivo($id, (int) $rol['tbrolId'])) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            $this->comprobarPrincipales($id);
            if ($this->tipoIdentificacion->buscarActivoPorId($datos['tipoId'], true) === null) {
                throw new ProductorHttpException('El tipo de identificación dejó de estar activo.', 409);
            }
            $anterior = $this->participante->buscarPorId($id);
            if ($anterior === null) {
                throw new \RuntimeException('No fue posible leer el estado anterior del productor.');
            }
            $duplicado = $this->identificacion->buscarPorTipoYNumero($datos['tipoId'], $datos['numeroNormalizado']);
            if ($duplicado !== null && (int) $duplicado['tbparticipanteId'] !== $id) {
                throw new ProductorHttpException('La identificación ya está registrada.', 409, null, [
                    'identificacion.numero' => 'La combinación de tipo y número ya existe.',
                ]);
            }
            $asociadas = $this->productorFinca->listarIdsAsociadosActivos($id);
            $nuevasAsociaciones = array_values(array_diff($datos['fincaIds'], $asociadas));
            if (!$this->finca->bloquearActivas($nuevasAsociaciones)) {
                throw new ProductorHttpException('Una de las fincas no existe o está inactiva.', 422, null, ['fincas' => 'Seleccione únicamente fincas activas.']);
            }
            $this->participante->actualizarContacto($id, $datos);
            $this->identificacion->actualizarPrincipal($id, $datos['tipoId'], $datos['numero'], $datos['numeroNormalizado']);
            $this->direccion->actualizarPrincipal($id, $datos['direccion']);
            $this->productorFinca->sincronizar($id, $datos['fincaIds']);
            $this->comprobarPrincipales($id);
            $nuevo = $this->participante->buscarPorId($id);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el productor actualizado.');
            }
            $this->bitacora->registrar('ACTUALIZAR', $id, $anterior, $nuevo, $this->solicitudId);

            return $nuevo;
        });

        return $this->respuesta(true, 'Productor actualizado correctamente.', $productor);
    }

    private function desactivar(array $cuerpo): array
    {
        $id = $this->validarIdUnico($cuerpo);
        $productor = $this->transaccion(function () use ($id): array {
            $bloqueado = $this->participante->bloquearPorId($id);
            $anterior = $this->participante->buscarPorId($id);
            if ($bloqueado === null || $anterior === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            if ((int) $bloqueado['tbparticipanteEstado'] === 0) {
                return $anterior;
            }
            $this->participante->cambiarEstado($id, false);
            $nuevo = $this->participante->buscarPorId($id);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el productor desactivado.');
            }
            $this->bitacora->registrar('DESACTIVAR', $id, $anterior, $nuevo, $this->solicitudId);

            return $nuevo;
        });

        return $this->respuesta(true, 'Productor desactivado correctamente.', $productor);
    }

    private function reactivar(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['participanteId', 'identificacion']);
        $porId = array_key_exists('participanteId', $cuerpo);
        $porIdentificacion = array_key_exists('identificacion', $cuerpo);
        if ($porId === $porIdentificacion) {
            throw new ProductorHttpException('Indique participanteId o identificación, pero no ambos.', 422);
        }
        if ($porId) {
            $id = $this->enteroEstricto($cuerpo['participanteId'], 'participanteId');
        } else {
            $erroresIdentificacion = [];
            $identidad = $this->validarIdentificacion($cuerpo['identificacion'] ?? null, $erroresIdentificacion);
            if ($erroresIdentificacion !== []) {
                throw new ProductorHttpException('Revise los campos indicados.', 422, null, $erroresIdentificacion);
            }
            $existente = $this->identificacion->buscarPorTipoYNumero($identidad['tipoId'], $identidad['numeroNormalizado']);
            if ($existente === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            $id = (int) $existente['tbparticipanteId'];
        }

        $productor = $this->transaccion(function () use ($id): array {
            $bloqueado = $this->participante->bloquearPorId($id);
            if ($bloqueado === null) {
                throw new ProductorHttpException('Productor no encontrado.', 404);
            }
            $anterior = $this->participante->buscarPorId($id);
            if ((int) $bloqueado['tbparticipanteEstado'] === 1) {
                if ($anterior === null) {
                    throw new ProductorHttpException('El productor activo no conserva sus datos principales.', 409);
                }
                return $anterior;
            }
            $rol = $this->rol->buscarActivoPorCodigo('PRODUCTOR', true);
            if ($rol === null || !$this->participanteRol->estaActivo($id, (int) $rol['tbrolId'])) {
                throw new ProductorHttpException('El participante no conserva un rol PRODUCTOR activo.', 409);
            }
            $tipoId = $this->identificacion->obtenerTipoPrincipalId($id);
            if ($tipoId === null || $this->tipoIdentificacion->buscarActivoPorId($tipoId, true) === null) {
                throw new ProductorHttpException('El participante no conserva un tipo de identificación activo.', 409);
            }
            $this->comprobarPrincipales($id);
            $anterior = $this->participante->buscarPorId($id);
            if ($anterior === null) {
                throw new \RuntimeException('No fue posible leer el estado anterior del productor.');
            }
            $this->participante->cambiarEstado($id, true);
            $nuevo = $this->participante->buscarPorId($id);
            if ($nuevo === null) {
                throw new \RuntimeException('No fue posible leer el productor reactivado.');
            }
            $this->bitacora->registrar('REACTIVAR', $id, $anterior, $nuevo, $this->solicitudId);

            return $nuevo;
        });

        return $this->respuesta(true, 'Productor reactivado correctamente.', $productor);
    }

    private function validarProductor(array $cuerpo, bool $actualizacion): array
    {
        $permitidos = ['identificacion', 'nombre', 'telefono', 'correoElectronico', 'direccionPrincipal', 'fincas'];
        if ($actualizacion) {
            $permitidos[] = 'participanteId';
        }
        $this->rechazarCamposDesconocidos($cuerpo, $permitidos);
        $errores = [];
        $identidad = $this->validarIdentificacion($cuerpo['identificacion'] ?? null, $errores);
        $nombre = $this->textoCampo($cuerpo['nombre'] ?? null, 'nombre', 150, $errores, 3);
        $telefono = $this->validarTelefono($cuerpo['telefono'] ?? null, $errores);
        $correo = $this->validarCorreo($cuerpo['correoElectronico'] ?? null, $errores);
        $direccion = $this->validarDireccion($cuerpo['direccionPrincipal'] ?? null, $errores);
        $fincaIds = $this->validarFincas($cuerpo['fincas'] ?? [], $errores);
        $participanteId = null;
        if ($actualizacion) {
            try {
                $participanteId = $this->enteroEstricto($cuerpo['participanteId'] ?? null, 'participanteId');
            } catch (ProductorHttpException $excepcion) {
                $errores += $excepcion->errores;
            }
        }
        if ($errores !== []) {
            throw new ProductorHttpException('Revise los campos indicados.', 422, null, $errores);
        }

        return [
            'participanteId' => $participanteId,
            'tipoId' => $identidad['tipoId'],
            'numero' => $identidad['numero'],
            'numeroNormalizado' => $identidad['numeroNormalizado'],
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correoElectronico' => $correo,
            'direccion' => $direccion,
            'fincaIds' => $fincaIds,
        ];
    }

    private function validarIdentificacion(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores['identificacion'] = 'La identificación debe ser un objeto.';
            return ['tipoId' => 0, 'numero' => '', 'numeroNormalizado' => ''];
        }
        try {
            $this->rechazarCamposDesconocidos($valor, ['tipoId', 'numero'], 'identificacion.');
        } catch (ProductorHttpException $excepcion) {
            $errores += $excepcion->errores;
        }
        try {
            $tipoId = $this->enteroEstricto($valor['tipoId'] ?? null, 'identificacion.tipoId');
        } catch (ProductorHttpException $excepcion) {
            $errores += $excepcion->errores;
            $tipoId = 0;
        }
        $numero = $this->textoCampo($valor['numero'] ?? null, 'identificacion.numero', 250, $errores, 1, false);
        $tipo = $tipoId > 0 ? $this->tipoIdentificacion->buscarActivoPorId($tipoId) : null;
        if ($tipoId > 0 && $tipo === null) {
            $errores['identificacion.tipoId'] = 'Seleccione un tipo de identificación activo.';
        }
        $codigo = $tipo['tbidentificaciontipoCodigo'] ?? '';
        $patron = in_array($codigo, ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'], true)
            ? '/^[0-9][0-9 -]*$/'
            : '/^[A-Za-z0-9][A-Za-z0-9 -]*$/';
        if ($numero !== '' && !preg_match($patron, $numero)) {
            $errores['identificacion.numero'] = in_array($codigo, ['CEDULA_FISICA', 'CEDULA_JURIDICA', 'DIMEX'], true)
                ? 'Este tipo admite dígitos, espacios y guiones.'
                : 'Este tipo admite letras, dígitos, espacios y guiones.';
        }
        $normalizado = mb_strtoupper(preg_replace('/[ -]+/u', '', $numero) ?? '', 'UTF-8');

        return ['tipoId' => $tipoId, 'numero' => $numero, 'numeroNormalizado' => $normalizado];
    }

    private function validarDireccion(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || array_is_list($valor)) {
            $errores['direccionPrincipal'] = 'La dirección principal debe ser un objeto.';
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

    private function validarFincas(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || !array_is_list($valor)) {
            $errores['fincas'] = 'Las fincas deben enviarse como una lista.';
            return [];
        }
        if (count($valor) > 100) {
            $errores['fincas'] = 'No se permiten más de 100 fincas por solicitud.';
            return [];
        }
        $ids = [];
        foreach ($valor as $indice => $finca) {
            $ruta = "fincas.{$indice}.fincaId";
            if (!is_array($finca) || array_is_list($finca) || array_keys($finca) !== ['fincaId']) {
                $errores[$ruta] = 'Cada finca debe contener únicamente fincaId.';
                continue;
            }
            try {
                $id = $this->enteroEstricto($finca['fincaId'], $ruta);
                if (in_array($id, $ids, true)) {
                    $errores[$ruta] = 'La finca está repetida.';
                } else {
                    $ids[] = $id;
                }
            } catch (ProductorHttpException $excepcion) {
                $errores += $excepcion->errores;
            }
        }

        return $ids;
    }

    private function validarTelefono(mixed $valor, array &$errores): string
    {
        if (!is_string($valor)) {
            $errores['telefono'] = 'El teléfono es obligatorio.';
            return '';
        }
        $visible = trim($valor);
        if (!preg_match('/^\+?[0-9 ()-]+$/', $visible)) {
            $errores['telefono'] = 'Use únicamente un prefijo +, dígitos, espacios, paréntesis o guiones.';
            return '';
        }
        $digitos = preg_replace('/\D+/', '', $visible) ?? '';
        if (strlen($digitos) < 8 || strlen($digitos) > 15) {
            $errores['telefono'] = 'El teléfono debe contener entre 8 y 15 dígitos.';
        }

        return str_starts_with($visible, '+') ? '+' . $digitos : $digitos;
    }

    private function validarCorreo(mixed $valor, array &$errores): string
    {
        if (!is_string($valor)) {
            $errores['correoElectronico'] = 'El correo electrónico es obligatorio.';
            return '';
        }
        $correo = mb_strtolower(trim($valor), 'UTF-8');
        if (mb_strlen($correo, 'UTF-8') > 150 || filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $errores['correoElectronico'] = 'Ingrese un correo electrónico válido de hasta 150 caracteres.';
        }

        return $correo;
    }

    private function textoCampo(mixed $valor, string $ruta, int $maximo, array &$errores, int $minimo, bool $colapsar = true): string
    {
        if (!is_string($valor)) {
            $errores[$ruta] = 'El campo es obligatorio.';
            return '';
        }
        $texto = trim($valor);
        if ($colapsar) {
            $texto = preg_replace('/\s+/u', ' ', $texto) ?? '';
        }
        $longitud = mb_strlen($texto, 'UTF-8');
        if ($longitud < $minimo || $longitud > $maximo) {
            $errores[$ruta] = "Debe contener entre {$minimo} y {$maximo} caracteres.";
        }

        return $texto;
    }

    private function textoOpcional(mixed $valor, string $ruta, int $maximo, array &$errores): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_string($valor)) {
            $errores[$ruta] = 'Debe ser texto o null.';
            return null;
        }
        $texto = preg_replace('/\s+/u', ' ', trim($valor)) ?? '';
        if (mb_strlen($texto, 'UTF-8') > $maximo) {
            $errores[$ruta] = "No debe superar {$maximo} caracteres.";
        }

        return $texto === '' ? null : $texto;
    }

    private function validarIdUnico(array $cuerpo): int
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['participanteId']);
        return $this->enteroEstricto($cuerpo['participanteId'] ?? null, 'participanteId');
    }

    private function enteroEstricto(mixed $valor, string $ruta): int
    {
        if (!is_int($valor) || $valor < 1) {
            throw new ProductorHttpException('Revise los campos indicados.', 422, null, [$ruta => 'Debe ser un entero positivo.']);
        }

        return $valor;
    }

    private function enteroConsulta(mixed $valor, string $ruta): int
    {
        if (!is_string($valor) || !preg_match('/^[1-9][0-9]*$/', $valor)) {
            throw new ProductorHttpException('Revise los parámetros indicados.', 422, null, [$ruta => 'Debe ser un entero positivo.']);
        }

        return (int) $valor;
    }

    private function textoConsulta(mixed $valor, int $maximo): string
    {
        if (!is_string($valor)) {
            throw new ProductorHttpException('Revise los parámetros indicados.', 422);
        }
        $texto = preg_replace('/\s+/u', ' ', trim($valor)) ?? '';
        if (mb_strlen($texto, 'UTF-8') > $maximo) {
            throw new ProductorHttpException('Un parámetro excede la longitud permitida.', 422);
        }

        return $texto;
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos, string $prefijo = ''): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) {
            return;
        }
        $errores = [];
        foreach ($desconocidos as $campo) {
            $errores[$prefijo . $campo] = 'Campo no reconocido.';
        }
        throw new ProductorHttpException('La solicitud contiene campos no reconocidos.', 422, null, $errores);
    }

    private function comprobarPrincipales(int $participanteId): void
    {
        if ($this->identificacion->contarPrincipalesActivas($participanteId) !== 1) {
            throw new ProductorHttpException('El participante debe tener exactamente una identificación principal activa.', 409);
        }
        if ($this->direccion->contarPrincipalesActivas($participanteId) !== 1) {
            throw new ProductorHttpException('El participante debe tener exactamente una dirección principal activa.', 409);
        }
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
        $valor = $valor === null ? '' : trim($valor);
        if ($valor !== '' && preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $valor)) {
            return $valor;
        }

        return bin2hex(random_bytes(16));
    }

    private function respuesta(bool $exito, string $mensaje, mixed $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
