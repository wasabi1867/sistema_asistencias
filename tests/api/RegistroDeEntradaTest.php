<?php

namespace Tests\Api;

/** El alumno registra su entrada sin sesión, con su número de control. */
final class RegistroDeEntradaTest extends PruebaDeApi
{
    private int $curso;
    private int $clase;
    private int $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->curso = $this->crearCurso();
        $this->clase = $this->abrirClase($this->curso, codigo: '4821');
        $this->ana = $this->crearAlumno('23303001', 'Ana', 'Alumna');
        $this->inscribir($this->curso, $this->ana);
    }

    private function entrar(array $datos, ?Cliente $cliente = null): Respuesta
    {
        return ($cliente ?? $this->anonimo())->post('registro/entrada', $datos + ['laboratorio' => 'programacion']);
    }

    private function estadoDe(string $etiqueta): string
    {
        return array_column($this->ok($this->anonimo()->get('laboratorios/programacion/equipos')), 'estado', 'etiqueta')[$etiqueta];
    }

    public function test_el_alumno_inscrito_entra_con_la_computadora_que_elige(): void
    {
        $respuesta = $this->ok($this->entrar(['matricula' => '23303001', 'equipo' => 'PC-4']), 201);

        $this->assertSame(['Ana', 'PC-4', $this->clase, 'Programación Web'], [$respuesta['nombre'], $respuesta['equipo'], $respuesta['clase']['id'], $respuesta['clase']['materia']]);
        $this->assertSame('ocupado', $this->estadoDe('PC-4'));
    }

    public function test_varios_pueden_traer_su_propio_equipo(): void
    {
        $beto = $this->crearAlumno('23303002');
        $this->inscribir($this->curso, $beto);

        $this->ok($this->entrar(['matricula' => '23303001', 'equipoPropio' => true]), 201);
        $segundo = $this->ok($this->entrar(['matricula' => '23303002', 'equipoPropio' => true]), 201);

        $this->assertNull($segundo['equipo']);
    }

    public function test_hay_que_decir_la_computadora_o_que_trae_la_suya_pero_no_las_dos(): void
    {
        $this->assertSame(400, $this->entrar(['matricula' => '23303001'])->estado);
        $this->assertSame(400, $this->entrar(['matricula' => '23303001', 'equipo' => 'PC-1', 'equipoPropio' => true])->estado);
    }

    public function test_cada_rechazo_trae_un_codigo_para_la_pantalla(): void
    {
        $this->crearAlumno('23303002');
        $this->pdo()->exec("UPDATE equipos SET fuera_de_servicio = TRUE WHERE etiqueta = 'PC-9'");

        $casos = [
            'matricula_no_encontrada' => [404, ['matricula' => '99999999', 'equipoPropio' => true]],
            'no_inscrito' => [409, ['matricula' => '23303002', 'equipoPropio' => true]],
            'equipo_fuera_de_servicio' => [409, ['matricula' => '23303001', 'equipo' => 'PC-9']],
        ];
        foreach ($casos as $codigo => [$estado, $datos]) {
            $respuesta = $this->entrar($datos);
            $this->assertSame([$estado, $codigo], [$respuesta->estado, $respuesta->json()['codigo'] ?? null], (string)$respuesta);
        }
    }

    public function test_al_no_inscrito_se_le_dice_de_que_clase_para_que_pida_ingreso(): void
    {
        $this->crearAlumno('23303002');

        $respuesta = $this->entrar(['matricula' => '23303002', 'equipoPropio' => true]);

        $this->assertSame(['error' => 'No estás inscrito en Programación Web 5A', 'codigo' => 'no_inscrito', 'claseId' => $this->clase, 'materia' => 'Programación Web'], $respuesta->json());
    }

    public function test_no_se_registra_dos_veces_ni_en_dos_laboratorios(): void
    {
        $this->ok($this->entrar(['matricula' => '23303001', 'equipoPropio' => true]), 201);
        $otraVez = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true]);

        $otroCurso = $this->crearCurso($this->crearMateria('Redes'), docente: $this->crearDocente('D200'));
        $this->inscribir($otroCurso, $this->ana);
        $this->abrirClase($otroCurso, 'redes');
        $enRedes = $this->anonimo()->post('registro/entrada', ['laboratorio' => 'redes', 'matricula' => '23303001', 'equipoPropio' => true]);

        $this->assertSame([409, 'ya_registrado', 'Ya registraste tu entrada en esta clase'], [$otraVez->estado, $otraVez->json()['codigo'], $otraVez->json()['error']]);
        $this->assertSame([409, 'Ya estás registrado en el Taller de Programación. Espera a que termine para registrarte en otra'], [$enRedes->estado, $enRedes->json()['error']]);
    }

    public function test_una_computadora_ocupada_no_se_puede_elegir(): void
    {
        $beto = $this->crearAlumno('23303002');
        $this->inscribir($this->curso, $beto);
        $this->ok($this->entrar(['matricula' => '23303001', 'equipo' => 'PC-1']), 201);

        $respuesta = $this->entrar(['matricula' => '23303002', 'equipo' => 'PC-1']);

        $this->assertSame([409, 'equipo_ocupado'], [$respuesta->estado, $respuesta->json()['codigo']]);
    }

    public function test_si_dos_eligen_la_misma_computadora_al_mismo_tiempo_solo_uno_la_obtiene(): void
    {
        $alumnos = [];
        for ($i = 2; $i <= 6; $i++) {
            $alumnos[] = $matricula = "2330300$i";
            $this->inscribir($this->curso, $this->crearAlumno($matricula));
        }

        $estados = $this->enParalelo(array_map(fn ($m) => ['matricula' => $m, 'equipo' => 'PC-1', 'laboratorio' => 'programacion'], $alumnos));

        sort($estados);
        $this->assertSame([201, 409, 409, 409, 409], $estados);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM asistencias WHERE equipo_id = ?', [$this->equipo('PC-1')]));
    }

    public function test_si_el_mismo_alumno_manda_dos_registros_a_la_vez_queda_uno(): void
    {
        $estados = $this->enParalelo([
            ['matricula' => '23303001', 'equipo' => 'PC-1', 'laboratorio' => 'programacion'],
            ['matricula' => '23303001', 'equipo' => 'PC-2', 'laboratorio' => 'programacion'],
        ]);

        sort($estados);
        $this->assertSame([201, 409], $estados);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM asistencias WHERE alumno_id = ?', [$this->ana]));
    }

    public function test_sin_clase_abierta_no_se_puede_entrar(): void
    {
        $this->ok($this->con('docente')->post("clases/$this->clase/cerrar"));

        $respuesta = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true]);
        $aEsaClase = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true, 'claseId' => $this->clase]);

        $this->assertSame([409, 'sin_clase'], [$respuesta->estado, $respuesta->json()['codigo']]);
        $this->assertSame([409, 'clase_cerrada'], [$aEsaClase->estado, $aEsaClase->json()['codigo']]);
    }

    public function test_quien_no_esta_inscrito_entra_a_la_clase_de_invitado_si_hay_una(): void
    {
        $invitado = $this->abrirClaseDeInvitado();
        $this->crearAlumno('23303002');

        $deOtroGrupo = $this->ok($this->entrar(['matricula' => '23303002', 'equipoPropio' => true]), 201);
        $inscrita = $this->ok($this->entrar(['matricula' => '23303001', 'equipoPropio' => true]), 201);

        $this->assertSame($invitado, $deOtroGrupo['clase']['id']);
        $this->assertSame($this->clase, $inscrita['clase']['id']);
    }

    public function test_alguien_de_fuera_entra_con_su_nombre_solo_a_una_clase_de_invitado(): void
    {
        $aLaRegular = $this->entrar(['tipo' => 'invitado', 'nombre' => 'Visita', 'apellido' => 'Externa', 'equipoPropio' => true]);
        $invitado = $this->abrirClaseDeInvitado();
        $aLaDeInvitado = $this->ok($this->entrar(['tipo' => 'invitado', 'nombre' => 'Visita', 'apellido' => 'Externa', 'equipo' => 'PC-2']), 201);

        $this->assertSame([409, 'solo_alumnos'], [$aLaRegular->estado, $aLaRegular->json()['codigo']]);
        $this->assertSame([$invitado, 'Visita'], [$aLaDeInvitado['clase']['id'], $aLaDeInvitado['nombre']]);
    }

    public function test_el_docente_registra_su_entrada_con_su_matricula(): void
    {
        $respuesta = $this->ok($this->entrar(['tipo' => 'docente', 'matricula' => 'D100', 'equipo' => 'PC-25']), 201);

        $this->assertSame(['Diego', $this->clase], [$respuesta['nombre'], $respuesta['clase']['id']]);
        $this->assertSame('docente', $this->valor('SELECT tipo_persona FROM asistencias'));
    }

    public function test_una_matricula_desactivada_no_entra(): void
    {
        $this->pdo()->exec("UPDATE alumnos SET activo = FALSE WHERE id = $this->ana");

        $this->assertSame(403, $this->entrar(['matricula' => '23303001', 'equipoPropio' => true])->estado);
    }

    public function test_una_computadora_de_otro_laboratorio_no_existe_aqui(): void
    {
        $this->insertar('equipos', ['laboratorio_id' => $this->laboratorio('redes'), 'etiqueta' => 'R-1']);

        $respuesta = $this->entrar(['matricula' => '23303001', 'equipo' => 'R-1']);

        $this->assertSame([400, 'equipo'], [$respuesta->estado, $respuesta->json()['campo']]);
    }

    public function test_si_se_exige_el_codigo_de_clase_hay_que_escribirlo(): void
    {
        $servidor = Entorno::url(['ASISTENCIAS_EXIGIR_CODIGO_DE_CLASE' => '1']);

        $abiertas = (new Cliente($servidor))->get('clases/abiertas?laboratorio=programacion')->json();
        $sinCodigo = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true], new Cliente($servidor));
        $equivocado = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true, 'codigo' => '0000'], new Cliente($servidor));
        $correcto = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true, 'codigo' => '4821'], new Cliente($servidor));

        $this->assertTrue($abiertas[0]['pideCodigo']);
        $this->assertSame([403, 'codigo_incorrecto'], [$sinCodigo->estado, $sinCodigo->json()['codigo']]);
        $this->assertSame([403, 'codigo_incorrecto'], [$equivocado->estado, $equivocado->json()['codigo']]);
        $this->assertSame(201, $correcto->estado, (string)$correcto);
    }

    public function test_demasiadas_matriculas_inexistentes_desde_una_red_la_hacen_esperar(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame(404, $this->entrar(['matricula' => "1000000$i", 'equipoPropio' => true])->estado);
        }

        $respuesta = $this->entrar(['matricula' => '23303001', 'equipoPropio' => true]);

        $this->assertSame(429, $respuesta->estado, (string)$respuesta);
    }

    /**
     * Manda varios registros de entrada al mismo tiempo y devuelve sus estados.
     * @param list<array<string, mixed>> $cuerpos
     * @return list<int>
     */
    private function enParalelo(array $cuerpos): array
    {
        $multi = curl_multi_init();
        $manejadores = [];
        foreach ($cuerpos as $cuerpo) {
            $curl = curl_init(Entorno::url() . '/api/registro/entrada');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($cuerpo),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_multi_add_handle($multi, $curl);
            $manejadores[] = $curl;
        }
        do {
            curl_multi_exec($multi, $corriendo);
            curl_multi_select($multi);
        } while ($corriendo > 0);
        $estados = array_map(static fn ($curl) => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $manejadores);
        foreach ($manejadores as $curl) {
            curl_multi_remove_handle($multi, $curl);
        }
        curl_multi_close($multi);
        return $estados;
    }
}
