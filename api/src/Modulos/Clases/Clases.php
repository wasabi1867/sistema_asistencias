<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Clases;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Academico\Cursos;
use Asistencias\Modulos\Avisos\Avisos;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Rol;
use Asistencias\Seguridad\Usuario;

/**
 * Las clases: cada sesión en un laboratorio. Mientras está abierta se puede registrar
 * la entrada; al cerrarla se registra la salida de todos.
 * - Regular: la abre el docente para uno de sus cursos (o el administrador por él);
 *   solo entran los inscritos.
 * - De maestro invitado: la abre el personal; entra cualquiera, incluso gente de fuera.
 */
final class Clases
{
    private const SELECCION = "SELECT cl.*, l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
            cu.grupo AS curso_grupo, cu.docente_id AS curso_docente_id, m.nombre AS curso_materia,
            CONCAT(ud.nombre, ' ', ud.apellido) AS curso_docente,
            p.numero AS practica_numero, p.titulo AS practica_titulo,
            CONCAT(ua.nombre, ' ', ua.apellido) AS abierta_por_nombre,
            CONCAT(uc.nombre, ' ', uc.apellido) AS cerrada_por_nombre,
            (SELECT COUNT(*) FROM asistencias s WHERE s.clase_id = cl.id) AS asistentes
        FROM clases cl
        JOIN laboratorios l ON l.id = cl.laboratorio_id
        LEFT JOIN cursos cu ON cu.id = cl.curso_id
        LEFT JOIN materias m ON m.id = cu.materia_id
        LEFT JOIN usuarios ud ON ud.id = cu.docente_id
        LEFT JOIN practicas p ON p.id = cl.practica_id
        JOIN usuarios ua ON ua.id = cl.abierta_por
        LEFT JOIN usuarios uc ON uc.id = cl.cerrada_por";

    public static function registrar(Enrutador $r): void
    {
        // Pública: la página de registro necesita saber qué clase hay en el laboratorio.
        $r->get('/clases/abiertas', Acceso::Publica, static fn (Contexto $c) => self::abiertas($c));
        $r->get('/clases', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->get('/clases/{id}', Acceso::Sesion, static fn (Contexto $c) => self::una($c));
        // Quién puede abrir, cerrar o registrar a mano depende de la clase: se revisa adentro.
        $r->post('/clases', Acceso::Sesion, static fn (Contexto $c) => self::abrir($c));
        $r->post('/clases/{id}/cerrar', Acceso::Sesion, static fn (Contexto $c) => self::cerrar($c));
        $r->post('/clases/{id}/asistencias', Acceso::Sesion, static fn (Contexto $c) => self::registrarAMano($c));
    }

    public static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE cl.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La clase');
    }

    /**
     * Quién lleva la clase (la cierra, registra a mano, corrige): el administrador, el
     * laboratorista (para liberar el laboratorio si el docente se fue sin cerrarla) y,
     * en una clase regular, el docente del curso.
     */
    public static function puedeLlevar(Usuario $usuario, array $clase): bool
    {
        return $usuario->esPersonal()
            || ($usuario->rol === Rol::Docente && $clase['tipo'] === 'regular' && $clase['curso_docente_id'] === $usuario->id);
    }

