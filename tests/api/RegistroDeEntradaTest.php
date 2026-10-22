<?php

namespace Tests\Api;

final class RegistroDeEntradaTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearAlumno('23303001', 'Ana', 'Alumna');
        $this->crearEquipos();
    }

    private function registrar(array $datos): Respuesta
    {
        return $this->anonimo()->post('asistencias.php?action=entrada', $datos);
    }

    public function test_los_datos_del_alumno_salen_de_la_base_y_no_del_navegador(): void
    {
        $respuesta = $this->registrar([
            'AlumnoId' => '23303001', 'Equipo' => 'PC-1',
            'Nombre' => 'Nombre', 'Apellido' => 'Inventado', 'Carrera' => 'Otra',
        ]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $fila = $this->pdo()->query("SELECT nombre, apellido, carrera, grupo FROM asistencias")->fetch();
        $this->assertSame(['nombre' => 'Ana', 'apellido' => 'Alumna', 'carrera' => 'Sistemas', 'grupo' => 'A'], $fila);
    }

    public function test_una_matricula_que_no_existe_no_registra_nada(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '99999999', 'Equipo' => 'PC-1', 'Nombre' => 'Alguien', 'Apellido' => 'Más']);

        $this->assertSame(404, $respuesta->estado);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
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

        $equipo = $this->pdo()->query("SELECT en_uso, usuario_actual FROM equipos WHERE id = 'PC-1'")->fetch();
        $this->assertSame(1, (int)$equipo['en_uso']);
        $this->assertSame('Ana Alumna (23303001)', $equipo['usuario_actual']);
    }

    public function test_el_equipo_personal_no_se_marca_en_uso(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'personal']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame(0, (int)$this->valor("SELECT en_uso FROM equipos WHERE id = 'personal'"));
    }

    public function test_el_invitado_da_su_nombre(): void
    {
        $sinNombre = $this->registrar(['AlumnoId' => 'INV-1', 'Equipo' => 'personal', 'TipoUsuario' => 'invitado']);
        $this->assertSame(400, $sinNombre->estado);

        $conNombre = $this->registrar(['AlumnoId' => 'INV-1', 'Equipo' => 'personal', 'TipoUsuario' => 'invitado', 'Nombre' => 'Iván', 'Apellido' => 'Invitado']);
        $this->assertSame(200, $conNombre->estado, (string)$conNombre);
        $this->assertSame('Iván', $this->valor("SELECT nombre FROM asistencias WHERE alumno_id = 'INV-1'"));
    }

    public function test_el_docente_se_busca_en_la_tabla_de_docentes(): void
    {
        $this->crearCuentas();

        $respuesta = $this->registrar(['AlumnoId' => self::MATRICULA_DOCENTE, 'Equipo' => 'personal', 'TipoUsuario' => 'docente', 'Nombre' => 'Otro']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame('Diego', $this->valor('SELECT nombre FROM asistencias WHERE alumno_id = ?', [self::MATRICULA_DOCENTE]));
    }

    public function test_rechaza_un_tipo_de_usuario_desconocido(): void
    {
        $respuesta = $this->registrar(['AlumnoId' => '23303001', 'Equipo' => 'PC-1', 'TipoUsuario' => 'administrador']);

        $this->assertSame(400, $respuesta->estado);
    }
}
