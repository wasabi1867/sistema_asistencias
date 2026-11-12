<?php

namespace Tests\Api;

final class ClasesTest extends PruebaDeApi
{
    private int $curso;
    private int $materia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->materia = $this->crearMateria();
        $this->curso = $this->crearCurso($this->materia);
    }

    public function test_el_docente_abre_la_clase_de_su_curso_en_su_laboratorio(): void
    {
        $practica = $this->crearPractica($this->materia, 1, 'HTML', $this->docente);

        $clase = $this->ok($this->con('docente')->post('clases', ['cursoId' => $this->curso, 'practicaId' => $practica]), 201);

        $this->assertSame(['regular', 'programacion', 'Programación Web', '5A', 'Diego Docente'], [
            $clase['tipo'], $clase['laboratorio']['clave'], $clase['materia'], $clase['grupo'], $clase['docente'],
        ]);
        $this->assertSame('HTML', $clase['practica']['titulo']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $clase['codigo']);
        $this->assertTrue($clase['abierta']);
        $this->assertSame('Programación Web 5A', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'abrir_clase'"));
    }

    public function test_solo_su_docente_o_el_administrador_abren_una_clase_regular(): void
    {
        $ajeno = $this->crearCurso(grupo: '5B', docente: $this->crearDocente('D200'));

        $this->assertSame(403, $this->con('docente')->post('clases', ['cursoId' => $ajeno])->estado);
        $this->assertSame(403, $this->con('laboratorista')->post('clases', ['cursoId' => $this->curso])->estado);
        $this->ok($this->con('admin')->post('clases', ['cursoId' => $ajeno]), 201);
    }

    public function test_la_practica_tiene_que_ser_de_la_materia_del_curso(): void
    {
        $deOtraMateria = $this->crearPractica($this->crearMateria('Redes'), 1, 'Cableado');
        $deOtroDocente = $this->crearPractica($this->materia, 1, 'Ajena', $this->crearDocente('D200'));
        $docente = $this->con('docente');

        $otraMateria = $docente->post('clases', ['cursoId' => $this->curso, 'practicaId' => $deOtraMateria]);
        $otroDocente = $docente->post('clases', ['cursoId' => $this->curso, 'practicaId' => $deOtroDocente]);

        $this->assertSame([400, 'practicaId'], [$otraMateria->estado, $otraMateria->json()['campo']]);
        $this->assertSame([400, 'practicaId'], [$otroDocente->estado, $otroDocente->json()['campo']]);
    }

    public function test_un_laboratorio_tiene_una_sola_clase_regular_abierta(): void
    {
        $otro = $this->crearCurso(grupo: '5B', docente: $this->crearDocente('D200'));
        $this->abrirClase($otro);

        $respuesta = $this->con('docente')->post('clases', ['cursoId' => $this->curso]);

        $this->assertSame([409, 'Ya hay una clase abierta en el Taller de Programación', 'laboratorio_ocupado'], [$respuesta->estado, $respuesta->json()['error'], $respuesta->json()['codigo']]);
        // En otro laboratorio sí puede.
        $this->ok($this->con('docente')->post('clases', ['cursoId' => $this->curso, 'laboratorio' => 'redes']), 201);
    }

    public function test_un_docente_no_tiene_dos_clases_abiertas_a_la_vez(): void
    {
        $otroCursoSuyo = $this->crearCurso(grupo: '5B');
        $this->abrirClase($otroCursoSuyo, 'redes');

        $respuesta = $this->con('docente')->post('clases', ['cursoId' => $this->curso]);

        $this->assertSame([409, 'docente_con_clase_abierta'], [$respuesta->estado, $respuesta->json()['codigo']]);
    }

    public function test_no_se_abre_un_curso_de_un_periodo_que_ya_termino(): void
    {
        $pasado = $this->insertar('periodos', ['nombre' => 'ENE-JUN 2020', 'fecha_inicio' => '2020-01-01', 'fecha_fin' => '2020-06-30']);
        $viejo = $this->crearCurso(grupo: '5B', periodo: $pasado);

        $respuesta = $this->con('docente')->post('clases', ['cursoId' => $viejo]);

        $this->assertSame([409, 'periodo_terminado'], [$respuesta->estado, $respuesta->json()['codigo']]);
    }

    public function test_el_laboratorista_abre_una_clase_de_maestro_invitado_junto_a_la_regular(): void
    {
        $this->abrirClase($this->curso);

        $invitado = $this->ok($this->con('laboratorista')->post('clases', [
            'tipo' => 'invitado', 'laboratorio' => 'programacion', 'materia' => 'Taller de Arduino', 'docente' => 'Iván Invitado', 'departamento' => 'Electrónica',
        ]), 201);

        $this->assertSame(['invitado', 'Taller de Arduino', 'Iván Invitado'], [$invitado['tipo'], $invitado['materia'], $invitado['docente']]);
        $this->assertSame(['clase', 'media'], array_values($this->fila('SELECT tipo, prioridad FROM avisos WHERE clase_id = ?', [$invitado['id']])));
        $this->assertSame(403, $this->con('docente')->post('clases', ['tipo' => 'invitado', 'laboratorio' => 'redes', 'materia' => 'X'])->estado);
    }

    public function test_al_cerrar_se_registra_la_salida_de_todos(): void
    {
        $clase = $this->abrirClase($this->curso);
        $this->registrarEntrada($clase, $this->crearAlumno('23303001'), $this->equipo('PC-1'));
        $this->registrarEntrada($clase, $this->crearAlumno('23303002'));

        $cerrada = $this->ok($this->con('docente')->post("clases/$clase/cerrar"));

        $this->assertSame([false, 'normal', 'Diego Docente'], [$cerrada['abierta'], $cerrada['cierre'], $cerrada['cerradaPor']]);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias WHERE hora_salida IS NULL'));
        $this->assertSame('libre', $this->ok($this->anonimo()->get('laboratorios/programacion/equipos'))[0]['estado']);
        $this->assertSame('Cierre normal, 2 salidas registradas', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'cerrar_clase'"));
    }

    public function test_el_cierre_dice_quien_la_cerro(): void
    {
        $porLaboratorista = $this->abrirClase($this->curso);
        $this->assertSame('externo', $this->ok($this->con('laboratorista')->post("clases/$porLaboratorista/cerrar"))['cierre']);

        $alSalir = $this->abrirClase($this->curso);
        $this->assertSame('automatico', $this->ok($this->con('docente')->post("clases/$alSalir/cerrar", ['automatico' => true]))['cierre']);
    }

    public function test_no_se_cierra_dos_veces_ni_la_cierra_otro_docente(): void
    {
        $clase = $this->abrirClase($this->curso);
        $this->crearDocente('D200');

        $this->assertSame(403, $this->con('docente', 'D200')->post("clases/$clase/cerrar")->estado);
        $this->ok($this->con('docente')->post("clases/$clase/cerrar"));
        $segunda = $this->con('docente')->post("clases/$clase/cerrar");
        $this->assertSame([409, 'clase_cerrada'], [$segunda->estado, $segunda->json()['codigo']]);
    }

    public function test_sin_sesion_se_ve_la_clase_abierta_pero_no_su_codigo(): void
    {
        $clase = $this->abrirClase($this->curso, codigo: '4821');

        $abiertas = $this->ok($this->anonimo()->get('clases/abiertas?laboratorio=programacion'));

        $this->assertSame([['id' => $clase, 'tipo' => 'regular', 'materia' => 'Programación Web', 'practica' => null, 'docente' => 'Diego Docente', 'grupo' => '5A']],
            array_map(fn ($c) => array_intersect_key($c, array_flip(['id', 'tipo', 'materia', 'practica', 'docente', 'grupo'])), $abiertas));
        $this->assertFalse($abiertas[0]['pideCodigo']);
        $this->assertStringNotContainsString('4821', json_encode($abiertas));
    }

    public function test_la_clase_trae_su_lista_con_faltas_y_el_codigo_solo_para_quien_la_lleva(): void
    {
        $clase = $this->abrirClase($this->curso, codigo: '4821');
        $ana = $this->crearAlumno('23303001', 'Ana', 'Alumna');
        $beto = $this->crearAlumno('23303002', 'Beto', 'Bueno');
        $this->inscribir($this->curso, $ana, $beto);
        $this->registrarEntrada($clase, $ana, $this->equipo('PC-3'));
        $this->crearDocente('D200');

        $vista = $this->ok($this->con('docente')->get("clases/$clase"));
        $deOtro = $this->ok($this->con('docente', 'D200')->get("clases/$clase"), 200);

        $this->assertSame('4821', $vista['codigo']);
        $this->assertArrayNotHasKey('codigo', $deOtro);
        $this->assertSame(['inscritos' => 2, 'presentes' => 1, 'faltas' => 1], array_slice($vista['lista'], 0, 3));
        $this->assertSame([['23303001', true, 'PC-3'], ['23303002', false, null]], array_map(fn ($a) => [$a['matricula'], $a['presente'], $a['equipo']], $vista['lista']['alumnos']));
        $this->assertSame(['23303001'], array_column($vista['personas'], 'matricula'));
    }

    public function test_la_lista_de_clases_se_filtra_y_trae_a_los_asistentes(): void
    {
        $vieja = $this->abrirClase($this->curso);
        $this->registrarEntrada($vieja, $this->crearAlumno('23303001'));
        $this->ok($this->con('docente')->post("clases/$vieja/cerrar"));
        $this->abrirClase($this->curso);
        $docente = $this->con('docente');

        $cerradas = $this->ok($docente->get('clases?estado=cerradas&docente=yo&conAsistentes=1'));
        $hoy = $this->ok($docente->get('clases?laboratorio=programacion&desde=' . date('Y-m-d') . '&hasta=' . date('Y-m-d')));

        $this->assertSame([$vieja], array_column($cerradas['clases'], 'id'));
        $this->assertSame(['23303001'], array_column($cerradas['clases'][0]['personas'], 'matricula'));
        $this->assertSame(2, $hoy['total']);
    }

    public function test_quien_lleva_la_clase_registra_a_mano_a_un_inscrito(): void
    {
        $clase = $this->abrirClase($this->curso);
        $this->inscribir($this->curso, $this->crearAlumno('23303001'));
        $this->crearAlumno('23303002');
        $docente = $this->con('docente');

        $registrada = $this->ok($docente->post("clases/$clase/asistencias", ['matricula' => '23303001', 'equipo' => 'PC-7']), 201);
        $noInscrito = $docente->post("clases/$clase/asistencias", ['matricula' => '23303002', 'equipoPropio' => true]);

        $this->assertSame(['23303001', 'PC-7'], [$registrada['matricula'], $registrada['equipo']]);
        $this->assertSame($this->docente, (int)$this->valor('SELECT registrada_por FROM asistencias WHERE id = ?', [$registrada['id']]));
        $this->assertSame([409, 'no_inscrito'], [$noInscrito->estado, $noInscrito->json()['codigo']]);
    }

    public function test_en_una_clase_de_invitado_se_registra_a_alguien_de_fuera_con_su_nombre(): void
    {
        $clase = $this->abrirClaseDeInvitado();

        $registrada = $this->ok($this->con('laboratorista')->post("clases/$clase/asistencias", ['nombre' => 'Visita', 'apellido' => 'Externa', 'equipoPropio' => true]), 201);

        $this->assertSame(['invitado', 'Visita', null], [$registrada['tipo'], $registrada['nombre'], $registrada['equipo']]);
    }

    public function test_la_lista_de_una_clase_pasada_es_de_quienes_estaban_inscritos_entonces(): void
    {
        $hace = static fn (string $cuanto) => date('Y-m-d H:i:s', strtotime($cuanto));
        $clase = $this->abrirClase($this->curso);
        $this->pdo()->prepare("UPDATE clases SET abierta_en = ?, cerrada_en = ?, cierre = 'normal' WHERE id = ?")
            ->execute([$hace('-10 days'), $hace('-10 days +2 hours'), $clase]);
        $inscripciones = [
            // Inscrita desde antes: le toca la clase.
            ['23303001', 'Ana', '-30 days', null],
            // Se inscribió después de la clase: no es falta suya.
            ['23303002', 'Beto', '-5 days', null],
            // Se dio de baja después de la clase: la falta se queda.
            ['23303003', 'Caro', '-30 days', '-2 days'],
            // Se dio de baja antes de la clase: ya no le tocaba.
            ['23303004', 'Dani', '-30 days', '-20 days'],
        ];
        foreach ($inscripciones as [$matricula, $nombre, $inscrito, $baja]) {
            $this->insertar('inscripciones', [
                'curso_id' => $this->curso, 'alumno_id' => $this->crearAlumno($matricula, $nombre, 'Alumno'),
                'inscrito_en' => $hace($inscrito), 'baja_en' => $baja === null ? null : $hace($baja),
            ]);
        }

        $lista = $this->ok($this->con('docente')->get("clases/$clase"))['lista'];

        $this->assertSame(['23303001', '23303003'], array_column($lista['alumnos'], 'matricula'));
        $this->assertSame([2, 0, 2], [$lista['inscritos'], $lista['presentes'], $lista['faltas']]);
    }

    public function test_la_clase_sigue_siendo_del_docente_que_la_dio_aunque_el_curso_cambie_de_docente(): void
    {
        $diego = $this->con('docente');
        $clase = $this->ok($diego->post('clases', ['cursoId' => $this->curso]), 201)['id'];
        $this->ok($diego->post("clases/$clase/cerrar"));
        $otro = $this->crearDocente('D200', 'Olga', 'Otra');

        $this->ok($this->con('admin')->put("cursos/$this->curso", ['docenteId' => $otro]));

        $olga = $this->con('docente', 'D200');
        $this->assertSame('Diego Docente', $this->ok($olga->get("clases/$clase"))['docente']);
        $this->assertSame([$clase], array_column($this->ok($diego->get('clases?docente=yo'))['clases'], 'id'));
        $this->assertSame([], $this->ok($olga->get('clases?docente=yo'))['clases']);
        // Las clases nuevas del curso ya son de Olga.
        $nueva = $this->ok($olga->post('clases', ['cursoId' => $this->curso]), 201);
        $this->assertSame(['Olga Otra', $otro], [$nueva['docente'], $nueva['docenteId']]);
        $this->assertSame(403, $diego->post("clases/{$nueva['id']}/cerrar")->estado);
    }

    public function test_una_clase_olvidada_la_cierra_el_sistema_a_la_hora_del_limite(): void
    {
        $olvidada = $this->abrirClase($this->curso);
        $reciente = $this->abrirClaseDeInvitado();
        $ana = $this->crearAlumno('23303001', 'Ana', 'Alumna');
        $beto = $this->crearAlumno('23303002', 'Beto', 'Bueno');
        $this->inscribir($this->curso, $ana, $beto);
        $deAna = $this->registrarEntrada($olvidada, $ana, $this->equipo('PC-3'));
        $deBeto = $this->registrarEntrada($olvidada, $beto);
        $this->pdo()->exec("UPDATE clases SET abierta_en = NOW() - INTERVAL 50 HOUR WHERE id = $olvidada");
        $this->pdo()->exec("UPDATE clases SET abierta_en = NOW() - INTERVAL 5 HOUR WHERE id = $reciente");
        $this->pdo()->exec("UPDATE asistencias SET hora_entrada = NOW() - INTERVAL 49 HOUR WHERE id = $deAna");
        // No debería haber una entrada después del límite; si la hay, su salida no queda antes que su entrada.
        $this->pdo()->exec("UPDATE asistencias SET hora_entrada = NOW() - INTERVAL 1 HOUR WHERE id = $deBeto");

        // Con el límite en cero no se cierra ninguna.
        $sinLimite = new Cliente(Entorno::url(['ASISTENCIAS_HORAS_MAXIMAS_DE_CLASE' => '0']));
        $this->assertCount(2, $sinLimite->get('clases/abiertas?laboratorio=programacion')->json());

        // Cualquier petición las revisa, también una sin sesión.
        $abiertas = $this->ok($this->anonimo()->get('clases/abiertas?laboratorio=programacion'));

        $this->assertSame([$reciente], array_column($abiertas, 'id'));
        $this->assertSame(
            ['cierre' => 'vencida', 'cerrada_por' => null, 'horas' => 6],
            $this->fila('SELECT cierre, cerrada_por, TIMESTAMPDIFF(HOUR, abierta_en, cerrada_en) AS horas FROM clases WHERE id = ?', [$olvidada])
        );
        $this->assertSame(1, (int)$this->valor('SELECT hora_salida = (SELECT cerrada_en FROM clases WHERE id = ?) FROM asistencias WHERE id = ?', [$olvidada, $deAna]));
        $this->assertSame(1, (int)$this->valor('SELECT hora_salida = hora_entrada FROM asistencias WHERE id = ?', [$deBeto]));
        $this->assertSame(
            ['usuario' => 'Sistema', 'usuario_id' => null, 'detalles' => 'Cierre vencida: llevaba más de 6 horas abierta, 2 salidas registradas'],
            $this->fila("SELECT usuario, usuario_id, detalles FROM bitacora WHERE accion = 'cerrar_clase'")
        );
        // El laboratorio y el equipo quedan libres, y la clase dice cómo se cerró.
        $this->ok($this->con('docente')->post('clases', ['cursoId' => $this->curso]), 201);
        $vista = $this->ok($this->con('admin')->get("clases/$olvidada"));
        $this->assertSame(['vencida', null, false], [$vista['cierre'], $vista['cerradaPor'], $vista['abierta']]);
        $this->ok($this->anonimo()->post('registro/entrada', ['laboratorio' => 'programacion', 'matricula' => '23303001', 'equipo' => 'PC-3']), 201);
    }
}
