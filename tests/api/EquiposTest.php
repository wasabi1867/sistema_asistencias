<?php

namespace Tests\Api;

final class EquiposTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    private function estados(array $equipos): array
    {
        return array_column($equipos, 'estado', 'etiqueta');
    }

    public function test_sin_sesion_se_ve_el_estado_de_cada_equipo_pero_no_quien_lo_usa(): void
    {
        $curso = $this->crearCurso();
        $alumno = $this->crearAlumno();
        $this->registrarEntrada($this->abrirClase($curso), $alumno, $this->equipo('PC-2'));
        $this->pdo()->exec("UPDATE equipos SET fuera_de_servicio = TRUE WHERE etiqueta = 'PC-3'");

        $publicos = $this->ok($this->anonimo()->get('laboratorios/programacion/equipos'));
        $delPersonal = $this->ok($this->con('laboratorista')->get('laboratorios/programacion/equipos'));

        $this->assertCount(25, $publicos);
        // PC-2 antes que PC-10.
        $this->assertSame(['PC-1', 'PC-2', 'PC-3', 'PC-4'], array_slice(array_column($publicos, 'etiqueta'), 0, 4));
        $this->assertSame(['PC-1' => 'libre', 'PC-2' => 'ocupado', 'PC-3' => 'fuera_de_servicio'], array_slice($this->estados($publicos), 0, 3));
        $this->assertArrayNotHasKey('ocupadoPor', $publicos[1]);
        $this->assertSame('23303001', $delPersonal[1]['ocupadoPor']['matricula']);
    }

    public function test_un_laboratorio_que_no_existe_responde_404(): void
    {
        $this->assertSame(404, $this->anonimo()->get('laboratorios/inventado/equipos')->estado);
    }

    public function test_configurar_deja_pc_1_a_pc_n_y_reactiva_los_retirados(): void
    {
        // PC-21 tiene historial; PC-22 no.
        $pc21 = $this->equipo('PC-21');
        $this->registrarEntrada($this->abrirClase($this->crearCurso()), $this->crearAlumno(), $pc21);
        $this->pdo()->exec('UPDATE asistencias SET hora_salida = hora_entrada');
        $laboratorista = $this->con('laboratorista');

        $menos = $this->ok($laboratorista->post('laboratorios/programacion/equipos/configurar', ['cantidad' => 20]));
        $this->assertSame(['equipos' => 20, 'agregados' => 0, 'retirados' => 5], $menos);
        $this->assertSame(0, (int)$this->valor('SELECT activo FROM equipos WHERE id = ?', [$pc21]));
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM equipos WHERE etiqueta = 'PC-22'"));

        $mas = $this->ok($laboratorista->post('laboratorios/programacion/equipos/configurar', ['cantidad' => 22]));

        $this->assertSame(['equipos' => 22, 'agregados' => 2, 'retirados' => 0], $mas);
        // La PC-21 que volvió es la misma, con su historial.
        $this->assertSame($pc21, $this->equipo('PC-21'));
        $this->assertCount(22, $this->ok($this->anonimo()->get('laboratorios/programacion/equipos')));
        $this->assertSame(2, (int)$this->valor("SELECT COUNT(*) FROM avisos WHERE tipo = 'sistema'"));
        $this->assertSame(2, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'configurar_equipos'"));
    }

    public function test_configurar_no_retira_un_equipo_en_uso(): void
    {
        $this->registrarEntrada($this->abrirClase($this->crearCurso()), $this->crearAlumno(), $this->equipo('PC-25'));

        $respuesta = $this->con('laboratorista')->post('laboratorios/programacion/equipos/configurar', ['cantidad' => 20]);

        $this->assertSame([409, 'equipo_ocupado'], [$respuesta->estado, $respuesta->json()['codigo']]);
        $this->assertCount(25, $this->ok($this->anonimo()->get('laboratorios/programacion/equipos')));
    }

    public function test_un_equipo_con_historial_se_retira_y_su_etiqueta_queda_libre(): void
    {
        $pc5 = $this->equipo('PC-5');
        $curso = $this->crearCurso();
        $alumno = $this->crearAlumno();
        $this->registrarEntrada($this->abrirClase($curso), $alumno, $pc5);
        $this->pdo()->exec('UPDATE asistencias SET hora_salida = hora_entrada');
        $admin = $this->con('admin');

        $this->ok($admin->delete("equipos/$pc5"), 204);
        $otro = $this->ok($admin->post('laboratorios/programacion/equipos', ['etiqueta' => 'PC-5']), 201);

        $this->assertSame(0, (int)$this->valor('SELECT activo FROM equipos WHERE id = ?', [$pc5]));
        $this->assertNotSame($pc5, $otro['id']);
        $this->assertSame(409, $admin->post('laboratorios/programacion/equipos', ['etiqueta' => 'PC-5'])->estado);
    }

    public function test_el_laboratorista_lo_marca_fuera_de_servicio_pero_solo_el_administrador_cambia_la_etiqueta(): void
    {
        $pc1 = $this->equipo('PC-1');
        $laboratorista = $this->con('laboratorista');

        $editado = $this->ok($laboratorista->put("equipos/$pc1", ['fueraDeServicio' => true, 'notas' => 'No enciende']));

        $this->assertSame([true, 'No enciende'], [$editado['fueraDeServicio'], $editado['notas']]);
        $this->assertSame(403, $laboratorista->put("equipos/$pc1", ['etiqueta' => 'PC-100'])->estado);
        $this->assertSame('PC-1. Campos: fuera_de_servicio, notas', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'editar' AND entidad = 'equipos'"));
    }

    public function test_el_docente_reporta_una_falla_y_el_laboratorista_la_resuelve(): void
    {
        $pc4 = $this->equipo('PC-4');

        $reporte = $this->ok($this->con('docente')->post("equipos/$pc4/reportes", ['descripcion' => 'La pantalla parpadea']), 201);
        $this->assertSame(['reportado', 'Diego Docente'], [$reporte['estado'], $reporte['reportadoPor']]);
        $this->assertSame('fuera_de_servicio', $this->estados($this->ok($this->anonimo()->get('laboratorios/programacion/equipos')))['PC-4']);
        $this->assertSame(['equipo', 'alta'], array_values($this->fila('SELECT tipo, prioridad FROM avisos WHERE reporte_id = ?', [$reporte['id']])));

        $laboratorista = $this->con('laboratorista');
        $this->ok($laboratorista->put("reportes-de-equipo/{$reporte['id']}", ['estado' => 'en_proceso', 'comentario' => 'Se pidió cable']));
        $resuelto = $this->ok($laboratorista->put("reportes-de-equipo/{$reporte['id']}", ['estado' => 'resuelto']));

        $this->assertNotNull($resuelto['resueltoEn']);
        $this->assertSame('libre', $this->estados($this->ok($this->anonimo()->get('laboratorios/programacion/equipos')))['PC-4']);
        $this->assertSame(['La pantalla parpadea'], array_column($this->ok($laboratorista->get("reportes-de-equipo?equipo=$pc4")), 'descripcion'));
        $this->assertSame([], $this->ok($laboratorista->get('reportes-de-equipo?estado=abiertos')));
    }

    public function test_el_equipo_vuelve_a_servicio_hasta_que_se_resuelven_todos_sus_reportes(): void
    {
        $pc4 = $this->equipo('PC-4');
        $docente = $this->con('docente');
        $primero = $this->ok($docente->post("equipos/$pc4/reportes", ['descripcion' => 'No enciende']), 201);
        $this->ok($docente->post("equipos/$pc4/reportes", ['descripcion' => 'Tampoco el teclado']), 201);

        $this->ok($this->con('laboratorista')->put("reportes-de-equipo/{$primero['id']}", ['estado' => 'resuelto']));

        $this->assertSame(1, (int)$this->valor('SELECT fuera_de_servicio FROM equipos WHERE id = ?', [$pc4]));
    }

    public function test_un_reporte_menor_no_saca_al_equipo_de_servicio(): void
    {
        $pc4 = $this->equipo('PC-4');

        $this->ok($this->con('docente')->post("equipos/$pc4/reportes", ['descripcion' => 'Falta una tecla', 'fueraDeServicio' => false]), 201);

        $this->assertSame(0, (int)$this->valor('SELECT fuera_de_servicio FROM equipos WHERE id = ?', [$pc4]));
    }
}
