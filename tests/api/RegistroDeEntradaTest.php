<?php

namespace Tests\Api;

final class RegistroDeEntradaTest extends PruebaDeApi
{
    private int $clase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->crearMateria('M1');
        $this->crearAlumno('23303001', 'Ana', 'Alumna', ['M1']);
        $this->crearAlumno('23303002', 'Beto', 'Bautista', ['M1']);
        $this->crearAlumno('23303009', 'Nora', 'NoInscrita', []);
        $this->crearEquipos();
        $this->crearEquipos('redes');
        $this->clase = $this->abrirClase();
    }

    private function registrar(array $datos): Respuesta
    {
        return $this->anonimo()->post('asistencias.php?action=entrada', $datos + ['Laboratorio' => 'programacion']);
    }

    public function test_los_datos_del_alumno_salen_de_la_base_y_no_del_navegador(): void
    {
        $respuesta = $this->registrar([
            'AlumnoId' => '23303001', 'Equipo' => 'PC-1',
            'Nombre' => 'Nombre', 'Apellido' => 'Inventado', 'Carrera' => 'Otra',
        ]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame('Ana Alumna', $respuesta->json()['nombre']);
        $fila = $this->pdo()->query("SELECT nombre, apellido, carrera, grupo, clase_id, materia_id FROM asistencias")->fetch();
        $this->assertSame(['nombre' => 'Ana', 'apellido' => 'Alumna', 'carrera' => 'Sistemas', 'grupo' => 'A', 'clase_id' => $this->clase, 'materia_id' => 'M1'], $fila);
    }

    public function test_sin_clase_abierta_nadie_se_registra(): void
    {
        $this->pdo()->exec("UPDATE clases SET cerrada_en = NOW()");

        $respuesta = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1']);

        $this->assertSame(409, $respuesta->estado);
        $this->assertStringContainsString('No hay ninguna clase iniciada', $respuesta->json()['error']);
    }

    public function test_el_alumno_no_inscrito_recibe_la_opcion_de_pedir_ingreso(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '23303009', 'Equipo' => 'PC-1']);

        $this->assertSame(403, $respuesta->estado);
        $this->assertSame('no_inscrito', $respuesta->json()['codigo']);
        $this->assertSame($this->clase, $respuesta->json()['clase_id']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
    }

    public function test_una_matricula_que_no_existe_no_registra_nada(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '99999999', 'Equipo' => 'PC-1', 'Nombre' => 'Alguien', 'Apellido' => 'Más']);

        $this->assertSame(404, $respuesta->estado);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
    }

    public function test_no_se_registra_dos_veces_en_la_misma_clase(): void
    {
        $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1']);

        $otraVez = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-2']);

        $this->assertSame(409, $otraVez->estado);
        $this->assertSame('ya_registrado', $otraVez->json()['codigo']);
        $this->assertSame(0, (int)$this->valor("SELECT en_uso FROM equipos WHERE id = 'PC-2' AND laboratorio = 'programacion'"));
    }

    public function test_no_se_registra_en_dos_laboratorios_a_la_vez(): void
    {
        $this->crearMateria('M2', 'Redes', 'OTRO', 2);
        $this->pdo()->exec("UPDATE alumnos SET materias = '[\"M1\",\"M2\"]' WHERE matricula = '23303001'");
        $this->abrirClase('redes', 'regular', 'M2', 'OTRO');
        $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1']);

        $respuesta = $this->anonimo()->post('asistencias.php?action=entrada', ['AlumnoId' => '23303001', 'Equipo' => 'PC-1', 'Laboratorio' => 'redes']);

        $this->assertSame(409, $respuesta->estado);
        $this->assertStringContainsString('Taller de Programación', $respuesta->json()['error']);
    }

    public function test_un_equipo_ocupado_no_se_vuelve_a_asignar(): void
    {
        $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1']);

        $respuesta = $this->registrar(['AlumnoId' => '23303002', 'Equipo' => 'PC-1']);

        $this->assertSame(409, $respuesta->estado);
        $this->assertSame('equipo_ocupado', $respuesta->json()['codigo']);
    }

    public function test_varios_comparten_el_equipo_personal(): void
    {
        $this->assertSame(200, $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'personal'])->estado);
        $this->assertSame(200, $this->registrar(['AlumnoId' => '23303002', 'Equipo' => 'personal'])->estado);
        $this->assertSame(0, (int)$this->valor("SELECT en_uso FROM equipos WHERE id = 'personal' AND laboratorio = 'programacion'"));
    }

    public function test_no_crea_equipos_que_no_existen(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-INVENTADA']);

        $this->assertSame(400, $respuesta->estado);
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM equipos WHERE id = 'PC-INVENTADA'"));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
    }

    public function test_no_registra_en_un_equipo_fuera_de_servicio(): void
    {
        $this->pdo()->exec("UPDATE equipos SET fuera_de_servicio = 1 WHERE id = 'PC-2'");

        $respuesta = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-2']);

        $this->assertSame(400, $respuesta->estado);
        $this->assertStringContainsString('fuera de servicio', $respuesta->json()['error']);
    }

    public function test_marca_el_equipo_en_uso_con_quien_lo_ocupa(): void
    {
        $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1']);

        $equipo = $this->pdo()->query("SELECT en_uso, usuario_actual FROM equipos WHERE id = 'PC-1' AND laboratorio = 'programacion'")->fetch();
        $this->assertSame(1, (int)$equipo['en_uso']);
        $this->assertSame('Ana Alumna (23303001)', $equipo['usuario_actual']);
    }

    public function test_en_la_clase_regular_no_entran_invitados(): void
    {
        $respuesta = $this->registrar(['Equipo' => 'personal', 'TipoUsuario' => 'invitado', 'Nombre' => 'Iván', 'Apellido' => 'Invitado']);

        $this->assertSame(409, $respuesta->estado);
    }

    public function test_en_la_clase_de_invitado_entra_cualquiera(): void
    {
        $this->pdo()->exec("UPDATE clases SET cerrada_en = NOW()");
        $this->abrirClase('programacion', 'invitado', null, null);

        $sinNombre = $this->registrar(['Equipo' => 'personal', 'TipoUsuario' => 'invitado']);
        $invitado = $this->registrar(['Equipo' => 'personal', 'TipoUsuario' => 'invitado', 'Nombre' => 'Iván', 'Apellido' => 'Invitado']);
        $noInscrita = $this->registrar(['AlumnoId' => '23303009', 'Equipo' => 'PC-1']);

        $this->assertSame(400, $sinNombre->estado);
        $this->assertSame(200, $invitado->estado, (string)$invitado);
        $this->assertSame(200, $noInscrita->estado, (string)$noInscrita);
        $this->assertStringStartsWith('INV-', $this->valor("SELECT alumno_id FROM asistencias WHERE nombre = 'Iván'"));
    }

    public function test_si_hay_dos_clases_abiertas_se_registra_en_la_de_invitado(): void
    {
        $invitado = $this->abrirClase('programacion', 'invitado', null, null);

        $respuesta = $this->registrar(['AlumnoId' => '23303009', 'Equipo' => 'personal']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame($invitado, $respuesta->json()['clase']['id']);
    }

    public function test_el_docente_se_busca_en_la_tabla_de_docentes(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => self::MATRICULA_DOCENTE, 'Equipo' => 'personal', 'TipoUsuario' => 'docente', 'Nombre' => 'Otro']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame('Diego', $this->valor('SELECT nombre FROM asistencias WHERE alumno_id = ?', [self::MATRICULA_DOCENTE]));
    }

    public function test_rechaza_un_tipo_de_usuario_desconocido(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1', 'TipoUsuario' => 'administrador']);

        $this->assertSame(400, $respuesta->estado);
    }

    public function test_rechaza_un_laboratorio_desconocido(): void
    {
        $respuesta = $this->anonimo()->post('asistencias.php?action=entrada', ['AlumnoId' => '23303001', 'Equipo' => 'PC-1', 'Laboratorio' => 'cocina']);

        $this->assertSame(400, $respuesta->estado);
    }

    public function test_la_salida_pasa_al_historico_y_libera_el_equipo(): void
    {
        $id = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1'])->json()['id'];

        $respuesta = $this->con('docente')->post('asistencias.php?action=salida', ['id' => $id]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
        $historico = $this->pdo()->query("SELECT clase_id, nombre, apellido, materia, docente, equipo_id FROM asistencias_historico")->fetch();
        $this->assertSame(['clase_id' => $this->clase, 'nombre' => 'Ana', 'apellido' => 'Alumna', 'materia' => 'Programación Web', 'docente' => 'Diego Docente', 'equipo_id' => 'PC-1'], $historico);
        $this->assertSame(0, (int)$this->valor("SELECT en_uso FROM equipos WHERE id = 'PC-1' AND laboratorio = 'programacion'"));
    }

    public function test_quitar_una_entrada_equivocada_libera_el_equipo_y_queda_en_la_bitacora(): void
    {
        $id = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1'])->json()['id'];

        $this->con('docente')->delete("asistencias.php?id=$id");

        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias_historico'));
        $this->assertSame(0, (int)$this->valor("SELECT en_uso FROM equipos WHERE id = 'PC-1' AND laboratorio = 'programacion'"));
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM logs WHERE accion = 'Eliminar Asistencia'"));
    }
}
