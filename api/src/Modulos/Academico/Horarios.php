<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Academico;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Modulos\Catalogos\Periodos;
use Asistencias\Seguridad\Acceso;

/**
 * El horario semanal de los laboratorios: qué curso ocupa cada laboratorio, qué día
 * y de qué hora a qué hora. Lo arma el personal. Antes solo vivía en la pantalla y se
 * perdía al recargarla.
 */
final class Horarios
{
    private const DIAS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado'];
    private const SELECCION = "SELECT h.*, l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
            cu.grupo, cu.periodo_id, cu.docente_id, m.nombre AS materia,
            CONCAT(u.nombre, ' ', u.apellido) AS docente
        FROM horarios h
        JOIN laboratorios l ON l.id = h.laboratorio_id
        JOIN cursos cu ON cu.id = h.curso_id
        JOIN materias m ON m.id = cu.materia_id
        LEFT JOIN usuarios u ON u.id = cu.docente_id";

    public static function registrar(Enrutador $r): void
    {
        $r->get('/horarios', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->post('/horarios', Acceso::Personal, static fn (Contexto $c) => self::crear($c));
        $r->put('/horarios/{id}', Acceso::Personal, static fn (Contexto $c) => self::editar($c));
        $r->delete('/horarios/{id}', Acceso::Personal, static fn (Contexto $c) => self::eliminar($c));
    }

    private static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE h.id = ?', [$id]) ?? throw ErrorHttp::noExiste('El horario');
    }

    private static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'dia' => $fila['dia_semana'],
            'horaInicio' => Formato::hora($fila['hora_inicio']),
            'horaFin' => Formato::hora($fila['hora_fin']),
            'laboratorio' => ['clave' => $fila['laboratorio_clave'], 'nombre' => $fila['laboratorio_nombre']],
            'curso' => ['id' => $fila['curso_id'], 'materia' => $fila['materia'], 'grupo' => $fila['grupo'], 'docente' => $fila['docente']],
        ];
    }

    /** Sin decir periodo, el del periodo actual. */
    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $periodo = $q->entero('periodo', 'el periodo', 1, null, false) ?? Periodos::idDelActual($c->bd);
        if ($periodo === null) {
            return [];
        }
        $condiciones = ['cu.periodo_id = ?'];
        $parametros = [$periodo];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'h.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if ($q->crudo('docente') === 'yo') {
            $condiciones[] = 'cu.docente_id = ?';
            $parametros[] = $c->usuarioConSesion()->id;
        } elseif (($docente = $q->entero('docente', 'el docente', 1, null, false)) !== null) {
            $condiciones[] = 'cu.docente_id = ?';
            $parametros[] = $docente;
        }
        if (($curso = $q->entero('curso', 'el curso', 1, null, false)) !== null) {
            $condiciones[] = 'h.curso_id = ?';
            $parametros[] = $curso;
        }
        $filas = $c->bd->filas(self::SELECCION . ' WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY h.dia_semana, h.hora_inicio, l.nombre', $parametros);
        return array_map(self::formato(...), $filas);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $valores = self::leer($c, null);
        $id = $c->cambios->crear('horarios', $valores, self::DIAS[$valores['dia_semana']] . ' ' . Formato::hora($valores['hora_inicio']), $valores['laboratorio_id']);
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function editar(Contexto $c): array
    {
        $horario = self::porId($c->bd, $c->peticion->id());
        $valores = self::leer($c, $horario);
        $c->cambios->actualizar('horarios', $horario['id'], $valores, null, $valores['laboratorio_id']);
        return self::formato(self::porId($c->bd, $horario['id']));
    }

    private static function eliminar(Contexto $c): Respuesta
    {
        $horario = self::porId($c->bd, $c->peticion->id());
        $c->cambios->eliminar('horarios', $horario['id'], "{$horario['materia']} {$horario['grupo']}", $horario['laboratorio_id']);
        return Respuesta::vacia();
    }

    /**
     * Los datos de un horario (al editar, los que no vienen se quedan como estaban) y
     * la revisión de encimes.
     */
    private static function leer(Contexto $c, ?array $actual): array
    {
        $e = $c->peticion->cuerpo();
        $nuevo = $actual === null;
        $curso = Cursos::porId($c->bd, $nuevo || $e->tiene('cursoId') ? $e->entero('cursoId', 'el curso', 1) : $actual['curso_id']);
        $laboratorio = $nuevo || $e->tiene('laboratorio')
            ? Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30))['id']
            : $actual['laboratorio_id'];
        $valores = [
            'curso_id' => $curso['id'],
            'laboratorio_id' => $laboratorio,
            'dia_semana' => $nuevo || $e->tiene('dia') ? $e->entero('dia', 'el día', 1, 6) : $actual['dia_semana'],
            'hora_inicio' => $nuevo || $e->tiene('horaInicio') ? $e->hora('horaInicio', 'la hora de inicio') : $actual['hora_inicio'],
            'hora_fin' => $nuevo || $e->tiene('horaFin') ? $e->hora('horaFin', 'la hora de fin') : $actual['hora_fin'],
        ];
        if ($valores['hora_fin'] <= $valores['hora_inicio']) {
            throw ErrorHttp::datosInvalidos('La hora de fin debe ser posterior a la de inicio', 'horaFin');
        }
        self::revisarEncimes($c->bd, $valores, $curso, $actual['id'] ?? 0);
        return $valores;
    }

    /**
     * El laboratorio no puede tener dos cursos a la misma hora, y ni el curso ni su
     * docente pueden estar en dos lugares a la vez. Solo cuentan los horarios del
     * mismo periodo.
     */
    private static function revisarEncimes(BaseDeDatos $bd, array $valores, array $curso, int $excepto): void
    {
        $encimados = $bd->filas(
            self::SELECCION . ' WHERE cu.periodo_id = ? AND h.dia_semana = ? AND h.hora_inicio < ? AND h.hora_fin > ? AND h.id <> ?
              AND (h.laboratorio_id = ? OR h.curso_id = ? OR (cu.docente_id IS NOT NULL AND cu.docente_id <=> ?))',
            [$curso['periodo_id'], $valores['dia_semana'], $valores['hora_fin'], $valores['hora_inicio'], $excepto,
             $valores['laboratorio_id'], $curso['id'], $curso['docente_id']]
        );
        foreach ($encimados as $otro) {
            $cuando = 'el ' . self::DIAS[$otro['dia_semana']] . ' de ' . Formato::hora($otro['hora_inicio']) . ' a ' . Formato::hora($otro['hora_fin']);
            $quien = "{$otro['materia']} {$otro['grupo']}";
            throw ErrorHttp::conflicto(match (true) {
                $otro['laboratorio_id'] === $valores['laboratorio_id'] => "El laboratorio ya está ocupado $cuando por $quien",
                $otro['curso_id'] === $curso['id'] => "Ese curso ya tiene clase $cuando en {$otro['laboratorio_nombre']}",
                default => "El docente ya tiene clase $cuando ($quien)",
            }, 'horario_encimado');
        }
    }
}
