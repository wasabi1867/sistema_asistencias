<?php

namespace Tests\Api;

final class SesionTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_entra_con_el_correo_o_con_la_matricula(): void
    {
        $porCorreo = $this->ok($this->anonimo()->post('sesion', ['usuario' => self::CORREO_ADMIN, 'contrasena' => self::CONTRASENA, 'rol' => 'admin']));
        $porMatricula = $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA, 'rol' => 'docente']));

        $this->assertSame(['id' => $this->admin, 'rol' => 'admin'], array_intersect_key($porCorreo, ['id' => 0, 'rol' => 0]));
        $this->assertSame('Diego', $porMatricula['nombre']);
        $this->assertArrayNotHasKey('password_hash', $porMatricula);
    }

    public function test_la_sesion_dice_quien_la_tiene_y_se_cierra(): void
    {
        $docente = $this->con('docente');
        $this->assertSame($this->docente, $this->ok($docente->get('sesion'))['id']);

        $this->ok($docente->delete('sesion'), 204);

        $this->assertSame(401, $docente->get('sesion')->estado);
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'cerrar_sesion'"));
    }

    public function test_sin_sesion_responde_401(): void
    {
        $respuesta = $this->anonimo()->get('sesion');

        $this->assertSame(401, $respuesta->estado);
        $this->assertSame('Inicia sesión para continuar', $respuesta->json()['error']);
    }

    public function test_el_rol_tiene_que_coincidir(): void
    {
        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA, 'rol' => 'admin']);

        $this->assertSame(401, $respuesta->estado);
    }

    public function test_contrasena_equivocada_y_cuenta_inexistente_responden_igual(): void
    {
        $equivocada = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'otra-contrasena', 'rol' => 'docente']);
        $inexistente = $this->anonimo()->post('sesion', ['usuario' => 'NADIE', 'contrasena' => 'otra-contrasena', 'rol' => 'docente']);

        $this->assertSame([401, 'Usuario o contraseña incorrectos'], [$equivocada->estado, $equivocada->json()['error']]);
        $this->assertSame([401, 'Usuario o contraseña incorrectos'], [$inexistente->estado, $inexistente->json()['error']]);
        $this->assertSame(2, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'sesion_fallida'"));
        $this->assertSame('NADIE', $this->valor("SELECT usuario FROM bitacora WHERE accion = 'sesion_fallida' ORDER BY id DESC LIMIT 1"));
    }

    public function test_despues_de_10_intentos_fallidos_hay_que_esperar(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'mal', 'rol' => 'docente']);
        }

        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA, 'rol' => 'docente']);

        $this->assertSame(429, $respuesta->estado, (string)$respuesta);
    }

    public function test_una_cuenta_desactivada_no_entra_y_pierde_su_sesion(): void
    {
        $docente = $this->con('docente');
        $this->pdo()->exec("UPDATE usuarios SET activo = FALSE WHERE id = $this->docente");

        $this->assertSame(401, $docente->get('sesion')->estado);
        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA, 'rol' => 'docente']);
        $this->assertSame(403, $respuesta->estado);
        $this->assertStringContainsString('desactivada', $respuesta->json()['error']);
    }

    public function test_los_hash_de_firebase_sirven_y_se_vuelven_a_cifrar(): void
    {
        // Así guardaba bcryptjs (el de la versión con Firebase) la contraseña "clave-de-firebase".
        $hashDeBcryptjs = str_replace('$2y$', '$2b$', password_hash('clave-de-firebase', PASSWORD_BCRYPT, ['cost' => 10]));
        $this->pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$hashDeBcryptjs, $this->docente]);

        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'clave-de-firebase', 'rol' => 'docente']));

        $this->assertStringStartsWith('$2y$', $this->valor('SELECT password_hash FROM usuarios WHERE id = ?', [$this->docente]));
    }

    public function test_cambia_su_propia_contrasena_dando_la_actual(): void
    {
        $docente = $this->con('docente');

        $mal = $docente->put('sesion/contrasena', ['actual' => 'no-es', 'nueva' => 'nueva-contrasena']);
        $corta = $docente->put('sesion/contrasena', ['actual' => self::CONTRASENA, 'nueva' => 'corta']);
        $this->ok($docente->put('sesion/contrasena', ['actual' => self::CONTRASENA, 'nueva' => 'nueva-contrasena']), 204);

        $this->assertSame([400, 'actual'], [$mal->estado, $mal->json()['campo']]);
        $this->assertSame([400, 'nueva'], [$corta->estado, $corta->json()['campo']]);
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'nueva-contrasena', 'rol' => 'docente']));
    }

    public function test_la_cookie_no_la_puede_leer_javascript(): void
    {
        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA, 'rol' => 'docente']);

        $cookie = $respuesta->encabezado('set-cookie');
        $this->assertStringContainsString('asistencias_sesion=', $cookie);
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        $this->assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }
}
