<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Personas;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Carreras;
use Asistencias\Seguridad\Acceso;

/**
 * Los alumnos. Se identifican con su número de control (la matrícula), que es lo
 * que usan las direcciones: /alumnos/23303080. Los da de alta el personal, uno por
 * uno o en lote desde un archivo; uno con historial no se borra, se desactiva.
 */
final class Alumnos
{
    private const MAXIMO_POR_LOTE = 2000;
    private const TURNOS = ['matutino', 'vespertino'];
    private const DUPLICADOS = ['uq_alumnos_matricula' => 'Ya hay un alumno con esa matrícula'];
    private const SELECCION = 'SELECT a.*, c.clave AS carrera_clave, c.nombre AS carrera_nombre
        FROM alumnos a LEFT JOIN carreras c ON c.id = a.carrera_id';

    public static function registrar(Enrutador $r): void
    {
        $r->get('/alumnos', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->get('/alumnos/{matricula}', Acceso::Sesion, static fn (Contexto $c) => self::uno($c));
        $r->post('/alumnos', Acceso::Personal, static fn (Contexto $c) => self::crear($c));
        $r->post('/alumnos/lote', Acceso::Personal, static fn (Contexto $c) => self::cargarLote($c));
        $r->post('/alumnos/cambio-masivo', Acceso::Personal, static fn (Contexto $c) => self::cambioMasivo($c));
        $r->put('/alumnos/{matricula}', Acceso::Personal, static fn (Contexto $c) => self::editar($c));
        $r->delete('/alumnos/{matricula}', Acceso::Admin, static fn (Contexto $c) => self::eliminar($c));
    }

    /** El renglón del alumno con esa matrícula, o 404. */
    public static function porMatricula(BaseDeDatos $bd, string $matricula): array
    {
        return $bd->fila(self::SELECCION . ' WHERE a.matricula = ?', [$matricula]) ?? throw ErrorHttp::noExiste('El alumno');
    }

    /** @return array<string, mixed> */
    public static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'matricula' => $fila['matricula'],
            'nombre' => $fila['nombre'],
            'apellido' => $fila['apellido'],
            'carrera' => $fila['carrera_clave'] === null ? null : ['clave' => $fila['carrera_clave'], 'nombre' => $fila['carrera_nombre']],
            'semestre' => $fila['semestre'],
            'turno' => $fila['turno'],
            'grupo' => $fila['grupo'],
            'activo' => (bool)$fila['activo'],
        ];
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($texto = $q->texto('q', 'la búsqueda', 100, false)) !== null) {
            $condiciones[] = "(a.matricula LIKE ? OR CONCAT(a.nombre, ' ', a.apellido) LIKE ? OR CONCAT(a.apellido, ' ', a.nombre) LIKE ?)";
            array_push($parametros, ...array_fill(0, 3, BaseDeDatos::patron($texto)));
        }
        if (($carrera = $q->texto('carrera', 'la carrera', 150, false)) !== null) {
            $condiciones[] = 'a.carrera_id = ?';
            $parametros[] = Carreras::idPor($c->bd, $carrera);
        }
        if (($semestre = $q->entero('semestre', 'el semestre', 1, 15, false)) !== null) {
            $condiciones[] = 'a.semestre = ?';
            $parametros[] = $semestre;
        }
        if (($grupo = $q->texto('grupo', 'el grupo', 20, false)) !== null) {
            $condiciones[] = 'a.grupo = ?';
            $parametros[] = $grupo;
        }
        if (($turno = $q->opcion('turno', 'el turno', self::TURNOS, false)) !== null) {
            $condiciones[] = 'a.turno = ?';
            $parametros[] = $turno;
        }
        // Sin decir nada, solo los activos.
        $condiciones[] = 'a.activo = ?';
        $parametros[] = $q->booleano('activo', true);

        $donde = ' WHERE ' . implode(' AND ', $condiciones);
        $pagina = $q->paginacion();
        $total = (int)$c->bd->valor("SELECT COUNT(*) FROM alumnos a$donde", $parametros);
        $filas = $c->bd->filas(self::SELECCION . "$donde ORDER BY a.apellido, a.nombre, a.matricula LIMIT {$pagina['porPagina']} OFFSET {$pagina['desde']}", $parametros);

        return ['alumnos' => array_map(self::formato(...), $filas), 'total' => $total, 'pagina' => $pagina['pagina'], 'porPagina' => $pagina['porPagina']];
    }

    /** El alumno y los cursos en que está inscrito, del más reciente al más antiguo. */
    private static function uno(Contexto $c): array
    {
        $alumno = self::porMatricula($c->bd, $c->peticion->parametro('matricula'));
        $cursos = $c->bd->filas(
            "SELECT cu.id, m.nombre AS materia, cu.grupo, p.nombre AS periodo, CONCAT(u.nombre, ' ', u.apellido) AS docente, i.inscrito_en
             FROM inscripciones i
             JOIN cursos cu ON cu.id = i.curso_id
             JOIN materias m ON m.id = cu.materia_id
             JOIN periodos p ON p.id = cu.periodo_id
             LEFT JOIN usuarios u ON u.id = cu.docente_id
             WHERE i.alumno_id = ?
             ORDER BY p.fecha_inicio DESC, m.nombre",
            [$alumno['id']]
        );
        return self::formato($alumno) + ['cursos' => array_map(static fn (array $f) => [
            'id' => $f['id'],
            'materia' => $f['materia'],
            'grupo' => $f['grupo'],
            'periodo' => $f['periodo'],
            'docente' => $f['docente'],
            'inscritoEn' => Formato::fechaHora($f['inscrito_en']),
        ], $cursos)];
    }

    private static function crear(Contexto $c): Respuesta
    {
        $valores = self::datos($c->bd, $c->peticion->cuerpo(), nuevo: true);
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear('alumnos', $valores, "Alumno {$valores['matricula']}"));
        return Respuesta::creado(self::formato(self::porMatricula($c->bd, $valores['matricula'])));
    }

    private static function editar(Contexto $c): array
    {
        $alumno = self::porMatricula($c->bd, $c->peticion->parametro('matricula'));
        $e = $c->peticion->cuerpo();
        $valores = self::datos($c->bd, $e, nuevo: false);
        if ($e->tiene('activo')) {
            $valores['activo'] = $e->booleano('activo');
        }
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('alumnos', $alumno['id'], $valores, "Alumno {$alumno['matricula']}"));
        return self::formato(self::porMatricula($c->bd, $valores['matricula'] ?? $alumno['matricula']));
    }

    /** Solo si no tiene historial; si lo tiene, se desactiva. Sus inscripciones se borran con él. */
    private static function eliminar(Contexto $c): Respuesta
    {
        $alumno = self::porMatricula($c->bd, $c->peticion->parametro('matricula'));
        $c->cambios->eliminar('alumnos', $alumno['id'], "Alumno {$alumno['matricula']}");
        return Respuesta::vacia();
    }

    /**
     * Da de alta a los nuevos y actualiza a los que ya existen (por su matrícula), por
     * ejemplo desde la lista que entrega el sistema escolar al empezar el semestre. Si
     * un renglón tiene un error no se guarda ninguno, y la respuesta dice cuáles fallaron.
     */
    private static function cargarLote(Contexto $c): array
    {
        $renglones = $c->peticion->cuerpo()->lista('alumnos', 'la lista de alumnos', self::MAXIMO_POR_LOTE);
        if ($renglones === []) {
            throw ErrorHttp::datosInvalidos('La lista de alumnos está vacía', 'alumnos');
        }

        $alumnos = [];
        $errores = [];
        $vistas = [];
        foreach ($renglones as $i => $renglon) {
            try {
                if (!is_array($renglon)) {
                    throw ErrorHttp::datosInvalidos('El renglón no es un alumno');
                }
                $alumno = self::datos($c->bd, new Entrada($renglon), nuevo: true);
                if (isset($vistas[$alumno['matricula']])) {
                    throw ErrorHttp::datosInvalidos("La matrícula se repite en el renglón {$vistas[$alumno['matricula']]}", 'matricula');
                }
                $vistas[$alumno['matricula']] = $i + 1;
                $alumnos[] = $alumno;
            } catch (ErrorHttp $e) {
                $errores[] = ['renglon' => $i + 1, 'campo' => $e->extra['campo'] ?? null, 'error' => $e->getMessage()];
            }
        }
        if ($errores !== []) {
            throw new ErrorHttp(400, count($errores) === 1 ? 'Un renglón tiene errores; no se guardó ninguno' : count($errores) . ' renglones tienen errores; no se guardó ninguno', ['renglones' => $errores]);
        }

        $existentes = [];
        foreach (array_chunk(array_keys($vistas), 500) as $grupo) {
            $marcas = implode(', ', array_fill(0, count($grupo), '?'));
            foreach ($c->bd->filas("SELECT id, matricula FROM alumnos WHERE matricula IN ($marcas)", $grupo) as $fila) {
                $existentes[mb_strtolower($fila['matricula'])] = $fila['id'];
            }
        }

        $creados = 0;
        $actualizados = 0;
        foreach ($alumnos as $alumno) {
            $id = $existentes[mb_strtolower($alumno['matricula'])] ?? null;
            if ($id === null) {
                $c->bd->insertar('alumnos', $alumno);
                $creados++;
                continue;
            }
            // Lo que el archivo deja vacío no borra lo que ya se sabía. Si estaba
            // desactivado y viene en la lista, vuelve a estar activo.
            $cambios = array_filter($alumno, static fn ($valor) => $valor !== null) + ['activo' => 1];
            unset($cambios['matricula']);
            $asignaciones = implode(', ', array_map(static fn (string $columna) => "`$columna` = ?", array_keys($cambios)));
            $c->bd->ejecutar("UPDATE alumnos SET $asignaciones WHERE id = ?", [...array_values($cambios), $id]);
            $actualizados++;
        }
        // Un solo renglón en la bitácora por toda la carga, y no uno por alumno.
        $c->bitacora->registrar('carga_de_alumnos', 'alumnos', null, "$creados nuevos, $actualizados actualizados");
        return ['creados' => $creados, 'actualizados' => $actualizados];
    }

    /**
     * Cambia el semestre, el grupo o el turno de todos los alumnos activos de una
     * carrera y semestre (y, si se indica, grupo), por ejemplo al pasar de semestre.
     */
    private static function cambioMasivo(Contexto $c): array
    {
        $e = $c->peticion->cuerpo();
        $carreraId = Carreras::idPor($c->bd, $e->texto('carrera', 'la carrera', 150));
        $condiciones = ['activo', 'carrera_id = ?', 'semestre = ?'];
        $parametros = [$carreraId, $e->entero('semestre', 'el semestre', 1, 15)];
        if (($grupo = $e->texto('grupo', 'el grupo', 20, false)) !== null) {
            $condiciones[] = 'grupo = ?';
            $parametros[] = $grupo;
        }

        $cambios = [];
        if (($semestre = $e->entero('nuevoSemestre', 'el semestre nuevo', 1, 15, false)) !== null) {
            $cambios['semestre'] = $semestre;
        }
        if (($nuevoGrupo = $e->texto('nuevoGrupo', 'el grupo nuevo', 20, false)) !== null) {
            $cambios['grupo'] = $nuevoGrupo;
        }
        if (($turno = self::turno($e, 'nuevoTurno')) !== null) {
            $cambios['turno'] = $turno;
        }
        if ($cambios === []) {
            throw ErrorHttp::datosInvalidos('Indica qué cambiar: el semestre, el grupo o el turno');
        }

        $asignaciones = implode(', ', array_map(static fn (string $columna) => "`$columna` = ?", array_keys($cambios)));
        $actualizados = $c->bd->ejecutar(
            "UPDATE alumnos SET $asignaciones WHERE " . implode(' AND ', $condiciones),
            [...array_values($cambios), ...$parametros]
        );
        $c->bitacora->registrar('cambio_masivo', 'alumnos', null, "$actualizados alumnos. Campos: " . implode(', ', array_keys($cambios)));
        return ['actualizados' => $actualizados];
    }

    /** Los datos de un alumno. Si es nuevo se leen todos; al editar, solo los que vinieron. */
    private static function datos(BaseDeDatos $bd, Entrada $e, bool $nuevo): array
    {
        $valores = [];
        $leer = static fn (string $campo) => $nuevo || $e->tiene($campo);
        if ($leer('matricula')) {
            $matricula = $e->texto('matricula', 'la matrícula', 20);
            if (!preg_match('/^[A-Za-z0-9-]+$/', $matricula)) {
                throw ErrorHttp::datosInvalidos('Revisa la matrícula: solo letras, números y guiones', 'matricula');
            }
            $valores['matricula'] = $matricula;
        }
        if ($leer('nombre')) {
            $valores['nombre'] = $e->texto('nombre', 'el nombre', 100);
        }
        if ($leer('apellido')) {
            $valores['apellido'] = $e->texto('apellido', 'el apellido', 100);
        }
        if ($leer('carrera')) {
            $valores['carrera_id'] = Carreras::idPor($bd, $e->texto('carrera', 'la carrera', 150, false));
        }
        if ($leer('semestre')) {
            $valores['semestre'] = $e->entero('semestre', 'el semestre', 1, 15, false);
        }
        if ($leer('turno')) {
            $valores['turno'] = self::turno($e, 'turno');
        }
        if ($leer('grupo')) {
            $valores['grupo'] = $e->texto('grupo', 'el grupo', 20, false);
        }
        return $valores;
    }

    /** Acepta "Matutino" o "matutino"; guarda en minúsculas. */
    private static function turno(Entrada $e, string $campo): ?string
    {
        $turno = $e->texto($campo, 'el turno', 20, false);
        if ($turno === null) {
            return null;
        }
        $turno = mb_strtolower($turno);
        if (!in_array($turno, self::TURNOS, true)) {
            throw ErrorHttp::datosInvalidos('Revisa el turno: debe ser matutino o vespertino', $campo);
        }
        return $turno;
    }
}
