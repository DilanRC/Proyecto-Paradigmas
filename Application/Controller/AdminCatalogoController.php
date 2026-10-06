<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalCatalogo;
use Application\Model\Bitacora;
use PDO;
use Throwable;

/**
 * Catálogos del animal desde el panel (P2-6): especies, tipos (con su sexo), razas y vacunas.
 * Crear, renombrar y activar o desactivar. No hay DELETE: los animales y las vacunas aplicadas
 * los referencian; desactivar los quita de los formularios sin romper lo ya registrado.
 * La especie de un tipo o raza y el sexo de un tipo no cambian: los animales ya guardados dependen de ellos.
 * La autorización de administrador la exige el endpoint antes de llegar aquí.
 */
final class AdminCatalogoController
{
    private const ORIGEN = 'API_ADMIN_CATALOGOS';
    private const LISTAS = ['ESPECIE' => 'especies', 'TIPO' => 'tipos', 'RAZA' => 'razas', 'VACUNA' => 'vacunas'];
    private const NOMBRE_MAXIMO = ['ESPECIE' => 80, 'TIPO' => 80, 'RAZA' => 100, 'VACUNA' => 100];

    private readonly AnimalCatalogo $catalogo;
    private readonly Bitacora $bitacora;
    private readonly string $solicitudId;

