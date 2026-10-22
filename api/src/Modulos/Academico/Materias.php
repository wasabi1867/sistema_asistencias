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
use Asistencias\Modulos\Catalogos\Carreras;
use Asistencias\Seguridad\Acceso;

/** El catálogo de materias, sin docente, grupo ni periodo: eso es el curso. */
final class Materias
{
    private const SELECCION = 'SELECT m.*, c.clave AS carrera_clave, c.nombre AS carrera_nombre
        FROM materias m LEFT JOIN carreras c ON c.id = m.carrera_id';
    private const DUPLICADOS = [
        'uq_materias_clave' => 'Ya hay una materia con esa clave',
        'uq_materias_nombre' => 'Esa carrera ya tiene una materia con ese nombre',
    ];

    public static function registrar(Enrutador $r): void
    {
        $r->get('/materias', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->get('/materias/{id}', Acceso::Sesion, static fn (Contexto $c) => self::formato(self::porId($c->bd, $c->peticion->id())));
        $r->post('/materias', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->put('/materias/{id}', Acceso::Admin, static fn (Contexto $c) => self::editar($c));
        $r->delete('/materias/{id}', Acceso::Admin, static fn (Contexto $c) => self::eliminar($c));
    }

    public static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE m.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La materia');
    }

    public static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'clave' => $fila['clave'],
            'nombre' => $fila['nombre'],
            'carrera' => $fila['carrera_clave'] === null ? null : ['clave' => $fila['carrera_clave'], 'nombre' => $fila['carrera_nombre']],
            'semestre' => $fila['semestre'],
            'activa' => (bool)$fila['activa'],
        ];
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = ['m.activa = ?'];
        $parametros = [$q->booleano('activa', true)];
        if (($texto = $q->texto('q', 'la búsqueda', 100, false)) !== null) {
            $condiciones[] = '(m.nombre LIKE ? OR m.clave LIKE ?)';
            array_push($parametros, BaseDeDatos::patron($texto), BaseDeDatos::patron($texto));
        }
        if (($carrera = $q->texto('carrera', 'la carrera', 150, false)) !== null) {
            $condiciones[] = 'm.carrera_id = ?';
            $parametros[] = Carreras::idPor($c->bd, $carrera);
        }
        if (($semestre = $q->entero('semestre', 'el semestre', 1, 15, false)) !== null) {
            $condiciones[] = 'm.semestre = ?';
            $parametros[] = $semestre;
        }
        $filas = $c->bd->filas(self::SELECCION . ' WHERE ' . implode(' AND ', $condiciones) . ' ORDER BY m.nombre', $parametros);
        return array_map(self::formato(...), $filas);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $valores = self::datos($c->bd, $c->peticion->cuerpo(), nueva: true);
        $id = ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear('materias', $valores, $valores['nombre']));
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function editar(Contexto $c): array
    {
        $materia = self::porId($c->bd, $c->peticion->id());
        $e = $c->peticion->cuerpo();
        $valores = self::datos($c->bd, $e, nueva: false);
        if ($e->tiene('activa')) {
            $valores['activa'] = $e->booleano('activa');
        }
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('materias', $materia['id'], $valores, $materia['nombre']));
        return self::formato(self::porId($c->bd, $materia['id']));
    }

    /** Solo si no tiene cursos ni prácticas; si no, se desactiva. */
    private static function eliminar(Contexto $c): Respuesta
    {
        $materia = self::porId($c->bd, $c->peticion->id());
        $c->cambios->eliminar('materias', $materia['id'], $materia['nombre']);
        return Respuesta::vacia();
    }

    private static function datos(BaseDeDatos $bd, Entrada $e, bool $nueva): array
    {
        $valores = [];
        $leer = static fn (string $campo) => $nueva || $e->tiene($campo);
        if ($leer('clave')) {
            $clave = $e->texto('clave', 'la clave', 30, false);
            $valores['clave'] = $clave === null ? null : mb_strtoupper($clave);
        }
        if ($leer('nombre')) {
            $valores['nombre'] = $e->texto('nombre', 'el nombre', 150);
        }
        if ($leer('carrera')) {
            $valores['carrera_id'] = Carreras::idPor($bd, $e->texto('carrera', 'la carrera', 150, false));
        }
        if ($leer('semestre')) {
            $valores['semestre'] = $e->entero('semestre', 'el semestre', 1, 15, false);
        }
        return $valores;
    }
}
