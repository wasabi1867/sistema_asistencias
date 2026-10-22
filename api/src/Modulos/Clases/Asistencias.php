<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Clases;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Carreras;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Modulos\Personas\Alumnos;
use Asistencias\Seguridad\Acceso;

/**
 * Las entradas a los laboratorios: quién está dentro ahora, el historial para los
 * reportes, el uso fuera de clase, la salida de alguien y quitar una entrada
 * registrada por error.
 */
final class Asistencias
{
    public const SELECCION = "SELECT s.*, e.etiqueta AS equipo, l.clave AS laboratorio_clave,
            COALESCE(a.matricula, u.matricula) AS matricula,
            COALESCE(a.nombre, u.nombre, s.invitado_nombre) AS nombre,
            COALESCE(a.apellido, u.apellido, s.invitado_apellido) AS apellido,
            ca.clave AS carrera_clave, ca.nombre AS carrera_nombre, a.semestre, a.grupo, a.turno
        FROM asistencias s
        JOIN laboratorios l ON l.id = s.laboratorio_id
        LEFT JOIN equipos e ON e.id = s.equipo_id
        LEFT JOIN alumnos a ON a.id = s.alumno_id
        LEFT JOIN carreras ca ON ca.id = a.carrera_id
        LEFT JOIN usuarios u ON u.id = s.usuario_id";

    public static function registrar(Enrutador $r): void
    {
        $r->get('/asistencias/activas', Acceso::Sesion, static fn (Contexto $c) => self::activas($c));
        $r->get('/asistencias', Acceso::Sesion, static fn (Contexto $c) => self::historial($c));
        $r->post('/asistencias', Acceso::Personal, static fn (Contexto $c) => self::usoFueraDeClase($c));
        // Quién puede registrar la salida o quitar una entrada se revisa adentro.
        $r->post('/asistencias/{id}/salida', Acceso::Sesion, static fn (Contexto $c) => self::registrarSalida($c));
        $r->delete('/asistencias/{id}', Acceso::Sesion, static fn (Contexto $c) => self::quitar($c));
    }

