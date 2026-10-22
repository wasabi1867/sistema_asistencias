<?php

namespace Tests\Api;

/** Lo que pasa cuando la petición no es la esperada: rutas, métodos, JSON, orígenes. */
final class ErroresTest extends PruebaDeApi
{
    public function test_una_ruta_que_no_existe_responde_404(): void
    {
        $respuesta = $this->anonimo()->get('no-existe');

        $this->assertSame([404, 'La ruta no existe'], [$respuesta->estado, $respuesta->json()['error']]);
        $this->assertStringStartsWith('application/json', $respuesta->encabezado('content-type'));
    }

    public function test_un_metodo_que_la_ruta_no_tiene_responde_405(): void
    {
        $this->assertSame(405, $this->anonimo()->pedir('PATCH', 'laboratorios')->estado);
    }

    public function test_un_cuerpo_que_no_es_un_objeto_json_responde_400(): void
    {
        $respuesta = $this->anonimo()->pedir('POST', 'sesion', null, ['Content-Type' => 'application/json']);
        $this->assertSame(400, $respuesta->estado);

        $lista = (new Cliente())->pedir('POST', 'sesion', [1, 2, 3]);
        $this->assertSame([400, 'Los datos deben enviarse como un objeto JSON'], [$lista->estado, $lista->json()['error']]);
    }

    public function test_los_archivos_del_codigo_no_se_sirven(): void
    {
        foreach (['src/Configuracion.php', 'config.ejemplo.php', '../db/esquema.sql'] as $ruta) {
            $respuesta = $this->anonimo()->get($ruta);
            $this->assertSame(404, $respuesta->estado, "$ruta: $respuesta");
            $this->assertStringNotContainsString('<?php', $respuesta->texto);
        }
    }

    public function test_solo_los_origenes_configurados_reciben_cors(): void
    {
        $permitido = $this->anonimo()->pedir('GET', 'laboratorios', null, ['Origin' => Entorno::ORIGEN_PERMITIDO]);
        $otro = $this->anonimo()->pedir('GET', 'laboratorios', null, ['Origin' => 'https://otro-sitio.test']);

        $this->assertSame(Entorno::ORIGEN_PERMITIDO, $permitido->encabezado('access-control-allow-origin'));
        $this->assertSame('true', $permitido->encabezado('access-control-allow-credentials'));
        $this->assertNull($otro->encabezado('access-control-allow-origin'));
    }

    public function test_la_consulta_previa_del_navegador_responde_204(): void
    {
        $respuesta = $this->anonimo()->pedir('OPTIONS', 'sesion', null, ['Origin' => Entorno::ORIGEN_PERMITIDO]);

        $this->assertSame(204, $respuesta->estado);
        $this->assertStringContainsString('POST', $respuesta->encabezado('access-control-allow-methods'));
    }

    public function test_un_dato_invalido_dice_cual_campo_es(): void
    {
        $respuesta = $this->anonimo()->post('sesion', ['contrasena' => 'x', 'rol' => 'docente']);

        $this->assertSame(400, $respuesta->estado);
        $this->assertSame(['error' => 'Falta el correo o la matrícula', 'campo' => 'usuario'], $respuesta->json());
    }
}
