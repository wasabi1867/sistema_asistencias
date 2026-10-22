<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Academico;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Http\Enrutador;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Rol;
use Asistencias\Seguridad\Usuario;

/**
 * Las prácticas de cada materia. Las comunes las captura el administrador y sirven
 * a todos los docentes de la materia; cada docente captura las suyas, solo de las
 * materias que imparte. Una práctica ya trabajada en clase no se borra: se desactiva.
 */
final class Practicas
{
    private const SELECCION = "SELECT p.*, CONCAT(u.nombre, ' ', u.apellido) AS docente_nombre
        FROM practicas p LEFT JOIN usuarios u ON u.id = p.docente_id";
    private const DUPLICADOS = ['uq_practicas_numero' => 'Ya hay una práctica con ese número en la lista'];

    public static function registrar(Enrutador $r): void
    {
        $r->get('/materias/{id}/practicas', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        // Quién puede capturar cada práctica se revisa adentro (ver puedeEditar).
        $r->post('/materias/{id}/practicas', Acceso::Sesion, static fn (Contexto $c) => self::crear($c));
        $r->put('/practicas/{id}', Acceso::Sesion, static fn (Contexto $c) => self::editar($c));
        $r->delete('/practicas/{id}', Acceso::Sesion, static fn (Contexto $c) => self::eliminar($c));
    }

    public static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE p.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La práctica');
    }

    public static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'materiaId' => $fila['materia_id'],
            'numero' => $fila['numero'],
            'titulo' => $fila['titulo'],
            'descripcion' => $fila['descripcion'],
            'duracionMinutos' => $fila['duracion_minutos'],
            'fecha' => $fila['fecha'],
            'comun' => $fila['docente_id'] === null,
            'docente' => $fila['docente_id'] === null ? null : ['id' => $fila['docente_id'], 'nombre' => $fila['docente_nombre']],
            'activa' => (bool)$fila['activa'],
        ];
    }

    /**
     * Las comunes y las de un docente. El docente ve las comunes y las suyas; el
     * personal ve todas, o las de un docente con ?docente=id.
     */
    private static function listar(Contexto $c): array
    {
        $materia = Materias::porId($c->bd, $c->peticion->id());
        $q = $c->peticion->consulta();
        $usuario = $c->usuarioConSesion();
        $docente = $q->entero('docente', 'el docente', 1, null, false) ?? ($usuario->rol === Rol::Docente ? $usuario->id : null);

        $condiciones = ['p.materia_id = ?'];
        $parametros = [$materia['id']];
        if (!$q->booleano('inactivas')) {
            $condiciones[] = 'p.activa';
        }
        if ($docente !== null) {
            $condiciones[] = '(p.docente_id IS NULL OR p.docente_id = ?)';
            $parametros[] = $docente;
        }
        // Primero las comunes, luego las de cada docente, cada lista por número.
        $filas = $c->bd->filas(self::SELECCION . ' WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY p.docente_id IS NOT NULL, p.docente_id, p.numero', $parametros);
        return array_map(self::formato(...), $filas);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $materia = Materias::porId($c->bd, $c->peticion->id());
        $usuario = $c->usuarioConSesion();
        $e = $c->peticion->cuerpo();

        if ($usuario->rol === Rol::Docente) {
            $imparte = $c->bd->valor('SELECT 1 FROM cursos WHERE materia_id = ? AND docente_id = ? LIMIT 1', [$materia['id'], $usuario->id]);
            if ($imparte === null) {
                throw ErrorHttp::sinPermiso('Solo puedes capturar prácticas de las materias que impartes');
            }
            $docente = $usuario->id;
        } elseif ($usuario->esAdmin()) {
            $docente = $e->entero('docenteId', 'el docente', 1, null, false);
            if ($docente !== null && $c->bd->valor("SELECT 1 FROM usuarios WHERE id = ? AND rol = 'docente'", [$docente]) === null) {
                throw ErrorHttp::datosInvalidos('Ese docente no existe', 'docenteId');
            }
        } else {
            throw ErrorHttp::sinPermiso('Las prácticas las capturan el administrador y los docentes');
        }

        $valores = ['materia_id' => $materia['id'], 'docente_id' => $docente] + self::datos($e, nueva: true);
        // Sin número, la siguiente de su lista.
        $valores['numero'] ??= 1 + (int)$c->bd->valor(
            'SELECT MAX(numero) FROM practicas WHERE materia_id = ? AND docente_id <=> ? AND activa',
            [$materia['id'], $docente]
        );
        $id = ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear('practicas', $valores, self::descripcion($materia['nombre'], $valores)));
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function editar(Contexto $c): array
    {
        $practica = self::editable($c);
        $valores = self::datos($c->peticion->cuerpo(), nueva: false);
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('practicas', $practica['id'], $valores, self::descripcion(null, $practica)));
        return self::formato(self::porId($c->bd, $practica['id']));
    }

    private static function eliminar(Contexto $c): Respuesta
    {
        $practica = self::editable($c);
        $usada = $c->bd->valor('SELECT 1 FROM clases WHERE practica_id = ? LIMIT 1', [$practica['id']]) !== null;
        $usada
            ? $c->cambios->actualizar('practicas', $practica['id'], ['activa' => false], self::descripcion(null, $practica))
            : $c->cambios->eliminar('practicas', $practica['id'], self::descripcion(null, $practica));
        return Respuesta::vacia();
    }

    /** La práctica de la ruta, si quien pide la puede cambiar: el administrador, o el docente si es suya. */
    private static function editable(Contexto $c): array
    {
        $practica = self::porId($c->bd, $c->peticion->id());
        if (!self::puedeEditar($c->usuarioConSesion(), $practica)) {
            throw ErrorHttp::sinPermiso($practica['docente_id'] === null
                ? 'Las prácticas comunes de la materia solo las cambia el administrador'
                : 'Solo puedes cambiar tus propias prácticas');
        }
        return $practica;
    }

    private static function puedeEditar(Usuario $usuario, array $practica): bool
    {
        return $usuario->esAdmin() || ($usuario->rol === Rol::Docente && $practica['docente_id'] === $usuario->id);
    }

    private static function datos(Entrada $e, bool $nueva): array
    {
        $valores = [];
        $leer = static fn (string $campo) => $nueva || $e->tiene($campo);
        if ($leer('numero')) {
            $valores['numero'] = $e->entero('numero', 'el número', 1, 999, !$nueva);
        }
        if ($leer('titulo')) {
            $valores['titulo'] = $e->texto('titulo', 'el título', 150);
        }
        if ($leer('descripcion')) {
            $valores['descripcion'] = $e->texto('descripcion', 'la descripción', 5000, false);
        }
        if ($leer('duracionMinutos')) {
            $valores['duracion_minutos'] = $e->entero('duracionMinutos', 'la duración', 1, 1440, false);
        }
        if ($leer('fecha')) {
            $valores['fecha'] = $e->fecha('fecha', 'la fecha', false);
        }
        return $valores;
    }

    private static function descripcion(?string $materia, array $practica): string
    {
        return ($materia !== null ? "$materia, práctica " : 'Práctica ') . ($practica['numero'] ?? '');
    }
}
