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
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Modulos\Clases\Clases;
use Asistencias\Modulos\Personas\Alumnos;
use Asistencias\Seguridad\Acceso;

/**
 * El alumno que llega después de la carga, y por eso no está dado de alta, pide su alta
 * desde la pantalla de registro. La resuelve el administrador: el jefe de carrera pidió
 * aprobarlas él (pregunta 3, 4 de noviembre de 2026), y en el sistema eso es el rol de
 * administrador (D22). Al aprobarla puede corregir o completar sus datos; se crea el
 * alumno y, si la pidió desde una clase, queda inscrito en ese curso.
 */
final class SolicitudesDeAlta
{
    // Un salón entero puede salir a internet por la misma red, y puede que todo el
    // instituto: el límite es por laboratorio, deja pasar a un grupo y frena a quien
    // mande solicitudes sin parar.
    private const MAXIMO_POR_RED = 40;
    private const MINUTOS_POR_RED = 60;
    private const SELECCION = "SELECT s.*, l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
            cl.curso_id, cl.cerrada_en AS clase_cerrada_en, m.nombre AS materia, cu.grupo AS curso_grupo,
            CONCAT(ud.nombre, ' ', ud.apellido) AS docente,
            CONCAT(ur.nombre, ' ', ur.apellido) AS resuelta_por_nombre
        FROM solicitudes_de_alta s
        JOIN laboratorios l ON l.id = s.laboratorio_id
        LEFT JOIN clases cl ON cl.id = s.clase_id
        LEFT JOIN cursos cu ON cu.id = cl.curso_id
        LEFT JOIN materias m ON m.id = cu.materia_id
        LEFT JOIN usuarios ud ON ud.id = cu.docente_id
        LEFT JOIN usuarios ur ON ur.id = s.resuelta_por";

    public static function registrar(Enrutador $r): void
    {
        // Pública: la manda el alumno desde la página de registro.
        $r->post('/solicitudes-de-alta', Acceso::Publica, static fn (Contexto $c) => self::crear($c));
        $r->get('/solicitudes-de-alta', Acceso::Admin, static fn (Contexto $c) => self::listar($c));
        $r->post('/solicitudes-de-alta/{id}/resolver', Acceso::Admin, static fn (Contexto $c) => self::resolver($c));
    }

    private static function porId(BaseDeDatos $bd, int $id, bool $bloquear = false): array
    {
        // Si dos administradores la resuelven a la vez, el segundo espera y la encuentra resuelta.
        if ($bloquear) {
            $bd->valor('SELECT id FROM solicitudes_de_alta WHERE id = ? FOR UPDATE', [$id]);
        }
        return $bd->fila(self::SELECCION . ' WHERE s.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La solicitud');
    }

