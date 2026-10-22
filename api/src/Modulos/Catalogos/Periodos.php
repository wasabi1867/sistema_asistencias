<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Catalogos;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Acceso;

/** Los semestres escolares. El actual es el que contiene la fecha de hoy. */
final class Periodos
{
    private const SELECCION = 'SELECT p.*, CURDATE() BETWEEN p.fecha_inicio AND p.fecha_fin AS actual FROM periodos p';

    public static function registrar(Enrutador $r): void
    {
        $r->get('/periodos', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->get('/periodos/actual', Acceso::Sesion, static fn (Contexto $c) => self::actual($c));
        $r->post('/periodos', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->put('/periodos/{id}', Acceso::Admin, static fn (Contexto $c) => self::editar($c));
        $r->delete('/periodos/{id}', Acceso::Admin, static fn (Contexto $c) => self::eliminar($c));
    }

    /** El id del periodo que contiene hoy, o null si no hay ninguno. */
    public static function idDelActual(BaseDeDatos $bd): ?int
    {
        $id = $bd->valor('SELECT id FROM periodos WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin ORDER BY fecha_inicio DESC LIMIT 1');
        return $id === null ? null : (int)$id;
    }

    private static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'nombre' => $fila['nombre'],
            'fechaInicio' => $fila['fecha_inicio'],
            'fechaFin' => $fila['fecha_fin'],
            'actual' => (bool)$fila['actual'],
        ];
    }

    private static function uno(Contexto $c, int $id): array
    {
        return self::formato($c->bd->fila(self::SELECCION . ' WHERE p.id = ?', [$id]) ?? throw ErrorHttp::noExiste('El periodo'));
    }

    private static function listar(Contexto $c): array
    {
        return array_map(self::formato(...), $c->bd->filas(self::SELECCION . ' ORDER BY p.fecha_inicio DESC'));
    }

    private static function actual(Contexto $c): array
    {
        $id = self::idDelActual($c->bd) ?? throw new ErrorHttp(404, 'No hay un periodo escolar que incluya la fecha de hoy');
        return self::uno($c, $id);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $valores = self::leer($c, null);
        $id = $c->cambios->crear('periodos', $valores, $valores['nombre']);
        return Respuesta::creado(self::uno($c, $id));
    }

    private static function editar(Contexto $c): array
    {
        $id = $c->peticion->id();
        self::uno($c, $id);
        $c->cambios->actualizar('periodos', $id, self::leer($c, $id));
        return self::uno($c, $id);
    }

    private static function eliminar(Contexto $c): Respuesta
    {
        $c->cambios->eliminar('periodos', $c->peticion->id());
        return Respuesta::vacia();
    }

    /** Los datos de un periodo, revisando que no se encime con otro. */
    private static function leer(Contexto $c, ?int $id): array
    {
        $e = $c->peticion->cuerpo();
        $valores = [
            'nombre' => $e->texto('nombre', 'el nombre', 50),
            'fecha_inicio' => $e->fecha('fechaInicio', 'la fecha de inicio'),
            'fecha_fin' => $e->fecha('fechaFin', 'la fecha de fin'),
        ];
        if ($valores['fecha_fin'] <= $valores['fecha_inicio']) {
            throw ErrorHttp::datosInvalidos('La fecha de fin debe ser posterior a la de inicio', 'fechaFin');
        }
        // Dos periodos encimados harían ambiguo cuál es el actual.
        $encimado = $c->bd->valor(
            'SELECT nombre FROM periodos WHERE fecha_inicio <= ? AND fecha_fin >= ? AND id <> ? LIMIT 1',
            [$valores['fecha_fin'], $valores['fecha_inicio'], $id ?? 0]
        );
        if ($encimado !== null) {
            throw ErrorHttp::conflicto("Las fechas se enciman con el periodo $encimado");
        }
        return $valores;
    }
}
