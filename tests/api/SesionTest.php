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
        $porCorreo = $this->ok($this->anonimo()->post('sesion', ['usuario' => self::CORREO_ADMIN, 'contrasena' => self::CONTRASENA]));
        $porMatricula = $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA]));

        $this->assertSame(['id' => $this->admin, 'roles' => ['admin']], array_intersect_key($porCorreo, ['id' => 0, 'roles' => 0]));
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

    public function test_el_rol_no_se_elige_sale_de_la_cuenta(): void
    {
        $this->pdo()->exec("UPDATE usuarios SET roles = 'laboratorista,docente' WHERE id = $this->docente");

        // Las pantallas anteriores mandaban el rol: se ignora.
        $cuenta = $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA, 'rol' => 'admin']));

        $this->assertSame(['laboratorista', 'docente'], $cuenta['roles']);
    }

    public function test_quien_tiene_dos_roles_puede_hacer_lo_de_los_dos(): void
    {
        $this->pdo()->exec("UPDATE usuarios SET roles = 'admin,docente' WHERE id = $this->docente");
        $diego = $this->con('docente');

        $this->ok($diego->get('bitacora'));
        $this->ok($diego->get('cursos?docente=yo'));
        $this->assertSame('admin,docente', $this->valor("SELECT roles FROM bitacora WHERE accion = 'iniciar_sesion' ORDER BY id DESC LIMIT 1"));
    }

    public function test_contrasena_equivocada_y_cuenta_inexistente_responden_igual(): void
    {
        $equivocada = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'otra-contrasena']);
        $inexistente = $this->anonimo()->post('sesion', ['usuario' => 'NADIE', 'contrasena' => 'otra-contrasena']);

        $this->assertSame([401, 'Usuario o contraseña incorrectos'], [$equivocada->estado, $equivocada->json()['error']]);
        $this->assertSame([401, 'Usuario o contraseña incorrectos'], [$inexistente->estado, $inexistente->json()['error']]);
        $this->assertSame(2, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'sesion_fallida'"));
        // De la cuenta que existe queda su correo; de la que no, una huella y no lo escrito.
        $this->assertSame('diego@pruebas.test', $this->valor("SELECT usuario FROM bitacora WHERE accion = 'sesion_fallida' ORDER BY id LIMIT 1"));
        $this->assertMatchesRegularExpression('/^Cuenta que no existe \([0-9a-f]{10}\)$/', $this->valor("SELECT usuario FROM bitacora WHERE accion = 'sesion_fallida' ORDER BY id DESC LIMIT 1"));
    }

    public function test_lo_escrito_en_usuario_no_queda_en_la_bitacora_si_no_es_una_cuenta(): void
    {
        // Pasa cuando alguien escribe su contraseña en el campo del usuario.
        $this->anonimo()->post('sesion', ['usuario' => 'MiContraseñaSecreta1', 'contrasena' => 'x']);

        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE usuario LIKE '%Secreta%' OR detalles LIKE '%Secreta%'"));
    }

    public function test_despues_de_10_intentos_fallidos_hay_que_esperar(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'mal']);
        }

        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA]);

        $this->assertSame(429, $respuesta->estado, (string)$respuesta);
        // Escribirla con otras mayúsculas no la hace otra cuenta.
        $this->assertSame(429, $this->anonimo()->post('sesion', ['usuario' => 'd100', 'contrasena' => self::CONTRASENA])->estado);
    }

    public function test_los_fallos_de_una_cuenta_no_dejan_fuera_a_las_demas_de_la_misma_red(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'mal']);
            $this->anonimo()->post('sesion', ['usuario' => 'NADIE', 'contrasena' => 'mal']);
        }

        // Todo el instituto puede salir a internet con la misma dirección.
        $this->ok($this->anonimo()->post('sesion', ['usuario' => self::CORREO_ADMIN, 'contrasena' => self::CONTRASENA]));
        // La que no existe espera igual que la que existe: no se distinguen.
        $this->assertSame(429, $this->anonimo()->post('sesion', ['usuario' => 'NADIE', 'contrasena' => 'mal'])->estado);
        $this->assertSame(401, $this->anonimo()->post('sesion', ['usuario' => 'OTRO', 'contrasena' => 'mal'])->estado);
    }

    public function test_una_direccion_tiene_un_tope_de_fallos_con_cualquier_cuenta(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->insertar('bitacora', ['usuario' => "Cuenta $i", 'accion' => 'sesion_fallida', 'ip' => '127.0.0.1']);
        }

        $this->assertSame(429, $this->anonimo()->post('sesion', ['usuario' => self::CORREO_ADMIN, 'contrasena' => self::CONTRASENA])->estado);
    }

    public function test_una_cuenta_desactivada_no_entra_y_pierde_su_sesion(): void
    {
        $docente = $this->con('docente');
        $this->pdo()->exec("UPDATE usuarios SET activo = FALSE WHERE id = $this->docente");

        $this->assertSame(401, $docente->get('sesion')->estado);
        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA]);
        $this->assertSame(403, $respuesta->estado);
        $this->assertStringContainsString('desactivada', $respuesta->json()['error']);
    }

    public function test_los_hash_de_firebase_sirven_y_se_vuelven_a_cifrar(): void
    {
        // Así guardaba bcryptjs (el de la versión con Firebase) la contraseña "clave-de-firebase".
        $hashDeBcryptjs = str_replace('$2y$', '$2b$', password_hash('clave-de-firebase', PASSWORD_BCRYPT, ['cost' => 10]));
        $this->pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$hashDeBcryptjs, $this->docente]);

        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'clave-de-firebase']));

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
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'nueva-contrasena']));
    }

    public function test_al_cambiar_su_contrasena_sus_otras_sesiones_dejan_de_servir(): void
    {
        $aqui = $this->con('docente');
        $enOtroLado = $this->con('docente');

        $this->ok($aqui->put('sesion/contrasena', ['actual' => self::CONTRASENA, 'nueva' => 'nueva-contrasena']), 204);

        $this->assertSame($this->docente, $this->ok($aqui->get('sesion'))['id']);
        $this->assertSame(401, $enOtroLado->get('sesion')->estado);
    }

    public function test_al_restablecerle_la_contrasena_sus_sesiones_dejan_de_servir(): void
    {
        $docente = $this->con('docente');
        $admin = $this->con('admin');

        $this->ok($admin->put("usuarios/$this->docente/contrasena", ['contrasena' => 'una-nueva-larga']), 204);
        // El administrador que restablece la suya conserva su sesión.
        $this->ok($admin->put("usuarios/$this->admin/contrasena", ['contrasena' => 'otra-nueva-larga']), 204);

        $this->assertSame(401, $docente->get('sesion')->estado);
        $this->assertSame($this->admin, $this->ok($admin->get('sesion'))['id']);
    }

    public function test_los_hash_viejos_se_vuelven_a_cifrar_sin_perder_la_sesion(): void
    {
        $hashDeBcryptjs = str_replace('$2y$', '$2b$', password_hash('clave-de-firebase', PASSWORD_BCRYPT, ['cost' => 10]));
        $this->pdo()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$hashDeBcryptjs, $this->docente]);
        $docente = $this->anonimo();

        $this->ok($docente->post('sesion', ['usuario' => 'D100', 'contrasena' => 'clave-de-firebase']));

        $this->assertSame($this->docente, $this->ok($docente->get('sesion'))['id']);
    }

    public function test_la_sesion_se_guarda_en_una_carpeta_propia(): void
    {
        $docente = $this->con('docente');

        $archivo = 'sess_' . $docente->cookies['asistencias_sesion'];
        $this->assertCount(1, glob(Entorno::carpetaTemporal() . "/sesiones-*/asistencias_sesiones/$archivo") ?: []);
        $this->assertSame([], glob(Entorno::carpetaTemporal() . "/sesiones-*/$archivo") ?: []);
    }

    public function test_lo_que_cambia_datos_solo_se_acepta_desde_el_propio_sitio(): void
    {
        $admin = $this->con('admin');
        $servidor = parse_url(Entorno::url());
        $propio = "http://{$servidor['host']}:{$servidor['port']}";
        $carrera = ['nombre' => 'Carrera Nueva', 'clave' => 'CNUE'];

        $deOtroSitio = $admin->pedir('POST', 'carreras', $carrera, ['Origin' => 'https://otro-sitio.test']);
        $deUnSubdominio = $admin->pedir('POST', 'carreras', $carrera, ['Origin' => "http://carrera.{$servidor['host']}:{$servidor['port']}"]);
        $sinOrigen = $admin->pedir('POST', 'carreras', $carrera, ['Sec-Fetch-Site' => 'same-site']);

        $this->assertSame([403, 403, 403], [$deOtroSitio->estado, $deUnSubdominio->estado, $sinOrigen->estado]);
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM carreras WHERE clave = 'CNUE'"));
        // Leer no cambia nada, y desde el propio sitio todo sigue igual.
        $this->ok($admin->pedir('GET', 'carreras', null, ['Origin' => 'https://otro-sitio.test']));
        $this->ok($admin->pedir('POST', 'carreras', $carrera, ['Origin' => $propio, 'Sec-Fetch-Site' => 'same-origin']), 201);
    }

    public function test_un_origen_configurado_si_puede_cambiar_datos(): void
    {
        $api = new Cliente(Entorno::url(['ASISTENCIAS_ORIGENES_PERMITIDOS' => 'http://localhost:3000']));

        $respuesta = $api->pedir('POST', 'sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA], ['Origin' => 'http://localhost:3000']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
    }

    public function test_la_cookie_no_la_puede_leer_javascript(): void
    {
        $respuesta = $this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => self::CONTRASENA]);

        $cookie = $respuesta->encabezado('set-cookie');
        $this->assertStringContainsString('asistencias_sesion=', $cookie);
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        $this->assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }
}
