<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Auth\ActorContext;
use Application\HttpException;
use Application\Model\Persona;
use Application\Service\RegistroPublicoService;
use Application\Service\ValidacionException;
use Application\Service\ValidacionService;
use PDO;

final class RegistroPublicoController
{
    private const CAPACIDADES = ['COMPRADOR', 'PRODUCTOR', 'TRANSPORTISTA'];

    private Persona $persona;
    private ValidacionService $validacion;
    private RegistroPublicoService $registro;

    public function __construct(
        private readonly PDO $conexion,
        private readonly ActorContext $actor,
        ?string $solicitudId = null,
    ) {
        $this->persona = new Persona($conexion);
        $this->validacion = new ValidacionService();
        $this->registro = new RegistroPublicoService(
            $conexion,
            $actor,
            $this->normalizarSolicitudId($solicitudId),
        );
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        try {
            if ($metodo !== 'POST') {
                return $this->respuesta(false, 'Método no permitido.', null, 405);
            }
            if (!$this->actor->estaAutenticado()) {
                throw new HttpException('Debe verificar su cuenta antes de completar el registro.', 401);
            }

            return $this->registrar($cuerpo);
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

    private function registrar(array $cuerpo): array
    {
        $errores = $this->rechazarCamposDesconocidos($cuerpo, ['persona', 'capacidades', 'fincas']);
        $capacidades = $this->validarCapacidades($cuerpo['capacidades'] ?? null, $errores);
        $fincas = $this->validarFincas($cuerpo['fincas'] ?? [], $errores);
        $personaDatos = $this->resolverDatosPersona($cuerpo['persona'] ?? null, $errores);

        if (!in_array('PRODUCTOR', $capacidades, true) && $fincas !== []) {
            $errores['fincas'] = 'Las fincas solo forman parte del contexto Productor.';
        }
        if ($errores !== []) {
            throw new HttpException('Revise los campos indicados.', 422, null, $errores);
        }

        $resultado = $this->registro->registrar($personaDatos, $capacidades, $fincas);
        $creadas = $resultado['capacidadesCreadas'] ?? [];

        return $this->respuesta(
            true,
            $creadas === []
                ? 'Las actividades seleccionadas ya estaban configuradas.'
                : 'Registro actualizado correctamente.',
            $resultado,
            $creadas === [] ? 200 : 201,
        );
    }

    /** @return list<string> */
    private function validarCapacidades(mixed $valor, array &$errores): array
    {
        if (!is_array($valor) || !array_is_list($valor)) {
            $errores['capacidades'] = 'Seleccione al menos una actividad.';
            return [];
        }

        $resultado = [];
        foreach ($valor as $indice => $capacidad) {
            if (!is_string($capacidad)) {
                $errores["capacidades.{$indice}"] = 'La actividad no es válida.';
                continue;
            }
            $normalizada = mb_strtoupper(trim($capacidad), 'UTF-8');
            if (!in_array($normalizada, self::CAPACIDADES, true)) {
                $errores["capacidades.{$indice}"] = 'Actividad no admitida.';
                continue;
            }
            if (!in_array($normalizada, $resultado, true)) {
                $resultado[] = $normalizada;
            }
        }
        if ($resultado === []) {
            $errores['capacidades'] = 'Seleccione al menos una actividad.';
        }

        return $resultado;
    }

    /** @return list<array{nombre:string,direccion?:array<string,mixed>}> */
    private function validarFincas(mixed $valor, array &$errores): array
    {
        $locales = [];
        $fincas = $this->validacion->validarFincas($valor, $locales);
        foreach ($locales as $campo => $mensaje) {
            $errores[$campo] = $mensaje;
        }
        return $fincas;
    }

    /** @return array<string,mixed> */
    private function resolverDatosPersona(mixed $valor, array &$errores): array
    {
        if ($this->actor->personaId !== null) {
            $persona = $this->persona->buscarPorId($this->actor->personaId);
            if ($persona === null || (int) $persona['tbpersonaestado'] !== 1) {
                throw new HttpException('La Persona vinculada a la sesión no está disponible.', 409);
            }

            // Si el navegador envía Persona durante una ampliación, solo se usa
            // para detectar un cache equivocado; nunca modifica identidad/contacto.
            if (is_array($valor)) {
                $identificacionEnviada = mb_strtoupper(
                    preg_replace('/[ -]+/u', '', trim((string) ($valor['identificacionNumero'] ?? ''))) ?? '',
                    'UTF-8',
                );
                $correoEnviado = mb_strtolower(trim((string) ($valor['correoElectronico'] ?? '')), 'UTF-8');
                if ($identificacionEnviada !== ''
                    && $identificacionEnviada !== $persona['tbpersonaidentificacionnumero']) {
                    $errores['persona.identificacionNumero'] = 'La identificación no corresponde a la sesión.';
                }
                if ($correoEnviado !== ''
                    && $correoEnviado !== mb_strtolower((string) $persona['tbpersonacorreoelectronico'], 'UTF-8')) {
                    $errores['persona.correoElectronico'] = 'El correo no corresponde a la sesión.';
                }
            } elseif ($valor !== null) {
                $errores['persona'] = 'Persona debe ser un objeto.';
            }

            return [
                'identificacionNumero' => $persona['tbpersonaidentificacionnumero'],
                'identificacionTipo' => $persona['tbpersonaidentificaciontipo'],
                'nombre' => $persona['tbpersonanombre'],
                'alias' => $persona['tbpersonaalias'],
                'telefono' => $persona['tbpersonatelefono'],
                'correoElectronico' => $persona['tbpersonacorreoelectronico'],
            ];
        }

        if (!is_array($valor) || array_is_list($valor)) {
            $errores['persona'] = 'Complete sus datos personales.';
            return [];
        }
        foreach ($this->rechazarCamposDesconocidos(
            $valor,
            ['identificacionTipo', 'identificacionNumero', 'nombre', 'alias', 'telefono', 'correoElectronico'],
            'persona.',
        ) as $campo => $mensaje) {
            $errores[$campo] = $mensaje;
        }

        // La contraseña deliberadamente no forma parte de este contrato PHP.
        // Solo Supabase Auth debe recibirla.
        try {
            return $this->validacion->validarPersona([
                'identificacion' => [
                    'tipoCodigo' => $valor['identificacionTipo'] ?? null,
                    'numero' => $valor['identificacionNumero'] ?? null,
                ],
                'nombre' => $valor['nombre'] ?? null,
                'alias' => $valor['alias'] ?? null,
                'telefono' => $valor['telefono'] ?? null,
                'correoElectronico' => $valor['correoElectronico'] ?? null,
            ], false)['datos'];
        } catch (ValidacionException $excepcion) {
            foreach ($excepcion->errores as $campo => $mensaje) {
                $campoPublico = match ($campo) {
                    'identificacion.tipoCodigo' => 'persona.identificacionTipo',
                    'identificacion.numero' => 'persona.identificacionNumero',
                    default => 'persona.' . $campo,
                };
                $errores[$campoPublico] = $mensaje;
            }
            return [];
        }
    }

    private function rechazarCamposDesconocidos(array $datos, array $permitidos, string $prefijo = ''): array
    {
        $errores = [];
        foreach (array_diff(array_keys($datos), $permitidos) as $campo) {
            $errores[$prefijo . $campo] = 'Campo no permitido.';
        }
        return $errores;
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
