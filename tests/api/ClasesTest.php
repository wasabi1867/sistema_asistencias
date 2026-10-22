<?php

namespace Tests\Api;

final class ClasesTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->crearMateria('M1');
        $this->crearAlumno('23303001', 'Ana', 'Alumna', ['M1']);
        $this->crearAlumno('23303002', 'Beto', 'Bautista', ['M1']);
        $this->crearEquipos();
    }

    private function abrir(Cliente $cliente, array $datos = []): Respuesta
    {
        return $cliente->post('clases.php?action=abrir', $datos + ['laboratorio' => 'programacion', 'materia_id' => 'M1', 'practica_id' => 1]);
    }

    private function entrar(string $matricula, string $equipo): Respuesta
    {
        return $this->anonimo()->post('asistencias.php?action=entrada', ['AlumnoId' => $matricula, 'Equipo' => $equipo, 'Laboratorio' => 'programacion']);
    }

    public function test_el_docente_abre_la_clase_de_su_materia(): void
    {
        $respuesta = $this->abrir($this->con('docente'));

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $clase = $respuesta->json()['data'];
        $this->assertTrue($clase['abierta']);
        $this->assertSame('Programación Web', $clase['materia']);
        $this->assertSame('Práctica de Programación Web', $clase['practica']);
        $this->assertSame('Diego Docente', $clase['docente']);
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM notificaciones_admin WHERE tipo = 'asistencia'"));
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM logs WHERE accion = 'Iniciar Clase'"));
    }

    public function test_el_docente_no_abre_la_clase_de_una_materia_ajena(): void
    {
        $this->crearMateria('M2', 'Redes', 'OTRO', 2);

        $respuesta = $this->abrir($this->con('docente'), ['materia_id' => 'M2', 'practica_id' => 2]);

        $this->assertSame(403, $respuesta->estado);
    }

    public function test_hace_falta_una_practica_de_la_materia(): void
    {
        $this->crearMateria('M2', 'Redes', self::MATRICULA_DOCENTE, 2);

        $sinPractica = $this->abrir($this->con('docente'), ['practica_id' => null]);
        $deOtraMateria = $this->abrir($this->con('docente'), ['practica_id' => 2]);

        $this->assertSame(400, $sinPractica->estado);
        $this->assertSame(400, $deOtraMateria->estado);
    }

    public function test_una_sola_clase_regular_abierta_por_laboratorio(): void
    {
        $this->abrirClase('programacion', 'regular', 'M1', 'OTRO');

        $respuesta = $this->abrir($this->con('docente'));

        $this->assertSame(409, $respuesta->estado);
        $this->assertStringContainsString('Ya hay una clase abierta', $respuesta->json()['error']);
    }

    public function test_un_docente_no_tiene_dos_clases_abiertas(): void
    {
        $this->abrir($this->con('docente'));

        $respuesta = $this->abrir($this->con('docente'), ['laboratorio' => 'redes']);

        $this->assertSame(409, $respuesta->estado);
    }

    public function test_el_laboratorista_abre_la_clase_de_un_maestro_invitado(): void
    {
        $respuesta = $this->abrir($this->con('laboratorista'), [
            'tipo' => 'invitado', 'docente_id' => self::MATRICULA_DOCENTE, 'departamento' => 'Ciencias Básicas',
            'materia' => 'Cálculo', 'practica' => 'Graficación', 'grupo' => '3B',
        ]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $clase = $respuesta->json()['data'];
        $this->assertSame('invitado', $clase['tipo']);
        $this->assertSame('Diego Docente', $clase['docente']);
        $this->assertSame('Cálculo', $clase['materia']);
        $this->assertNull($clase['materiaId']);
    }

    public function test_la_pagina_de_registro_ve_las_clases_abiertas_sin_iniciar_sesion(): void
    {
        $this->abrir($this->con('docente'));

        $respuesta = $this->anonimo()->get('clases.php?abiertas=1&laboratorio=programacion');

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertCount(1, $respuesta->json()['data']);
        $this->assertSame('Programación Web', $respuesta->json()['data'][0]['materia']);
        $this->assertSame([], $this->anonimo()->get('clases.php?abiertas=1&laboratorio=redes')->json()['data']);
    }

    public function test_al_cerrar_registra_la_salida_de_todos_y_libera_los_equipos(): void
    {
        $docente = $this->con('docente');
        $id = $this->abrir($docente)->json()['data']['id'];
        $this->entrar('23303001', 'PC-1');
        $this->entrar('23303002', 'PC-2');

        $respuesta = $docente->post('clases.php?action=cerrar', ['id' => $id]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $clase = $respuesta->json()['data'];
        $this->assertFalse($clase['abierta']);
        $this->assertSame('normal', $clase['cierre']);
        $this->assertSame(2, $clase['totalAsistencias']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
        $this->assertSame(2, (int)$this->valor('SELECT COUNT(*) FROM asistencias_historico WHERE clase_id = ?', [$id]));
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM equipos WHERE en_uso = 1"));
        // Ya nadie puede registrarse.
        $this->assertSame(409, $this->entrar('23303001', 'PC-1')->estado);
    }

    public function test_la_clase_cerrada_conserva_quienes_asistieron(): void
    {
        $docente = $this->con('docente');
        $id = $this->abrir($docente)->json()['data']['id'];
        $this->entrar('23303002', 'PC-2');
        $this->entrar('23303001', 'PC-1');
        $docente->post('clases.php?action=cerrar', ['id' => $id]);

        $detalle = $docente->get("clases.php?id=$id")->json()['data'];
        $lista = $docente->get('clases.php?con_alumnos=1&estado=cerradas')->json()['data'];

        $this->assertSame(['Alumna', 'Bautista'], array_column($detalle['alumnos'], 'apellido'));
        $this->assertSame(['PC-1', 'PC-2'], array_column($detalle['alumnos'], 'equipo'));
        $this->assertCount(1, $lista);
        $this->assertCount(2, $lista[0]['alumnos']);
    }

    public function test_si_el_personal_cierra_la_clase_del_docente_queda_como_externo(): void
    {
        $id = $this->abrir($this->con('docente'))->json()['data']['id'];

        $respuesta = $this->con('laboratorista')->post('clases.php?action=cerrar', ['id' => $id]);

        $this->assertSame('externo', $respuesta->json()['data']['cierre']);
        // La pantalla del docente lo consulta para enterarse.
        $this->assertSame('externo', $this->con('docente')->get("clases.php?id=$id")->json()['data']['cierre']);
    }

    public function test_el_navegador_del_docente_la_cierra_sola_al_cerrar_la_pestana(): void
    {
        $docente = $this->con('docente');
        $id = $this->abrir($docente)->json()['data']['id'];

        $respuesta = $docente->post('clases.php?action=cerrar', ['id' => $id, 'automatico' => true]);

        $this->assertSame('automatico', $respuesta->json()['data']['cierre']);
    }

    public function test_un_docente_no_cierra_la_clase_de_otro(): void
    {
        $id = $this->abrirClase('programacion', 'regular', 'M1', 'OTRO');

        $respuesta = $this->con('docente')->post('clases.php?action=cerrar', ['id' => $id]);

        $this->assertSame(403, $respuesta->estado);
        $this->assertNull($this->valor('SELECT cerrada_en FROM clases WHERE id = ?', [$id]));
    }

    public function test_no_se_cierra_dos_veces(): void
    {
        $docente = $this->con('docente');
        $id = $this->abrir($docente)->json()['data']['id'];
        $docente->post('clases.php?action=cerrar', ['id' => $id]);

        $this->assertSame(409, $docente->post('clases.php?action=cerrar', ['id' => $id])->estado);
    }

    public function test_los_reportes_filtran_por_fecha_y_docente(): void
    {
        $id = $this->abrirClase();
        $this->pdo()->exec("UPDATE clases SET abierta_en = '2026-09-15 10:00:00', cerrada_en = '2026-09-15 12:00:00' WHERE id = $id");
        $this->abrirClase('redes', 'regular', 'M1', 'OTRO');
        $admin = $this->con('admin');

        $deSeptiembre = $admin->get('clases.php?desde=2026-09-01&hasta=2026-09-15')->json()['data'];
        $delDocente = $admin->get('clases.php?docente_id=' . self::MATRICULA_DOCENTE)->json()['data'];

        $this->assertSame([$id], array_column($deSeptiembre, 'id'));
        $this->assertSame([$id], array_column($delDocente, 'id'));
    }
}
