<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Equipos;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Avisos\Avisos;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;

/**
 * Las fallas de los equipos y su seguimiento: cualquiera del personal (también el
 * docente) reporta una falla; el administrador y el laboratorista la atienden. Queda
 * el historial de cada computadora.
 */
final class ReportesDeEquipo
{
    private const ESTADOS = ['reportado', 'en_proceso', 'resuelto'];
    private const SELECCION = "SELECT r.*, e.etiqueta, e.laboratorio_id, l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
            CONCAT(u.nombre, ' ', u.apellido) AS reportado_por_nombre
        FROM reportes_de_equipo r
        JOIN equipos e ON e.id = r.equipo_id
        JOIN laboratorios l ON l.id = e.laboratorio_id
        LEFT JOIN usuarios u ON u.id = r.reportado_por";

    public static function registrar(Enrutador $r): void
    {
        $r->get('/reportes-de-equipo', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->post('/equipos/{id}/reportes', Acceso::Sesion, static fn (Contexto $c) => self::reportar($c));
        $r->put('/reportes-de-equipo/{id}', Acceso::Personal, static fn (Contexto $c) => self::actualizar($c));
    }

    private static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila(self::SELECCION . ' WHERE r.id = ?', [$id]) ?? throw ErrorHttp::noExiste('El reporte');
    }

    private static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'equipo' => ['id' => $fila['equipo_id'], 'etiqueta' => $fila['etiqueta']],
            'laboratorio' => ['clave' => $fila['laboratorio_clave'], 'nombre' => $fila['laboratorio_nombre']],
            'descripcion' => $fila['descripcion'],
            'estado' => $fila['estado'],
            'comentario' => $fila['comentario'],
            'reportadoPor' => $fila['reportado_por_nombre'],
            'reportadoEn' => Formato::fechaHora($fila['reportado_en']),
            'actualizadoEn' => Formato::fechaHora($fila['actualizado_en']),
            'resueltoEn' => Formato::fechaHora($fila['resuelto_en']),
        ];
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'e.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($estado = $q->opcion('estado', 'el estado', [...self::ESTADOS, 'abiertos'], false)) !== null) {
            $condiciones[] = $estado === 'abiertos' ? "r.estado <> 'resuelto'" : 'r.estado = ?';
            if ($estado !== 'abiertos') {
                $parametros[] = $estado;
            }
        }
        if (($equipo = $q->entero('equipo', 'el equipo', 1, null, false)) !== null) {
            $condiciones[] = 'r.equipo_id = ?';
            $parametros[] = $equipo;
        }
        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return array_map(self::formato(...), $c->bd->filas(self::SELECCION . "$donde ORDER BY r.reportado_en DESC LIMIT 500", $parametros));
    }

    /** Reporta una falla. Si la falla impide usar el equipo, lo marca fuera de servicio. */
    private static function reportar(Contexto $c): Respuesta
    {
        $equipo = Equipos::porId($c->bd, $c->peticion->id());
        $e = $c->peticion->cuerpo();
        $descripcion = $e->texto('descripcion', 'la descripción de la falla', 2000);
        $fueraDeServicio = $e->booleano('fueraDeServicio', true);

        $id = $c->bd->insertar('reportes_de_equipo', [
            'equipo_id' => $equipo['id'],
            'descripcion' => $descripcion,
            'reportado_por' => $c->usuarioConSesion()->id,
        ]);
        if ($fueraDeServicio && !$equipo['fuera_de_servicio']) {
            $c->bd->ejecutar('UPDATE equipos SET fuera_de_servicio = TRUE WHERE id = ?', [$equipo['id']]);
        }
        $c->bitacora->registrar('reportar_equipo', 'equipos', $equipo['id'], $equipo['etiqueta'] . ($fueraDeServicio ? ', fuera de servicio' : ''), $equipo['laboratorio_id']);
        Avisos::crear(
            $c->bd,
            'equipo',
            "Falla en el equipo {$equipo['etiqueta']}",
            $descripcion,
            $fueraDeServicio ? 'alta' : 'media',
            $equipo['laboratorio_id'],
            reporteId: $id
        );
        return Respuesta::creado(self::formato(self::porId($c->bd, $id)));
    }

    /**
     * Cambia el estado del reporte. Al resolverse el último reporte abierto del equipo,
     * el equipo vuelve a estar en servicio.
     */
    private static function actualizar(Contexto $c): array
    {
        $reporte = self::porId($c->bd, $c->peticion->id());
        $e = $c->peticion->cuerpo();
        $valores = [];
        if ($e->tiene('estado')) {
            $valores['estado'] = $e->opcion('estado', 'el estado', self::ESTADOS);
            $valores['resuelto_en'] = $valores['estado'] === 'resuelto' ? ($reporte['resuelto_en'] ?? date('Y-m-d H:i:s')) : null;
        }
        if ($e->tiene('comentario')) {
            $valores['comentario'] = $e->texto('comentario', 'el comentario', 2000, false);
        }
        $c->cambios->actualizar('reportes_de_equipo', $reporte['id'], $valores, "Equipo {$reporte['etiqueta']}", $reporte['laboratorio_id']);

        if (($valores['estado'] ?? null) === 'resuelto') {
            $abiertos = (int)$c->bd->valor("SELECT COUNT(*) FROM reportes_de_equipo WHERE equipo_id = ? AND estado <> 'resuelto'", [$reporte['equipo_id']]);
            if ($abiertos === 0) {
                $c->bd->ejecutar('UPDATE equipos SET fuera_de_servicio = FALSE WHERE id = ?', [$reporte['equipo_id']]);
            }
        }
        return self::formato(self::porId($c->bd, $reporte['id']));
    }
}
