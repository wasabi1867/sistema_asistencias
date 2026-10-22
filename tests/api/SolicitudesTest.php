<?php

namespace Tests\Api;

final class SolicitudesTest extends PruebaDeApi
{
    private int $clase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->crearMateria('M1');
        $this->crearAlumno('23303009', 'Nora', 'NoInscrita', []);
        $this->crearEquipos();
        $this->clase = $this->abrirClase();
    }

    private function solicitar(array $datos = []): Respuesta
    {
        return $this->anonimo()->post('solicitudes.php', $datos + ['alumno_id' => '23303009', 'laboratorio' => 'programacion', 'equipo' => 'PC-1']);
    }

    private function resolver(int $id, string $estado): Respuesta
    {
        return $this->con('admin')->put('solicitudes.php', ['id' => $id, 'estado' => $estado]);
    }

    public function test_la_solicitud_guarda_la_clase_y_avisa_al_administrador(): void
    {
        $respuesta = $this->solicitar(['nombre' => 'Inventado']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $fila = $this->pdo()->query('SELECT nombre, clase_id, materia_id, equipo_id, estado FROM solicitudes_ingreso')->fetch();
        $this->assertSame(['nombre' => 'Nora NoInscrita', 'clase_id' => $this->clase, 'materia_id' => 'M1', 'equipo_id' => 'PC-1', 'estado' => 'pendiente'], $fila);
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM notificaciones_admin WHERE tipo = 'solicitud'"));
    }

    public function test_no_se_repite_una_solicitud_pendiente(): void
    {
        $this->solicitar();

        $this->assertSame(409, $this->solicitar()->estado);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM solicitudes_ingreso'));
    }

    public function test_sin_clase_abierta_no_hay_solicitud(): void
    {
        $this->pdo()->exec('UPDATE clases SET cerrada_en = NOW()');

        $this->assertSame(409, $this->solicitar()->estado);
    }

    public function test_la_aprobacion_temporal_lo_registra_en_la_clase(): void
    {
        $id = $this->solicitar()->json()['id'];

        $respuesta = $this->resolver($id, 'aprobado_temporal');

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame($this->clase, (int)$this->valor("SELECT clase_id FROM asistencias WHERE alumno_id = '23303009'"));
        $this->assertSame('PC-1', $this->valor("SELECT equipo_id FROM asistencias WHERE alumno_id = '23303009'"));
        $this->assertSame('[]', $this->valor("SELECT materias FROM alumnos WHERE matricula = '23303009'"));
        $this->assertSame('aprobado_temporal', $this->valor('SELECT estado FROM solicitudes_ingreso WHERE id = ?', [$id]));
    }

    public function test_si_su_equipo_ya_se_ocupo_entra_con_el_suyo_propio(): void
    {
        $id = $this->solicitar()->json()['id'];
        $this->pdo()->exec("UPDATE equipos SET en_uso = 1 WHERE id = 'PC-1'");

        $this->assertSame(200, $this->resolver($id, 'aprobado_temporal')->estado);
        $this->assertSame('personal', $this->valor("SELECT equipo_id FROM asistencias WHERE alumno_id = '23303009'"));
    }

    public function test_la_aprobacion_permanente_lo_inscribe_en_la_materia(): void
    {
        $id = $this->solicitar()->json()['id'];

        $this->assertSame(200, $this->resolver($id, 'aprobado_permanente')->estado);

        $this->assertSame(['M1'], json_decode($this->valor("SELECT materias FROM alumnos WHERE matricula = '23303009'"), true));
        // Ya inscrito, se registra solo.
        $entrada = $this->anonimo()->post('asistencias.php?action=entrada', ['AlumnoId' => '23303009', 'Equipo' => 'PC-2', 'Laboratorio' => 'programacion']);
        $this->assertSame(200, $entrada->estado, (string)$entrada);
    }

    public function test_rechazar_no_registra_nada(): void
    {
        $id = $this->solicitar()->json()['id'];

        $this->resolver($id, 'rechazada');

        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
        $this->assertSame('rechazada', $this->valor('SELECT estado FROM solicitudes_ingreso WHERE id = ?', [$id]));
    }

    public function test_una_solicitud_resuelta_no_se_vuelve_a_resolver(): void
    {
        $id = $this->solicitar()->json()['id'];
        $this->resolver($id, 'rechazada');

        $this->assertSame(409, $this->resolver($id, 'aprobado_temporal')->estado);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
    }

    public function test_si_la_clase_ya_cerro_la_aprobacion_temporal_no_procede(): void
    {
        $id = $this->solicitar()->json()['id'];
        $this->pdo()->exec('UPDATE clases SET cerrada_en = NOW()');

        $this->assertSame(409, $this->resolver($id, 'aprobado_temporal')->estado);
        // Sigue pendiente: nada quedó a medias.
        $this->assertSame('pendiente', $this->valor('SELECT estado FROM solicitudes_ingreso WHERE id = ?', [$id]));
    }
}
