<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Clases;

use Asistencias\Datos\BaseDeDatos;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Modulos\Equipos\Equipos;

/**
 * Registrar la entrada de alguien a un laboratorio. Lo usan el registro del alumno,
 * el registro a mano del personal, la aprobación de una solicitud y el uso fuera de
 * clase, así las reglas están en un solo lugar:
 * - la clase sigue abierta;
 * - la persona no está ya dentro de otra clase;
 * - el equipo es del laboratorio, está en servicio y está libre.
 */
final class Entradas
{
    /**
     * La computadora que indicó la persona, o null si trae la suya. Tiene que decir
     * una de las dos cosas: así una asistencia sin equipo siempre significa equipo
     * propio y nunca un dato que faltó.
     */
    public static function equipoElegido(Entrada $e): ?string
    {
        $etiqueta = $e->texto('equipo', 'la computadora', 30, false);
        $propio = $e->booleano('equipoPropio');
        if (($etiqueta === null) === !$propio) {
            throw ErrorHttp::datosInvalidos('Indica la computadora que vas a usar o marca que traes tu propio equipo', 'equipo');
        }
        return $etiqueta;
    }

    /**
     * @param array{tipo: string, alumno_id?: int, usuario_id?: int, nombre?: string, apellido?: string} $persona
     * @param string|null $etiqueta la computadora; null si trae la suya
     * @param bool $siNoEstaLibreUsaElPropio al aprobar una solicitud, si la computadora
     *                                       que eligió el alumno ya no está libre, entra
     *                                       con su propio equipo en lugar de fallar
     * @return array{id: int, equipo: ?string}
     */
    public static function registrar(
        BaseDeDatos $bd,
        int $laboratorioId,
        ?int $claseId,
        array $persona,
        ?string $etiqueta,
        ?int $registradaPor = null,
        ?int $solicitudId = null,
        bool $siNoEstaLibreUsaElPropio = false,
    ): array {
        return $bd->transaccion(static function () use ($bd, $laboratorioId, $claseId, $persona, $etiqueta, $registradaPor, $solicitudId, $siNoEstaLibreUsaElPropio) {
            if ($claseId !== null) {
                // Cerrar la clase bloquea la misma fila: si alguien la cierra en este
                // momento, uno espera al otro y nadie queda dentro de una clase cerrada.
                $abierta = $bd->valor('SELECT cerrada_en IS NULL FROM clases WHERE id = ? FOR UPDATE', [$claseId]);
                if (!$abierta) {
                    throw ErrorHttp::conflicto('La clase acaba de cerrarse', 'clase_cerrada');
                }
            }
            self::revisarQueNoEsteDentro($bd, $persona, $claseId);

            $equipo = null;
            if ($etiqueta !== null) {
                // Bloquear el equipo: si dos personas lo eligen al mismo tiempo, la
                // segunda espera y ya lo encuentra ocupado.
                $equipo = Equipos::porEtiqueta($bd, $laboratorioId, $etiqueta, bloquear: true);
                if ($equipo === null && !$siNoEstaLibreUsaElPropio) {
                    throw ErrorHttp::datosInvalidos("La computadora $etiqueta no existe en este laboratorio", 'equipo');
                }
                $problema = match (true) {
                    $equipo === null => ["La computadora $etiqueta ya no está en el laboratorio", 'equipo_retirado'],
                    (bool)$equipo['fuera_de_servicio'] => ["La computadora $etiqueta está fuera de servicio", 'equipo_fuera_de_servicio'],
                    $bd->valor('SELECT 1 FROM asistencias WHERE equipo_id = ? AND hora_salida IS NULL', [$equipo['id']]) !== null
                        => ["La computadora $etiqueta ya está ocupada", 'equipo_ocupado'],
                    default => null,
                };
                if ($problema !== null) {
                    if (!$siNoEstaLibreUsaElPropio) {
                        throw ErrorHttp::conflicto(...$problema);
                    }
                    $equipo = null;
                }
            }

            try {
                $id = $bd->insertar('asistencias', [
                    'laboratorio_id' => $laboratorioId,
                    'clase_id' => $claseId,
                    'equipo_id' => $equipo['id'] ?? null,
                    'tipo_persona' => $persona['tipo'],
                    'alumno_id' => $persona['alumno_id'] ?? null,
                    'usuario_id' => $persona['usuario_id'] ?? null,
                    'invitado_nombre' => $persona['tipo'] === 'invitado' ? $persona['nombre'] : null,
                    'invitado_apellido' => $persona['tipo'] === 'invitado' ? $persona['apellido'] : null,
                    'registrada_por' => $registradaPor,
                    'solicitud_id' => $solicitudId,
                ]);
            } catch (ErrorDeIntegridad $e) {
                // Dos registros al mismo tiempo (un doble clic con internet lento): la
                // base deja pasar uno solo.
                throw match ($e->restriccion) {
                    'uq_asistencias_equipo_ocupado' => ErrorHttp::conflicto("La computadora $etiqueta ya está ocupada", 'equipo_ocupado'),
                    'uq_asistencias_alumno_dentro', 'uq_asistencias_usuario_dentro' => ErrorHttp::conflicto('Ya registraste tu entrada', 'ya_registrado'),
                    default => $e,
                };
            }
            return ['id' => $id, 'equipo' => $equipo['etiqueta'] ?? null];
        });
    }

    /** Una persona solo está en un lugar a la vez. A un invitado no se le puede revisar. */
    private static function revisarQueNoEsteDentro(BaseDeDatos $bd, array $persona, ?int $claseId): void
    {
        $columna = match ($persona['tipo']) {
            'alumno' => 'alumno_id',
            'docente' => 'usuario_id',
            default => null,
        };
        if ($columna === null) {
            return;
        }
        $previa = $bd->fila(
            "SELECT s.clase_id, l.nombre AS laboratorio FROM asistencias s JOIN laboratorios l ON l.id = s.laboratorio_id
             WHERE s.$columna = ? AND s.hora_salida IS NULL",
            [$persona[$columna]]
        );
        if ($previa === null) {
            return;
        }
        throw ErrorHttp::conflicto(
            $claseId !== null && $previa['clase_id'] === $claseId
                ? 'Ya registraste tu entrada en esta clase'
                : "Ya estás registrado en el {$previa['laboratorio']}. Espera a que termine para registrarte en otra",
            'ya_registrado'
        );
    }
}
