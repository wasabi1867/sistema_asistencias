<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Clases;

use Asistencias\Datos\Formato;

/**
 * Cuentas de asistencia, sin HTTP ni base de datos: reciben renglones y devuelven
 * renglones, y se prueban solas (tests/api/ListaDeAsistenciaTest.php).
 */
final class ListaDeAsistencia
{
    /**
     * La lista de una clase: los inscritos en el curso, con quién asistió y quién
     * faltó. Quien asistió sin estar inscrito (con una solicitud aprobada, o porque
     * lo dieron de baja después) aparece marcado como no inscrito, para que la lista
     * no pierda una asistencia real.
     *
     * @param list<array{id: int, matricula: string, nombre: string, apellido: string}> $inscritos
     * @param list<array{asistencia_id: int, alumno_id: int, matricula: string, nombre: string, apellido: string, equipo: ?string, hora_entrada: string, hora_salida: ?string}> $asistentes
     * @return array{inscritos: int, presentes: int, faltas: int, alumnos: list<array<string, mixed>>}
     */
    public static function deUnaClase(array $inscritos, array $asistentes): array
    {
        $entradas = [];
        foreach ($asistentes as $asistente) {
            // Si alguien entró dos veces (salió y volvió), cuenta la primera entrada.
            $previa = $entradas[$asistente['alumno_id']] ?? null;
            if ($previa === null || $asistente['hora_entrada'] < $previa['hora_entrada']) {
                $entradas[$asistente['alumno_id']] = $asistente;
            }
        }

        $renglones = [];
        foreach ($inscritos as $alumno) {
            $renglones[] = self::renglon($alumno, true, $entradas[$alumno['id']] ?? null);
            unset($entradas[$alumno['id']]);
        }
        foreach ($entradas as $entrada) {
            $renglones[] = self::renglon(['id' => $entrada['alumno_id']] + $entrada, false, $entrada);
        }
        usort($renglones, static fn (array $a, array $b) => [self::paraOrdenar($a['apellido']), self::paraOrdenar($a['nombre']), $a['alumnoId']]
            <=> [self::paraOrdenar($b['apellido']), self::paraOrdenar($b['nombre']), $b['alumnoId']]);

        $presentes = count(array_filter($renglones, static fn (array $r) => $r['presente']));
        $faltas = count(array_filter($renglones, static fn (array $r) => $r['inscrito'] && !$r['presente']));
        return ['inscritos' => count($inscritos), 'presentes' => $presentes, 'faltas' => $faltas, 'alumnos' => $renglones];
    }

    /**
     * La asistencia de cada inscrito en un curso. A cada alumno le cuentan las clases
     * que terminaron después de su inscripción, más las que asistió: las anteriores a
     * inscribirse no son falta.
     *
     * @param list<array{id: int, cerrada_en: string}> $clases las clases ya cerradas del curso
     * @param list<array{id: int, matricula: string, nombre: string, apellido: string, inscrito_en: string}> $inscritos
     * @param list<array{alumno_id: int, clase_id: int}> $asistencias
     * @return list<array<string, mixed>>
     */
    public static function delCurso(array $clases, array $inscritos, array $asistencias): array
    {
        $asistidas = [];
        foreach ($asistencias as $asistencia) {
            $asistidas[$asistencia['alumno_id']][$asistencia['clase_id']] = true;
        }

        $resumen = [];
        foreach ($inscritos as $alumno) {
            $suyas = $asistidas[$alumno['id']] ?? [];
            $cuentan = 0;
            $asistio = 0;
            foreach ($clases as $clase) {
                $fue = isset($suyas[$clase['id']]);
                if ($fue || $clase['cerrada_en'] >= $alumno['inscrito_en']) {
                    $cuentan++;
                    $asistio += $fue ? 1 : 0;
                }
            }
            $resumen[] = [
                'alumnoId' => $alumno['id'],
                'matricula' => $alumno['matricula'],
                'nombre' => $alumno['nombre'],
                'apellido' => $alumno['apellido'],
                'clases' => $cuentan,
                'asistencias' => $asistio,
                'faltas' => $cuentan - $asistio,
                'porcentaje' => self::porcentaje($asistio, $cuentan),
            ];
        }
        return $resumen;
    }

    /** Sin clases no hay porcentaje: 0 % daría a entender que el alumno faltó a todo. */
    public static function porcentaje(int $asistencias, int $clases): ?float
    {
        return $clases === 0 ? null : round(100 * $asistencias / $clases, 1);
    }

    private static function renglon(array $alumno, bool $inscrito, ?array $entrada): array
    {
        return [
            'alumnoId' => $alumno['id'],
            'matricula' => $alumno['matricula'],
            'nombre' => $alumno['nombre'],
            'apellido' => $alumno['apellido'],
            'inscrito' => $inscrito,
            'presente' => $entrada !== null,
            'asistenciaId' => $entrada['asistencia_id'] ?? null,
            'equipo' => $entrada['equipo'] ?? null,
            'horaEntrada' => Formato::fechaHora($entrada['hora_entrada'] ?? null),
            'horaSalida' => Formato::fechaHora($entrada['hora_salida'] ?? null),
        ];
    }

    /** Para ordenar sin que importen mayúsculas ni acentos (sin depender de la extensión intl). */
    private static function paraOrdenar(string $texto): string
    {
        return strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n~']);
    }
}