    public static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'tipo' => $fila['tipo_persona'],
            'matricula' => $fila['matricula'],
            'nombre' => $fila['nombre'],
            'apellido' => $fila['apellido'],
            'carrera' => $fila['carrera_clave'] === null ? null : ['clave' => $fila['carrera_clave'], 'nombre' => $fila['carrera_nombre']],
            'semestre' => $fila['semestre'],
            'grupo' => $fila['grupo'],
            'turno' => $fila['turno'],
            'equipo' => $fila['equipo'],
            'laboratorio' => $fila['laboratorio_clave'],
            'claseId' => $fila['clase_id'],
            'horaEntrada' => Formato::fechaHora($fila['hora_entrada']),
            'horaSalida' => Formato::fechaHora($fila['hora_salida']),
        ];
    }

    public static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE s.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La asistencia');
    }

    /** Quién está dentro del laboratorio en este momento. */
    private static function activas(Contexto $c): array
    {
        $laboratorio = Laboratorios::porClave($c->bd, $c->peticion->consulta()->texto('laboratorio', 'el laboratorio', 30));
        $filas = $c->bd->filas(self::SELECCION . ' WHERE s.laboratorio_id = ? AND s.hora_salida IS NULL ORDER BY s.hora_entrada', [$laboratorio['id']]);
        return array_map(self::formato(...), $filas);
    }

    /**
     * El historial, para los reportes: filtrable por laboratorio, fechas, alumno,
     * carrera, curso y clase. Lo más reciente primero.
     */
    private static function historial(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 's.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($desde = $q->fecha('desde', 'la fecha de inicio', false)) !== null) {
            $condiciones[] = 's.hora_entrada >= ?';
            $parametros[] = $desde;
        }
        if (($hasta = $q->fecha('hasta', 'la fecha de fin', false)) !== null) {
            $condiciones[] = 's.hora_entrada < ? + INTERVAL 1 DAY';
            $parametros[] = $hasta;
        }
        if (($matricula = $q->texto('alumno', 'el alumno', 20, false)) !== null) {
            $condiciones[] = 's.alumno_id = ?';
            $parametros[] = Alumnos::porMatricula($c->bd, $matricula)['id'];
        }
        if (($carrera = $q->texto('carrera', 'la carrera', 150, false)) !== null) {
            $condiciones[] = 'a.carrera_id = ?';
            $parametros[] = Carreras::idPor($c->bd, $carrera);
        }
        if (($curso = $q->entero('curso', 'el curso', 1, null, false)) !== null) {
            $condiciones[] = 's.clase_id IN (SELECT id FROM clases WHERE curso_id = ?)';
            $parametros[] = $curso;
        }
        if (($clase = $q->entero('clase', 'la clase', 1, null, false)) !== null) {
            $condiciones[] = 's.clase_id = ?';
            $parametros[] = $clase;
        }
        if ($q->booleano('fueraDeClase')) {
            $condiciones[] = 's.clase_id IS NULL';
        }

        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        $pagina = $q->paginacion(100, 2000);
        $total = (int)$c->bd->valor("SELECT COUNT(*) FROM asistencias s LEFT JOIN alumnos a ON a.id = s.alumno_id$donde", $parametros);
        $filas = $c->bd->filas(self::SELECCION . "$donde ORDER BY s.hora_entrada DESC, s.id DESC LIMIT {$pagina['porPagina']} OFFSET {$pagina['desde']}", $parametros);
        return ['asistencias' => array_map(self::formato(...), $filas), 'total' => $total, 'pagina' => $pagina['pagina'], 'porPagina' => $pagina['porPagina']];
    }

    /**
     * Uso del laboratorio fuera de clase. Mientras el instituto no diga quién lo
     * autoriza, el personal lo registra sin pedir permiso a nadie más.
     */
    private static function usoFueraDeClase(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $laboratorio = Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30));
        $etiqueta = Entradas::equipoElegido($e);
        $persona = $e->tiene('matricula')
            ? self::alumnoQueEntra($c->bd, $e->texto('matricula', 'la matrícula', 20))
            : ['tipo' => 'invitado', 'nombre' => $e->texto('nombre', 'el nombre', 100), 'apellido' => $e->texto('apellido', 'el apellido', 100)];
        $entrada = Entradas::registrar($c->bd, $laboratorio['id'], null, $persona, $etiqueta, registradaPor: $c->usuarioConSesion()->id);
        return Respuesta::creado(self::formato(self::porId($c->bd, $entrada['id'])));
    }

    private static function registrarSalida(Contexto $c): array
    {
        $asistencia = self::corregible($c);
        if ($asistencia['hora_salida'] !== null) {
            throw ErrorHttp::conflicto('Esa persona ya había salido', 'ya_salio');
        }
        $c->bd->ejecutar('UPDATE asistencias SET hora_salida = NOW() WHERE id = ?', [$asistencia['id']]);
        return self::formato(self::porId($c->bd, $asistencia['id']));
    }

    /** Quita una entrada registrada por error. Queda en la bitácora, porque el registro desaparece. */
    private static function quitar(Contexto $c): Respuesta
    {
        $asistencia = self::corregible($c);
        $c->bd->ejecutar('DELETE FROM asistencias WHERE id = ?', [$asistencia['id']]);
        $quien = $asistencia['matricula'] ?? trim("{$asistencia['nombre']} {$asistencia['apellido']}");
        $c->bitacora->registrar(
            'quitar_asistencia',
            'asistencias',
            $asistencia['id'],
            "$quien" . ($asistencia['clase_id'] !== null ? ", clase {$asistencia['clase_id']}" : ', fuera de clase'),
            $asistencia['laboratorio_id']
        );
        return Respuesta::vacia();
    }

    /** El alumno activo con esa matrícula, como persona que entra. */
    public static function alumnoQueEntra(BaseDeDatos $bd, string $matricula): array
    {
        $alumno = $bd->fila('SELECT id, activo FROM alumnos WHERE matricula = ?', [$matricula])
            ?? throw new ErrorHttp(404, 'No encontramos esa matrícula. Revisa que esté bien escrita', ['codigo' => 'matricula_no_encontrada']);
        if (!$alumno['activo']) {
            throw ErrorHttp::sinPermiso('Esa matrícula está desactivada. Acude con el personal del laboratorio');
        }
        return ['tipo' => 'alumno', 'alumno_id' => $alumno['id']];
    }

    /** La asistencia de la ruta, si quien pide puede corregirla: quien lleva su clase o, fuera de clase, el personal. */
    private static function corregible(Contexto $c): array
    {
        $asistencia = self::porId($c->bd, $c->peticion->id());
        $usuario = $c->usuarioConSesion();
        $puede = $asistencia['clase_id'] === null
            ? $usuario->esPersonal()
            : Clases::puedeLlevar($usuario, Clases::porId($c->bd, $asistencia['clase_id']));
        if (!$puede) {
            throw ErrorHttp::sinPermiso('Solo quien lleva la clase puede corregir sus asistencias');
        }
        return $asistencia;
    }
}