    public function __construct(private readonly PDO $conexion, ?string $solicitudId = null, ?ActorContext $actor = null)
    {
        $this->catalogo = new AnimalCatalogo($conexion);
        $this->bitacora = new Bitacora($conexion, $actor ?? ActorContext::noAutenticado());
        $this->solicitudId = is_string($solicitudId) && trim($solicitudId) !== '' ? trim($solicitudId) : bin2hex(random_bytes(16));
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->respuesta(true, 'Catálogos consultados correctamente.', $this->todos()),
                'POST' => $this->crear($cuerpo),
                'PATCH' => $this->actualizar($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    /** POST { catalogo, nombre, especieId (tipo y raza), sexo: 'M'|'H'|null (tipo) } */
    private function crear(array $cuerpo): array
    {
        $catalogo = $this->catalogoDe($cuerpo);
        $t = AnimalCatalogo::TABLAS[$catalogo];
        $this->rechazarCampos($cuerpo, array_merge(['catalogo', 'nombre'], $t['especie'] ? ['especieId'] : [], $t['sexo'] ? ['sexo'] : []));
        $errores = [];
        $nombre = $this->nombre($cuerpo['nombre'] ?? null, $catalogo, $errores);
        $especieId = null;
        if ($t['especie']) {
            $especieId = filter_var($cuerpo['especieId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $especie = $especieId === false ? null : $this->catalogo->especie($especieId);
            if ($especie === null || !$especie['activo']) $errores['especieId'] = 'Elige una especie activa.';
        }
        $sexo = $t['sexo'] ? $this->sexo($cuerpo['sexo'] ?? null, $errores) : null;
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $nuevo = $this->catalogo->ejecutarConBloqueoAlta(fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($catalogo, $nombre, $especieId, $sexo): array {
                $this->exigirNombreLibre($catalogo, $nombre, $especieId);
                $id = $this->catalogo->crear($catalogo, $nombre, $especieId ?: null, $sexo);
                $nuevo = ['catalogo' => $catalogo] + $this->catalogo->buscarAdmin($catalogo, $id);
                $this->bitacora->registrar('CREAR', "{$catalogo}:{$id}", null, $nuevo, $this->solicitudId,
                    entidad: 'CATALOGO', origen: self::ORIGEN);
                return $nuevo;
            }),
        ));

        return $this->respuesta(true, 'Registro creado correctamente.', ['registro' => $nuevo] + $this->todos(), 201);
    }

    /** PATCH { catalogo, id, nombre?, activo? } */
    private function actualizar(array $cuerpo): array
    {
        $catalogo = $this->catalogoDe($cuerpo);
        $this->rechazarCampos($cuerpo, ['catalogo', 'id', 'nombre', 'activo']);
        $errores = [];
        $id = filter_var($cuerpo['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) $errores['id'] = 'Debe ser un entero positivo.';
        $cambios = [];
        if (array_key_exists('nombre', $cuerpo)) $cambios['nombre'] = $this->nombre($cuerpo['nombre'], $catalogo, $errores);
        if (array_key_exists('activo', $cuerpo)) {
            if (!is_bool($cuerpo['activo'])) $errores['activo'] = 'Debe ser true o false.';
            $cambios['activo'] = $cuerpo['activo'];
        }
        if ($errores === [] && $cambios === []) $errores['nombre'] = 'Indica qué deseas cambiar.';
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $nuevo = $this->catalogo->ejecutarConBloqueoAlta(fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
            fn (): array => $this->transaccion(function () use ($catalogo, $id, $cambios): array {
                $anterior = $this->catalogo->buscarAdmin($catalogo, $id);
                if ($anterior === null) throw new HttpException('El registro no existe.', 404);
                if (isset($cambios['nombre'])) $this->exigirNombreLibre($catalogo, $cambios['nombre'], $anterior['especieId'], $id);
                // Reactivar un tipo o raza de una especie inactiva lo dejaría huérfano en los formularios.
                if (($cambios['activo'] ?? false) && $anterior['especieId'] !== null && !($this->catalogo->especie($anterior['especieId'])['activo'] ?? false)) {
                    throw new HttpException('Primero reactiva su especie.', 409);
                }
                $this->catalogo->actualizar($catalogo, $id, $cambios);
                $nuevo = $this->catalogo->buscarAdmin($catalogo, $id);
                if ($nuevo === $anterior) return ['catalogo' => $catalogo] + $nuevo;
                $accion = match (true) {
                    ($cambios['activo'] ?? null) === false && $anterior['activo'] => 'DESACTIVAR',
                    ($cambios['activo'] ?? null) === true && !$anterior['activo'] => 'REACTIVAR',
                    default => 'ACTUALIZAR',
                };
                $this->bitacora->registrar($accion, "{$catalogo}:{$id}", $anterior, $nuevo, $this->solicitudId,
                    entidad: 'CATALOGO', origen: self::ORIGEN);
                return ['catalogo' => $catalogo] + $nuevo;
            }),
        ));

        return $this->respuesta(true, 'Registro actualizado correctamente.', ['registro' => $nuevo] + $this->todos());
    }

    private function todos(): array
    {
        $datos = [];
        foreach (self::LISTAS as $catalogo => $lista) $datos[$lista] = $this->catalogo->listarAdmin($catalogo);

        return $datos;
    }

    private function catalogoDe(array $cuerpo): string
    {
        $catalogo = $cuerpo['catalogo'] ?? null;
        if (!is_string($catalogo) || !isset(AnimalCatalogo::TABLAS[$catalogo])) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['catalogo' => 'Usa ESPECIE, TIPO, RAZA o VACUNA.']);
        }

        return $catalogo;
    }

    private function nombre(mixed $valor, string $catalogo, array &$errores): ?string
    {
        $nombre = is_string($valor) ? preg_replace('/\s+/u', ' ', trim($valor)) : '';
        $maximo = self::NOMBRE_MAXIMO[$catalogo];
        if ($nombre === '') $errores['nombre'] = 'El nombre es obligatorio.';
        elseif (mb_strlen($nombre) > $maximo) $errores['nombre'] = "Debe tener hasta {$maximo} caracteres.";

        return $nombre === '' ? null : $nombre;
    }

    private function sexo(mixed $valor, array &$errores): ?string
    {
        if ($valor === null || $valor === '') return null;
        if (!in_array($valor, ['M', 'H'], true)) $errores['sexo'] = 'Usa M (macho), H (hembra) o déjalo vacío.';

        return in_array($valor, ['M', 'H'], true) ? $valor : null;
    }

    private function exigirNombreLibre(string $catalogo, string $nombre, ?int $especieId, ?int $exceptoId = null): void
    {
        if ($this->catalogo->existeNombre($catalogo, $nombre, $especieId, $exceptoId)) {
            throw new HttpException('Revise los campos indicados.', 409, null, ['nombre' => 'Ya existe un registro con ese nombre.']);
        }
    }

    private function rechazarCampos(array $cuerpo, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($cuerpo), $permitidos);
        if ($desconocidos !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, array_fill_keys(array_map('strval', $desconocidos), 'Campo no permitido.'));
        }
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();
            return $resultado;
        } catch (Throwable $error) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $error;
        }
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
