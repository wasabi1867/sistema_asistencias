<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Solicitudes;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Avisos\Avisos;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Modulos\Clases\Clases;
use Asistencias\Modulos\Clases\Entradas;
use Asistencias\Modulos\Equipos\Equipos;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Usuario;

/**
 * Un alumno pide entrar a una clase en la que no está inscrito, o usar el laboratorio
 * fuera de clase. Las de una clase las resuelve el administrador; las de fuera de
 * clase, también el laboratorista (mientras el instituto no diga quién las autoriza).
 * - aprobada_temporal: entra por esa vez.
 * - aprobada_permanente: entra y queda inscrito en el curso de la clase.
 * - rechazada.
 */
final class Solicitudes
{
    private const RESOLUCIONES = ['aprobada_temporal', 'aprobada_permanente', 'rechazada'];
    private const SELECCION = "SELECT s.*, l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
            a.matricula, a.nombre, a.apellido, a.semestre, a.grupo, a.turno, ca.clave AS carrera_clave, ca.nombre AS carrera_nombre,
            e.etiqueta AS equipo, cl.curso_id, cl.cerrada_en AS clase_cerrada_en,
            COALESCE(m.nombre, cl.materia_invitado) AS materia, cu.grupo AS curso_grupo,
            COALESCE(CONCAT(ud.nombre, ' ', ud.apellido), cl.docente_invitado) AS docente,
            CONCAT(ur.nombre, ' ', ur.apellido) AS resuelta_por_nombre
        FROM solicitudes_ingreso s
        JOIN laboratorios l ON l.id = s.laboratorio_id
        JOIN alumnos a ON a.id = s.alumno_id
        LEFT JOIN carreras ca ON ca.id = a.carrera_id
        LEFT JOIN equipos e ON e.id = s.equipo_id
        LEFT JOIN clases cl ON cl.id = s.clase_id
        LEFT JOIN cursos cu ON cu.id = cl.curso_id
        LEFT JOIN materias m ON m.id = cu.materia_id
        LEFT JOIN usuarios ud ON ud.id = cu.docente_id
        LEFT JOIN usuarios ur ON ur.id = s.resuelta_por";

    public static function registrar(Enrutador $r): void
    {
        // Pública: la manda el alumno desde la página de registro.
        $r->post('/solicitudes', Acceso::Publica, static fn (Contexto $c) => self::crear($c));
        $r->get('/solicitudes', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->post('/solicitudes/{id}/resolver', Acceso::Personal, static fn (Contexto $c) => self::resolver($c));
        $r->put('/solicitudes/{id}', Acceso::Personal, static fn (Contexto $c) => self::marcarLeida($c));
    }

    private static function porId(BaseDeDatos $bd, int $id, bool $bloquear = false): array
    {
        // Se bloquea solo el renglón de la solicitud (un FOR UPDATE sobre la consulta
        // completa bloquearía también al alumno, la clase...): si dos personas la
        // resuelven a la vez, la segunda espera y la encuentra ya resuelta.
        if ($bloquear) {
            $bd->valor('SELECT id FROM solicitudes_ingreso WHERE id = ? FOR UPDATE', [$id]);
        }
        return $bd->fila(self::SELECCION . ' WHERE s.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La solicitud');
    }

    private static function formato(array $f): array
    {
        return [
            'id' => $f['id'],
            'estado' => $f['estado'],
            'leida' => (bool)$f['leida'],
            'motivo' => $f['motivo'],
            'comentario' => $f['comentario'],
            'laboratorio' => ['clave' => $f['laboratorio_clave'], 'nombre' => $f['laboratorio_nombre']],
            'alumno' => [
                'matricula' => $f['matricula'], 'nombre' => $f['nombre'], 'apellido' => $f['apellido'],
                'carrera' => $f['carrera_clave'] === null ? null : ['clave' => $f['carrera_clave'], 'nombre' => $f['carrera_nombre']],
                'semestre' => $f['semestre'], 'grupo' => $f['grupo'], 'turno' => $f['turno'],
            ],
            'equipo' => $f['equipo'],
            'clase' => $f['clase_id'] === null ? null : [
                'id' => $f['clase_id'], 'materia' => $f['materia'], 'grupo' => $f['curso_grupo'], 'docente' => $f['docente'],
                'abierta' => $f['clase_cerrada_en'] === null,
            ],
            'creadaEn' => Formato::fechaHora($f['creada_en']),
            'resueltaEn' => Formato::fechaHora($f['resuelta_en']),
            'resueltaPor' => $f['resuelta_por_nombre'],
        ];
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $laboratorio = Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30));
        $alumno = $c->bd->fila('SELECT id, nombre, apellido, activo FROM alumnos WHERE matricula = ?', [$e->texto('matricula', 'la matrícula', 20)])
            ?? throw new ErrorHttp(404, 'No encontramos esa matrícula. Revisa que esté bien escrita', ['codigo' => 'matricula_no_encontrada']);
        if (!$alumno['activo']) {
            throw ErrorHttp::sinPermiso('Esa matrícula está desactivada. Acude con el personal del laboratorio');
        }

