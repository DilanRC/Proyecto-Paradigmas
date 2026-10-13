<?php

declare(strict_types=1);

namespace Application;

require_once __DIR__ . '/HttpException.php';

/**
 * La base local no puede usarse. Es una HttpException 503: las API la responden como JSON con su
 * mensaje y las páginas la muestran como aviso. El mensaje es para la persona; el detalle va al log.
 */
final class LocalDatabaseUnavailable extends HttpException
{
    public const SIN_CONEXION = 'sin_conexion';
    public const CREDENCIALES = 'credenciales';
    public const SIN_PERMISO = 'sin_permiso';
    public const SIN_PREPARAR = 'sin_preparar';
    public const INCOMPATIBLE = 'incompatible';
    public const INSTALACION = 'instalacion';

    private const MENSAJES = [
        self::SIN_CONEXION => 'No se pudo conectar con MySQL en esta computadora. Compruebe en el panel de XAMPP que MySQL esté iniciado y vuelva a cargar la página.',
        self::CREDENCIALES => 'El MySQL de esta computadora pide un usuario o una contraseña distintos a los configurados en el paquete. La aplicación no prueba otras contraseñas: quien entregó el proyecto debe indicar la conexión correcta.',
        self::SIN_PERMISO => 'El usuario de MySQL configurado no tiene permiso para crear la base bdmercadoganadero o escribir en ella. No se modificó ningún dato.',
        self::SIN_PREPARAR => 'La base de datos todavía no está preparada. Abra la aplicación una vez desde la misma computadora donde corre XAMPP (http://localhost/...).',
        self::INCOMPATIBLE => 'MySQL ya tiene una base bdmercadoganadero con una estructura distinta a la de esta versión. No se modificó ni se borró nada; la aplicación no puede usarla así.',
        self::INSTALACION => 'No se pudo terminar de preparar la base de datos. Vuelva a cargar la página en unos segundos.',
    ];

    private string $motivo;

    public function __construct(string $motivo)
    {
        $this->motivo = $motivo;
        parent::__construct(self::MENSAJES[$motivo], 503);
    }

    public function motivo(): string
    {
        return $this->motivo;
    }
}
