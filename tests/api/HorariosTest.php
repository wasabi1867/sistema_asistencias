<?php

namespace Tests\Api;

final class HorariosTest extends PruebaDeApi
{
    private int $curso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->curso = $this->crearCurso($this->crearMateria('Programación Web'));
    }

    private function agregar(Cliente $cliente, array $horario): Respuesta
    {
        return $cliente->post('horarios', $horario + ['cursoId' => $this->curso, 'laboratorio' => 'programacion', 'dia' => 1, 'horaInicio' => '08:00', 'horaFin' => '10:00']);
    }

    public function test_el_laboratorista_arma_el_horario_y_todos_lo_ven(): void
    {
        $laboratorista = $this->con('laboratorista');

        $horario = $this->ok($this->agregar($laboratorista, []), 201);
        $this->ok($this->agregar($laboratorista, ['dia' => 3]), 201);

        $this->assertSame(['dia' => 1, 'horaInicio' => '08:00', 'horaFin' => '10:00'], array_intersect_key($horario, ['dia' => 0, 'horaInicio' => 0, 'horaFin' => 0]));
        $this->assertSame('Programación Web', $horario['curso']['materia']);
        $this->assertSame([1, 3], array_column($this->ok($this->con('docente')->get('horarios?laboratorio=programacion')), 'dia'));
        $this->assertSame([1, 3], array_column($this->ok($this->con('docente')->get('horarios?docente=yo')), 'dia'));
        $this->assertSame(403, $this->agregar($this->con('docente'), ['dia' => 5])->estado);
    }

    public function test_el_laboratorio_no_tiene_dos_cursos_a_la_misma_hora(): void
    {
        $laboratorista = $this->con('laboratorista');
        $this->ok($this->agregar($laboratorista, []), 201);
        $otroCurso = $this->crearCurso($this->crearMateria('Redes'), docente: $this->crearDocente('D200'));

        $encimado = $this->agregar($laboratorista, ['cursoId' => $otroCurso, 'horaInicio' => '09:00', 'horaFin' => '11:00']);
        $pegado = $this->agregar($laboratorista, ['cursoId' => $otroCurso, 'horaInicio' => '10:00', 'horaFin' => '11:00']);

        $this->assertSame(409, $encimado->estado);
        $this->assertSame('El laboratorio ya está ocupado el lunes de 08:00 a 10:00 por Programación Web 5A', $encimado->json()['error']);
        $this->assertSame(201, $pegado->estado, (string)$pegado);
    }

    public function test_el_docente_no_puede_estar_en_dos_laboratorios_a_la_vez(): void
    {
        $laboratorista = $this->con('laboratorista');
        $this->ok($this->agregar($laboratorista, []), 201);
        $otroCursoSuyo = $this->crearCurso($this->crearMateria('Redes'));

        $respuesta = $this->agregar($laboratorista, ['cursoId' => $otroCursoSuyo, 'laboratorio' => 'redes']);

        $this->assertSame([409, 'El docente ya tiene clase el lunes de 08:00 a 10:00 (Programación Web 5A)'], [$respuesta->estado, $respuesta->json()['error']]);
    }

    public function test_la_hora_de_fin_va_despues_de_la_de_inicio_y_el_dia_de_lunes_a_sabado(): void
    {
        $laboratorista = $this->con('laboratorista');

        $this->assertSame(400, $this->agregar($laboratorista, ['horaInicio' => '10:00', 'horaFin' => '09:00'])->estado);
        $this->assertSame(400, $this->agregar($laboratorista, ['dia' => 7])->estado);
        $this->assertSame(400, $this->agregar($laboratorista, ['horaInicio' => '25:00'])->estado);
    }

    public function test_al_editar_no_choca_consigo_mismo_y_se_borra(): void
    {
        $laboratorista = $this->con('laboratorista');
        $horario = $this->ok($this->agregar($laboratorista, []), 201);

        $editado = $this->ok($laboratorista->put("horarios/{$horario['id']}", ['horaFin' => '11:00']));
        $this->ok($laboratorista->delete("horarios/{$horario['id']}"), 204);

        $this->assertSame(['08:00', '11:00'], [$editado['horaInicio'], $editado['horaFin']]);
        $this->assertSame([], $this->ok($laboratorista->get('horarios')));
    }
}
