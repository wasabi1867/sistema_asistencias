<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Avisos;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;

/**
 * Avisos para el personal: una solicitud nueva, una falla en un equipo, una clase de
 * maestro invitado. Los crea la API cuando pasa algo; nadie los escribe a mano. Cada
 * aviso apunta a aquello de lo que avisa, así la pantalla muestra su estado actual
 * (una solicitud ya resuelta, un equipo ya reparado).
 */
final class Avisos
{
    private const TIPOS = ['solicitud', 'equipo', 'clase', 'sistema'];

    public static function registrar(Enrutador $r): void
    {
        $r->get('/avisos', Acceso::Personal, static fn (Contexto $c) => self::listar($c));
        $r->put('/avisos/{id}', Acceso::Personal, static fn (Contexto $c) => self::marcar($c));
        $r->post('/avisos/leidos', Acceso::Personal, static fn (Contexto $c) => self::marcarTodos($c));
    }

    public static function crear(
        BaseDeDatos $bd,
        string $tipo,
        string $titulo,
        ?string $mensaje,
        string $prioridad = 'media',
        ?int $laboratorioId = null,
        ?int $solicitudId = null,
        ?int $reporteId = null,
        ?int $claseId = null,
    ): int {
        return $bd->insertar('avisos', [
            'tipo' => $tipo,
            'titulo' => mb_substr($titulo, 0, 150),
            'mensaje' => $mensaje,
            'prioridad' => $prioridad,
            'laboratorio_id' => $laboratorioId,
            'solicitud_id' => $solicitudId,
            'reporte_id' => $reporteId,
            'clase_id' => $claseId,
        ]);
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $condiciones = [];
        $parametros = [];
        if (($laboratorio = $q->texto('laboratorio', 'el laboratorio', 30, false)) !== null) {
            $condiciones[] = 'v.laboratorio_id = ?';
            $parametros[] = Laboratorios::porClave($c->bd, $laboratorio)['id'];
        }
        if (($tipo = $q->opcion('tipo', 'el tipo', self::TIPOS, false)) !== null) {
            $condiciones[] = 'v.tipo = ?';
            $parametros[] = $tipo;
        }
        if ($q->tiene('leido')) {
            $condiciones[] = 'v.leido = ?';
            $parametros[] = $q->booleano('leido');
        }
        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        $filas = $c->bd->filas(
            "SELECT v.*, l.clave AS laboratorio_clave, l.nombre AS laboratorio_nombre,
                    s.estado AS solicitud_estado, r.estado AS reporte_estado, r.equipo_id, e.etiqueta AS equipo_etiqueta,
                    cl.cerrada_en IS NULL AS clase_abierta
             FROM avisos v
             LEFT JOIN laboratorios l ON l.id = v.laboratorio_id
             LEFT JOIN solicitudes_ingreso s ON s.id = v.solicitud_id
             LEFT JOIN reportes_de_equipo r ON r.id = v.reporte_id
             LEFT JOIN equipos e ON e.id = r.equipo_id
             LEFT JOIN clases cl ON cl.id = v.clase_id
             $donde
             ORDER BY v.creado_en DESC, v.id DESC
             LIMIT 200",
            $parametros
        );
        return array_map(static fn (array $f) => [
            'id' => $f['id'],
            'tipo' => $f['tipo'],
            'titulo' => $f['titulo'],
            'mensaje' => $f['mensaje'],
            'prioridad' => $f['prioridad'],
            'leido' => (bool)$f['leido'],
            'creadoEn' => Formato::fechaHora($f['creado_en']),
            'laboratorio' => $f['laboratorio_clave'] === null ? null : ['clave' => $f['laboratorio_clave'], 'nombre' => $f['laboratorio_nombre']],
            'solicitud' => $f['solicitud_id'] === null ? null : ['id' => $f['solicitud_id'], 'estado' => $f['solicitud_estado']],
            'reporte' => $f['reporte_id'] === null ? null : [
                'id' => $f['reporte_id'], 'estado' => $f['reporte_estado'], 'equipo' => ['id' => $f['equipo_id'], 'etiqueta' => $f['equipo_etiqueta']],
            ],
            'clase' => $f['clase_id'] === null ? null : ['id' => $f['clase_id'], 'abierta' => (bool)$f['clase_abierta']],
        ], $filas);
    }

    private static function marcar(Contexto $c): Respuesta
    {
        $leido = $c->peticion->cuerpo()->booleano('leido', true);
        $id = $c->peticion->id();
        if ($c->bd->valor('SELECT 1 FROM avisos WHERE id = ?', [$id]) === null) {
            throw ErrorHttp::noExiste('El aviso');
        }
        $c->bd->ejecutar('UPDATE avisos SET leido = ? WHERE id = ?', [$leido, $id]);
        return Respuesta::vacia();
    }

    private static function marcarTodos(Contexto $c): array
    {
        $laboratorio = $c->peticion->cuerpo()->texto('laboratorio', 'el laboratorio', 30, false);
        $marcados = $laboratorio === null
            ? $c->bd->ejecutar('UPDATE avisos SET leido = TRUE WHERE NOT leido')
            : $c->bd->ejecutar('UPDATE avisos SET leido = TRUE WHERE NOT leido AND laboratorio_id = ?', [Laboratorios::porClave($c->bd, $laboratorio)['id']]);
        return ['marcados' => $marcados];
    }
}
