<?php

namespace Tests\Api;

/** Avisos, eventos, bitácora y estadísticas. */
final class ConsultasTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_el_aviso_de_una_falla_muestra_el_estado_actual_del_reporte(): void
    {
        $reporte = $this->ok($this->con('docente')->post('equipos/' . $this->equipo('PC-3') . '/reportes', ['descripcion' => 'No enciende']), 201);
        $laboratorista = $this->con('laboratorista');
        $this->ok($laboratorista->put("reportes-de-equipo/{$reporte['id']}", ['estado' => 'resuelto']));

        $avisos = $this->ok($laboratorista->get('avisos?tipo=equipo'));

        $this->assertSame(['resuelto', 'PC-3'], [$avisos[0]['reporte']['estado'], $avisos[0]['reporte']['equipo']['etiqueta']]);
        $this->assertSame('programacion', $avisos[0]['laboratorio']['clave']);
        $this->assertSame(403, $this->con('docente')->get('avisos')->estado);
    }

    public function test_los_avisos_se_marcan_leidos_uno_por_uno_o_todos(): void
    {
        $a = $this->insertar('avisos', ['tipo' => 'sistema', 'titulo' => 'Uno', 'laboratorio_id' => $this->laboratorio('programacion')]);
        $this->insertar('avisos', ['tipo' => 'sistema', 'titulo' => 'Dos', 'laboratorio_id' => $this->laboratorio('redes')]);
        $admin = $this->con('admin');

        $this->ok($admin->put("avisos/$a", ['leido' => true]), 204);
        $this->assertSame(['Dos'], array_column($this->ok($admin->get('avisos?leido=0')), 'titulo'));
        $this->assertSame(['marcados' => 1], $this->ok($admin->post('avisos/leidos', ['laboratorio' => 'redes'])));
        $this->assertSame([], $this->ok($admin->get('avisos?leido=0')));
    }

    public function test_el_laboratorista_lleva_los_eventos_y_los_demas_los_ven(): void
    {
        $laboratorista = $this->con('laboratorista');

        $evento = $this->ok($laboratorista->post('eventos', [
            'laboratorio' => 'programacion', 'nombre' => 'Hackatón', 'tipo' => 'Concurso', 'participantes' => 40, 'fecha' => '2026-11-20', 'hora' => '09:00',
        ]), 201);
        $editado = $this->ok($laboratorista->put("eventos/{$evento['id']}", ['resultados' => 'Ganó el equipo 3']));

        $this->assertSame(['Hackatón', '09:00', 'Ganó el equipo 3'], [$editado['nombre'], $editado['hora'], $editado['resultados']]);
        $this->assertSame(['Hackatón'], array_column($this->ok($this->con('docente')->get('eventos?laboratorio=programacion')), 'nombre'));
        $this->assertSame(403, $this->con('docente')->post('eventos', ['laboratorio' => 'programacion', 'nombre' => 'X'])->estado);
        $this->ok($laboratorista->delete("eventos/{$evento['id']}"), 204);
    }

    public function test_la_bitacora_se_consulta_con_filtros_y_no_la_ve_el_docente(): void
    {
        $this->con('docente');
        $this->con('laboratorista');
        $admin = $this->con('admin');
        $this->ok($admin->post('carreras', ['clave' => 'IGER', 'nombre' => 'Gestión Empresarial']), 201);

        $inicios = $this->ok($admin->get('bitacora?accion=iniciar_sesion'));
        $deCarreras = $this->ok($admin->get('bitacora?entidad=carreras&desde=' . date('Y-m-d')));
        $buscando = $this->ok($admin->get('bitacora?q=Laura'));

        $this->assertSame(3, $inicios['total']);
        $this->assertSame(['crear'], array_column($deCarreras['entradas'], 'accion'));
        $this->assertSame('Ada Admin', $deCarreras['entradas'][0]['usuario']);
        $this->assertSame(['Laura Laboratorista'], array_unique(array_column($buscando['entradas'], 'usuario')));
        $this->assertSame(403, $this->con('docente')->get('bitacora')->estado);
    }

    public function test_las_estadisticas_cuentan_lo_activo_y_lo_que_pasa_en_el_laboratorio(): void
    {
        $curso = $this->crearCurso();
        $this->crearAlumno('23303001');
        $inactivo = $this->crearAlumno('23303002');
        $this->pdo()->exec("UPDATE alumnos SET activo = FALSE WHERE id = $inactivo");
        $this->registrarEntrada($this->abrirClase($curso), $this->crearAlumno('23303003'), $this->equipo('PC-1'));
        $this->pdo()->exec("UPDATE equipos SET fuera_de_servicio = TRUE WHERE etiqueta = 'PC-2'");

        $estadisticas = $this->ok($this->con('docente')->get('estadisticas?laboratorio=programacion'));

        $this->assertSame([2, 1, 1, 1, 1], [
            $estadisticas['alumnos'], $estadisticas['docentes'], $estadisticas['laboratoristas'], $estadisticas['administradores'], $estadisticas['cursosDelPeriodo'],
        ]);
        $this->assertSame(['total' => 25, 'ocupados' => 1, 'fueraDeServicio' => 1, 'libres' => 23], $estadisticas['laboratorio']['equipos']);
        $this->assertSame([1, 1, 1], [$estadisticas['laboratorio']['personasDentro'], $estadisticas['laboratorio']['clasesAbiertas'], $estadisticas['laboratorio']['asistenciasHoy']]);
    }
}
