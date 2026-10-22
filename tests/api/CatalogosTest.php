<?php

namespace Tests\Api;

/** Laboratorios, carreras y periodos. */
final class CatalogosTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_los_laboratorios_se_ven_sin_sesion(): void
    {
        $laboratorios = $this->ok($this->anonimo()->get('laboratorios'));

        $this->assertSame(['laboratorio_a', 'laboratorio_c', 'redes', 'programacion'], array_column($laboratorios, 'clave'));
        $this->assertSame('Taller de Programación', $laboratorios[3]['nombre']);
    }

    public function test_un_laboratorio_desactivado_ya_no_aparece(): void
    {
        $admin = $this->con('admin');
        $this->ok($admin->put('laboratorios/' . $this->laboratorio('redes'), ['activo' => false]));

        $this->assertNotContains('redes', array_column($this->ok($this->anonimo()->get('laboratorios')), 'clave'));
        $this->assertContains('redes', array_column($this->ok($admin->get('laboratorios?todos=1')), 'clave'));
    }

    public function test_la_clave_de_un_laboratorio_nuevo_solo_lleva_minusculas(): void
    {
        $admin = $this->con('admin');

        $mal = $admin->post('laboratorios', ['clave' => 'Laboratorio B', 'nombre' => 'Laboratorio B']);
        $bien = $this->ok($admin->post('laboratorios', ['clave' => 'laboratorio_b', 'nombre' => 'Laboratorio B']), 201);

        $this->assertSame([400, 'clave'], [$mal->estado, $mal->json()['campo']]);
        $this->assertSame('laboratorio_b', $bien['clave']);
        $this->assertSame('crear', $this->valor("SELECT accion FROM bitacora WHERE entidad = 'laboratorios'"));
    }

    public function test_las_carreras_se_crean_editan_y_borran(): void
    {
        $admin = $this->con('admin');

        $nueva = $this->ok($admin->post('carreras', ['clave' => 'iger', 'nombre' => 'Ingeniería en Gestión Empresarial']), 201);
        $this->assertSame('IGER', $nueva['clave']);

        $editada = $this->ok($admin->put("carreras/{$nueva['id']}", ['nombre' => 'Ingeniería en Gestión Empresarial (plan 2010)']));
        $this->assertSame('Ingeniería en Gestión Empresarial (plan 2010)', $editada['nombre']);
        $this->assertSame('Campos: nombre', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'editar' AND entidad = 'carreras'"));

        $this->ok($admin->delete("carreras/{$nueva['id']}"), 204);
        $this->assertNotContains('IGER', array_column($this->ok($admin->get('carreras')), 'clave'));
    }

    public function test_una_carrera_con_alumnos_no_se_borra_se_desactiva(): void
    {
        $admin = $this->con('admin');
        $this->crearAlumno(carrera: 'ICIV');

        $respuesta = $admin->delete('carreras/' . $this->carrera('ICIV'));

        $this->assertSame(409, $respuesta->estado);
        $this->assertStringContainsString('Desactívalo', $respuesta->json()['error']);
    }

    public function test_una_clave_de_carrera_repetida_responde_409(): void
    {
        $respuesta = $this->con('admin')->post('carreras', ['clave' => 'ISIC', 'nombre' => 'Otra']);

        $this->assertSame([409, 'Ya existe un registro con esos datos'], [$respuesta->estado, $respuesta->json()['error']]);
    }

    public function test_el_periodo_actual_es_el_que_incluye_hoy(): void
    {
        $admin = $this->con('admin');
        $this->assertSame(404, $admin->get('periodos/actual')->estado);

        $this->ok($admin->post('periodos', ['nombre' => 'Pasado', 'fechaInicio' => '2020-01-01', 'fechaFin' => '2020-06-30']), 201);
        $actual = $this->ok($admin->post('periodos', [
            'nombre' => 'Este', 'fechaInicio' => date('Y-m-d', strtotime('-10 days')), 'fechaFin' => date('Y-m-d', strtotime('+10 days')),
        ]), 201);

        $this->assertTrue($actual['actual']);
        $this->assertSame('Este', $this->ok($this->con('docente')->get('periodos/actual'))['nombre']);
        $this->assertSame(['Este', 'Pasado'], array_column($this->ok($admin->get('periodos')), 'nombre'));
    }

    public function test_dos_periodos_no_se_enciman(): void
    {
        $admin = $this->con('admin');
        $this->ok($admin->post('periodos', ['nombre' => 'AGO-DIC 2026', 'fechaInicio' => '2026-08-17', 'fechaFin' => '2026-12-18']), 201);

        $encimado = $admin->post('periodos', ['nombre' => 'Otro', 'fechaInicio' => '2026-12-01', 'fechaFin' => '2027-06-30']);
        $alReves = $admin->post('periodos', ['nombre' => 'Otro', 'fechaInicio' => '2027-06-30', 'fechaFin' => '2027-01-01']);
        $fechaMala = $admin->post('periodos', ['nombre' => 'Otro', 'fechaInicio' => '2027-02-30', 'fechaFin' => '2027-06-30']);

        $this->assertSame([409, 'Las fechas se enciman con el periodo AGO-DIC 2026'], [$encimado->estado, $encimado->json()['error']]);
        $this->assertSame(400, $alReves->estado);
        $this->assertSame([400, 'fechaInicio'], [$fechaMala->estado, $fechaMala->json()['campo']]);
    }

    public function test_editar_un_periodo_no_choca_consigo_mismo(): void
    {
        $admin = $this->con('admin');
        $periodo = $this->ok($admin->post('periodos', ['nombre' => 'AGO-DIC 2026', 'fechaInicio' => '2026-08-17', 'fechaFin' => '2026-12-18']), 201);

        $editado = $this->ok($admin->put("periodos/{$periodo['id']}", ['nombre' => 'Agosto-Diciembre 2026', 'fechaInicio' => '2026-08-10', 'fechaFin' => '2026-12-18']));

        $this->assertSame('2026-08-10', $editado['fechaInicio']);
    }
}