    private static function formato(array $f): array
    {
        return [
            'id' => $f['id'],
            'estado' => $f['estado'],
            'matricula' => $f['matricula'],
            'nombre' => $f['nombre'],
            'apellido' => $f['apellido'],
            'semestre' => $f['semestre'],
            'grupo' => $f['grupo'],
            'laboratorio' => ['clave' => $f['laboratorio_clave'], 'nombre' => $f['laboratorio_nombre']],
            'clase' => $f['clase_id'] === null ? null : [
                'id' => $f['clase_id'], 'materia' => $f['materia'], 'grupo' => $f['curso_grupo'], 'docente' => $f['docente'],
                'abierta' => $f['clase_cerrada_en'] === null,
            ],
            'dadoDeAlta' => $f['alumno_id'] !== null,
            'comentario' => $f['comentario'],
            'creadaEn' => Formato::fechaHora($f['creada_en']),
            'resueltaEn' => Formato::fechaHora($f['resuelta_en']),
            'resueltaPor' => $f['resuelta_por_nombre'],
        ];
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $laboratorio = Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30));
        $recientes = (int)$c->bd->valor(
            'SELECT COUNT(*) FROM solicitudes_de_alta WHERE ip = ? AND laboratorio_id = ? AND creada_en > NOW() - INTERVAL ' . self::MINUTOS_POR_RED . ' MINUTE',
            [$c->peticion->ip, $laboratorio['id']]
        );
        if ($recientes >= self::MAXIMO_POR_RED) {
            throw new ErrorHttp(429, 'Llegaron demasiadas solicitudes desde esta red. Espera un rato o acude con el personal del laboratorio');
        }
        $matricula = mb_strtoupper($e->texto('matricula', 'el número de control', 20));
        if (!preg_match('/^[A-Z0-9-]+$/', $matricula)) {
            throw ErrorHttp::datosInvalidos('Revisa tu número de control: solo letras, números y guiones', 'matricula');
        }
        if ($c->bd->valor('SELECT 1 FROM alumnos WHERE matricula = ?', [$matricula]) !== null) {
            throw ErrorHttp::conflicto('Ese número de control ya está dado de alta. Registra tu entrada con él', 'ya_dado_de_alta');
        }

        // Solo una clase de un curso sirve para inscribirlo; la de un maestro invitado no tiene curso.
        $clase = null;
        if (($claseId = $e->entero('claseId', 'la clase', 1, null, false)) !== null) {
            $clase = Clases::porId($c->bd, $claseId);
            if ($clase['laboratorio_id'] !== $laboratorio['id'] || $clase['cerrada_en'] !== null) {
                throw ErrorHttp::conflicto('Esa clase ya no está abierta', 'clase_cerrada');
            }
        }

        $id = ErrorDeIntegridad::explicar(
            ['uq_solicitudes_de_alta_pendiente' => 'Ya pediste tu alta. Espera a que el administrador la revise'],
            static fn () => $c->bd->insertar('solicitudes_de_alta', [
                'matricula' => $matricula,
                'nombre' => $e->texto('nombre', 'tu nombre', 100),
                'apellido' => $e->texto('apellido', 'tus apellidos', 100),
                'semestre' => $e->entero('semestre', 'el semestre', 1, 15, false),
                'grupo' => $e->texto('grupo', 'el grupo', 20, false),
                'laboratorio_id' => $laboratorio['id'],
                'clase_id' => $clase !== null && $clase['curso_id'] !== null ? $clase['id'] : null,
                'ip' => $c->peticion->ip,
            ])
        );
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function listar(Contexto $c): array
    {
        $estado = $c->peticion->consulta()->opcion('estado', 'el estado', ['pendiente', 'aprobada', 'rechazada'], false);
        return array_map(self::formato(...), $c->bd->filas(
            self::SELECCION . ($estado === null ? '' : ' WHERE s.estado = ?') . ' ORDER BY s.creada_en DESC, s.id DESC LIMIT 200',
            $estado === null ? [] : [$estado]
        ));
    }

    private static function resolver(Contexto $c): array
    {
        $solicitud = self::porId($c->bd, $c->peticion->id(), bloquear: true);
        if ($solicitud['estado'] !== 'pendiente') {
            throw ErrorHttp::conflicto('Esa solicitud ya se había resuelto', 'ya_resuelta');
        }
        $e = $c->peticion->cuerpo();
        $estado = $e->opcion('estado', 'la resolución', ['aprobada', 'rechazada']);

        $alumnoId = null;
        if ($estado === 'aprobada') {
            $existente = $c->bd->fila('SELECT id, activo FROM alumnos WHERE matricula = ?', [$solicitud['matricula']]);
            if ($existente !== null && !$existente['activo']) {
                throw ErrorHttp::conflicto("El alumno {$solicitud['matricula']} ya existe y está desactivado. Actívalo en Alumnos y luego resuelve la solicitud", 'alumno_inactivo');
            }
            // Si mientras esperaba ya se le dio de alta (por ejemplo, con la lista de la
            // materia), se queda como está: la solicitud no cambia sus datos.
            $alumnoId = $existente['id'] ?? $c->cambios->crear('alumnos', [
                'matricula' => $solicitud['matricula'],
                'nombre' => $solicitud['nombre'],
                'apellido' => $solicitud['apellido'],
                'semestre' => $solicitud['semestre'],
                'grupo' => $solicitud['grupo'],
                // Lo que el administrador corrigió o completó al revisarla.
                ...array_diff_key(Alumnos::datos($c->bd, $e, nuevo: false), ['matricula' => true]),
            ], "Alumno {$solicitud['matricula']}");
            if ($solicitud['curso_id'] !== null && $e->booleano('inscribir', true)) {
                $c->bd->ejecutar('INSERT IGNORE INTO inscripciones (curso_id, alumno_id) VALUES (?, ?)', [$solicitud['curso_id'], $alumnoId]);
            }
        }

        $c->bd->ejecutar(
            'UPDATE solicitudes_de_alta SET estado = ?, alumno_id = ?, comentario = ?, resuelta_por = ?, resuelta_en = NOW() WHERE id = ?',
            [$estado, $alumnoId, $e->texto('comentario', 'el comentario', 255, false), $c->usuarioConSesion()->id, $solicitud['id']]
        );
        $c->bitacora->registrar('resolver_solicitud_de_alta', 'solicitudes_de_alta', $solicitud['id'], "Matrícula {$solicitud['matricula']}: $estado", $solicitud['laboratorio_id']);
        return self::formato(self::porId($c->bd, $solicitud['id']));
    }
}
