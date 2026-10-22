<?php

namespace Tests\Api;

final class AlumnosTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_el_laboratorista_da_de_alta_un_alumno(): void
    {
        $alumno = $this->ok($this->con('laboratorista')->post('alumnos', [
            'matricula' => '23303080', 'nombre' => 'Ana', 'apellido' => 'Ruiz', 'carrera' => 'ingenieria en sistemas computacionales',
            'semestre' => 5, 'turno' => 'Matutino', 'grupo' => 'A',
        ]), 201);

        $this->assertSame(['clave' => 'ISIC', 'nombre' => 'Ingeniería en Sistemas Computacionales'], $alumno['carrera']);
        $this->assertSame('matutino', $alumno['turno']);
        $this->assertSame('Alumno 23303080', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'crear'"));
    }

    public function test_los_datos_invalidos_dicen_que_campo_revisar(): void
    {
        $laboratorista = $this->con('laboratorista');
        $base = ['matricula' => '23303080', 'nombre' => 'Ana', 'apellido' => 'Ruiz'];

        $casos = [
            'carrera' => $base + ['carrera' => 'Medicina'],
            'semestre' => $base + ['semestre' => 20],
            'turno' => $base + ['turno' => 'nocturno'],
            'matricula' => ['matricula' => '233 030', 'nombre' => 'Ana', 'apellido' => 'Ruiz'],
            'apellido' => ['matricula' => '23303080', 'nombre' => 'Ana'],
        ];
        foreach ($casos as $campo => $cuerpo) {
            $respuesta = $laboratorista->post('alumnos', $cuerpo);
            $this->assertSame([400, $campo], [$respuesta->estado, $respuesta->json()['campo'] ?? null], (string)$respuesta);
        }
    }

    public function test_una_matricula_repetida_responde_409(): void
    {
        $this->crearAlumno('23303080');

        $respuesta = $this->con('laboratorista')->post('alumnos', ['matricula' => '23303080', 'nombre' => 'Otra', 'apellido' => 'Persona']);

        $this->assertSame([409, 'Ya hay un alumno con esa matrícula'], [$respuesta->estado, $respuesta->json()['error']]);
    }

    public function test_busca_por_matricula_o_nombre_y_pagina(): void
    {
        $this->crearAlumno('23303001', 'Ana', 'Ruiz');
        $this->crearAlumno('23303002', 'Beto', 'Paz');
        $this->crearAlumno('23303003', 'Carla', 'Ruiz');
        $docente = $this->con('docente');

        $porNombre = $this->ok($docente->get('alumnos?q=ruiz'));
        $porMatricula = $this->ok($docente->get('alumnos?q=233030'));
        $pagina2 = $this->ok($docente->get('alumnos?porPagina=2&pagina=2'));

        $this->assertSame(['23303001', '23303003'], array_column($porNombre['alumnos'], 'matricula'));
        $this->assertSame(3, $porMatricula['total']);
        $this->assertSame(['Ruiz'], array_column($pagina2['alumnos'], 'apellido'));
        $this->assertSame(3, $pagina2['total']);
    }

    public function test_los_comodines_de_la_busqueda_son_texto(): void
    {
        $this->crearAlumno('23303001');

        $this->assertSame(0, $this->ok($this->con('docente')->get('alumnos?q=%25'))['total']);
    }

    public function test_un_alumno_desactivado_no_sale_en_la_lista_salvo_que_se_pida(): void
    {
        $this->crearAlumno('23303001');
        $laboratorista = $this->con('laboratorista');
        $this->ok($laboratorista->put('alumnos/23303001', ['activo' => false]));

        $this->assertSame(0, $this->ok($laboratorista->get('alumnos'))['total']);
        $this->assertSame(1, $this->ok($laboratorista->get('alumnos?activo=0'))['total']);
    }

    public function test_se_edita_por_matricula_y_dice_sus_cursos(): void
    {
        $alumno = $this->crearAlumno('23303001');
        $this->inscribir($this->crearCurso(), $alumno);
        $laboratorista = $this->con('laboratorista');

        $editado = $this->ok($laboratorista->put('alumnos/23303001', ['grupo' => 'B', 'semestre' => 6]));
        $consultado = $this->ok($laboratorista->get('alumnos/23303001'));

        $this->assertSame(['B', 6], [$editado['grupo'], $editado['semestre']]);
        $this->assertSame(['Programación Web'], array_column($consultado['cursos'], 'materia'));
        $this->assertSame(404, $laboratorista->get('alumnos/99999999')->estado);
    }

    public function test_un_alumno_con_asistencias_no_se_borra(): void
    {
        $alumno = $this->crearAlumno('23303001');
        $curso = $this->crearCurso();
        $this->registrarEntrada($this->abrirClase($curso), $alumno);
        $sinHistorial = $this->crearAlumno('23303002');
        $this->inscribir($curso, $sinHistorial);
        $admin = $this->con('admin');

        $this->assertSame(409, $admin->delete('alumnos/23303001')->estado);
        $this->ok($admin->delete('alumnos/23303002'), 204);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM inscripciones WHERE alumno_id = ?', [$sinHistorial]));
    }

    public function test_la_carga_en_lote_crea_a_los_nuevos_y_actualiza_a_los_demas(): void
    {
        $this->crearAlumno('23303001', 'Ana', 'Ruiz');
        $this->pdo()->exec("UPDATE alumnos SET activo = FALSE WHERE matricula = '23303001'");

        $resultado = $this->ok($this->con('laboratorista')->post('alumnos/lote', ['alumnos' => [
            ['matricula' => '23303001', 'nombre' => 'Ana María', 'apellido' => 'Ruiz', 'carrera' => 'ISIC', 'semestre' => 6],
            ['matricula' => '23303002', 'nombre' => 'Beto', 'apellido' => 'Paz', 'carrera' => 'Ingeniería Industrial', 'turno' => 'vespertino'],
        ]]));

        $this->assertSame(['creados' => 1, 'actualizados' => 1], $resultado);
        $ana = $this->fila("SELECT nombre, semestre, grupo, activo FROM alumnos WHERE matricula = '23303001'");
        // Lo que el archivo dejó vacío (el grupo) se conserva, y vuelve a estar activa.
        $this->assertSame(['nombre' => 'Ana María', 'semestre' => 6, 'grupo' => 'A', 'activo' => 1], $ana);
        $this->assertSame('1 nuevos, 1 actualizados', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'carga_de_alumnos'"));
    }

    public function test_si_un_renglon_del_lote_tiene_error_no_se_guarda_ninguno(): void
    {
        $respuesta = $this->con('laboratorista')->post('alumnos/lote', ['alumnos' => [
            ['matricula' => '23303001', 'nombre' => 'Ana', 'apellido' => 'Ruiz'],
            ['matricula' => '23303002', 'nombre' => 'Beto', 'apellido' => 'Paz', 'carrera' => 'Medicina'],
            ['matricula' => '23303001', 'nombre' => 'Ana', 'apellido' => 'Repetida'],
        ]]);

        $this->assertSame(400, $respuesta->estado);
        $this->assertSame('2 renglones tienen errores; no se guardó ninguno', $respuesta->json()['error']);
        $this->assertSame([2, 3], array_column($respuesta->json()['renglones'], 'renglon'));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM alumnos'));
    }

    public function test_el_cambio_masivo_pasa_de_semestre_a_un_grupo(): void
    {
        $this->crearAlumno('23303001');
        $this->crearAlumno('23303002');
        $this->crearAlumno('23303003', carrera: 'IIND');

        $resultado = $this->ok($this->con('laboratorista')->post('alumnos/cambio-masivo', ['carrera' => 'ISIC', 'semestre' => 5, 'nuevoSemestre' => 6]));

        $this->assertSame(['actualizados' => 2], $resultado);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM alumnos WHERE semestre = 5'));
    }

    public function test_el_cambio_masivo_necesita_saber_que_cambiar(): void
    {
        $respuesta = $this->con('laboratorista')->post('alumnos/cambio-masivo', ['carrera' => 'ISIC', 'semestre' => 5]);

        $this->assertSame(400, $respuesta->estado);
    }
}
