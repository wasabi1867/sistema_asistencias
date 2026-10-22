<?php

namespace Tests\Api;

/**
 * Lo que la API agregó al pasar las pantallas de Firebase a la API: prácticas con
 * fecha, configurar los equipos de un laboratorio, avisos de equipos para el
 * laboratorista y cierre automático de la clase.
 */
final class PantallasTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_el_docente_crea_y_edita_las_practicas_de_su_materia_con_fecha(): void
    {
        $this->crearMateria('M1');
        $docente = $this->con('docente');

        $creada = $docente->post('materias.php?action=crear_practica', ['materia_id' => 'M1', 'titulo' => 'Formularios', 'fecha' => '2026-11-03']);
        $id = $creada->json()['id'];
        $editada = $docente->post('materias.php?action=editar_practica', ['id' => $id, 'titulo' => 'Formularios con validación', 'duracion' => '2 h', 'fecha' => '2026-11-04']);

        $this->assertSame(200, $editada->estado, (string)$editada);
        $practica = $this->pdo()->query("SELECT titulo, duracion, fecha FROM practicas WHERE id = $id")->fetch();
        $this->assertSame(['titulo' => 'Formularios con validación', 'duracion' => '2 h', 'fecha' => '2026-11-04'], $practica);
        $this->assertSame(400, $docente->post('materias.php?action=editar_practica', ['id' => $id, 'titulo' => 'x', 'fecha' => '04/11/2026'])->estado);
    }

    public function test_configurar_los_equipos_reemplaza_la_lista(): void
    {
        $this->crearEquipos('programacion', 3);
        $laboratorista = $this->con('laboratorista');

        $respuesta = $laboratorista->post('equipos.php?action=configurar', ['laboratorio' => 'programacion', 'cantidad' => 5]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame(3, $respuesta->json()['anteriores']);
        $ids = $this->pdo()->query("SELECT id FROM equipos WHERE laboratorio = 'programacion' ORDER BY LENGTH(id), id")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['PC-1', 'PC-2', 'PC-3', 'PC-4', 'PC-5', 'personal'], $ids);
    }

    public function test_no_se_configuran_los_equipos_con_alguno_en_uso(): void
    {
        $this->crearEquipos('programacion', 3);
        $this->pdo()->exec("UPDATE equipos SET en_uso = 1 WHERE id = 'PC-2'");

        $respuesta = $this->con('admin')->post('equipos.php?action=configurar', ['laboratorio' => 'programacion', 'cantidad' => 10]);

        $this->assertSame(409, $respuesta->estado);
        $this->assertSame(4, (int)$this->valor("SELECT COUNT(*) FROM equipos WHERE laboratorio = 'programacion'"));
    }

    public function test_los_avisos_de_equipos_muestran_el_estado_actual_del_equipo(): void
    {
        $this->crearEquipos();
        $this->insertar('notificaciones_admin', ['tipo' => 'equipo', 'titulo' => 'PC-2 fuera de servicio', 'mensaje' => 'No enciende', 'laboratorio' => 'programacion', 'datos' => json_encode(['equipoId' => 'PC-2'])]);
        $this->insertar('notificaciones_admin', ['tipo' => 'sistema', 'titulo' => 'Otro aviso', 'mensaje' => '...', 'laboratorio' => 'programacion']);
        $laboratorista = $this->con('laboratorista');

        $laboratorista->post('equipos.php', ['id' => 'PC-2', 'laboratorio' => 'programacion', 'estadoReparacion' => 'en_proceso', 'comentarioEstado' => 'Se cambió la fuente']);
        $avisos = $laboratorista->get('notificaciones.php?laboratorio=programacion&tipo=equipo')->json()['data'];

        $this->assertCount(1, $avisos);
        $this->assertSame('en_proceso', $avisos[0]['estadoEquipo']);
        $this->assertSame('Se cambió la fuente', $avisos[0]['comentarioEstado']);
        // Los demás avisos siguen siendo solo del administrador.
        $this->assertSame(403, $laboratorista->get('notificaciones.php')->estado);
    }

    public function test_un_aviso_vuelve_a_no_leido(): void
    {
        $this->insertar('notificaciones_admin', ['id' => 7, 'tipo' => 'sistema', 'titulo' => 'Aviso', 'mensaje' => '...', 'leida' => 1]);

        $this->con('admin')->put('notificaciones.php', ['id' => 7, 'leida' => false]);

        $this->assertSame(0, (int)$this->valor('SELECT leida FROM notificaciones_admin WHERE id = 7'));
    }

    public function test_las_estadisticas_cuentan_las_cuentas_del_personal(): void
    {
        $datos = $this->con('admin')->get('stats.php')->json()['data'];

        $this->assertSame(1, $datos['laboratoristas']);
        $this->assertSame(1, $datos['administradores']);
        $this->assertSame(1, $datos['docentes']);
    }

    public function test_el_equipo_propio_sirve_en_un_laboratorio_sin_equipos(): void
    {
        $this->crearMateria('M1');
        $this->crearAlumno('23303001', 'Ana', 'Alumna', ['M1']);
        $this->abrirClase('laboratorio_a');

        $respuesta = $this->anonimo()->post('asistencias.php?action=entrada', ['AlumnoId' => '23303001', 'Equipo' => 'personal', 'Laboratorio' => 'laboratorio_a']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
    }

    public function test_si_el_laboratorista_cierra_la_pestana_la_clase_de_invitado_queda_como_automatica(): void
    {
        $id = $this->abrirClase('programacion', 'invitado', null, null);

        $respuesta = $this->con('laboratorista')->post('clases.php?action=cerrar', ['id' => $id, 'automatico' => true]);

        $this->assertSame('automatico', $respuesta->json()['data']['cierre']);
    }
}
