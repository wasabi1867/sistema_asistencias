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
            'roles' => ['docente'], 'matricula' => 'D200', 'email' => 'Rosa@Pruebas.test', 'nombre' => 'Rosa', 'apellido' => 'Ríos',
            'departamento' => 'Ciencias Básicas', 'contrasena' => 'contrasena-de-rosa',
        ]), 201);

        $this->assertSame('rosa@pruebas.test', $nueva['email']);
        $this->assertArrayNotHasKey('password_hash', $nueva);
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D200', 'contrasena' => 'contrasena-de-rosa']));
        // La bitácora dice qué se creó, pero no guarda la contraseña ni los datos.
        $this->assertSame('Cuenta D200', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'crear' AND entidad = 'usuarios'"));
    }

    public function test_una_cuenta_nueva_necesita_contrasena_de_8_caracteres_y_matricula_o_correo(): void
    {
        $admin = $this->con('admin');

        $sinContrasena = $admin->post('usuarios', ['roles' => ['docente'], 'matricula' => 'D200', 'nombre' => 'Rosa']);
        $sinIdentificacion = $admin->post('usuarios', ['roles' => ['docente'], 'nombre' => 'Rosa', 'contrasena' => 'contrasena-larga']);
        $correoMalo = $admin->post('usuarios', ['roles' => ['docente'], 'email' => 'no-es-correo', 'nombre' => 'Rosa', 'contrasena' => 'contrasena-larga']);

        $this->assertSame([400, 'contrasena'], [$sinContrasena->estado, $sinContrasena->json()['campo']]);
        $this->assertSame([400, 'matricula'], [$sinIdentificacion->estado, $sinIdentificacion->json()['campo']]);
        $this->assertSame([400, 'email'], [$correoMalo->estado, $correoMalo->json()['campo']]);
    }

    public function test_una_persona_tiene_una_sola_cuenta_y_se_le_agregan_roles(): void
    {
        $admin = $this->con('admin');

        $otraCuenta = $admin->post('usuarios', ['roles' => ['admin'], 'matricula' => 'D100', 'nombre' => 'Diego', 'contrasena' => 'contrasena-larga']);
        $mismoCorreo = $admin->post('usuarios', ['roles' => ['laboratorista'], 'email' => 'diego@pruebas.test', 'nombre' => 'Diego', 'contrasena' => 'contrasena-larga']);
        $conRol = $this->ok($admin->put("usuarios/$this->docente", ['roles' => ['docente', 'admin']]));

        $this->assertSame(409, $otraCuenta->estado);
        $this->assertStringStartsWith('Ya hay una cuenta con esa matrícula', $otraCuenta->json()['error']);
        $this->assertStringStartsWith('Ya hay una cuenta con ese correo', $mismoCorreo->json()['error']);
        // Siempre en el mismo orden, sin importar cómo se mandaron.
        $this->assertSame(['admin', 'docente'], $conRol['roles']);
        $this->assertSame('Cuenta D100. Campos: roles', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'editar'"));
        $this->ok($this->con('docente')->get('bitacora'));
    }

    public function test_una_cuenta_necesita_al_menos_un_rol_valido(): void
    {
        $admin = $this->con('admin');

        $sinRoles = $admin->post('usuarios', ['matricula' => 'D200', 'nombre' => 'Rosa', 'contrasena' => 'contrasena-larga']);
        $vacios = $admin->put("usuarios/$this->docente", ['roles' => []]);
        $inventado = $admin->put("usuarios/$this->docente", ['roles' => ['docente', 'director']]);

        $this->assertSame([400, 'roles', 'Elige al menos un rol'], [$sinRoles->estado, $sinRoles->json()['campo'], $sinRoles->json()['error']]);
        $this->assertSame([400, 'Elige al menos un rol'], [$vacios->estado, $vacios->json()['error']]);
        $this->assertSame([400, 'roles'], [$inventado->estado, $inventado->json()['campo']]);
        $this->assertSame('docente', $this->valor("SELECT roles FROM usuarios WHERE id = $this->docente"));
    }

    public function test_nadie_se_quita_a_si_mismo_el_rol_de_administrador(): void
    {
        $admin = $this->con('admin');
        $otro = $this->insertar('usuarios', ['roles' => 'admin,docente', 'email' => 'otro@pruebas.test', 'nombre' => 'Otro', 'password_hash' => 'x']);

        $aSiMismo = $admin->put("usuarios/$this->admin", ['roles' => ['docente']]);
        $this->ok($admin->put("usuarios/$otro", ['roles' => ['docente']]));

        $this->assertSame([409, 'No puedes quitarte a ti mismo el rol de administrador'], [$aSiMismo->estado, $aSiMismo->json()['error']]);
        $this->assertSame('docente', $this->valor("SELECT roles FROM usuarios WHERE id = $otro"));
    }

    public function test_editar_no_cambia_la_contrasena_y_anota_los_campos(): void
    {
        $admin = $this->con('admin');

        $editada = $this->ok($admin->put("usuarios/$this->docente", ['nombre' => 'Diego Armando', 'departamento' => 'Sistemas']));

        $this->assertSame('Diego Armando', $editada['nombre']);
        $this->con('docente');
        $this->assertSame('Cuenta D100. Campos: nombre', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'editar'"));
    }

    public function test_el_administrador_restablece_la_contrasena_de_otro(): void
    {
        $this->ok($this->con('admin')->put("usuarios/$this->docente/contrasena", ['contrasena' => 'contrasena-nueva']), 204);

        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'contrasena-nueva']));
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'restablecer_contrasena'"));
    }

    public function test_nadie_se_desactiva_a_si_mismo_y_siempre_queda_un_administrador(): void
    {
        $admin = $this->con('admin');
        $otro = $this->insertar('usuarios', ['roles' => 'admin', 'email' => 'otro@pruebas.test', 'nombre' => 'Otro', 'password_hash' => 'x', 'activo' => 0]);

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
        // Quien tiene varios roles sale en la lista de cada uno.
        $this->pdo()->exec("UPDATE usuarios SET roles = 'laboratorista,docente' WHERE matricula = 'L100'");
        $this->assertEqualsCanonicalizing(['D100', 'L100'], array_column($this->ok($this->con('admin')->get('usuarios?rol=docente&activo=1')), 'matricula'));
    }

    public function test_solo_el_administrador_maneja_cuentas(): void
    {
        $laboratorista = $this->con('laboratorista');

        $this->assertSame(403, $laboratorista->post('usuarios', ['roles' => ['docente'], 'matricula' => 'X', 'nombre' => 'X', 'contrasena' => 'contrasena-larga'])->estado);
        $this->assertSame(403, $laboratorista->get("usuarios/$this->docente")->estado);
        $this->assertSame(401, $this->anonimo()->get('usuarios')->estado);
    }
}
