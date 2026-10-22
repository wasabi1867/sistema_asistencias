<?php

namespace Tests\Api;

final class AutenticacionTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_inicia_sesion_y_la_recuerda(): void
    {
        $cliente = new Cliente();
        $respuesta = $cliente->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => self::CONTRASENA, 'role' => 'admin']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame('admin', $respuesta->json()['role']);
        $this->assertArrayNotHasKey('password', $respuesta->json()['user']);

        $sesion = $cliente->get('auth.php');
        $this->assertSame(200, $sesion->estado, (string)$sesion);
        $this->assertSame('admin', $sesion->json()['user']['rol']);
        $this->assertSame(self::CORREO_ADMIN, $sesion->json()['user']['email']);
    }

    public function test_la_cookie_de_sesion_no_la_puede_leer_javascript(): void
    {
        $cliente = new Cliente();
        $respuesta = $cliente->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => self::CONTRASENA, 'role' => 'admin']);

        $cookie = $respuesta->encabezado('Set-Cookie');
        $this->assertNotNull($cookie);
        $this->assertStringStartsWith('asistencias_sesion=', $cookie);
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        $this->assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }

    public function test_docente_y_laboratorista_entran_con_su_matricula(): void
    {
        $this->assertSame('docente', $this->con('docente')->get('auth.php')->json()['role']);
        $this->assertSame('laboratorista', $this->con('laboratorista')->get('auth.php')->json()['role']);
    }

    public function test_rechaza_una_contrasena_incorrecta(): void
    {
        $cliente = new Cliente();
        $respuesta = $cliente->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => 'otra-contrasena', 'role' => 'admin']);

        $this->assertSame(401, $respuesta->estado);
        $this->assertSame(401, $cliente->get('auth.php')->estado);
    }

    public function test_rechaza_a_un_usuario_que_no_existe(): void
    {
        $respuesta = (new Cliente())->post('auth.php', ['email' => 'nadie@pruebas.test', 'password' => self::CONTRASENA, 'role' => 'admin']);

        $this->assertSame(401, $respuesta->estado);
        $this->assertSame('Credenciales incorrectas', $respuesta->json()['error']);
    }

    public function test_no_confunde_los_roles(): void
    {
        // La contraseña del docente es correcta, pero no es administrador.
        $respuesta = (new Cliente())->post('auth.php', ['email' => self::MATRICULA_DOCENTE, 'password' => self::CONTRASENA, 'role' => 'admin']);
        $this->assertSame(401, $respuesta->estado);

        $respuesta = (new Cliente())->post('auth.php', ['email' => self::MATRICULA_DOCENTE, 'password' => self::CONTRASENA, 'role' => 'superusuario']);
        $this->assertSame(400, $respuesta->estado);
    }

    public function test_una_contrasena_en_texto_plano_se_cifra_al_entrar(): void
    {
        $this->insertar('docentes', ['matricula' => 'D200', 'nombre' => 'Viejo', 'apellido' => 'Docente', 'password' => 'clave-de-antes']);

        $respuesta = (new Cliente())->post('auth.php', ['email' => 'D200', 'password' => 'clave-de-antes', 'role' => 'docente']);
        $this->assertSame(200, $respuesta->estado, (string)$respuesta);

        $guardada = $this->valor('SELECT password FROM docentes WHERE matricula = ?', ['D200']);
        $this->assertNotSame('clave-de-antes', $guardada);
        $this->assertTrue(password_verify('clave-de-antes', $guardada));

        // Y se sigue pudiendo entrar, ya contra el hash.
        $otra = (new Cliente())->post('auth.php', ['email' => 'D200', 'password' => 'clave-de-antes', 'role' => 'docente']);
        $this->assertSame(200, $otra->estado);
    }

    public function test_entra_con_la_contrasena_que_cifro_firebase(): void
    {
        // Hash de 'clave-de-firebase' hecho con bcryptjs, como los de la versión con Firebase.
        $deFirebase = '$2b$10$/ZROYJEdcZ8PItQWMOvMteQ.hgU2qNgc6jJRVoe4CrAcM/TO/HEWi';
        $this->insertar('docentes', ['matricula' => 'D300', 'nombre' => 'De', 'apellido' => 'Firebase', 'password' => $deFirebase]);

        $mala = (new Cliente())->post('auth.php', ['email' => 'D300', 'password' => $deFirebase, 'role' => 'docente']);
        $buena = (new Cliente())->post('auth.php', ['email' => 'D300', 'password' => 'clave-de-firebase', 'role' => 'docente']);

        // Escribir el hash mismo como contraseña no sirve (no se toma por texto plano).
        $this->assertSame(401, $mala->estado);
        $this->assertSame(200, $buena->estado, (string)$buena);
        // Queda cifrada en el formato de PHP, y sigue sirviendo.
        $guardada = $this->valor("SELECT password FROM docentes WHERE matricula = 'D300'");
        $this->assertStringStartsWith('$2y$', $guardada);
        $this->assertSame(200, (new Cliente())->post('auth.php', ['email' => 'D300', 'password' => 'clave-de-firebase', 'role' => 'docente'])->estado);
    }

    public function test_una_cuenta_sin_contrasena_no_entra(): void
    {
        $this->insertar('laboratoristas', ['matricula' => 'L200', 'nombre' => 'Sin', 'apellido' => 'Contraseña', 'password' => '']);

        $respuesta = (new Cliente())->post('auth.php', ['email' => 'L200', 'password' => 'lo-que-sea', 'role' => 'laboratorista']);

        $this->assertSame(401, $respuesta->estado);
    }

    public function test_cerrar_sesion(): void
    {
        $cliente = $this->con('admin');
        $this->assertSame(200, $cliente->get('alumnos.php')->estado);

        $this->assertSame(200, $cliente->delete('auth.php')->estado);

        $this->assertSame(401, $cliente->get('auth.php')->estado);
        $this->assertSame(401, $cliente->get('alumnos.php')->estado);
    }

    public function test_la_sesion_cerrada_no_sirve_aunque_se_guarde_la_cookie(): void
    {
        $cliente = $this->con('admin');
        $cookieRobada = $cliente->cookies;
        $cliente->delete('auth.php');

        $otro = new Cliente();
        $otro->cookies = $cookieRobada;
        $this->assertSame(401, $otro->get('alumnos.php')->estado);
    }

    public function test_demasiados_intentos_fallidos_hacen_esperar(): void
    {
        for ($i = 0; $i < 10; $i++) {
            (new Cliente())->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => "intento-$i", 'role' => 'admin']);
        }

        // Aun con la contraseña correcta: desde esa dirección hay que esperar.
        $respuesta = (new Cliente())->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => self::CONTRASENA, 'role' => 'admin']);

        $this->assertSame(429, $respuesta->estado);
        $this->assertSame(10, (int)$this->valor("SELECT COUNT(*) FROM logs WHERE accion = 'Inicio de sesión fallido'"));
    }

    public function test_los_intentos_viejos_no_cuentan(): void
    {
        for ($i = 0; $i < 10; $i++) {
            (new Cliente())->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => "intento-$i", 'role' => 'admin']);
        }
        $this->pdo()->exec("UPDATE logs SET fecha = NOW() - INTERVAL 16 MINUTE");

        $respuesta = (new Cliente())->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => self::CONTRASENA, 'role' => 'admin']);

        $this->assertSame(200, $respuesta->estado);
    }

    public function test_el_administrador_tambien_entra_con_su_matricula(): void
    {
        $this->pdo()->exec("UPDATE administradores SET matricula = 'ADM01'");

        $respuesta = (new Cliente())->post('auth.php', ['email' => 'ADM01', 'password' => self::CONTRASENA, 'role' => 'admin']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
    }

    public function test_entrar_y_salir_queda_en_la_bitacora(): void
    {
        $cliente = $this->con('docente');
        $cliente->delete('auth.php');

        $acciones = $this->pdo()->query("SELECT accion, usuario FROM logs ORDER BY id")->fetchAll();
        $this->assertSame([
            ['accion' => 'Inicio de Sesión', 'usuario' => 'Diego Docente (docente)'],
            ['accion' => 'Cerrar Sesión', 'usuario' => 'Diego Docente (docente)'],
        ], $acciones);
    }

    public function test_no_acepta_un_identificador_de_sesion_inventado(): void
    {
        $cliente = new Cliente();
        $cliente->cookies['asistencias_sesion'] = 'identificadorinventado123';
        $this->assertSame(401, $cliente->get('alumnos.php')->estado);

        // Al iniciar sesión recibe un identificador nuevo, no el que traía.
        $cliente->post('auth.php', ['email' => self::CORREO_ADMIN, 'password' => self::CONTRASENA, 'role' => 'admin']);
        $this->assertNotSame('identificadorinventado123', $cliente->cookies['asistencias_sesion'] ?? null);
        $this->assertSame(200, $cliente->get('alumnos.php')->estado);
    }
}
