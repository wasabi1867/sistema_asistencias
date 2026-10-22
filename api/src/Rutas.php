<?php

declare(strict_types=1);

namespace Asistencias;

use Asistencias\Http\Enrutador;
use Asistencias\Modulos;

/** Todas las rutas de la API. El índice, con quién puede usar cada una, está en docs/api.md. */
final class Rutas
{
    private const MODULOS = [
        Modulos\Sesion\InicioDeSesion::class,
        Modulos\Catalogos\Laboratorios::class,
        Modulos\Catalogos\Carreras::class,
        Modulos\Catalogos\Periodos::class,
        Modulos\Personas\Usuarios::class,
    ];

    public static function todas(): Enrutador
    {
        $enrutador = new Enrutador();
        foreach (self::MODULOS as $modulo) {
            $modulo::registrar($enrutador);
        }
        return $enrutador;
    }
}
