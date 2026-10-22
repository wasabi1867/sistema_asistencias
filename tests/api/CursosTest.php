<?php

namespace Tests\Api;

final class CursosTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_el_administrador_crea_un_curso_del_periodo(): void
    {
        $periodo = $this->crearPeriodoActual('AGO-DIC 2026');
        $materia = $this->crearMateria();

        $curso = $this->ok($this->con('admin')->post('cursos', [
            'materiaId' => $materia, 'periodoId' => $periodo, 'grupo' => '5a', 'turno' => 'matutino', 'docenteId' => $this->docente, 'laboratorio' => 'programacion',
        ]), 201);

        $this->assertSame('5A', $curso['grupo']);
        $this->assertSame(['id' => $periodo, 'nombre' => 'AGO-DIC 2026', 'actual' => true], $curso['periodo']);
        $this->assertSame(['clave' => 'programacion', 'nombre' => 'Taller de Programación'], $curso['laboratorio']);
        $this->assertSame(0, $curso['inscritos']);
    }

    public function test_no_se_repite_materia_grupo_y_periodo_ni_se_asigna_a_quien_no_es_docente(): void
    {
        $curso = $this->crearCurso();
        $existente = $this->fila('SELECT materia_id, periodo_id FROM cursos WHERE id = ?', [$curso]);
        $admin = $this->con('admin');

        $repetido = $admin->post('cursos', ['materiaId' => $existente['materia_id'], 'periodoId' => $existente['periodo_id'], 'grupo' => '5A']);
        $aLaboratorista = $admin->post('cursos', ['materiaId' => $existente['materia_id'], 'periodoId' => $existente['periodo_id'], 'grupo' => '5B', 'docenteId' => $this->laboratorista]);

        $this->assertSame([409, 'Esa materia ya tiene ese grupo en el periodo'], [$repetido->estado, $repetido->json()['error']]);
        $this->assertSame([400, 'docenteId'], [$aLaboratorista->estado, $aLaboratorista->json()['campo']]);
    }

    public function test_la_lista_muestra_los_del_periodo_actual_y_los_del_docente(): void
    {
        $actual = $this->crearCurso();
        $pasado = $this->insertar('periodos', ['nombre' => 'Pasado', 'fecha_inicio' => '2020-01-01', 'fecha_fin' => '2020-06-30']);
        $this->crearCurso(grupo: '5B', periodo: $pasado);
        $otro = $this->crearCurso(grupo: '5C', docente: $this->crearDocente('D200'));

        $docente = $this->con('docente');
        $this->assertSame([$actual, $otro], array_column($this->ok($docente->get('cursos')), 'id'));
        $this->assertSame([$actual], array_column($this->ok($docente->get('cursos?docente=yo')), 'id'));
        $this->assertCount(3, $this->ok($docente->get('cursos?periodo=todos')));
    }

    public function test_el_docente_inscribe_a_su_grupo_con_una_lista_de_matriculas(): void
    {
        $curso = $this->crearCurso();
        $this->crearAlumno('23303001');
        $this->crearAlumno('23303002');
        $inactivo = $this->crearAlumno('23303003');
        $this->pdo()->exec("UPDATE alumnos SET activo = FALSE WHERE id = $inactivo");
        $docente = $this->con('docente');
        $this->ok($docente->post("cursos/$curso/alumnos", ['matriculas' => ['23303002']]));

        $resultado = $this->ok($docente->post("cursos/$curso/alumnos", ['matriculas' => ['23303001', ' 23303002 ', '23303003', '99999999', '23303001']]));

        $this->assertSame(['inscritos' => ['23303001'], 'yaInscritos' => ['23303002'], 'noEncontrados' => ['99999999'], 'inactivos' => ['23303003']], $resultado);
        $this->assertSame(['23303001', '23303002'], array_column($this->ok($docente->get("cursos/$curso/alumnos")), 'matricula'));
        $this->assertSame(2, $this->ok($docente->get("cursos/$curso"))['inscritos']);
    }

    public function test_solo_el_personal_o_el_docente_del_curso_cambian_su_lista(): void
    {
        $ajeno = $this->crearCurso(docente: $this->crearDocente('D200'));
        $this->crearAlumno('23303001');

        $this->assertSame(403, $this->con('docente')->post("cursos/$ajeno/alumnos", ['matriculas' => ['23303001']])->estado);
        $this->ok($this->con('laboratorista')->post("cursos/$ajeno/alumnos", ['matriculas' => ['23303001']]));
    }

    public function test_dar_de_baja_quita_la_inscripcion(): void
    {
        $curso = $this->crearCurso();
        $this->inscribir($curso, $this->crearAlumno('23303001'));
        $docente = $this->con('docente');

        $this->ok($docente->delete("cursos/$curso/alumnos/23303001"), 204);

        $this->assertSame(404, $docente->delete("cursos/$curso/alumnos/23303001")->estado);
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM bitacora WHERE accion = 'dar_de_baja'"));
    }

    public function test_un_curso_con_clases_no_se_borra(): void
    {
        $conClases = $this->crearCurso();
        $this->abrirClase($conClases);
        $sinClases = $this->crearCurso(grupo: '5B');
        $this->inscribir($sinClases, $this->crearAlumno());
        $admin = $this->con('admin');

        $this->assertSame([409, 'El curso ya tiene clases registradas y no se puede eliminar'], [
            $admin->delete("cursos/$conClases")->estado, $admin->delete("cursos/$conClases")->json()['error'],
        ]);
        $this->ok($admin->delete("cursos/$sinClases"), 204);
    }

    public function test_el_resumen_de_asistencia_no_cuenta_faltas_antes_de_inscribirse(): void
    {
        $curso = $this->crearCurso();
        $ana = $this->crearAlumno('23303001', 'Ana', 'Alumna');
        $beto = $this->crearAlumno('23303002', 'Beto', 'Bueno');
        $this->inscribir($curso, $ana);
        $clases = [];
        foreach (['-20 days', '-10 days', '-2 days'] as $hace) {
            $clase = $this->abrirClase($curso);
            $this->pdo()->prepare("UPDATE clases SET abierta_en = ?, cerrada_en = ? + INTERVAL 2 HOUR, cierre = 'normal' WHERE id = ?")
                ->execute([date('Y-m-d H:i:s', strtotime($hace)), date('Y-m-d H:i:s', strtotime($hace)), $clase]);
            $clases[] = $clase;
        }
        // Beto se inscribió después de la segunda clase.
        $this->insertar('inscripciones', ['curso_id' => $curso, 'alumno_id' => $beto, 'inscrito_en' => date('Y-m-d H:i:s', strtotime('-5 days'))]);
        foreach ([[$clases[0], $ana], [$clases[2], $ana], [$clases[2], $beto]] as [$clase, $alumno]) {
            $this->registrarEntrada($clase, $alumno);
            $this->pdo()->exec('UPDATE asistencias SET hora_salida = hora_entrada');
        }

        $resumen = $this->ok($this->con('docente')->get("cursos/$curso/asistencia"));

        $this->assertSame(3, $resumen['clases']);
        $this->assertSame(
            [['23303001', 3, 2, 1, 66.7], ['23303002', 1, 1, 0, 100.0]],
            array_map(fn ($a) => [$a['matricula'], $a['clases'], $a['asistencias'], $a['faltas'], $a['porcentaje']], $resumen['alumnos'])
        );
    }
}
