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
        Modulos\Personas\Alumnos::class,
        Modulos\Academico\Materias::class,
        Modulos\Academico\Practicas::class,
        Modulos\Academico\Cursos::class,
        Modulos\Academico\Horarios::class,
        Modulos\Equipos\Equipos::class,
        Modulos\Equipos\ReportesDeEquipo::class,
        Modulos\Clases\Clases::class,
        Modulos\Clases\RegistroDeEntrada::class,
        Modulos\Clases\Asistencias::class,
        Modulos\Solicitudes\Solicitudes::class,
        Modulos\Avisos\Avisos::class,
        Modulos\Laboratorio\Eventos::class,
        Modulos\Consultas\ConsultaDeBitacora::class,
        Modulos\Consultas\Estadisticas::class,
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
