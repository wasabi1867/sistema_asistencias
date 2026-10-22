<?php

namespace Tests\Api;

final class SolicitudesTest extends PruebaDeApi
{
    private int $curso;
    private int $clase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->curso = $this->crearCurso();
        $this->clase = $this->abrirClase($this->curso);
        $this->crearAlumno('23303002', 'Beto', 'Bueno');
    }

    private function solicitar(array $datos = []): Respuesta
    {
        return $this->anonimo()->post('solicitudes', $datos + [
            'laboratorio' => 'programacion', 'matricula' => '23303002', 'claseId' => $this->clase, 'equipo' => 'PC-5', 'motivo' => 'Recursé la materia',
        ]);
    }

    private function resolver(int $id, string $estado, string $rol = 'admin', array $extra = []): Respuesta
    {
        return $this->con($rol)->post("solicitudes/$id/resolver", ['estado' => $estado] + $extra);
    }

    public function test_el_alumno_no_inscrito_pide_entrar_y_se_avisa_al_administrador(): void
    {
        $solicitud = $this->ok($this->solicitar(), 201);

        $this->assertSame(['pendiente', '23303002', 'PC-5', 'Programación Web'], [
            $solicitud['estado'], $solicitud['alumno']['matricula'], $solicitud['equipo'], $solicitud['clase']['materia'],
        ]);
        $aviso = $this->fila('SELECT tipo, titulo, prioridad FROM avisos WHERE solicitud_id = ?', [$solicitud['id']]);
        $this->assertSame(['tipo' => 'solicitud', 'titulo' => 'Beto Bueno pide entrar a Programación Web 5A', 'prioridad' => 'alta'], $aviso);
    }

    public function test_no_se_repite_una_solicitud_pendiente(): void
    {
        $this->ok($this->solicitar(), 201);

        $otra = $this->solicitar();

        $this->assertSame([409, 'Ya enviaste una solicitud. Espera a que la revisen'], [$otra->estado, $otra->json()['error']]);
    }

    public function test_una_solicitud_necesita_una_matricula_existente_y_una_clase_abierta(): void
    {
        $this->assertSame(404, $this->solicitar(['matricula' => '99999999'])->estado);
        $this->ok($this->con('docente')->post("clases/$this->clase/cerrar"));
        $this->assertSame([409, 'clase_cerrada'], [$this->solicitar()->estado, $this->solicitar()->json()['codigo']]);
    }

    public function test_aprobada_por_esa_vez_entra_a_la_clase_con_la_computadora_que_eligio(): void
    {
        $solicitud = $this->ok($this->solicitar(), 201);

        $resuelta = $this->ok($this->resolver($solicitud['id'], 'aprobada_temporal', extra: ['comentario' => 'Adelante']));

        $this->assertSame(['aprobada_temporal', 'Ada Admin', 'Adelante'], [$resuelta['estado'], $resuelta['resueltaPor'], $resuelta['comentario']]);
        $asistencia = $this->fila('SELECT clase_id, equipo_id, solicitud_id FROM asistencias');
        $this->assertSame(['clase_id' => $this->clase, 'equipo_id' => $this->equipo('PC-5'), 'solicitud_id' => $solicitud['id']], $asistencia);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM inscripciones WHERE alumno_id = (SELECT id FROM alumnos WHERE matricula = ?)', ['23303002']));
        $this->assertSame(1, (int)$this->valor('SELECT leido FROM avisos WHERE solicitud_id = ?', [$solicitud['id']]));
    }

    public function test_si_su_computadora_se_ocupo_mientras_esperaba_entra_con_su_propio_equipo(): void
    {
        $solicitud = $this->ok($this->solicitar(), 201);
        $this->inscribir($this->curso, $ana = $this->crearAlumno('23303001'));
        $this->registrarEntrada($this->clase, $ana, $this->equipo('PC-5'));

        $this->ok($this->resolver($solicitud['id'], 'aprobada_temporal'));

        $this->assertNull($this->valor('SELECT equipo_id FROM asistencias WHERE solicitud_id = ?', [$solicitud['id']]));
    }

    public function test_aprobada_inscribiendolo_queda_en_el_curso(): void
    {
        $solicitud = $this->ok($this->solicitar(), 201);

        $this->ok($this->resolver($solicitud['id'], 'aprobada_permanente'));

        $this->assertSame($solicitud['id'], (int)$this->valor('SELECT solicitud_id FROM inscripciones WHERE curso_id = ?', [$this->curso]));
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
    }

    public function test_con_la_clase_cerrada_solo_se_puede_inscribir(): void
    {
        $porEsaVez = $this->ok($this->solicitar(), 201);
        $this->ok($this->con('docente')->post("clases/$this->clase/cerrar"));

        $temporal = $this->resolver($porEsaVez['id'], 'aprobada_temporal');
        $permanente = $this->ok($this->resolver($porEsaVez['id'], 'aprobada_permanente'));

        $this->assertSame([409, 'clase_cerrada'], [$temporal->estado, $temporal->json()['codigo']]);
        $this->assertSame('aprobada_permanente', $permanente['estado']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
    }

    public function test_rechazada_no_entra_y_no_se_resuelve_dos_veces(): void
    {
        $solicitud = $this->ok($this->solicitar(), 201);

        $this->ok($this->resolver($solicitud['id'], 'rechazada', extra: ['comentario' => 'No hay lugar']));
        $otraVez = $this->resolver($solicitud['id'], 'aprobada_temporal');

        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
        $this->assertSame([409, 'ya_resuelta'], [$otraVez->estado, $otraVez->json()['codigo']]);
    }

    public function test_las_de_una_clase_las_resuelve_el_administrador_y_las_de_fuera_de_clase_tambien_el_laboratorista(): void
    {
        $deClase = $this->ok($this->solicitar(), 201);
        $fuera = $this->ok($this->solicitar(['claseId' => null, 'laboratorio' => 'redes', 'equipo' => null, 'equipoPropio' => true, 'motivo' => 'Tarea']), 201);

        $this->assertSame(403, $this->resolver($deClase['id'], 'aprobada_temporal', 'laboratorista')->estado);
        $this->assertSame(403, $this->resolver($fuera['id'], 'aprobada_temporal', 'docente')->estado);
        $this->assertSame([400, 'estado'], [
            $this->resolver($fuera['id'], 'aprobada_permanente', 'laboratorista')->estado,
            $this->resolver($fuera['id'], 'aprobada_permanente', 'laboratorista')->json()['campo'],
        ]);
        $this->ok($this->resolver($fuera['id'], 'aprobada_temporal', 'laboratorista'));

        $this->assertNull($this->valor('SELECT clase_id FROM asistencias WHERE solicitud_id = ?', [$fuera['id']]));
        $this->assertSame('Beto Bueno pide usar el Laboratorio de Redes', $this->valor('SELECT titulo FROM avisos WHERE solicitud_id = ?', [$fuera['id']]));
    }

    public function test_el_personal_ve_las_solicitudes_y_las_marca_como_leidas(): void
    {
        $solicitud = $this->ok($this->solicitar(), 201);
        $laboratorista = $this->con('laboratorista');

        $pendientes = $this->ok($laboratorista->get('solicitudes?laboratorio=programacion&estado=pendiente'));
        $this->ok($laboratorista->put("solicitudes/{$solicitud['id']}", ['leida' => true]), 204);

        $this->assertSame([$solicitud['id']], array_column($pendientes, 'id'));
        $this->assertTrue($this->ok($laboratorista->get('solicitudes'))[0]['leida']);
    }
}
