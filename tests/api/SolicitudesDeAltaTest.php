<?php

namespace Tests\Api;

final class SolicitudesDeAltaTest extends PruebaDeApi
{
    private int $curso;
    private int $clase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->curso = $this->crearCurso();
        $this->clase = $this->abrirClase($this->curso);
    }

    private function pedir(array $datos = []): Respuesta
    {
        return $this->anonimo()->post('solicitudes-de-alta', $datos + [
            'laboratorio' => 'programacion', 'matricula' => '24303099', 'nombre' => 'Nora', 'apellido' => 'Nueva Pérez',
            'semestre' => 3, 'grupo' => 'S3U', 'claseId' => $this->clase,
        ]);
    }

    private function resolver(int $id, string $estado, array $extra = [], string $rol = 'admin'): Respuesta
    {
        return $this->con($rol)->post("solicitudes-de-alta/$id/resolver", ['estado' => $estado] + $extra);
    }

    public function test_el_alumno_que_no_esta_dado_de_alta_pide_su_alta_desde_una_clase(): void
    {
        $solicitud = $this->ok($this->pedir(['matricula' => '24303099 ']), 201);

        $this->assertSame(['pendiente', '24303099', 'Nora', 'Nueva Pérez', 3, 'S3U', 'Programación Web', false], [
            $solicitud['estado'], $solicitud['matricula'], $solicitud['nombre'], $solicitud['apellido'],
            $solicitud['semestre'], $solicitud['grupo'], $solicitud['clase']['materia'], $solicitud['dadoDeAlta'],
        ]);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM alumnos WHERE matricula = ?', ['24303099']));
        $this->assertSame(1, $this->ok($this->con('admin')->get('estadisticas'))['altasPendientes']);
    }

    public function test_no_se_repite_una_pendiente_ni_la_pide_quien_ya_esta_dado_de_alta(): void
    {
        $this->ok($this->pedir(), 201);
        $otra = $this->pedir();
        $this->assertSame([409, 'Ya pediste tu alta. Espera a que el administrador la revise'], [$otra->estado, $otra->json()['error']]);

        $this->crearAlumno('23303001');
        $existente = $this->pedir(['matricula' => '23303001']);
        $this->assertSame([409, 'ya_dado_de_alta'], [$existente->estado, $existente->json()['codigo']]);
    }

    public function test_se_pide_sin_clase_o_con_una_abierta_del_laboratorio(): void
    {
        $sinClase = $this->ok($this->pedir(['claseId' => null]), 201);
        $this->assertNull($sinClase['clase']);

        $this->ok($this->con('docente')->post("clases/$this->clase/cerrar"));
        $cerrada = $this->pedir(['matricula' => '24303100']);
        $this->assertSame([409, 'clase_cerrada'], [$cerrada->estado, $cerrada->json()['codigo']]);
        $this->assertSame(400, $this->pedir(['matricula' => '24 30', 'claseId' => null])->estado);
    }

    public function test_desde_una_clase_de_maestro_invitado_no_queda_ligada_a_ningun_curso(): void
    {
        $this->ok($this->con('docente')->post("clases/$this->clase/cerrar"));
        $invitado = $this->abrirClaseDeInvitado();

        $this->assertNull($this->ok($this->pedir(['claseId' => $invitado]), 201)['clase']);
    }

    public function test_se_limita_cuantas_llegan_de_una_misma_red(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->insertar('solicitudes_de_alta', [
                'matricula' => "X$i", 'nombre' => 'N', 'apellido' => 'A', 'laboratorio_id' => $this->laboratorio(), 'ip' => '127.0.0.1',
            ]);
        }

        $this->assertSame(429, $this->pedir()->estado);
        // El límite es por laboratorio: desde otro sí se puede pedir.
        $this->ok($this->pedir(['laboratorio' => 'redes', 'claseId' => null]), 201);
    }

    public function test_al_aprobarla_se_da_de_alta_con_lo_que_corrigio_el_administrador_y_queda_inscrito(): void
    {
        $solicitud = $this->ok($this->pedir(), 201);

        $resuelta = $this->ok($this->resolver($solicitud['id'], 'aprobada', ['apellido' => 'Nueva', 'carrera' => 'ISIC', 'turno' => 'Matutino']));

        $this->assertSame(['aprobada', true, 'Ada Admin'], [$resuelta['estado'], $resuelta['dadoDeAlta'], $resuelta['resueltaPor']]);
        $alumno = $this->fila(
            'SELECT a.nombre, a.apellido, c.clave AS carrera, a.semestre, a.grupo, a.turno FROM alumnos a LEFT JOIN carreras c ON c.id = a.carrera_id WHERE a.matricula = ?',
            ['24303099']
        );
        $this->assertSame(['nombre' => 'Nora', 'apellido' => 'Nueva', 'carrera' => 'ISIC', 'semestre' => 3, 'grupo' => 'S3U', 'turno' => 'matutino'], $alumno);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM inscripciones i JOIN alumnos a ON a.id = i.alumno_id WHERE a.matricula = ? AND i.curso_id = ?', ['24303099', $this->curso]));
        $this->assertSame('Matrícula 24303099: aprobada', $this->valor("SELECT detalles FROM bitacora WHERE accion = 'resolver_solicitud_de_alta'"));

        // Ya dado de alta e inscrito, registra su entrada como cualquiera.
        $this->ok($this->anonimo()->post('registro/entrada', ['laboratorio' => 'programacion', 'matricula' => '24303099', 'equipoPropio' => true]), 201);
    }

    public function test_se_puede_aprobar_sin_inscribirlo_en_el_curso(): void
    {
        $solicitud = $this->ok($this->pedir(), 201);

        $this->ok($this->resolver($solicitud['id'], 'aprobada', ['inscribir' => false]));

        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM alumnos WHERE matricula = ?', ['24303099']));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM inscripciones'));
    }

    public function test_si_ya_lo_dieron_de_alta_mientras_esperaba_no_se_cambian_sus_datos(): void
    {
        $solicitud = $this->ok($this->pedir(), 201);
        $id = $this->crearAlumno('24303099', 'Nora', 'Como En La Lista');

        $this->ok($this->resolver($solicitud['id'], 'aprobada', ['apellido' => 'Otro']));

        $this->assertSame('Como En La Lista', $this->valor('SELECT apellido FROM alumnos WHERE id = ?', [$id]));
        $this->assertSame($id, (int)$this->valor('SELECT alumno_id FROM solicitudes_de_alta WHERE id = ?', [$solicitud['id']]));
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM inscripciones WHERE alumno_id = ?', [$id]));
    }

    public function test_rechazada_no_da_de_alta_a_nadie_y_no_se_resuelve_dos_veces(): void
    {
        $solicitud = $this->ok($this->pedir(), 201);

        $resuelta = $this->ok($this->resolver($solicitud['id'], 'rechazada', ['comentario' => 'No es de este instituto']));

        $this->assertSame(['rechazada', false, 'No es de este instituto'], [$resuelta['estado'], $resuelta['dadoDeAlta'], $resuelta['comentario']]);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM alumnos WHERE matricula = ?', ['24303099']));
        $otra = $this->resolver($solicitud['id'], 'aprobada');
        $this->assertSame([409, 'ya_resuelta'], [$otra->estado, $otra->json()['codigo']]);
        // Ya resuelta, puede volver a pedirla.
        $this->ok($this->pedir(), 201);
    }

    public function test_un_dato_mal_al_aprobar_no_deja_nada_a_medias(): void
    {
        $solicitud = $this->ok($this->pedir(), 201);

        $mal = $this->resolver($solicitud['id'], 'aprobada', ['carrera' => 'NO-EXISTE']);

        $this->assertSame(400, $mal->estado);
        $this->assertSame('pendiente', $this->valor('SELECT estado FROM solicitudes_de_alta WHERE id = ?', [$solicitud['id']]));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM alumnos'));
    }

    public function test_las_listan_por_estado(): void
    {
        $primera = $this->ok($this->pedir(), 201);
        $this->ok($this->pedir(['matricula' => '24303100']), 201);
        $this->ok($this->resolver($primera['id'], 'rechazada'));

        $pendientes = $this->ok($this->con('admin')->get('solicitudes-de-alta?estado=pendiente'));

        $this->assertSame(['24303100'], array_column($pendientes, 'matricula'));
        $this->assertCount(2, $this->ok($this->con('admin')->get('solicitudes-de-alta')));
    }
}
