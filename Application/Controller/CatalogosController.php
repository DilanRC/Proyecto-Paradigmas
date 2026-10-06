<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\HttpException;
use Application\Model\AnimalCatalogo;
use PDO;

/**
 * Catálogos del animal para los formularios (P2-2, P2-3): especies, tipos, razas y vacunas activos.
 * Solo lectura y con sesión. La gestión (alta, baja, edición) es del panel de administración (P2-6).
 */
final class CatalogosController
{
    private AnimalCatalogo $catalogo;

    public function __construct(PDO $conexion)
    {
        $this->catalogo = new AnimalCatalogo($conexion);
    }

    public function procesar(string $metodo, array $consulta = []): array
    {
        try {
            if ($metodo !== 'GET') return $this->respuesta(false, 'Método no permitido.', null, 405);

            $especieId = null;
            if (($consulta['especieId'] ?? '') !== '') {
                $especieId = filter_var($consulta['especieId'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($especieId === false) {
                    throw new HttpException('Revise los campos indicados.', 422, null, ['especieId' => 'Debe ser un entero positivo.']);
                }
            }

            return $this->respuesta(true, 'Catálogos consultados correctamente.', [
                'especies' => $this->catalogo->especies(),
                'tipos' => $this->catalogo->tipos($especieId),
                'razas' => $this->catalogo->razas($especieId),
                'vacunas' => $this->catalogo->vacunas(),
            ]);
        } catch (HttpException $excepcion) {
            return $this->respuesta(false, $excepcion->getMessage(), $excepcion->datos, $excepcion->estadoHttp, $excepcion->errores);
        }
    }

    private function respuesta(bool $exito, string $mensaje, ?array $datos, int $estado = 200, array $errores = []): array
    {
        $cuerpo = ['success' => $exito, 'message' => $mensaje, 'data' => $datos];
        if ($errores !== []) $cuerpo['errors'] = $errores;

        return ['status' => $estado, 'body' => $cuerpo];
    }
}
