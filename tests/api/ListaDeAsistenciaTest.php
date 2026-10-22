<?php

namespace Tests\Api;

use Asistencias\Modulos\Clases\ListaDeAsistencia;
use PHPUnit\Framework\TestCase;

/** Las cuentas de asistencia, sin base de datos. */
final class ListaDeAsistenciaTest extends TestCase
{
    private const ANA = ['id' => 1, 'matricula' => 'A1', 'nombre' => 'Ana', 'apellido' => 'Zúñiga'];
    private const BETO = ['id' => 2, 'matricula' => 'B2', 'nombre' => 'Beto', 'apellido' => 'Álvarez'];

    private static function entrada(array $alumno, string $hora, int $asistencia = 10): array
    {
        return ['asistencia_id' => $asistencia, 'alumno_id' => $alumno['id'], 'matricula' => $alumno['matricula'], 'nombre' => $alumno['nombre'],
            'apellido' => $alumno['apellido'], 'equipo' => 'PC-1', 'hora_entrada' => $hora, 'hora_salida' => null];
    }

    public function test_cruza_inscritos_con_asistentes(): void
    {
        $lista = ListaDeAsistencia::deUnaClase([self::ANA, self::BETO], [self::entrada(self::ANA, '2026-10-21 08:05:00')]);

        $this->assertSame(['inscritos' => 2, 'presentes' => 1, 'faltas' => 1], array_slice($lista, 0, 3));
        // Por apellido, sin que importen los acentos: Álvarez antes que Zúñiga.
        $this->assertSame(['B2', 'A1'], array_column($lista['alumnos'], 'matricula'));
        $this->assertSame([false, true], array_column($lista['alumnos'], 'presente'));
    }

    public function test_quien_asistio_sin_estar_inscrito_aparece_y_no_cuenta_como_falta(): void
    {
        $lista = ListaDeAsistencia::deUnaClase([self::ANA], [self::entrada(self::BETO, '2026-10-21 08:05:00')]);

        $this->assertSame(['inscritos' => 1, 'presentes' => 1, 'faltas' => 1], array_slice($lista, 0, 3));
        $this->assertSame([['B2', false, true], ['A1', true, false]], array_map(fn ($r) => [$r['matricula'], $r['inscrito'], $r['presente']], $lista['alumnos']));
    }

    public function test_si_entro_dos_veces_cuenta_la_primera_entrada(): void
    {
        $lista = ListaDeAsistencia::deUnaClase([self::ANA], [
            self::entrada(self::ANA, '2026-10-21 09:00:00', 11),
            self::entrada(self::ANA, '2026-10-21 08:05:00', 10),
        ]);

        $this->assertSame(10, $lista['alumnos'][0]['asistenciaId']);
        $this->assertStringStartsWith('2026-10-21T08:05:00', $lista['alumnos'][0]['horaEntrada']);
    }

    public function test_las_clases_anteriores_a_la_inscripcion_no_cuentan_salvo_que_haya_ido(): void
    {
        $clases = [['id' => 1, 'cerrada_en' => '2026-10-01 10:00:00'], ['id' => 2, 'cerrada_en' => '2026-10-08 10:00:00'], ['id' => 3, 'cerrada_en' => '2026-10-15 10:00:00']];
        $inscritos = [self::ANA + ['inscrito_en' => '2026-10-05 00:00:00']];

        $sinIr = ListaDeAsistencia::delCurso($clases, $inscritos, []);
        $fueALaPrimera = ListaDeAsistencia::delCurso($clases, $inscritos, [['alumno_id' => 1, 'clase_id' => 1]]);

        $this->assertSame([2, 0, 2, 0.0], [$sinIr[0]['clases'], $sinIr[0]['asistencias'], $sinIr[0]['faltas'], $sinIr[0]['porcentaje']]);
        $this->assertSame([3, 1, 2, 33.3], [$fueALaPrimera[0]['clases'], $fueALaPrimera[0]['asistencias'], $fueALaPrimera[0]['faltas'], $fueALaPrimera[0]['porcentaje']]);
    }

    public function test_sin_clases_no_hay_porcentaje(): void
    {
        $this->assertNull(ListaDeAsistencia::porcentaje(0, 0));
        $this->assertSame(100.0, ListaDeAsistencia::porcentaje(4, 4));
    }
}