        $clase = null;
        if (($claseId = $e->entero('claseId', 'la clase', 1, null, false)) !== null) {
            $clase = Clases::porId($c->bd, $claseId);
            if ($clase['laboratorio_id'] !== $laboratorio['id'] || $clase['cerrada_en'] !== null) {
                throw ErrorHttp::conflicto('Esa clase ya no está abierta', 'clase_cerrada');
            }
        }
        $etiqueta = $e->tiene('equipo') || $e->tiene('equipoPropio') ? Entradas::equipoElegido($e) : null;
        $equipo = $etiqueta === null ? null : (Equipos::porEtiqueta($c->bd, $laboratorio['id'], $etiqueta)
            ?? throw ErrorHttp::datosInvalidos("La computadora $etiqueta no existe en este laboratorio", 'equipo'));

        $id = ErrorDeIntegridad::explicar(
            ['uq_solicitudes_pendiente' => 'Ya enviaste una solicitud. Espera a que la revisen'],
            static fn () => $c->bd->insertar('solicitudes_ingreso', [
                'laboratorio_id' => $laboratorio['id'],
                'clase_id' => $clase['id'] ?? null,
                'alumno_id' => $alumno['id'],
                'equipo_id' => $equipo['id'] ?? null,
                'motivo' => $e->texto('motivo', 'el motivo', 1000, false),
            ])
        );
        $quien = "{$alumno['nombre']} {$alumno['apellido']}";
        Avisos::crear(
            $c->bd,
            'solicitud',
            $clase === null ? "$quien pide usar el {$laboratorio['nombre']}" : "$quien pide entrar a {$clase['curso_materia']} {$clase['curso_grupo']}",
            $e->texto('motivo', 'el motivo', 1000, false),
            'alta',
            $laboratorio['id'],
            solicitudId: $id
        );
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 's.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($estado = $q->opcion('estado', 'el estado', ['pendiente', ...self::RESOLUCIONES], false)) !== null) {
            $condiciones[] = 's.estado = ?';
            $parametros[] = $estado;
        }
        if (($clase = $q->entero('clase', 'la clase', 1, null, false)) !== null) {
            $condiciones[] = 's.clase_id = ?';
            $parametros[] = $clase;
        }
        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return array_map(self::formato(...), $c->bd->filas(self::SELECCION . "$donde ORDER BY s.creada_en DESC, s.id DESC LIMIT 200", $parametros));
    }

    private static function resolver(Contexto $c): array
    {
        $usuario = $c->usuarioConSesion();
        $solicitud = self::porId($c->bd, $c->peticion->id(), bloquear: true);
        if (!self::puedeResolver($usuario, $solicitud)) {
            throw ErrorHttp::sinPermiso('Las solicitudes para entrar a una clase las resuelve el administrador');
        }
        if ($solicitud['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('Esa solicitud ya se había resuelto', 'ya_resuelta');
        }
        $e = $c->peticion->cuerpo();
        $estado = $e->opcion('estado', 'la resolución', self::RESOLUCIONES);
        if ($estado === 'aprobada_permanente' && $solicitud['clase_id'] === null) {
            throw ErrorHttp::datosInvalidos('Una solicitud fuera de clase solo se aprueba por esa vez', 'estado');
        }

        if ($estado !== 'rechazada') {
            $claseAbierta = $solicitud['clase_id'] === null || $solicitud['clase_cerrada_en'] === null;
            if ($estado === 'aprobada_permanente') {
                $c->bd->ejecutar(
                    'INSERT IGNORE INTO inscripciones (curso_id, alumno_id, solicitud_id) VALUES (?, ?, ?)',
                    [$solicitud['curso_id'], $solicitud['alumno_id'], $solicitud['id']]
                );
            } elseif (!$claseAbierta) {
                throw ErrorHttp::conflicto('La clase ya se cerró: ya no se puede aprobar por esa vez', 'clase_cerrada');
            }
            // Si la clase sigue abierta (o es fuera de clase), entra ya. Si la computadora
            // que eligió se ocupó mientras esperaba, entra con su propio equipo.
            if ($claseAbierta) {
                Entradas::registrar(
                    $c->bd,
                    $solicitud['laboratorio_id'],
                    $solicitud['clase_id'],
                    ['tipo' => 'alumno', 'alumno_id' => $solicitud['alumno_id']],
                    $solicitud['equipo'],
                    registradaPor: $usuario->id,
                    solicitudId: $solicitud['id'],
                    siNoEstaLibreUsaElPropio: true,
                );
            }
        }

        $c->bd->ejecutar(
            'UPDATE solicitudes_ingreso SET estado = ?, comentario = ?, resuelta_por = ?, resuelta_en = NOW(), leida = TRUE WHERE id = ?',
            [$estado, $e->texto('comentario', 'el comentario', 255, false), $usuario->id, $solicitud['id']]
        );
        $c->bd->ejecutar('UPDATE avisos SET leido = TRUE WHERE solicitud_id = ?', [$solicitud['id']]);
        $c->bitacora->registrar('resolver_solicitud', 'solicitudes_ingreso', $solicitud['id'], "Alumno {$solicitud['matricula']}: $estado", $solicitud['laboratorio_id']);
        return self::formato(self::porId($c->bd, $solicitud['id']));
    }

    private static function marcarLeida(Contexto $c): Respuesta
    {
        $solicitud = self::porId($c->bd, $c->peticion->id());
        $c->bd->ejecutar('UPDATE solicitudes_ingreso SET leida = ? WHERE id = ?', [$c->peticion->cuerpo()->booleano('leida', true), $solicitud['id']]);
        return Respuesta::vacia();
    }

    private static function puedeResolver(Usuario $usuario, array $solicitud): bool
    {
        return $usuario->esAdmin() || ($solicitud['clase_id'] === null && $usuario->esPersonal());
    }
}
