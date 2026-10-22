<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Academico;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Modulos\Catalogos\Periodos;
use Asistencias\Modulos\Clases\ListaDeAsistencia;
use Asistencias\Modulos\Personas\Alumnos;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Rol;
use Asistencias\Seguridad\Usuario;

/**
 * Los cursos (una materia que un docente imparte a un grupo en un periodo) y quiénes
 * están inscritos. Los crea el administrador; a los alumnos los inscriben el personal
 * y el docente del curso.
 */
final class Cursos
{
    private const MAXIMO_POR_INSCRIPCION = 500;
    private const SELECCION = "SELECT cu.*, m.nombre AS materia_nombre, m.clave AS materia_clave,
            p.nombre AS periodo_nombre, CURDATE() BETWEEN p.fecha_inicio AND p.fecha_fin AS periodo_actual,
            u.nombre AS docente_nombre, u.apellido AS docente_apellido,
            l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
            (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = cu.id) AS inscritos
        FROM cursos cu
        JOIN materias m ON m.id = cu.materia_id
        JOIN periodos p ON p.id = cu.periodo_id
        LEFT JOIN usuarios u ON u.id = cu.docente_id
        LEFT JOIN laboratorios l ON l.id = cu.laboratorio_id";
    private const DUPLICADOS = ['uq_cursos' => 'Esa materia ya tiene ese grupo en el periodo'];

