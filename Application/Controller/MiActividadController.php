<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Comprador;
use Application\Model\Persona;
use Application\Model\Productor;
use Application\Model\ProductorFinca;
use Application\Model\Transportista;
use Application\Model\TransportistaVehiculo;
use PDO;

/**
 * Proceso público de autogestión de actividades de una Persona autenticada.
 *
 * A diferencia de los CRUD administrativos, este controlador NUNCA recibe una
 * cédula objetivo desde el navegador. La Persona se deriva exclusivamente del
 * ActorContext que SupabaseActorResolver obtuvo de un JWT verificado y vinculó
 * por correo con tbpersona. Esto evita una autorización por identificador
 * manipulable (IDOR/BOLA).
 *
 * Productor y Transportista ya tienen una transición de estado aprobada y se
 * reutilizan sus controladores para no duplicar reglas, históricos o bitácora.
 * Comprador permanece solo lectura en Avance 2: su alta/baja debe provenir del
 * proceso de compra cuando ese contrato sea aprobado.
 */
final class MiActividadController
{
    private Persona $persona;
    private Productor $productor;
    private Transportista $transportista;
    private Comprador $comprador;
    private string $solicitudId;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->persona = new Persona($conexion);
        $this->productor = new Productor($conexion, new ProductorFinca($conexion));
        $this->transportista = new Transportista($conexion, new TransportistaVehiculo($conexion));
        $this->comprador = new Comprador($conexion);
        $this->solicitudId = $this->normalizarSolicitudId($solicitudId);
    }

    public function procesar(string $metodo, array $cuerpo = []): array
    {
        try {
            return match ($metodo) {
                'GET' => $this->consultar(),
                'PATCH' => $this->cambiarEstado($cuerpo),
                default => $this->respuesta(false, 'Método no permitido.', null, 405),
            };
        } catch (HttpException $excepcion) {
            return $this->respuesta(
                false,
                $excepcion->getMessage(),
                $excepcion->datos,
                $excepcion->estadoHttp,
                $excepcion->errores,
            );
        }
    }

    private function consultar(): array
    {
        $persona = $this->personaAutenticada();
        $identificacion = (string) $persona['tbpersonaidentificacionnumero'];

        return $this->respuesta(true, 'Actividad consultada correctamente.', [
            'persona' => [
                'personaId' => (int) $persona['tbpersonaid'],
                'identificacionNumero' => $identificacion,
                'nombre' => $persona['tbpersonanombre'],
                'alias' => $persona['tbpersonaalias'],
                'telefono' => $persona['tbpersonatelefono'],
                'correoElectronico' => $persona['tbpersonacorreoelectronico'],
            ],
            'capacidades' => $this->capacidades($identificacion),
        ]);
    }

    private function cambiarEstado(array $cuerpo): array
    {
        $desconocidos = array_diff(array_keys($cuerpo), ['contexto', 'activo']);
        if ($desconocidos !== []) {
            $errores = [];
            foreach ($desconocidos as $campo) {
                $errores[$campo] = 'Campo no permitido.';
            }
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $contexto = is_string($cuerpo['contexto'] ?? null)
            ? mb_strtoupper(trim($cuerpo['contexto']), 'UTF-8')
            : '';
        if (!in_array($contexto, ['PRODUCTOR', 'COMPRADOR', 'TRANSPORTISTA'], true)) {
            throw new HttpException('El contexto no es válido.', 422, null, [
                'contexto' => 'Use PRODUCTOR, COMPRADOR o TRANSPORTISTA.',
            ]);
        }
        if (!array_key_exists('activo', $cuerpo) || !is_bool($cuerpo['activo'])) {
            throw new HttpException('El estado solicitado no es válido.', 422, null, [
                'activo' => 'Debe enviar true o false.',
            ]);
        }

        $persona = $this->personaAutenticada();
        $identificacion = (string) $persona['tbpersonaidentificacionnumero'];
        $activo = $cuerpo['activo'];
        $actual = $this->capacidades($identificacion)[$contexto];

        if ($contexto === 'COMPRADOR') {
            throw new HttpException(
                'Comprador todavía no tiene una transición pública aprobada; su estado debe provenir del proceso de compra.',
                409,
                [
                    'contexto' => 'COMPRADOR',
                    'estado' => $actual['estado'],
                    'escrituraDisponible' => false,
                ],
            );
        }

        if ($actual['estado'] === 'NO_CONFIGURADO') {
            throw new HttpException(
                "La actividad {$contexto} todavía no está configurada para esta Persona.",
                409,
                [
                    'contexto' => $contexto,
                    'estado' => 'NO_CONFIGURADO',
                    'siguientePaso' => $contexto === 'PRODUCTOR'
                        ? 'registro.php?capacidad=PRODUCTOR&next=mi-actividad.php'
                        : 'registro.php?capacidad=TRANSPORTISTA&next=mi-actividad.php',
                ],
            );
        }

        $controlador = $contexto === 'PRODUCTOR'
            ? new ProductorController($this->conexion, $this->solicitudId, $this->actor)
            : new TransportistaController($this->conexion, $this->solicitudId, $this->actor);
        $resultado = $controlador->procesar(
            $activo ? 'PATCH' : 'DELETE',
            [],
            ['identificacionNumero' => $identificacion],
        );

        if (($resultado['status'] ?? 500) >= 400) {
            return $resultado;
        }

        $capacidades = $this->capacidades($identificacion);
        return $this->respuesta(
            true,
            $activo ? 'Actividad reactivada correctamente.' : 'Actividad desactivada correctamente.',
            [
                'contexto' => $contexto,
                'estado' => $capacidades[$contexto]['estado'],
                'capacidades' => $capacidades,
            ],
        );
    }

    private function personaAutenticada(): array
    {
        if ($this->actor->supabaseUserId === null || $this->actor->personaId === null) {
            throw new HttpException('Debe iniciar sesión para administrar sus actividades.', 401);
        }

        $persona = $this->persona->buscarPorId($this->actor->personaId);
        if ($persona === null) {
            throw new HttpException('La sesión no está vinculada a una Persona del negocio.', 409);
        }
        if ((int) $persona['tbpersonaestado'] !== 1) {
            throw new HttpException('La Persona está inactiva y no puede administrar actividades.', 409);
        }

        return $persona;
    }

    private function capacidades(string $identificacion): array
    {
        $productor = $this->productor->buscar($identificacion);
        $comprador = $this->comprador->buscar($identificacion);
        $transportista = $this->transportista->buscar($identificacion);

        return [
            'PRODUCTOR' => $this->capacidad(
                $productor,
                true,
                'publicar.php',
                'registro.php?capacidad=PRODUCTOR&next=mi-actividad.php',
            ),
            'COMPRADOR' => $this->capacidad(
                $comprador,
                false,
                'explorar.php',
                'registro.php?capacidad=COMPRADOR&next=mi-actividad.php',
                'La transición de Comprador debe provenir del proceso de compra aprobado.',
            ),
            'TRANSPORTISTA' => $this->capacidad(
                $transportista,
                true,
                'fletes.php',
                'registro.php?capacidad=TRANSPORTISTA&next=mi-actividad.php',
            ),
        ];
    }

    private function capacidad(
        ?array $registro,
        bool $escrituraDisponible,
        string $destinoActivo,
        string $destinoConfiguracion,
        ?string $motivoBloqueo = null,
    ): array {
        return [
            'estado' => $registro['estado'] ?? 'NO_CONFIGURADO',
            'escrituraDisponible' => $registro !== null && $escrituraDisponible,
            'destinoActivo' => $destinoActivo,
            'destinoConfiguracion' => $destinoConfiguracion,
            'motivoBloqueo' => $motivoBloqueo,
        ];
    }

    private function normalizarSolicitudId(?string $valor): string
    {
        $valor = trim((string) $valor);
        if ($valor !== '' && strlen($valor) <= 100 && preg_match('/^[A-Za-z0-9._:-]+$/', $valor)) {
            return $valor;
        }
        return 'REQ-' . bin2hex(random_bytes(16));
    }

    private function respuesta(
        bool $exito,
        string $mensaje,
        ?array $datos,
        int $estado = 200,
        array $errores = [],
    ): array {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) {
            $cuerpo['errors'] = $errores;
        }
        return ['status' => $estado, 'body' => $cuerpo];
    }
}
