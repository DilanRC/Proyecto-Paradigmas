<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\AnimalComercial;
use Application\Model\Bitacora;
use Application\Model\Comprador;
use Application\Model\CompraSolicitud;
use Application\Model\PagoMetodo;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Model\Transportista;
use Application\Model\TransportistaOferta;
use Application\Model\TransportistaVehiculo;
use Application\Model\Direccion;
use Application\Service\PublicacionCercaniaService;
use PDO;
use Throwable;

/**
 * Solicitudes de compra (DEC-COMPRA-001). El comprador pide un animal, con flete
 * opcional; el vendedor acepta o rechaza; el transportista responde su flete.
 * GET lista lo que la persona hizo, recibió y lo que le piden como flete.
 */
final class SolicitudesCompraController
{
    private const ACCIONES = ['CANCELAR', 'ACEPTAR', 'RECHAZAR', 'ACEPTAR_FLETE', 'RECHAZAR_FLETE'];
    private const ORIGEN = 'API_SOLICITUDES_COMPRA';

    private CompraSolicitud $solicitudes;
    private AnimalComercial $animales;
    private Comprador $comprador;
    private Productor $productor;
    private Transportista $transportista;
    private TransportistaOferta $ofertas;
    private PagoMetodo $pagos;
    private Bitacora $bitacora;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->solicitudes = new CompraSolicitud($conexion);
        $this->animales = new AnimalComercial($conexion);
        $this->comprador = new Comprador($conexion);
        $this->productor = new Productor($conexion, new ProductorFinca($conexion));
        $this->transportista = new Transportista($conexion, new TransportistaVehiculo($conexion));
        $this->ofertas = new TransportistaOferta($conexion, new Direccion($conexion));
        $this->pagos = new PagoMetodo($conexion);
        $this->bitacora = new Bitacora($conexion, $actor);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesar(string $metodo, array $cuerpo = []): array
    {
        try {
            if (!$this->actor->tienePersona()) {
                throw new HttpException('Debe iniciar sesión para gestionar solicitudes de compra.', 401);
            }
            return match ($metodo) {
                'GET' => $this->consultar(),
                'POST' => $this->crear($cuerpo),
                'PATCH' => $this->responder($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function consultar(): array
    {
        $personaId = (int) $this->actor->personaId;
        $comprador = $this->comprador->buscarPorPersona($personaId);
        $productor = $this->productor->buscarPorPersonaId($personaId);
        $transportista = $this->transportista->buscarPorPersonaId($personaId);
        $listar = fn (string $rol, ?int $id): array => $id === null ? [] : array_map(
            fn (array $fila): array => $this->presentar($fila, $rol),
            $this->solicitudes->listar($rol, $id),
        );

        return $this->respuesta(true, 'Solicitudes consultadas correctamente.', [
            'hechas' => $listar('COMPRADOR', $comprador === null ? null : $comprador['compradorId']),
            'recibidas' => $listar('VENDEDOR', $productor === null ? null : (int) $productor['tbproductorid']),
            'fletes' => $listar('TRANSPORTISTA', $transportista === null ? null : $transportista['transportistaId']),
        ]);
    }

    private function crear(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['publicacionId', 'ofertaId', 'pagoMetodoId', 'mensaje']);
        $errores = [];
        $publicacionId = $this->entero($cuerpo['publicacionId'] ?? null, 'publicacionId', $errores, true);
        $ofertaId = $this->entero($cuerpo['ofertaId'] ?? null, 'ofertaId', $errores, false);
        $pagoMetodoId = $this->entero($cuerpo['pagoMetodoId'] ?? null, 'pagoMetodoId', $errores, false);
        $mensaje = $this->texto($cuerpo['mensaje'] ?? null, 'mensaje', 500, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $personaId = (int) $this->actor->personaId;
        $comprador = $this->comprador->buscarPorPersona($personaId);
        if ($comprador === null || $comprador['estado'] !== 'ACTIVO') {
            throw new HttpException('Activa la actividad Comprador en Ajustes para solicitar animales.', 409);
        }

        $solicitud = $this->ejecutarConBloqueos(function () use ($publicacionId, $ofertaId, $pagoMetodoId, $mensaje, $comprador, $personaId): array {
            $datos = $this->solicitudes->datosParaVenta($publicacionId);
            $publicacion = $datos === null ? null : $this->animales->buscarPublicacion($publicacionId);
            if ($datos === null || $publicacion === null) throw new HttpException('La publicación no existe.', 404);
            if ($publicacion['estado'] !== 'ACTIVO') throw new HttpException('La publicación ya no está disponible.', 409);
            $propio = $this->productor->buscarPorPersonaId($personaId);
            if ($propio !== null && (int) $propio['tbproductorid'] === $datos['vendedorId']) {
                throw new HttpException('No puedes solicitar tu propia publicación.', 409);
            }
            if ($this->solicitudes->hayPendiente($publicacionId, $comprador['compradorId'])) {
                throw new HttpException('Ya tienes una solicitud pendiente para esta publicación.', 409);
            }
            if ($pagoMetodoId !== null) {
                $pago = $this->pagos->buscarPorId($pagoMetodoId);
                if ($pago === null || !$pago['activo']) {
                    throw new HttpException('Revise los campos indicados.', 422, null, ['pagoMetodoId' => 'Elige un método de pago disponible.']);
                }
            }
            if ($ofertaId !== null) $this->validarFlete($ofertaId, $publicacionId, $personaId);

            $id = $this->solicitudes->crear([
                'publicacionId' => $publicacionId, 'compradorId' => $comprador['compradorId'], 'ofertaId' => $ofertaId,
                'pagoMetodoId' => $pagoMetodoId, 'precio' => $datos['precio'], 'mensaje' => $mensaje,
            ]);
            $nueva = $this->presentar($this->solicitudes->buscar($id), 'COMPRADOR');
            $this->bitacora->registrar('CREAR', (string) $id, null, $nueva, $this->solicitudId,
                entidad: 'COMPRA_SOLICITUD', origen: self::ORIGEN);
            return $nueva;
        });

        return $this->respuesta(true, 'Solicitud enviada. El vendedor la revisará.', ['solicitud' => $solicitud], 201);
    }

    /** El flete debe estar disponible, no ser del propio comprador y, si ambos puntos existen, cubrir la finca. */
    private function validarFlete(int $ofertaId, int $publicacionId, int $personaId): void
    {
        $oferta = $this->ofertas->buscarDisponible($ofertaId);
        if ($oferta === null) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['ofertaId' => 'Ese flete ya no está disponible.']);
        }
        if ($oferta['personaId'] === $personaId) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['ofertaId' => 'No puedes pedir tu propio flete.']);
        }
        $finca = $this->solicitudes->puntoDeFinca($publicacionId);
        if ($finca !== null && $oferta['latitud'] !== null && $oferta['longitud'] !== null
            && PublicacionCercaniaService::calcularDistanciaKm($finca['latitud'], $finca['longitud'], $oferta['latitud'], $oferta['longitud']) > $oferta['radioKm']) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['ofertaId' => 'Ese flete no cubre la finca del animal.']);
        }
    }

    private function responder(array $cuerpo): array
    {
        $this->rechazarCamposDesconocidos($cuerpo, ['solicitudId', 'accion', 'motivo', 'precio']);
        $errores = [];
        $id = $this->entero($cuerpo['solicitudId'] ?? null, 'solicitudId', $errores, true);
        $accion = is_string($cuerpo['accion'] ?? null) ? mb_strtoupper(trim($cuerpo['accion']), 'UTF-8') : '';
        if (!in_array($accion, self::ACCIONES, true)) $errores['accion'] = 'Use ' . implode(', ', self::ACCIONES) . '.';
        $motivo = $this->texto($cuerpo['motivo'] ?? null, 'motivo', 250, $errores);
        $precio = $this->precio($cuerpo['precio'] ?? null, $errores);
        if ($errores !== []) throw new HttpException('Revise los campos indicados.', 422, null, $errores);

        $solicitud = $this->ejecutarConBloqueos(function () use ($id, $accion, $motivo, $precio): array {
            $fila = $this->solicitudes->buscar($id, true);
            $personaId = (int) $this->actor->personaId;
            // Una solicitud ajena responde 404, sin revelar que existe.
            $dueno = match ($accion) {
                'CANCELAR' => 'compradorpersonaid',
                'ACEPTAR', 'RECHAZAR' => 'vendedorpersonaid',
                default => 'transportistapersonaid',
            };
            if ($fila === null || (int) ($fila[$dueno] ?? 0) !== $personaId) throw new HttpException('La solicitud no existe.', 404);

            $vista = match ($accion) { 'CANCELAR' => 'COMPRADOR', 'ACEPTAR', 'RECHAZAR' => 'VENDEDOR', default => 'TRANSPORTISTA' };
            $anterior = $this->presentar($fila, $vista);
            match ($accion) {
                'CANCELAR' => $this->cancelar($fila),
                'RECHAZAR' => $this->rechazar($fila, $motivo),
                'ACEPTAR' => $this->aceptar($fila, $precio),
                default => $this->responderFlete($fila, $accion === 'ACEPTAR_FLETE'),
            };
            $nueva = $this->presentar($this->solicitudes->buscar($id), $vista);
            $this->bitacora->registrar($accion, (string) $id, $anterior, $nueva, $this->solicitudId,
                entidad: 'COMPRA_SOLICITUD', origen: self::ORIGEN);
            return $nueva;
        });

        $mensajes = [
            'CANCELAR' => 'Solicitud cancelada.', 'ACEPTAR' => 'Venta aceptada. Ya puedes contactar al comprador.',
            'RECHAZAR' => 'Solicitud rechazada.', 'ACEPTAR_FLETE' => 'Flete aceptado.', 'RECHAZAR_FLETE' => 'Flete rechazado.',
        ];
        return $this->respuesta(true, $mensajes[$accion], ['solicitud' => $solicitud]);
    }

    private function exigirPendiente(array $fila): void
    {
        if ($fila['estado'] !== 'PENDIENTE') {
            throw new HttpException('La solicitud ya fue respondida.', 409);
        }
    }

    private function cancelar(array $fila): void
    {
        $this->exigirPendiente($fila);
        $this->solicitudes->cancelar((int) $fila['solicitudid']);
    }

    private function rechazar(array $fila, ?string $motivo): void
    {
        $this->exigirPendiente($fila);
        $this->solicitudes->responder((int) $fila['solicitudid'], 'RECHAZADA', $motivo, 'CANCELADA');
    }

    /** Registra compra y venta, vende la publicación y rechaza las demás solicitudes pendientes. */
    private function aceptar(array $fila, ?float $precioVendedor): void
    {
        $this->exigirPendiente($fila);
        if ($fila['publicacionestado'] !== 'ACTIVO') {
            throw new HttpException('La publicación ya no está disponible.', 409);
        }
        // Una publicación "a convenir" no tiene precio: lo fija el vendedor al aceptar.
        $precio = $fila['precio'] !== null ? (float) $fila['precio'] : $precioVendedor;
        if ($precio === null) {
            throw new HttpException('Revise los campos indicados.', 422, null, ['precio' => 'Indica el precio acordado para aceptar.']);
        }
        $publicacionId = (int) $fila['publicacionid'];
        $solicitudId = (int) $fila['solicitudid'];
        $datos = $this->solicitudes->datosParaVenta($publicacionId);
        if ($datos === null) throw new HttpException('La publicación no existe.', 404);

        $hecho = ['compradorId' => (int) $fila['compradorid'], 'fecha' => gmdate('Y-m-d'), 'hora' => gmdate('H:i:s'),
            'precio' => $precio, 'pagoMetodoId' => $fila['pagometodoid'] === null ? null : (int) $fila['pagometodoid'],
            'origen' => self::ORIGEN];
        // Compra y venta son hechos POR ANIMAL: un lote (DEC-ANIMAL-001) registra un par por cada animal y el precio
        // acordado se reparte en partes iguales en centavos (el primer animal absorbe el resto).
        $animales = $datos['animales'];
        if ($animales === []) throw new HttpException('La publicación no tiene animales registrados.', 409);
        $centavos = (int) round($precio * 100);
        $cuota = intdiv($centavos, count($animales));
        foreach ($animales as $i => $animal) {
            $porAnimal = ['precio' => ($cuota + ($i === 0 ? $centavos - $cuota * count($animales) : 0)) / 100] + $hecho;
            $compraId = $this->animales->ejecutarConBloqueoAlta('tbcompra',
                fn (): int => $this->animales->registrarCompra($animal['animalId'], null, $datos['fincaId'], $porAnimal));
            $this->animales->ejecutarConBloqueoAlta('tbventa',
                fn (): int => $this->animales->registrarVenta($animal['animalId'], $datos['vendedorId'], null, $datos['fincaId'], $compraId, $porAnimal + [
                    'solicitudId' => $solicitudId, 'proposito' => $animal['proposito'], 'edadMeses' => $animal['edadMeses'],
                    'peso' => $animal['peso'], 'razaSnapshot' => $animal['raza'],
                ]));
        }
        $this->animales->marcarEstadoAnimales(array_column($animales, 'animalId'), 'VENDIDO');
        $this->animales->cambiarEstadoPublicacion($publicacionId, 'VENDIDO', 'Venta aceptada', self::ORIGEN);
        $this->solicitudes->responder($solicitudId, 'ACEPTADA', null, 'PENDIENTE', $precio);
        foreach ($this->solicitudes->rechazarPendientesDe($publicacionId, $solicitudId, 'La publicación se vendió a otra solicitud.') as $otra) {
            $this->bitacora->registrar('RECHAZAR', (string) $otra, null, ['estado' => 'RECHAZADA', 'motivo' => 'Venta a otra solicitud'],
                $this->solicitudId, entidad: 'COMPRA_SOLICITUD', origen: self::ORIGEN);
        }
    }

    private function responderFlete(array $fila, bool $acepta): void
    {
        if ($fila['estado'] !== 'ACEPTADA' || $fila['fleteestado'] !== 'PENDIENTE') {
            throw new HttpException('Este flete ya no admite respuesta.', 409);
        }
        $this->solicitudes->responderFlete((int) $fila['solicitudid'], $acepta ? 'ACEPTADA' : 'RECHAZADA');
    }

    /** Orden de locks: solicitud -> bitácora, dentro de una transacción. */
    private function ejecutarConBloqueos(callable $operacion): array
    {
        return $this->solicitudes->ejecutarConBloqueoAlta(
            fn (): array => $this->bitacora->ejecutarConBloqueoAlta(
                fn (): array => $this->transaccion($operacion),
            ),
        );
    }

    /** Vista de la solicitud para cada parte: los teléfonos solo se comparten cuando hay trato. */
    private function presentar(array $f, string $vista): array
    {
        $ventaAceptada = $f['estado'] === 'ACEPTADA';
        $fleteAceptado = $f['fleteestado'] === 'ACEPTADA';
        $contacto = static fn (string $nombre, ?string $telefono, bool $visible): array
            => ['nombre' => $nombre, 'telefono' => $visible ? $telefono : null];

        $solicitud = [
            'solicitudId' => (int) $f['solicitudid'],
            'estado' => $f['estado'],
            'fecha' => $f['fecha'],
            'mensaje' => $f['mensaje'],
            'precio' => $f['precio'] === null ? null : (float) $f['precio'],
            'pagoMetodoId' => $f['pagometodoid'] === null ? null : (int) $f['pagometodoid'],
            'respuestaFecha' => $f['respuestafecha'],
            'respuestaMotivo' => $f['respuestamotivo'],
            'publicacion' => [
                'publicacionId' => (int) $f['publicacionid'], 'titulo' => $f['titulo'],
                'imagenUrl' => $f['imagenurl'], 'estado' => $f['publicacionestado'],
            ],
            'flete' => $f['ofertaid'] === null ? null : [
                'ofertaId' => (int) $f['ofertaid'],
                'estado' => $f['fleteestado'],
                'vehiculo' => $f['fletemodelo'],
                'transportista' => $contacto((string) $f['transportistanombre'], $f['transportistatelefono'], $fleteAceptado),
            ],
        ];
        if ($vista === 'COMPRADOR') {
            $solicitud['vendedor'] = $contacto((string) $f['vendedornombre'], $f['vendedortelefono'], $ventaAceptada);
        } elseif ($vista === 'VENDEDOR') {
            $solicitud['comprador'] = $contacto((string) $f['compradornombre'], $f['compradortelefono'], $ventaAceptada);
        } else {
            $solicitud['comprador'] = $contacto((string) $f['compradornombre'], $f['compradortelefono'], $fleteAceptado);
            $solicitud['vendedor'] = $contacto((string) $f['vendedornombre'], $f['vendedortelefono'], $fleteAceptado);
        }

        return $solicitud;
    }

    private function entero(mixed $valor, string $campo, array &$errores, bool $obligatorio): ?int
    {
        if ($valor === null || $valor === '') {
            if ($obligatorio) $errores[$campo] = 'Debe ser un entero positivo.';
            return null;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($entero === false) {
            $errores[$campo] = 'Debe ser un entero positivo.';
            return null;
        }
        return $entero;
    }

    private function texto(mixed $valor, string $campo, int $maximo, array &$errores): ?string
    {
        if ($valor === null) return null;
        if (!is_string($valor)) {
            $errores[$campo] = 'Debe ser texto.';
            return null;
        }
        $texto = trim($valor);
        if (mb_strlen($texto) > $maximo) {
            $errores[$campo] = "Debe tener hasta {$maximo} caracteres.";
            return null;
        }
        return $texto === '' ? null : $texto;
    }

    private function precio(mixed $valor, array &$errores): ?float
    {
        if ($valor === null || (is_string($valor) && trim($valor) === '')) return null;
        if (!is_numeric($valor) || (float) $valor <= 0 || (float) $valor > 9999999999.99) {
            $errores['precio'] = 'El precio debe ser mayor que cero.';
            return null;
        }
        return round((float) $valor, 2);
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos): void
    {
        $desconocidos = array_diff(array_keys($datos), $permitidos);
        if ($desconocidos === []) return;
        $errores = [];
        foreach ($desconocidos as $campo) $errores[$campo] = 'Campo no permitido.';
        throw new HttpException('Revise los campos indicados.', 422, null, $errores);
    }

    private function transaccion(callable $operacion): mixed
    {
        $this->conexion->beginTransaction();
        try {
            $resultado = $operacion();
            $this->conexion->commit();
            return $resultado;
        } catch (Throwable $excepcion) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $excepcion;
        }
    }

    private function normalizarSolicitudId(?string $valor): string
    {
        $valor = trim((string) $valor);
        return $valor !== '' && strlen($valor) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $valor)
            ? $valor : 'REQ-' . bin2hex(random_bytes(16));
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
