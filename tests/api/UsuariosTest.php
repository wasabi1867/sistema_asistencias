<?php

namespace Tests\Api;

final class UsuariosTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_el_administrador_crea_una_cuenta_que_luego_entra(): void
    {
        $nueva = $this->ok($this->con('admin')->post('usuarios', [
            'rol' => 'docente', 'matricula' => 'D200', 'email' => 'Rosa@Pruebas.test', 'nombre' => 'Rosa', 'apellido' => 'Ríos',
            'departamento' => 'Ciencias Básicas', 'contrasena' => 'contrasena-de-rosa',
        ]), 201);

        $this->assertSame('rosa@pruebas.test', $nueva['email']);
        $this->assertArrayNotHasKey('password_hash', $nueva);
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D200', 'contrasena' => 'contrasena-de-rosa', 'rol' => 'docente']));
        // La bitácora dice qué se creó, pero no guarda la contraseña ni los datos.
        $this->assertSame('Docente D200', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'crear' AND entidad = 'usuarios'"));
    }

    public function test_una_cuenta_nueva_necesita_contrasena_de_8_caracteres_y_matricula_o_correo(): void
    {
        $admin = $this->con('admin');

        $sinContrasena = $admin->post('usuarios', ['rol' => 'docente', 'matricula' => 'D200', 'nombre' => 'Rosa']);
        $sinIdentificacion = $admin->post('usuarios', ['rol' => 'docente', 'nombre' => 'Rosa', 'contrasena' => 'contrasena-larga']);
        $correoMalo = $admin->post('usuarios', ['rol' => 'docente', 'email' => 'no-es-correo', 'nombre' => 'Rosa', 'contrasena' => 'contrasena-larga']);

        $this->assertSame([400, 'contrasena'], [$sinContrasena->estado, $sinContrasena->json()['campo']]);
        $this->assertSame([400, 'matricula'], [$sinIdentificacion->estado, $sinIdentificacion->json()['campo']]);
        $this->assertSame([400, 'email'], [$correoMalo->estado, $correoMalo->json()['campo']]);
    }

    public function test_la_misma_matricula_puede_tener_cuenta_en_otro_rol_pero_no_en_el_mismo(): void
    {
        $admin = $this->con('admin');

        $otroRol = $admin->post('usuarios', ['rol' => 'admin', 'matricula' => 'D100', 'nombre' => 'Diego', 'contrasena' => 'contrasena-larga']);
        $mismoRol = $admin->post('usuarios', ['rol' => 'docente', 'matricula' => 'D100', 'nombre' => 'Otro', 'contrasena' => 'contrasena-larga']);

        $this->assertSame(201, $otroRol->estado, (string)$otroRol);
        $this->assertSame([409, 'Ya hay una cuenta de ese rol con esa matrícula'], [$mismoRol->estado, $mismoRol->json()['error']]);
    }

    public function test_editar_no_cambia_la_contrasena_y_anota_los_campos(): void
    {
        $admin = $this->con('admin');

        $editada = $this->ok($admin->put("usuarios/$this->docente", ['nombre' => 'Diego Armando', 'departamento' => 'Sistemas']));

        $this->assertSame('Diego Armando', $editada['nombre']);
        $this->con('docente');
        $this->assertSame('Docente D100. Campos: nombre', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'editar'"));
    }

    public function test_el_administrador_restablece_la_contrasena_de_otro(): void
    {
        $this->ok($this->con('admin')->put("usuarios/$this->docente/contrasena", ['contrasena' => 'contrasena-nueva']), 204);

        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'contrasena-nueva', 'rol' => 'docente']));
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'restablecer_contrasena'"));
    }

    public function test_nadie_se_desactiva_a_si_mismo_y_siempre_queda_un_administrador(): void
    {
        $admin = $this->con('admin');
        $otro = $this->insertar('usuarios', ['rol' => 'admin', 'email' => 'otro@pruebas.test', 'nombre' => 'Otro', 'password_hash' => 'x', 'activo' => 0]);

        $aSiMismo = $admin->put("usuarios/$this->admin", ['activo' => false]);
        $borrarseASiMismo = $admin->delete("usuarios/$this->admin");

        $this->assertSame(409, $aSiMismo->estado);
        $this->assertSame(409, $borrarseASiMismo->estado);
        // El otro administrador está desactivado: borrarlo deja al de la sesión como único activo, y se puede.
        $this->ok($admin->delete("usuarios/$otro"), 204);
    }

    public function test_una_cuenta_con_historial_no_se_borra(): void
    {
        $curso = $this->crearCurso();
        $this->abrirClase($curso);

        $respuesta = $this->con('admin')->delete("usuarios/$this->docente");

        $this->assertSame(409, $respuesta->estado);
    }

    public function test_quien_no_es_administrador_solo_ve_docentes_y_laboratoristas_activos(): void
    {
        $this->crearDocente('D300', 'Inactivo');
        $this->pdo()->exec("UPDATE usuarios SET activo = FALSE WHERE matricula = 'D300'");

        $vistosPorDocente = $this->ok($this->con('docente')->get('usuarios'));
        $vistosPorAdmin = $this->ok($this->con('admin')->get('usuarios'));
        $docentesActivos = $this->ok($this->con('admin')->get('usuarios?rol=docente&activo=1'));

        $this->assertEqualsCanonicalizing(['D100', 'L100'], array_column($vistosPorDocente, 'matricula'));
        $this->assertCount(4, $vistosPorAdmin);
        $this->assertSame(['D100'], array_column($docentesActivos, 'matricula'));
    }

    public function test_solo_el_administrador_maneja_cuentas(): void
    {
        $laboratorista = $this->con('laboratorista');

        $this->assertSame(403, $laboratorista->post('usuarios', ['rol' => 'docente', 'matricula' => 'X', 'nombre' => 'X', 'contrasena' => 'contrasena-larga'])->estado);
        $this->assertSame(403, $laboratorista->get("usuarios/$this->docente")->estado);
        $this->assertSame(401, $this->anonimo()->get('usuarios')->estado);
    }
}
