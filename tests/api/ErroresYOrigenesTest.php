<?php

namespace Tests\Api;

final class ErroresYOrigenesTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_un_error_de_la_base_no_muestra_sus_detalles(): void
    {
        // nombre_materia admite 150 caracteres: la base rechaza el INSERT.
        $respuesta = $this->con('admin')->post('materias.php', ['id' => 'M1', 'nombre_materia' => str_repeat('x', 300)]);

        $this->assertSame(500, $respuesta->estado);
        $this->assertSame('Error interno del servidor', $respuesta->json()['error']);
        $this->assertStringNotContainsString('SQLSTATE', $respuesta->texto);
        $this->assertStringNotContainsString('nombre_materia', $respuesta->texto);
        // El detalle sí queda en el registro del servidor, para quien lo administra.
        $this->assertStringContainsString('SQLSTATE', Entorno::erroresDelServidor());
    }

    public function test_un_estado_de_solicitud_desconocido_se_rechaza_antes_de_llegar_a_la_base(): void
    {
        $this->insertar('solicitudes_ingreso', ['id' => 1, 'alumno_id' => 'A1', 'nombre' => 'Ana', 'motivo' => 'Práctica']);

        $respuesta = $this->con('admin')->put('solicitudes.php', ['id' => 1, 'estado' => 'aceptadisima']);

        $this->assertSame(400, $respuesta->estado);
    }

    public function test_otro_sitio_no_recibe_permiso_de_cors(): void
    {
        $respuesta = $this->anonimo()->pedir('GET', 'equipos.php', null, ['Origin' => 'https://sitio-malicioso.test']);

        $this->assertNull($respuesta->encabezado('Access-Control-Allow-Origin'));
    }

    public function test_un_origen_configurado_si_recibe_permiso_de_cors(): void
    {
        $respuesta = $this->anonimo()->pedir('OPTIONS', 'auth.php', null, [
            'Origin' => Entorno::ORIGEN_PERMITIDO,
            'Access-Control-Request-Method' => 'POST',
        ]);

        $this->assertSame(204, $respuesta->estado);
        $this->assertSame(Entorno::ORIGEN_PERMITIDO, $respuesta->encabezado('Access-Control-Allow-Origin'));
        $this->assertSame('true', $respuesta->encabezado('Access-Control-Allow-Credentials'));
    }
}
