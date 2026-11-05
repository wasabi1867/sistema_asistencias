<?php

namespace Tests\Api;

/**
 * Cada laboratorista atiende solo los laboratorios que le asigna el administrador
 * (D21). Aquí la laboratorista atiende solo el Laboratorio de Redes.
 */
final class LaboratoriosDelPersonalTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->ok($this->con('admin')->put("usuarios/$this->laboratorista", ['laboratorios' => ['redes']]));
    }

    public function test_el_administrador_asigna_los_laboratorios_y_queda_en_la_bitacora(): void
    {
        $cuenta = $this->ok($this->con('admin')->get("usuarios/$this->laboratorista"));
        $sesion = $this->ok($this->con('laboratorista')->get('sesion'));

        $this->assertSame([['redes'], ['redes']], [$cuenta['laboratorios'], $sesion['laboratorios']]);
        $this->assertSame(
            'Cuenta ' . self::MATRICULA_LABORATORISTA . ': Laboratorio de Redes',
            $this->valor("SELECT detalles FROM bitacora WHERE accion = 'asignar_laboratorios' ORDER BY id DESC LIMIT 1")
        );
    }

    public function test_solo_al_laboratorista_se_le_asignan_laboratorios(): void
    {
        $respuesta = $this->con('admin')->put("usuarios/$this->docente", ['laboratorios' => ['redes']]);

        $this->assertSame([400, 'laboratorios'], [$respuesta->estado, $respuesta->json()['campo']]);
    }

    public function test_al_quitarle_el_rol_de_laboratorista_deja_de_atender_sus_laboratorios(): void
    {
        $cuenta = $this->ok($this->con('admin')->put("usuarios/$this->laboratorista", ['roles' => ['docente']]));

        $this->assertSame([], $cuenta['laboratorios']);
    }

    public function test_al_crear_una_cuenta_de_laboratorista_se_le_asignan_sus_laboratorios(): void
    {
        $cuenta = $this->ok($this->con('admin')->post('usuarios', [
            'roles' => ['laboratorista'], 'matricula' => 'L200', 'nombre' => 'Gil', 'contrasena' => 'una-contrasena-larga', 'laboratorios' => ['programacion', 'redes'],
        ]), 201);

        $this->assertSame(['redes', 'programacion'], $cuenta['laboratorios']);
    }

    public function test_en_un_laboratorio_que_no_atiende_no_puede_actuar(): void
    {
        $laboratorista = $this->con('laboratorista');
        $clase = $this->abrirClase($this->crearCurso());
        $this->crearAlumno('23303001');
        $solicitud = $this->insertar('solicitudes_ingreso', ['laboratorio_id' => $this->laboratorio(), 'alumno_id' => $this->crearAlumno('23303002')]);
        $evento = $this->insertar('eventos', ['laboratorio_id' => $this->laboratorio(), 'nombre' => 'Hackatón']);

        $intentos = [
            'abrir una clase de invitado' => $laboratorista->post('clases', ['tipo' => 'invitado', 'laboratorio' => 'programacion', 'materia' => 'Arduino']),
            'cerrar una clase' => $laboratorista->post("clases/$clase/cerrar"),
            'registrar el uso fuera de clase' => $laboratorista->post('asistencias', ['laboratorio' => 'programacion', 'matricula' => '23303001', 'equipoPropio' => true]),
            'resolver una solicitud de fuera de clase' => $laboratorista->post("solicitudes/$solicitud/resolver", ['estado' => 'rechazada']),
            'configurar los equipos' => $laboratorista->post('laboratorios/programacion/equipos/configurar', ['cantidad' => 20]),
            'sacar de servicio un equipo' => $laboratorista->put('equipos/' . $this->equipo('PC-1'), ['fueraDeServicio' => true]),
            'crear un evento' => $laboratorista->post('eventos', ['laboratorio' => 'programacion', 'nombre' => 'Concurso']),
            'borrar un evento' => $laboratorista->delete("eventos/$evento"),
        ];

        foreach ($intentos as $que => $respuesta) {
            $this->assertSame(403, $respuesta->estado, "$que: $respuesta");
        }
        $this->assertSame(
            'No atiendes el Taller de Programación. Si te toca, pide al administrador que te lo asigne',
            $intentos['abrir una clase de invitado']->json()['error']
        );
        $this->assertNull($this->valor('SELECT cerrada_en FROM clases WHERE id = ?', [$clase]));
    }

    public function test_en_el_laboratorio_que_atiende_si_puede(): void
    {
        $laboratorista = $this->con('laboratorista');
        $this->crearAlumno('23303001');

        $this->ok($laboratorista->post('clases', ['tipo' => 'invitado', 'laboratorio' => 'redes', 'materia' => 'Arduino']), 201);
        $this->ok($laboratorista->post('asistencias', ['laboratorio' => 'redes', 'matricula' => '23303001', 'equipoPropio' => true]), 201);
        $this->ok($laboratorista->post('laboratorios/redes/equipos/configurar', ['cantidad' => 2]));
        $this->ok($laboratorista->put('equipos/' . $this->equipo('PC-1', 'redes'), ['fueraDeServicio' => true]));
    }

    public function test_el_administrador_y_el_docente_no_dependen_de_la_asignacion(): void
    {
        $clase = $this->abrirClase($this->crearCurso());

        $this->ok($this->con('admin')->post('clases', ['tipo' => 'invitado', 'laboratorio' => 'redes', 'materia' => 'Arduino']), 201);
        $this->ok($this->con('docente')->post("clases/$clase/cerrar"));
    }
}
