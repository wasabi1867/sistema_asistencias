<?php

namespace Tests\Api;

final class EquiposTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->crearEquipos();
        $this->pdo()->exec("UPDATE equipos SET en_uso = 1, usuario_actual = 'Ana Alumna (23303001)', notas = 'Teclado flojo' WHERE id = 'PC-1'");
    }

    private function pc1(array $equipos): array
    {
        return array_values(array_filter($equipos, fn ($e) => $e['id'] === 'PC-1'))[0];
    }

    public function test_sin_sesion_se_ve_que_el_equipo_esta_ocupado_pero_no_por_quien(): void
    {
        $respuesta = $this->anonimo()->get('equipos.php');

        $this->assertSame(200, $respuesta->estado, (string)$respuesta);
        $pc1 = $this->pc1($respuesta->json()['data']);
        $this->assertTrue($pc1['enUso']);
        $this->assertNull($pc1['usuarioActual']);
        $this->assertSame('', $pc1['notas']);
    }

    public function test_con_sesion_se_ve_quien_lo_ocupa(): void
    {
        $pc1 = $this->pc1($this->con('docente')->get('equipos.php')->json()['data']);

        $this->assertSame('Ana Alumna (23303001)', $pc1['usuarioActual']);
        $this->assertSame('Teclado flojo', $pc1['notas']);
    }

    public function test_sin_sesion_no_se_crean_equipos_en_un_laboratorio_inventado(): void
    {
        $respuesta = $this->anonimo()->get('equipos.php?laboratorio=inventado');

        $this->assertSame([], $respuesta->json()['data']);
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM equipos WHERE laboratorio = 'inventado'"));
    }

    public function test_el_personal_inicializa_los_equipos_de_un_laboratorio_nuevo(): void
    {
        $respuesta = $this->con('laboratorista')->get('equipos.php?laboratorio=redes');

        $this->assertCount(26, $respuesta->json()['data']);
    }
}
