<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Laboratorio;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;

/** Talleres, concursos y otras actividades del laboratorio fuera de clase. Los lleva el personal. */
final class Eventos
{
    private const SELECCION = 'SELECT ev.*, l.clave AS laboratorio_clave FROM eventos ev JOIN laboratorios l ON l.id = ev.laboratorio_id';

    public static function registrar(Enrutador $r): void
    {
        $r->get('/eventos', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->post('/eventos', Acceso::Personal, static fn (Contexto $c) => self::crear($c));
        $r->put('/eventos/{id}', Acceso::Personal, static fn (Contexto $c) => self::editar($c));
        $r->delete('/eventos/{id}', Acceso::Personal, static fn (Contexto $c) => self::eliminar($c));
    }

    private static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE ev.id = ?', [$id]) ?? throw ErrorHttp::noExiste('El evento');
    }

    private static function formato(array $f): array
    {
        return [
            'id' => $f['id'],
            'laboratorio' => $f['laboratorio_clave'],
            'nombre' => $f['nombre'],
            'tipo' => $f['tipo'],
            'organizador' => $f['organizador'],
            'participantes' => $f['participantes'],
            'fecha' => $f['fecha'],
            'hora' => Formato::hora($f['hora']),
            'duracion' => $f['duracion'],
            'descripcion' => $f['descripcion'],
            'resultados' => $f['resultados'],
            'creadoEn' => Formato::fechaHora($f['creado_en']),
        ];
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'ev.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($desde = $q->fecha('desde', 'la fecha de inicio', false)) !== null) {
            $condiciones[] = 'ev.fecha >= ?';
            $parametros[] = $desde;
        }
        if (($hasta = $q->fecha('hasta', 'la fecha de fin', false)) !== null) {
            $condiciones[] = 'ev.fecha <= ?';
            $parametros[] = $hasta;
        }
        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return array_map(self::formato(...), $c->bd->filas(self::SELECCION . "$donde ORDER BY ev.fecha DESC, ev.hora DESC, ev.id DESC", $parametros));
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $laboratorio = Laboratorios::porClave($c->bd, $e->texto('laboratorio', 'el laboratorio', 30));
        $valores = ['laboratorio_id' => $laboratorio['id'], 'creado_por' => $c->usuarioConSesion()->id] + self::datos($e, nuevo: true);
        $id = $c->cambios->crear('eventos', $valores, $valores['nombre'], $laboratorio['id']);
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    private static function editar(Contexto $c): array
    {
        $evento = self::porId($c->bd, $c->peticion->id());
        $c->cambios->actualizar('eventos', $evento['id'], self::datos($c->peticion->cuerpo(), nuevo: false), $evento['nombre'], $evento['laboratorio_id']);
        return self::formato(self::porId($c->bd, $evento['id']));
    }

    private static function eliminar(Contexto $c): Respuesta
    {
        $evento = self::porId($c->bd, $c->peticion->id());
        $c->cambios->eliminar('eventos', $evento['id'], $evento['nombre'], $evento['laboratorio_id']);
        return Respuesta::vacia();
    }

    private static function datos(Entrada $e, bool $nuevo): array
    {
        $campos = [
            'nombre' => static fn () => $e->texto('nombre', 'el nombre', 150),
            'tipo' => static fn () => $e->texto('tipo', 'el tipo', 50, false),
            'organizador' => static fn () => $e->texto('organizador', 'el organizador', 150, false),
            'participantes' => static fn () => $e->entero('participantes', 'los participantes', 0, 10000, false),
            'fecha' => static fn () => $e->fecha('fecha', 'la fecha', false),
            'hora' => static fn () => $e->hora('hora', 'la hora', false),
            'duracion' => static fn () => $e->texto('duracion', 'la duración', 50, false),
            'descripcion' => static fn () => $e->texto('descripcion', 'la descripción', 5000, false),
            'resultados' => static fn () => $e->texto('resultados', 'los resultados', 5000, false),
        ];
        $valores = [];
        foreach ($campos as $campo => $leer) {
            if ($nuevo || $e->tiene($campo)) {
                $valores[$campo] = $leer();
            }
        }
        return $valores;
    }
}
