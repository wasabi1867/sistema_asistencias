<?php

namespace Tests\Api;

/** Cuentas de administrador, bitácora, eventos, equipos y cambios masivos. */
final class AdministracionTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_crear_un_administrador_y_que_pueda_entrar(): void
    {
        $respuesta = $this->con('admin')->post('administradores.php', [
            'matricula' => 'ADM02', 'nombre' => 'Olga', 'apellido' => 'Otra', 'email' => 'olga@pruebas.test', 'password' => 'contrasena-de-olga',
        ]);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $entrar = (new Cliente())->post('auth.php', ['email' => 'ADM02', 'password' => 'contrasena-de-olga', 'role' => 'admin']);
        $this->assertSame(200, $entrar->estado);
        $this->assertStringNotContainsString('password', $this->con('admin')->get('administradores.php')->texto);
    }

    public function test_no_se_repite_el_correo(): void
    {
        $respuesta = $this->con('admin')->post('administradores.php', ['nombre' => 'Copia', 'email' => self::CORREO_ADMIN, 'password' => 'contrasena-segura']);

        $this->assertSame(409, $respuesta->estado);
    }

    public function test_nadie_borra_su_propia_cuenta_ni_al_ultimo_administrador(): void
    {
        $admin = $this->con('admin');
        $miId = (int)$this->valor('SELECT id FROM administradores');
        $this->insertar('administradores', ['email' => 'otro@pruebas.test', 'nombre' => 'Otro', 'password' => 'x']);
        $otroId = (int)$this->pdo()->lastInsertId();

        $this->assertSame(409, $admin->delete("administradores.php?id=$miId")->estado);
        $this->assertSame(200, $admin->delete("administradores.php?id=$otroId")->estado);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM administradores'));
    }

    public function test_la_bitacora_se_consulta_de_lo_mas_reciente_a_lo_mas_viejo(): void
    {
        $admin = $this->con('admin');
        $this->crearAlumno('23303001');
        $admin->delete('alumnos.php?matricula=23303001');

        $bitacora = $admin->get('bitacora.php')->json()['data'];
        $soloBorrados = $admin->get('bitacora.php?accion=' . rawurlencode('Eliminar Alumno'))->json()['data'];

        $this->assertSame('Eliminar Alumno', $bitacora[0]['accion']);
        $this->assertSame('Admin de Pruebas (admin)', $bitacora[0]['usuario']);
        $this->assertSame('127.0.0.1', $bitacora[0]['ip']);
        $this->assertCount(1, $soloBorrados);
    }

    public function test_eventos_del_laboratorio(): void
    {
        $laboratorista = $this->con('laboratorista');

        $id = $laboratorista->post('eventos.php', ['nombre' => 'Hackatón', 'tipo' => 'Concurso', 'participantes' => '30', 'fecha' => '2026-11-20', 'hora' => '09:00'])->json()['id'];
        $laboratorista->post('eventos.php', ['id' => $id, 'nombre' => 'Hackatón ITSPP', 'participantes' => 32, 'fecha' => '2026-11-20']);

        $eventos = $laboratorista->get('eventos.php')->json()['data'];
        $this->assertCount(1, $eventos);
        $this->assertSame('Hackatón ITSPP', $eventos[0]['nombre']);
        $this->assertSame(32, $eventos[0]['participantes']);
        $this->assertSame(400, $laboratorista->post('eventos.php', ['nombre' => 'Mala fecha', 'fecha' => '20/11/2026'])->estado);
    }

    public function test_agregar_y_quitar_equipos(): void
    {
        $this->crearEquipos();
        $this->pdo()->exec("UPDATE equipos SET en_uso = 1 WHERE id = 'PC-1'");
        $admin = $this->con('admin');

        $this->assertSame(200, $admin->post('equipos.php?action=crear', ['id' => 'PC-4', 'laboratorio' => 'programacion'])->estado);
        $this->assertSame(409, $admin->post('equipos.php?action=crear', ['id' => 'PC-4', 'laboratorio' => 'programacion'])->estado);
        $this->assertSame(409, $admin->delete('equipos.php?id=PC-1&laboratorio=programacion')->estado);
        $this->assertSame(200, $admin->delete('equipos.php?id=PC-2&laboratorio=programacion')->estado);

        $ids = $this->pdo()->query("SELECT id FROM equipos ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['PC-1', 'PC-3', 'PC-4', 'personal'], $ids);
    }

    public function test_cambio_masivo_de_semestre_y_de_grupo(): void
    {
        $this->crearAlumno('A1');
        $this->crearAlumno('A2');
        $this->pdo()->exec("UPDATE alumnos SET grupo = 'B' WHERE matricula = 'A2'");
        $this->insertar('alumnos', ['matricula' => 'A3', 'nombre' => 'Otra', 'apellido' => 'Carrera', 'carrera' => 'Industrial', 'semestre' => '5']);
        $laboratorista = $this->con('laboratorista');

        $grupo = $laboratorista->post('alumnos.php?action=cambio_masivo', ['carrera' => 'Sistemas', 'semestre' => '5', 'grupo' => 'B', 'nuevo_grupo' => 'C']);
        $semestre = $laboratorista->post('alumnos.php?action=cambio_masivo', ['carrera' => 'Sistemas', 'semestre' => '5', 'nuevo_semestre' => '6']);

        $this->assertSame(1, $grupo->json()['actualizados']);
        $this->assertSame(2, $semestre->json()['actualizados']);
        $this->assertSame(['A1' => '6', 'A2' => '6', 'A3' => '5'], $this->pdo()->query("SELECT matricula, semestre FROM alumnos ORDER BY matricula")->fetchAll(\PDO::FETCH_KEY_PAIR));
        $this->assertSame('C', $this->valor("SELECT grupo FROM alumnos WHERE matricula = 'A2'"));
    }
}
