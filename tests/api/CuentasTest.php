<?php

namespace Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;

final class CuentasTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public static function tiposDeCuenta(): array
    {
        return [
            'docente' => ['docentes.php', 'docentes', 'docente'],
            'laboratorista' => ['laboratoristas.php', 'laboratoristas', 'laboratorista'],
        ];
    }

    #[DataProvider('tiposDeCuenta')]
    public function test_una_cuenta_nueva_necesita_contrasena(string $ruta, string $tabla): void
    {
        $respuesta = $this->con('admin')->post($ruta, ['matricula' => 'N1', 'nombre' => 'Nueva', 'apellido' => 'Cuenta']);

        $this->assertSame(400, $respuesta->estado);
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM $tabla WHERE matricula = 'N1'"));
    }

    #[DataProvider('tiposDeCuenta')]
    public function test_rechaza_una_contrasena_corta(string $ruta): void
    {
        $respuesta = $this->con('admin')->post($ruta, ['matricula' => 'N1', 'nombre' => 'Nueva', 'apellido' => 'Cuenta', 'password' => '123456']);

        $this->assertSame(400, $respuesta->estado);
        $this->assertStringContainsString('8 caracteres', $respuesta->json()['error']);
    }

    #[DataProvider('tiposDeCuenta')]
    public function test_la_contrasena_se_guarda_cifrada(string $ruta, string $tabla, string $rol): void
    {
        $this->con('admin')->post($ruta, ['matricula' => 'N1', 'nombre' => 'Nueva', 'apellido' => 'Cuenta', 'password' => 'contrasena-nueva']);

        $guardada = $this->valor("SELECT password FROM $tabla WHERE matricula = 'N1'");
        $this->assertNotSame('contrasena-nueva', $guardada);
        $this->assertTrue(password_verify('contrasena-nueva', $guardada));

        $entrar = (new Cliente())->post('auth.php', ['email' => 'N1', 'password' => 'contrasena-nueva', 'role' => $rol]);
        $this->assertSame(200, $entrar->estado, (string)$entrar);
    }

    #[DataProvider('tiposDeCuenta')]
    public function test_editar_sin_contrasena_conserva_la_que_tenia(string $ruta, string $tabla, string $rol): void
    {
        $matricula = $rol === 'docente' ? self::MATRICULA_DOCENTE : self::MATRICULA_LABORATORISTA;

        $respuesta = $this->con('admin')->post($ruta, ['matricula' => $matricula, 'nombre' => 'Nombre', 'apellido' => 'Corregido']);

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $this->assertSame('Corregido', $this->valor("SELECT apellido FROM $tabla WHERE matricula = ?", [$matricula]));
        $this->con($rol); // sigue entrando con su contraseña de siempre
        $entrarConLaDeAntes = (new Cliente())->post('auth.php', ['email' => $matricula, 'password' => '123456', 'role' => $rol]);
        $this->assertSame(401, $entrarConLaDeAntes->estado);
    }

    #[DataProvider('tiposDeCuenta')]
    public function test_editar_con_contrasena_la_cambia(string $ruta, string $tabla, string $rol): void
    {
        $matricula = $rol === 'docente' ? self::MATRICULA_DOCENTE : self::MATRICULA_LABORATORISTA;

        $this->con('admin')->post($ruta, ['matricula' => $matricula, 'nombre' => 'Nombre', 'apellido' => 'Igual', 'password' => 'contrasena-cambiada']);

        $vieja = (new Cliente())->post('auth.php', ['email' => $matricula, 'password' => self::CONTRASENA, 'role' => $rol]);
        $nueva = (new Cliente())->post('auth.php', ['email' => $matricula, 'password' => 'contrasena-cambiada', 'role' => $rol]);
        $this->assertSame(401, $vieja->estado);
        $this->assertSame(200, $nueva->estado);
    }

    public function test_las_listas_de_cuentas_no_incluyen_contrasenas(): void
    {
        $admin = $this->con('admin');

        $this->assertStringNotContainsString('password', $admin->get('docentes.php')->texto);
        $this->assertStringNotContainsString('password', $admin->get('laboratoristas.php')->texto);
        $this->assertStringNotContainsString('$2y$', $admin->get('docentes.php')->texto);
    }
}