    /** @param bool $conCodigo el código de la clase solo lo ve quien la lleva */
    public static function formato(array $fila, bool $conCodigo = false): array
    {
        $regular = $fila['tipo'] === 'regular';
        $datos = [
            'id' => $fila['id'],
            'tipo' => $fila['tipo'],
            'laboratorio' => ['clave' => $fila['laboratorio_clave'], 'nombre' => $fila['laboratorio_nombre']],
            'cursoId' => $fila['curso_id'],
            'materia' => $regular ? $fila['curso_materia'] : $fila['materia_invitado'],
            'practica' => match (true) {
                $fila['practica_id'] !== null => ['id' => $fila['practica_id'], 'numero' => $fila['practica_numero'], 'titulo' => $fila['practica_titulo']],
                $fila['practica_invitado'] !== null => ['id' => null, 'numero' => null, 'titulo' => $fila['practica_invitado']],
                default => null,
            },
            'docente' => $regular ? $fila['curso_docente'] : $fila['docente_invitado'],
            'docenteId' => $regular ? $fila['curso_docente_id'] : null,
            'grupo' => $regular ? $fila['curso_grupo'] : $fila['grupo_invitado'],
            'departamento' => $fila['departamento'],
            'abierta' => $fila['cerrada_en'] === null,
            'abiertaEn' => Formato::fechaHora($fila['abierta_en']),
            'cerradaEn' => Formato::fechaHora($fila['cerrada_en']),
            'cierre' => $fila['cierre'],
            'abiertaPor' => $fila['abierta_por_nombre'],
            'cerradaPor' => $fila['cerrada_por_nombre'],
            'asistentes' => (int)$fila['asistentes'],
        ];
        return $conCodigo ? $datos + ['codigo' => $fila['codigo']] : $datos;
    }

    /** Lo que ve el alumno sin sesión: qué clase hay y si pide código, pero no el código. */
    private static function abiertas(Contexto $c): array
    {
        $laboratorio = Laboratorios::porClave($c->bd, $c->peticion->consulta()->texto('laboratorio', 'el laboratorio', 30));
        $filas = $c->bd->filas(self::SELECCION . ' WHERE cl.laboratorio_id = ? AND cl.cerrada_en IS NULL ORDER BY cl.abierta_en', [$laboratorio['id']]);
        return array_map(static function (array $fila) use ($c) {
            $clase = self::formato($fila);
            return [
                'id' => $clase['id'],
                'tipo' => $clase['tipo'],
                'materia' => $clase['materia'],
                'practica' => $clase['practica']['titulo'] ?? null,
                'docente' => $clase['docente'],
                'grupo' => $clase['grupo'],
                'abiertaEn' => $clase['abiertaEn'],
                'pideCodigo' => $c->config->exigirCodigoDeClase,
            ];
        }, $filas);
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'cl.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($estado = $q->opcion('estado', 'el estado', ['abiertas', 'cerradas'], false)) !== null) {
            $condiciones[] = $estado === 'abiertas' ? 'cl.cerrada_en IS NULL' : 'cl.cerrada_en IS NOT NULL';
        }
        if (($tipo = $q->opcion('tipo', 'el tipo', ['regular', 'invitado'], false)) !== null) {
            $condiciones[] = 'cl.tipo = ?';
            $parametros[] = $tipo;
        }
        if (($desde = $q->fecha('desde', 'la fecha de inicio', false)) !== null) {
            $condiciones[] = 'cl.abierta_en >= ?';
            $parametros[] = $desde;
        }
        if (($hasta = $q->fecha('hasta', 'la fecha de fin', false)) !== null) {
            $condiciones[] = 'cl.abierta_en < ? + INTERVAL 1 DAY';
            $parametros[] = $hasta;
        }
        if ($q->crudo('docente') === 'yo') {
            $condiciones[] = 'cu.docente_id = ?';
            $parametros[] = $c->usuarioConSesion()->id;
        } elseif (($docente = $q->entero('docente', 'el docente', 1, null, false)) !== null) {
            $condiciones[] = 'cu.docente_id = ?';
            $parametros[] = $docente;
        }
        if (($curso = $q->entero('curso', 'el curso', 1, null, false)) !== null) {
            $condiciones[] = 'cl.curso_id = ?';
            $parametros[] = $curso;
        }

        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        $pagina = $q->paginacion(100, 1000);
        $total = (int)$c->bd->valor("SELECT COUNT(*) FROM clases cl LEFT JOIN cursos cu ON cu.id = cl.curso_id$donde", $parametros);
        $filas = $c->bd->filas(self::SELECCION . "$donde ORDER BY cl.abierta_en DESC, cl.id DESC LIMIT {$pagina['porPagina']} OFFSET {$pagina['desde']}", $parametros);
        $clases = array_map(static fn (array $f) => self::formato($f), $filas);

