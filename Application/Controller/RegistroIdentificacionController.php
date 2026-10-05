<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\Persona;
use Application\Service\ValidacionService;
use PDO;

final class RegistroIdentificacionController
{
    private Persona $persona;
    private ValidacionService $validacion;

    public function __construct(PDO $conexion)
    {
        $this->persona = new Persona($conexion);
        $this->validacion = new ValidacionService();
    }

    public function procesar(string $metodo, array $cuerpo): array
    {
        if (strtoupper($metodo) !== 'POST') {
            return $this->respuesta(false, 'Método no permitido.', null, 405);
        }

        // Un dato por consulta: la identificación (tipo + número) o el correo.
        if (array_key_exists('correoElectronico', $cuerpo)) {
            return $this->disponibilidadCorreo($cuerpo);
        }

        $permitidos = ['identificacionTipo', 'identificacionNumero'];
        $errores = [];
        foreach (array_diff(array_keys($cuerpo), $permitidos) as $campo) {
            $errores[$campo] = 'Campo no permitido.';
        }

        $identificacion = $this->validacion->validarIdentificacion([
            'tipoCodigo' => $cuerpo['identificacionTipo'] ?? null,
            'numero' => $cuerpo['identificacionNumero'] ?? null,
        ], $errores);
        if ($errores !== []) {
            $erroresCampo = [];
            foreach ($errores as $campo => $mensaje) {
                $erroresCampo[match ($campo) {
                    'identificacion.tipoCodigo' => 'identificacionTipo',
                    'identificacion.numero' => 'identificacionNumero',
                    default => $campo,
                }] = $mensaje;
            }

            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => 'Revise el tipo y el número de identificación.',
                    'data' => null,
                    'errors' => $erroresCampo,
                ],
            ];
        }

        $disponible = !$this->persona->existeIdentificacion($identificacion['numero']);

        return $this->respuesta(
            true,
            $disponible ? 'Identificación disponible.' : 'La identificación ya está registrada.',
            ['disponible' => $disponible],
        );
    }

    private function disponibilidadCorreo(array $cuerpo): array
    {
        $errores = [];
        foreach (array_diff(array_keys($cuerpo), ['correoElectronico']) as $campo) {
            $errores[$campo] = 'Consulte la identificación o el correo, no ambos.';
        }
        // validarCorreo() quita espacios y pasa a minúscula antes de buscar.
        $correo = $this->validacion->validarCorreo($cuerpo['correoElectronico'], $errores);
        if ($errores !== []) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'message' => 'Revise el correo electrónico.',
                    'data' => null,
                    'errors' => $errores,
                ],
            ];
        }

        $disponible = !$this->persona->existeCorreo($correo);

        return $this->respuesta(
            true,
            $disponible ? 'Correo disponible.' : 'El correo ya está registrado.',
            ['disponible' => $disponible],
        );
    }

    private function respuesta(bool $success, string $mensaje, ?array $datos, int $estado = 200): array
    {
        return [
            'status' => $estado,
            'body' => [
                'success' => $success,
                'message' => $mensaje,
                'data' => $datos,
                'errors' => [],
            ],
        ];
    }
}
