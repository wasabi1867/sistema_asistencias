<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Consultas;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;

/** Consulta de la bitácora. No hay ruta para editarla ni borrarla. */
final class ConsultaDeBitacora
{
    public static function registrar(Enrutador $r): void
    {
        $r->get('/bitacora', Acceso::Personal, static fn (Contexto $c) => self::consultar($c));
    }

    private static function consultar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($desde = $q->fecha('desde', 'la fecha de inicio', false)) !== null) {
            $condiciones[] = 'b.fecha >= ?';
            $parametros[] = $desde;
        }
        if (($hasta = $q->fecha('hasta', 'la fecha de fin', false)) !== null) {
            $condiciones[] = 'b.fecha < ? + INTERVAL 1 DAY';
            $parametros[] = $hasta;
        }
        if (($accion = $q->texto('accion', 'la acción', 60, false)) !== null) {
            $condiciones[] = 'b.accion = ?';
            $parametros[] = $accion;
        }
        if (($entidad = $q->texto('entidad', 'la tabla', 40, false)) !== null) {
            $condiciones[] = 'b.entidad = ?';
            $parametros[] = $entidad;
        }
        if (($usuario = $q->entero('usuario', 'el usuario', 1, null, false)) !== null) {
            $condiciones[] = 'b.usuario_id = ?';
            $parametros[] = $usuario;
        }
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'b.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($texto = $q->texto('q', 'la búsqueda', 100, false)) !== null) {
            $condiciones[] = '(b.usuario LIKE ? OR b.detalles LIKE ?)';
            array_push($parametros, BaseDeDatos::patron($texto), BaseDeDatos::patron($texto));
        }

        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        $pagina = $q->paginacion(100, 1000);
        $total = (int)$c->bd->valor("SELECT COUNT(*) FROM bitacora b$donde", $parametros);
        $filas = $c->bd->filas(
            "SELECT b.*, l.clave AS laboratorio_clave FROM bitacora b LEFT JOIN laboratorios l ON l.id = b.laboratorio_id$donde
             ORDER BY b.fecha DESC, b.id DESC LIMIT {$pagina['porPagina']} OFFSET {$pagina['desde']}",
            $parametros
        );
        return [
            'entradas' => array_map(static fn (array $f) => [
                'id' => $f['id'],
                'fecha' => Formato::fechaHora($f['fecha']),
                'usuario' => $f['usuario'],
                'usuarioId' => $f['usuario_id'],
                'rol' => $f['rol'],
                'accion' => $f['accion'],
                'entidad' => $f['entidad'],
                'entidadId' => $f['entidad_id'],
                'detalles' => $f['detalles'],
                'laboratorio' => $f['laboratorio_clave'],
                'ip' => $f['ip'],
            ], $filas),
            'total' => $total,
            'pagina' => $pagina['pagina'],
            'porPagina' => $pagina['porPagina'],
        ];
    }
}
