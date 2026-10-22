<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Catalogos;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Acceso;

final class Carreras
{
    public static function registrar(Enrutador $r): void
    {
        $r->get('/carreras', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->post('/carreras', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->put('/carreras/{id}', Acceso::Admin, static fn (Contexto $c) => self::editar($c));
        $r->delete('/carreras/{id}', Acceso::Admin, static fn (Contexto $c) => self::eliminar($c));
    }

    /**
     * El id de la carrera por su clave o por su nombre. La colación de la base no
     * distingue mayúsculas ni acentos, así que "ingenieria industrial" encuentra
     * "Ingeniería Industrial". Responde 400 si no está en el catálogo.
     */
    public static function idPor(BaseDeDatos $bd, ?string $claveONombre, string $campo = 'carrera'): ?int
    {
        if ($claveONombre === null) {
            return null;
        }
        $id = $bd->valor('SELECT id FROM carreras WHERE clave = ? OR nombre = ? ORDER BY clave = ? DESC LIMIT 1', [$claveONombre, $claveONombre, $claveONombre]);
        return $id === null
            ? throw ErrorHttp::datosInvalidos("La carrera \"$claveONombre\" no está en el catálogo", $campo)
            : (int)$id;
    }

    private static function formato(array $fila): array
    {
        return ['id' => $fila['id'], 'clave' => $fila['clave'], 'nombre' => $fila['nombre'], 'activa' => (bool)$fila['activa']];
    }

    private static function uno(Contexto $c, int $id): array
    {
        return self::formato($c->bd->fila('SELECT * FROM carreras WHERE id = ?', [$id]));
    }

    private static function listar(Contexto $c): array
    {
        $todas = $c->peticion->consulta()->booleano('todas');
        return array_map(self::formato(...), $c->bd->filas('SELECT * FROM carreras' . ($todas ? '' : ' WHERE activa') . ' ORDER BY nombre'));
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $clave = mb_strtoupper($e->texto('clave', 'la clave', 20));
        $id = $c->cambios->crear('carreras', ['clave' => $clave, 'nombre' => $e->texto('nombre', 'el nombre', 150)], $clave);
        return Respuesta::creado(self::uno($c, $id));
    }

    private static function editar(Contexto $c): array
    {
        $id = $c->peticion->id();
        $e = $c->peticion->cuerpo();
        $valores = [];
        if ($e->tiene('clave')) {
            $valores['clave'] = mb_strtoupper($e->texto('clave', 'la clave', 20));
        }
        if ($e->tiene('nombre')) {
            $valores['nombre'] = $e->texto('nombre', 'el nombre', 150);
        }
        if ($e->tiene('activa')) {
            $valores['activa'] = $e->booleano('activa');
        }
        $c->cambios->actualizar('carreras', $id, $valores);
        return self::uno($c, $id);
    }

    /** Solo si ningún alumno ni materia la usa; si no, se desactiva. */
    private static function eliminar(Contexto $c): Respuesta
    {
        $c->cambios->eliminar('carreras', $c->peticion->id());
        return Respuesta::vacia();
    }
}
