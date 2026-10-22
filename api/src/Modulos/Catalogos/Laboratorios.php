<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Catalogos;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Acceso;

/**
 * Los laboratorios. Las pantallas y las direcciones los nombran por su clave
 * (`programacion`); la base, por su id. No se borran: tienen historial.
 */
final class Laboratorios
{
    public static function registrar(Enrutador $r): void
    {
        // Pública: la página de registro muestra el nombre del laboratorio.
        $r->get('/laboratorios', Acceso::Publica, static fn (Contexto $c) => self::listar($c));
        $r->post('/laboratorios', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->put('/laboratorios/{id}', Acceso::Admin, static fn (Contexto $c) => self::editar($c));
    }

    /**
     * El laboratorio activo con esa clave, o 404.
     * @return array{id: int, clave: string, nombre: string}
     */
    public static function porClave(BaseDeDatos $bd, ?string $clave): array
    {
        $fila = $clave === null ? null : $bd->fila('SELECT id, clave, nombre FROM laboratorios WHERE clave = ? AND activo', [$clave]);
        return $fila ?? throw ErrorHttp::noExiste('El laboratorio');
    }

    /** @return array<string, mixed> */
    public static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'clave' => $fila['clave'],
            'nombre' => $fila['nombre'],
            'ubicacion' => $fila['ubicacion'],
            'activo' => (bool)$fila['activo'],
        ];
    }

    private static function listar(Contexto $c): array
    {
        $todos = $c->usuario()?->esAdmin() && $c->peticion->consulta()->booleano('todos');
        $filas = $c->bd->filas('SELECT * FROM laboratorios' . ($todos ? '' : ' WHERE activo') . ' ORDER BY nombre');
        return array_map(self::formato(...), $filas);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $clave = $e->texto('clave', 'la clave', 30);
        if (!preg_match('/^[a-z0-9_]+$/', $clave)) {
            throw ErrorHttp::datosInvalidos('Revisa la clave: solo minúsculas sin acentos, números y guion bajo', 'clave');
        }
        $id = $c->cambios->crear('laboratorios', [
            'clave' => $clave,
            'nombre' => $e->texto('nombre', 'el nombre', 100),
            'ubicacion' => $e->texto('ubicacion', 'la ubicación', 100, false),
        ], $clave);
        return Respuesta::creado(self::formato($c->bd->fila('SELECT * FROM laboratorios WHERE id = ?', [$id])));
    }

    private static function editar(Contexto $c): array
    {
        $id = $c->peticion->id();
        $e = $c->peticion->cuerpo();
        $valores = [];
        if ($e->tiene('nombre')) {
            $valores['nombre'] = $e->texto('nombre', 'el nombre', 100);
        }
        if ($e->tiene('ubicacion')) {
            $valores['ubicacion'] = $e->texto('ubicacion', 'la ubicación', 100, false);
        }
        if ($e->tiene('activo')) {
            $valores['activo'] = $e->booleano('activo');
        }
        $c->cambios->actualizar('laboratorios', $id, $valores);
        return self::formato($c->bd->fila('SELECT * FROM laboratorios WHERE id = ?', [$id]));
    }
}