        // Para los reportes: quiénes asistieron a cada clase, en una sola consulta.
        if ($q->booleano('conAsistentes') && $clases !== []) {
            $ids = array_column($clases, 'id');
            $marcas = implode(', ', array_fill(0, count($ids), '?'));
            $porClase = [];
            foreach ($c->bd->filas(Asistencias::SELECCION . " WHERE s.clase_id IN ($marcas) ORDER BY s.hora_entrada", $ids) as $fila) {
                $porClase[$fila['clase_id']][] = Asistencias::formato($fila);
            }
            foreach ($clases as &$clase) {
                $clase['personas'] = $porClase[$clase['id']] ?? [];
            }
            unset($clase);
        }
        return ['clases' => $clases, 'total' => $total, 'pagina' => $pagina['pagina'], 'porPagina' => $pagina['porPagina']];
    }

    /** Una clase con quiénes entraron y, si es regular, la lista con las faltas. */
    private static function una(Contexto $c): array
    {
        $clase = self::porId($c->bd, $c->peticion->id());
        $asistencias = $c->bd->filas(Asistencias::SELECCION . ' WHERE s.clase_id = ? ORDER BY s.hora_entrada', [$clase['id']]);
        $datos = self::formato($clase, self::puedeLlevar($c->usuarioConSesion(), $clase))
            + ['personas' => array_map(Asistencias::formato(...), $asistencias)];

        if ($clase['tipo'] === 'regular') {
            $inscritos = $c->bd->filas(
                'SELECT a.id, a.matricula, a.nombre, a.apellido FROM inscripciones i JOIN alumnos a ON a.id = i.alumno_id WHERE i.curso_id = ?',
                [$clase['curso_id']]
            );
            $asistentes = array_map(static fn (array $f) => [
                'asistencia_id' => $f['id'], 'alumno_id' => $f['alumno_id'], 'matricula' => $f['matricula'], 'nombre' => $f['nombre'],
                'apellido' => $f['apellido'], 'equipo' => $f['equipo'], 'hora_entrada' => $f['hora_entrada'], 'hora_salida' => $f['hora_salida'],
            ], array_filter($asistencias, static fn (array $f) => $f['alumno_id'] !== null));
            $datos['lista'] = ListaDeAsistencia::deUnaClase($inscritos, array_values($asistentes));
        }
        return $datos;
    }

    private static function abrir(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $usuario = $c->usuarioConSesion();
        $tipo = $e->opcion('tipo', 'el tipo de clase', ['regular', 'invitado'], false) ?? 'regular';

        if ($tipo === 'regular') {
            $curso = Cursos::porId($c->bd, $e->entero('cursoId', 'el curso', 1));
            if (!$usuario->esAdmin() && !Cursos::esSuDocente($usuario, $curso)) {
                throw ErrorHttp::sinPermiso('Solo puedes abrir clases de tus cursos');
            }
            $laboratorio = $e->tiene('laboratorio') || $curso['laboratorio_clave'] === null
                ? Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30))
                : Laboratorios::porClave($c->bd, $curso['laboratorio_clave']);
            self::revisarQueSePuedaAbrir($c->bd, $curso);
            $valores = [
                'curso_id' => $curso['id'],
                'practica_id' => self::practicaDelCurso($c->bd, $curso, $e->entero('practicaId', 'la práctica', 1, null, false)),
            ];
            $descripcion = "{$curso['materia_nombre']} {$curso['grupo']}";
        } else {
            if (!$usuario->esPersonal()) {
                throw ErrorHttp::sinPermiso('Las clases de maestro invitado las abre el personal del laboratorio');
            }
            $laboratorio = Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30));
            $valores = [
                'materia_invitado' => $e->texto('materia', 'la materia', 150),
                'practica_invitado' => $e->texto('practica', 'la práctica', 150, false),
                'docente_invitado' => $e->texto('docente', 'el docente', 200, false),
                'departamento' => $e->texto('departamento', 'el departamento', 150, false),
                'grupo_invitado' => $e->texto('grupo', 'el grupo', 20, false),
            ];
            $descripcion = "Maestro invitado: {$valores['materia_invitado']}";
        }

        try {
            $id = $c->bd->insertar('clases', $valores + [
                'laboratorio_id' => $laboratorio['id'],
                'tipo' => $tipo,
                // El código que se muestra en el aula, por si el registro lo pide.
                'codigo' => str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                'abierta_por' => $usuario->id,
            ]);
        } catch (ErrorDeIntegridad $e) {
            // Dos clases abiertas a la vez: la base lo impide aunque las abran al mismo tiempo.
            throw match ($e->restriccion) {
                'uq_clases_laboratorio_abierto' => ErrorHttp::conflicto("Ya hay una clase abierta en el {$laboratorio['nombre']}", 'laboratorio_ocupado'),
                'uq_clases_curso_abierto' => ErrorHttp::conflicto('Ese curso ya tiene una clase abierta', 'curso_abierto'),
                default => $e,
            };
        }
        $c->bitacora->registrar('abrir_clase', 'clases', $id, $descripcion, $laboratorio['id']);
        if ($tipo === 'invitado') {
            Avisos::crear($c->bd, 'clase', "Clase de maestro invitado en el {$laboratorio['nombre']}",
                trim($valores['materia_invitado'] . ($valores['docente_invitado'] !== null ? " con {$valores['docente_invitado']}" : '')),
                'media', $laboratorio['id'], claseId: $id);
        }
        return Respuesta::creado(self::formato(self::porId($c->bd, $id), conCodigo: true));
    }

    /** Cierra la clase y registra la salida de todos los que seguían dentro. */
    private static function cerrar(Contexto $c): array
    {
        $usuario = $c->usuarioConSesion();
        $clase = self::porId($c->bd, $c->peticion->id());
        if (!self::puedeLlevar($usuario, $clase)) {
            throw ErrorHttp::sinPermiso('Solo quien lleva la clase puede cerrarla');
        }
        // Bloquear la clase: un registro de entrada que llegue en este momento espera a
        // que termine el cierre y ya la encuentra cerrada.
        if ($c->bd->valor('SELECT cerrada_en FROM clases WHERE id = ? FOR UPDATE', [$clase['id']]) !== null) {
            throw ErrorHttp::conflicto('La clase ya estaba cerrada', 'clase_cerrada');
        }
        $salidas = $c->bd->ejecutar('UPDATE asistencias SET hora_salida = NOW() WHERE clase_id = ? AND hora_salida IS NULL', [$clase['id']]);
        $cierre = match (true) {
            // El navegador del docente la cierra solo al cerrar la pestaña.
            $c->peticion->cuerpo()->booleano('automatico') => 'automatico',
            $usuario->id === $clase['abierta_por'] => 'normal',
            default => 'externo',
        };
        $c->bd->ejecutar('UPDATE clases SET cerrada_en = NOW(), cerrada_por = ?, cierre = ? WHERE id = ?', [$usuario->id, $cierre, $clase['id']]);
        $c->bitacora->registrar('cerrar_clase', 'clases', $clase['id'], "Cierre $cierre, $salidas salidas registradas", $clase['laboratorio_id']);
        return self::formato(self::porId($c->bd, $clase['id']), conCodigo: true);
    }

    /**
     * Quien lleva la clase registra a mano a alguien: un alumno inscrito (en una clase
     * de invitado, cualquier alumno) o, en una clase de invitado, a alguien de fuera.
     */
    private static function registrarAMano(Contexto $c): Respuesta
    {
        $usuario = $c->usuarioConSesion();
        $clase = self::porId($c->bd, $c->peticion->id());
        if (!self::puedeLlevar($usuario, $clase)) {
            throw ErrorHttp::sinPermiso('Solo quien lleva la clase puede registrar entradas a mano');
        }
        $e = $c->peticion->cuerpo();
        $etiqueta = Entradas::equipoElegido($e);
        if ($e->tiene('matricula')) {
            $persona = Asistencias::alumnoQueEntra($c->bd, $e->texto('matricula', 'la matrícula', 20));
            if ($clase['tipo'] === 'regular' && !self::estaInscrito($c->bd, $clase['curso_id'], $persona['alumno_id'])) {
                throw ErrorHttp::conflicto('El alumno no está inscrito en este curso. Puede pedir ingreso con una solicitud', 'no_inscrito');
            }
        } elseif ($clase['tipo'] === 'invitado') {
            $persona = ['tipo' => 'invitado', 'nombre' => $e->texto('nombre', 'el nombre', 100), 'apellido' => $e->texto('apellido', 'el apellido', 100)];
        } else {
            throw ErrorHttp::datosInvalidos('Falta la matrícula', 'matricula');
        }
        $entrada = Entradas::registrar($c->bd, $clase['laboratorio_id'], $clase['id'], $persona, $etiqueta, registradaPor: $usuario->id);
        return Respuesta::creado(Asistencias::formato(Asistencias::porId($c->bd, $entrada['id'])));
    }

    public static function estaInscrito(BaseDeDatos $bd, ?int $cursoId, int $alumnoId): bool
    {
        return $cursoId !== null && $bd->valor('SELECT 1 FROM inscripciones WHERE curso_id = ? AND alumno_id = ?', [$cursoId, $alumnoId]) !== null;
    }

    /**
     * Un curso de un periodo que ya terminó no se abre, y un docente no puede tener
     * dos clases abiertas a la vez (no puede estar en dos laboratorios).
     */
    private static function revisarQueSePuedaAbrir(BaseDeDatos $bd, array $curso): void
    {
        if ($bd->valor('SELECT fecha_fin < CURDATE() FROM periodos WHERE id = ?', [$curso['periodo_id']])) {
            throw ErrorHttp::conflicto("Ese curso es del periodo {$curso['periodo_nombre']}, que ya terminó", 'periodo_terminado');
        }
        if ($curso['docente_id'] !== null) {
            $otra = $bd->fila(
                'SELECT l.nombre AS laboratorio FROM clases cl JOIN cursos cu ON cu.id = cl.curso_id JOIN laboratorios l ON l.id = cl.laboratorio_id
                 WHERE cl.cerrada_en IS NULL AND cu.docente_id = ? AND cu.id <> ?',
                [$curso['docente_id'], $curso['id']]
            );
            if ($otra !== null) {
                throw ErrorHttp::conflicto("El docente ya tiene una clase abierta en el {$otra['laboratorio']}. Hay que cerrarla antes de abrir otra", 'docente_con_clase_abierta');
            }
        }
    }

    /** La práctica tiene que ser de la materia del curso: común o del docente del curso. */
    private static function practicaDelCurso(BaseDeDatos $bd, array $curso, ?int $practicaId): ?int
    {
        if ($practicaId === null) {
            return null;
        }
        $valida = $bd->valor(
            'SELECT 1 FROM practicas WHERE id = ? AND materia_id = ? AND activa AND (docente_id IS NULL OR docente_id <=> ?)',
            [$practicaId, $curso['materia_id'], $curso['docente_id']]
        );
        if ($valida === null) {
            throw ErrorHttp::datosInvalidos('Esa práctica no es de la materia de este curso', 'practicaId');
        }
        return $practicaId;
    }
}
