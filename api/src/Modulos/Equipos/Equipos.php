<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Equipos;

use Asistencias\Contexto;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Datos\Formato;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Avisos\Avisos;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;

/**
 * Las computadoras de cada laboratorio. Que un equipo esté ocupado no se guarda: se
 * sabe porque tiene una asistencia sin hora de salida. Un equipo retirado con
 * historial se desactiva y libera su etiqueta.
 */
final class Equipos
{
    private const MAXIMO_POR_LABORATORIO = 200;
    private const DUPLICADOS = ['uq_equipos_etiqueta' => 'Ya hay un equipo con esa etiqueta en el laboratorio'];

    public static function registrar(Enrutador $r): void
    {
        // Pública: el alumno elige su computadora sin iniciar sesión.
        $r->get('/laboratorios/{clave}/equipos', Acceso::Publica, static fn (Contexto $c) => self::listar($c));
        $r->post('/laboratorios/{clave}/equipos', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->post('/laboratorios/{clave}/equipos/configurar', Acceso::Personal, static fn (Contexto $c) => self::configurar($c));
        $r->put('/equipos/{id}', Acceso::Personal, static fn (Contexto $c) => self::editar($c));
        $r->delete('/equipos/{id}', Acceso::Admin, static fn (Contexto $c) => self::retirar($c));
    }

    /** El equipo activo con esa etiqueta en el laboratorio, o null. */
    public static function porEtiqueta(BaseDeDatos $bd, int $laboratorioId, string $etiqueta, bool $bloquear = false): ?array
    {
        return $bd->fila(
            'SELECT * FROM equipos WHERE laboratorio_id = ? AND etiqueta = ? AND activo' . ($bloquear ? ' FOR UPDATE' : ''),
            [$laboratorioId, $etiqueta]
        );
    }

    public static function porId(BaseDeDatos $bd, int $id): array
    {
        return $bd->fila('SELECT e.*, l.clave AS laboratorio_clave FROM equipos e JOIN laboratorios l ON l.id = e.laboratorio_id WHERE e.id = ?', [$id])
            ?? throw ErrorHttp::noExiste('El equipo');
    }

    private static function listar(Contexto $c): array
    {
        $laboratorio = Laboratorios::porClave($c->bd, $c->peticion->parametro('clave'));
        // PC-2 antes que PC-10: primero por largo y luego por texto.
        $equipos = $c->bd->filas('SELECT * FROM equipos WHERE laboratorio_id = ? AND activo ORDER BY LENGTH(etiqueta), etiqueta', [$laboratorio['id']]);
        $ocupantes = [];
        foreach ($c->bd->filas(
            "SELECT s.equipo_id, s.tipo_persona, s.hora_entrada, s.clase_id,
                    COALESCE(a.matricula, u.matricula) AS matricula,
                    COALESCE(a.nombre, u.nombre, s.invitado_nombre) AS nombre,
                    COALESCE(a.apellido, u.apellido, s.invitado_apellido) AS apellido
             FROM asistencias s
             LEFT JOIN alumnos a ON a.id = s.alumno_id
             LEFT JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.laboratorio_id = ? AND s.hora_salida IS NULL AND s.equipo_id IS NOT NULL",
            [$laboratorio['id']]
        ) as $ocupante) {
            $ocupantes[$ocupante['equipo_id']] = $ocupante;
        }
        $reportes = [];
        foreach ($c->bd->filas(
            "SELECT r.* FROM reportes_de_equipo r JOIN equipos e ON e.id = r.equipo_id
             WHERE e.laboratorio_id = ? AND r.estado <> 'resuelto' ORDER BY r.reportado_en",
            [$laboratorio['id']]
        ) as $reporte) {
            $reportes[$reporte['equipo_id']] = $reporte;
        }

        $conSesion = $c->usuario() !== null;
        return array_map(static function (array $equipo) use ($ocupantes, $reportes, $conSesion) {
            $ocupante = $ocupantes[$equipo['id']] ?? null;
            $datos = [
                'id' => $equipo['id'],
                'etiqueta' => $equipo['etiqueta'],
                'estado' => match (true) {
                    (bool)$equipo['fuera_de_servicio'] => 'fuera_de_servicio',
                    $ocupante !== null => 'ocupado',
                    default => 'libre',
                },
            ];
            if (!$conSesion) {
                return $datos;
            }
            // Quién lo ocupa y sus notas solo se ven con sesión.
            $reporte = $reportes[$equipo['id']] ?? null;
            return $datos + [
                'ocupadoPor' => $ocupante === null ? null : [
                    'tipo' => $ocupante['tipo_persona'],
                    'matricula' => $ocupante['matricula'],
                    'nombre' => trim("{$ocupante['nombre']} {$ocupante['apellido']}"),
                    'desde' => Formato::fechaHora($ocupante['hora_entrada']),
                    'claseId' => $ocupante['clase_id'],
                ],
                'notas' => $equipo['notas'],
                'reporteAbierto' => $reporte === null ? null : ['id' => $reporte['id'], 'estado' => $reporte['estado'], 'descripcion' => $reporte['descripcion']],
                'actualizadoEn' => Formato::fechaHora($equipo['actualizado_en']),
            ];
        }, $equipos);
    }

    private static function crear(Contexto $c): Respuesta
    {
        $laboratorio = Laboratorios::porClave($c->bd, $c->peticion->parametro('clave'));
        $etiqueta = $c->peticion->cuerpo()->texto('etiqueta', 'la etiqueta', 30);
        $id = ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear(
            'equipos',
            ['laboratorio_id' => $laboratorio['id'], 'etiqueta' => $etiqueta],
            $etiqueta,
            $laboratorio['id']
        ));
        $equipo = self::porId($c->bd, $id);
        return Respuesta::creado(['id' => $equipo['id'], 'etiqueta' => $equipo['etiqueta'], 'estado' => 'libre']);
    }

