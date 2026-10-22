<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Consultas;

use Asistencias\Contexto;
use Asistencias\Http\Enrutador;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Modulos\Catalogos\Periodos;
use Asistencias\Seguridad\Acceso;

/** Los números de los tableros: cuántos hay de cada cosa y qué pasa hoy en un laboratorio. */
final class Estadisticas
{
    public static function registrar(Enrutador $r): void
    {
        $r->get('/estadisticas', Acceso::Sesion, static fn (Contexto $c) => self::generales($c));
    }

    private static function generales(Contexto $c): array
    {
        $bd = $c->bd;
        $cuentas = [];
        foreach ($bd->filas('SELECT rol, COUNT(*) AS total FROM usuarios WHERE activo GROUP BY rol') as $fila) {
            $cuentas[$fila['rol']] = (int)$fila['total'];
        }
        $periodo = Periodos::idDelActual($bd);
        $datos = [
            'alumnos' => (int)$bd->valor('SELECT COUNT(*) FROM alumnos WHERE activo'),
            'docentes' => $cuentas['docente'] ?? 0,
            'laboratoristas' => $cuentas['laboratorista'] ?? 0,
            'administradores' => $cuentas['admin'] ?? 0,
            'materias' => (int)$bd->valor('SELECT COUNT(*) FROM materias WHERE activa'),
            'cursosDelPeriodo' => $periodo === null ? 0 : (int)$bd->valor('SELECT COUNT(*) FROM cursos WHERE periodo_id = ?', [$periodo]),
            'solicitudesPendientes' => (int)$bd->valor("SELECT COUNT(*) FROM solicitudes_ingreso WHERE estado = 'pendiente'"),
            'reportesAbiertos' => (int)$bd->valor("SELECT COUNT(*) FROM reportes_de_equipo WHERE estado <> 'resuelto'"),
        ];

        $clave = $c->peticion->consulta()->texto('laboratorio', 'el laboratorio', 30, false);
        if ($clave === null) {
            return $datos;
        }
        $laboratorio = Laboratorios::porClave($bd, $clave)['id'];
        $equipos = $bd->fila(
            'SELECT COUNT(*) AS total, COALESCE(SUM(fuera_de_servicio), 0) AS fuera_de_servicio,
                    COALESCE(SUM(EXISTS (SELECT 1 FROM asistencias s WHERE s.equipo_id = e.id AND s.hora_salida IS NULL)), 0) AS ocupados
             FROM equipos e WHERE e.laboratorio_id = ? AND e.activo',
            [$laboratorio]
        );
        return $datos + [
            'laboratorio' => [
                'equipos' => [
                    'total' => (int)$equipos['total'],
                    'ocupados' => (int)$equipos['ocupados'],
                    'fueraDeServicio' => (int)$equipos['fuera_de_servicio'],
                    'libres' => (int)$equipos['total'] - (int)$equipos['ocupados'] - (int)$equipos['fuera_de_servicio'],
                ],
                'personasDentro' => (int)$bd->valor('SELECT COUNT(*) FROM asistencias WHERE laboratorio_id = ? AND hora_salida IS NULL', [$laboratorio]),
                'clasesAbiertas' => (int)$bd->valor('SELECT COUNT(*) FROM clases WHERE laboratorio_id = ? AND cerrada_en IS NULL', [$laboratorio]),
                'asistenciasHoy' => (int)$bd->valor('SELECT COUNT(*) FROM asistencias WHERE laboratorio_id = ? AND hora_entrada >= CURDATE()', [$laboratorio]),
                'clasesHoy' => (int)$bd->valor('SELECT COUNT(*) FROM clases WHERE laboratorio_id = ? AND abierta_en >= CURDATE()', [$laboratorio]),
            ],
        ];
    }
}