    public static function registrar(Enrutador $r): void
    {
        $r->get('/cursos', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->get('/cursos/{id}', Acceso::Sesion, static fn (Contexto $c) => self::formato(self::porId($c->bd, $c->peticion->id())));
        $r->post('/cursos', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->put('/cursos/{id}', Acceso::Admin, static fn (Contexto $c) => self::editar($c));
        $r->delete('/cursos/{id}', Acceso::Admin, static fn (Contexto $c) => self::eliminar($c));
        $r->get('/cursos/{id}/alumnos', Acceso::Sesion, static fn (Contexto $c) => self::inscritos($c));
        // Inscribir y dar de baja: el personal o el docente del curso (se revisa adentro).
        $r->post('/cursos/{id}/alumnos', Acceso::Sesion, static fn (Contexto $c) => self::inscribir($c));
        $r->delete('/cursos/{id}/alumnos/{matricula}', Acceso::Sesion, static fn (Contexto $c) => self::darDeBaja($c));
        $r->get('/cursos/{id}/asistencia', Acceso::Sesion, static fn (Contexto $c) => self::asistencia($c));
    }

    public static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE cu.id = ?', [$id]) ?? throw ErrorHttp::noExiste('El curso');
    }

    public static function esSuDocente(Usuario $usuario, array $curso): bool
    {
        return $usuario->rol === Rol::Docente && $curso['docente_id'] === $usuario->id;
    }

    public static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'materia' => ['id' => $fila['materia_id'], 'clave' => $fila['materia_clave'], 'nombre' => $fila['materia_nombre']],
            'periodo' => ['id' => $fila['periodo_id'], 'nombre' => $fila['periodo_nombre'], 'actual' => (bool)$fila['periodo_actual']],
            'grupo' => $fila['grupo'],
            'turno' => $fila['turno'],
            'docente' => $fila['docente_id'] === null ? null : ['id' => $fila['docente_id'], 'nombre' => $fila['docente_nombre'], 'apellido' => $fila['docente_apellido']],
            'laboratorio' => $fila['laboratorio_clave'] === null ? null : ['clave' => $fila['laboratorio_clave'], 'nombre' => $fila['laboratorio_nombre']],
            'inscritos' => (int)$fila['inscritos'],
        ];
    }

    /** Sin decir periodo, los del periodo actual. ?docente=yo da los del docente de la sesión. */
    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];

        if ($q->crudo('periodo') !== 'todos') {
            $periodo = $q->entero('periodo', 'el periodo', 1, null, false) ?? Periodos::idDelActual($c->bd);
            if ($periodo === null) {
                return [];
            }
            $condiciones[] = 'cu.periodo_id = ?';
            $parametros[] = $periodo;
        }
        if ($q->crudo('docente') === 'yo') {
            $condiciones[] = 'cu.docente_id = ?';
            $parametros[] = $c->usuarioConSesion()->id;
        } elseif (($docente = $q->entero('docente', 'el docente', 1, null, false)) !== null) {
            $condiciones[] = 'cu.docente_id = ?';
            $parametros[] = $docente;
        }
        if (($materia = $q->entero('materia', 'la materia', 1, null, false)) !== null) {
            $condiciones[] = 'cu.materia_id = ?';
            $parametros[] = $materia;
        }
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'cu.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }

        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        $filas = $c->bd->filas(self::SELECCION . "$donde ORDER BY p.fecha_inicio DESC, m.nombre, cu.grupo", $parametros);
        return array_map(self::formato(...), $filas);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $valores = self::datos($c, $c->peticion->cuerpo(), nuevo: true);
        $id = ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear('cursos', $valores, "Grupo {$valores['grupo']}"));
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function editar(Contexto $c): array
    {
        $curso = self::porId($c->bd, $c->peticion->id());
        $valores = self::datos($c, $c->peticion->cuerpo(), nuevo: false);
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('cursos', $curso['id'], $valores, self::descripcion($curso)));
        return self::formato(self::porId($c->bd, $curso['id']));
    }

    /** Solo si no tiene clases. Sus inscripciones y su horario se borran con él. */
    private static function eliminar(Contexto $c): Respuesta
    {
        $curso = self::porId($c->bd, $c->peticion->id());
        ErrorDeIntegridad::explicar(
            ['fk_clases_curso' => 'El curso ya tiene clases registradas y no se puede eliminar'],
            static fn () => $c->cambios->eliminar('cursos', $curso['id'], self::descripcion($curso))
        );
        return Respuesta::vacia();
    }

    private static function inscritos(Contexto $c): array
    {
        $curso = self::porId($c->bd, $c->peticion->id());
        $filas = $c->bd->filas(
            'SELECT a.*, c.clave AS carrera_clave, c.nombre AS carrera_nombre, i.inscrito_en
             FROM inscripciones i
             JOIN alumnos a ON a.id = i.alumno_id
             LEFT JOIN carreras c ON c.id = a.carrera_id
             WHERE i.curso_id = ?
             ORDER BY a.apellido, a.nombre, a.matricula',
            [$curso['id']]
        );
        return array_map(static fn (array $f) => Alumnos::formato($f) + ['inscritoEn' => Formato::fechaHora($f['inscrito_en'])], $filas);
    }

    /**
     * Inscribe una lista de números de control y dice qué pasó con cada uno. No es un
     * error mandar a alguien que ya estaba inscrito.
     */
    private static function inscribir(Contexto $c): array
    {
        $curso = self::administrable($c);
        $matriculas = array_values(array_unique(array_filter(array_map(
            static fn (mixed $m) => is_scalar($m) ? trim((string)$m) : '',
            $c->peticion->cuerpo()->lista('matriculas', 'la lista de matrículas', self::MAXIMO_POR_INSCRIPCION)
        ), static fn (string $m) => $m !== '')));
        if ($matriculas === []) {
            throw ErrorHttp::datosInvalidos('La lista de matrículas está vacía', 'matriculas');
        }

        $marcas = implode(', ', array_fill(0, count($matriculas), '?'));
        $alumnos = [];
        foreach ($c->bd->filas(
            "SELECT a.id, a.matricula, a.activo, i.alumno_id IS NOT NULL AS inscrito
             FROM alumnos a LEFT JOIN inscripciones i ON i.alumno_id = a.id AND i.curso_id = ?
             WHERE a.matricula IN ($marcas)",
            [$curso['id'], ...$matriculas]
        ) as $fila) {
            $alumnos[mb_strtolower($fila['matricula'])] = $fila;
        }

        $resultado = ['inscritos' => [], 'yaInscritos' => [], 'noEncontrados' => [], 'inactivos' => []];
        foreach ($matriculas as $matricula) {
            $alumno = $alumnos[mb_strtolower($matricula)] ?? null;
            $que = match (true) {
                $alumno === null => 'noEncontrados',
                (bool)$alumno['inscrito'] => 'yaInscritos',
                !$alumno['activo'] => 'inactivos',
                default => 'inscritos',
            };
            if ($que === 'inscritos') {
                $c->bd->insertar('inscripciones', ['curso_id' => $curso['id'], 'alumno_id' => $alumno['id']]);
            }
            $resultado[$que][] = $alumno['matricula'] ?? $matricula;
        }
        if ($resultado['inscritos'] !== []) {
            $c->bitacora->registrar('inscribir', 'cursos', $curso['id'], self::descripcion($curso) . ': ' . count($resultado['inscritos']) . ' alumnos');
        }
        return $resultado;
    }

    private static function darDeBaja(Contexto $c): Respuesta
    {
        $curso = self::administrable($c);
        $alumno = Alumnos::porMatricula($c->bd, $c->peticion->parametro('matricula'));
        if ($c->bd->ejecutar('DELETE FROM inscripciones WHERE curso_id = ? AND alumno_id = ?', [$curso['id'], $alumno['id']]) === 0) {
            throw new ErrorHttp(404, 'El alumno no está inscrito en este curso');
        }
        $c->bitacora->registrar('dar_de_baja', 'cursos', $curso['id'], self::descripcion($curso) . ": alumno {$alumno['matricula']}");
        return Respuesta::vacia();
    }

    /** Asistencias, faltas y porcentaje de cada inscrito, sobre las clases ya cerradas. */
    private static function asistencia(Contexto $c): array
    {
        $curso = self::porId($c->bd, $c->peticion->id());
        $clases = $c->bd->filas('SELECT id, cerrada_en FROM clases WHERE curso_id = ? AND cerrada_en IS NOT NULL', [$curso['id']]);
        $inscritos = $c->bd->filas(
            'SELECT a.id, a.matricula, a.nombre, a.apellido, i.inscrito_en
             FROM inscripciones i JOIN alumnos a ON a.id = i.alumno_id
             WHERE i.curso_id = ? ORDER BY a.apellido, a.nombre, a.matricula',
            [$curso['id']]
        );
        $asistencias = $c->bd->filas(
            'SELECT DISTINCT s.alumno_id, s.clase_id FROM asistencias s JOIN clases cl ON cl.id = s.clase_id
             WHERE cl.curso_id = ? AND cl.cerrada_en IS NOT NULL AND s.alumno_id IS NOT NULL',
            [$curso['id']]
        );
        return ['clases' => count($clases), 'alumnos' => ListaDeAsistencia::delCurso($clases, $inscritos, $asistencias)];
    }

    /** El curso de la ruta, si quien pide puede cambiar su lista: el personal o su docente. */
    private static function administrable(Contexto $c): array
    {
        $curso = self::porId($c->bd, $c->peticion->id());
        $usuario = $c->usuarioConSesion();
        if (!$usuario->esPersonal() && !self::esSuDocente($usuario, $curso)) {
            throw ErrorHttp::sinPermiso('Solo el personal o el docente del curso pueden cambiar su lista de alumnos');
        }
        return $curso;
    }

    private static function datos(Contexto $c, Entrada $e, bool $nuevo): array
    {
        $valores = [];
        $leer = static fn (string $campo) => $nuevo || $e->tiene($campo);
        if ($leer('materiaId')) {
            $valores['materia_id'] = Materias::porId($c->bd, $e->entero('materiaId', 'la materia', 1))['id'];
        }
        if ($leer('periodoId')) {
            $periodo = $e->entero('periodoId', 'el periodo', 1);
            if ($c->bd->valor('SELECT 1 FROM periodos WHERE id = ?', [$periodo]) === null) {
                throw ErrorHttp::datosInvalidos('Ese periodo no existe', 'periodoId');
            }
            $valores['periodo_id'] = $periodo;
        }
        if ($leer('grupo')) {
            $valores['grupo'] = mb_strtoupper($e->texto('grupo', 'el grupo', 20));
        }
        if ($leer('turno')) {
            $valores['turno'] = $e->opcion('turno', 'el turno', ['matutino', 'vespertino'], false);
        }
        if ($leer('docenteId')) {
            $docente = $e->entero('docenteId', 'el docente', 1, null, false);
            // Un curso solo se asigna a una cuenta de docente activa; si después se
            // desactiva, el curso la conserva para no perder quién lo impartió.
            if ($docente !== null && $c->bd->valor("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'docente' AND activo", [$docente]) === null) {
                throw ErrorHttp::datosInvalidos('Ese docente no existe o su cuenta está desactivada', 'docenteId');
            }
            $valores['docente_id'] = $docente;
        }
        if ($leer('laboratorio')) {
            $clave = $e->texto('laboratorio', 'el laboratorio', 30, false);
            $valores['laboratorio_id'] = $clave === null ? null : Laboratorios::porClave($c->bd, $clave)['id'];
        }
        return $valores;
    }

    private static function descripcion(array $curso): string
    {
        return "{$curso['materia_nombre']} {$curso['grupo']}";
    }
}