    /** Marca un equipo fuera de servicio (o de vuelta en servicio), o cambia sus notas o su etiqueta. */
    private static function editar(Contexto $c): array
    {
        $equipo = self::porId($c->bd, $c->peticion->id());
        $e = $c->peticion->cuerpo();
        $valores = [];
        if ($e->tiene('fueraDeServicio')) {
            $valores['fuera_de_servicio'] = $e->booleano('fueraDeServicio');
        }
        if ($e->tiene('notas')) {
            $valores['notas'] = $e->texto('notas', 'las notas', 2000, false);
        }
        if ($e->tiene('etiqueta')) {
            if (!$c->usuarioConSesion()->esAdmin()) {
                throw ErrorHttp::sinPermiso('Solo el administrador cambia la etiqueta de un equipo');
            }
            $valores['etiqueta'] = $e->texto('etiqueta', 'la etiqueta', 30);
        }
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('equipos', $equipo['id'], $valores, $equipo['etiqueta'], $equipo['laboratorio_id']));
        $editado = self::porId($c->bd, $equipo['id']);
        return [
            'id' => $editado['id'],
            'etiqueta' => $editado['etiqueta'],
            'fueraDeServicio' => (bool)$editado['fuera_de_servicio'],
            'notas' => $editado['notas'],
        ];
    }

    /** Lo quita del laboratorio: se borra si nunca se usó y, si tiene historial, se desactiva. */
    private static function retirar(Contexto $c): Respuesta
    {
        $equipo = self::porId($c->bd, $c->peticion->id());
        self::retirarEquipo($c, $equipo);
        return Respuesta::vacia();
    }

    /**
     * Deja el laboratorio con PC-1 a PC-N: agrega las que falten, reactiva las que se
     * habían retirado y retira las demás. No retira un equipo que alguien está usando.
     */
    private static function configurar(Contexto $c): array
    {
        $laboratorio = Laboratorios::porClave($c->bd, $c->peticion->parametro('clave'));
        $cantidad = $c->peticion->cuerpo()->entero('cantidad', 'la cantidad de equipos', 1, self::MAXIMO_POR_LABORATORIO);

        $deseadas = array_map(static fn (int $n) => "PC-$n", range(1, $cantidad));
        $activos = $c->bd->filas('SELECT * FROM equipos WHERE laboratorio_id = ? AND activo FOR UPDATE', [$laboratorio['id']]);
        $etiquetasActivas = array_column($activos, 'etiqueta');

        $retirados = 0;
        foreach ($activos as $equipo) {
            if (!in_array($equipo['etiqueta'], $deseadas, true)) {
                self::retirarEquipo($c, $equipo);
                $retirados++;
            }
        }
        $agregados = 0;
        foreach ($deseadas as $etiqueta) {
            if (in_array($etiqueta, $etiquetasActivas, true)) {
                continue;
            }
            // Un equipo retirado con esa etiqueta es, casi seguro, la misma máquina: se
            // reactiva y conserva su historial.
            $retirado = $c->bd->valor(
                'SELECT id FROM equipos WHERE laboratorio_id = ? AND etiqueta = ? AND NOT activo ORDER BY id DESC LIMIT 1',
                [$laboratorio['id'], $etiqueta]
            );
            $retirado !== null
                ? $c->bd->ejecutar('UPDATE equipos SET activo = TRUE WHERE id = ?', [$retirado])
                : $c->bd->insertar('equipos', ['laboratorio_id' => $laboratorio['id'], 'etiqueta' => $etiqueta]);
            $agregados++;
        }

        $detalle = "$cantidad equipos: $agregados agregados, $retirados retirados";
        $c->bitacora->registrar('configurar_equipos', 'laboratorios', $laboratorio['id'], $detalle, $laboratorio['id']);
        if ($agregados + $retirados > 0) {
            Avisos::crear($c->bd, 'sistema', "Equipos del {$laboratorio['nombre']} actualizados", "El laboratorio ahora tiene $detalle.", 'media', $laboratorio['id']);
        }
        return ['equipos' => $cantidad, 'agregados' => $agregados, 'retirados' => $retirados];
    }

    private static function retirarEquipo(Contexto $c, array $equipo): void
    {
        if ($c->bd->valor('SELECT 1 FROM asistencias WHERE equipo_id = ? AND hora_salida IS NULL', [$equipo['id']]) !== null) {
            throw ErrorHttp::conflicto("El equipo {$equipo['etiqueta']} está en uso. Espera a que lo desocupen para retirarlo", 'equipo_ocupado');
        }
        $tieneHistorial = $c->bd->valor(
            'SELECT 1 FROM asistencias WHERE equipo_id = ? UNION SELECT 1 FROM reportes_de_equipo WHERE equipo_id = ? UNION SELECT 1 FROM solicitudes_ingreso WHERE equipo_id = ? LIMIT 1',
            [$equipo['id'], $equipo['id'], $equipo['id']]
        ) !== null;
        $tieneHistorial
            ? $c->cambios->actualizar('equipos', $equipo['id'], ['activo' => false], $equipo['etiqueta'], $equipo['laboratorio_id'])
            : $c->cambios->eliminar('equipos', $equipo['id'], $equipo['etiqueta'], $equipo['laboratorio_id']);
    }
}
