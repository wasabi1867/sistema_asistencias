<?php

namespace Tests\Api;

/** Quién está dentro, el historial, el uso fuera de clase y las correcciones. */
final class AsistenciasTest extends PruebaDeApi
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

    public function test_dice_quien_esta_dentro_del_laboratorio(): void
    {
        $this->registrarEntrada($this->clase, $this->crearAlumno('23303001'), $this->equipo('PC-1'));
        $salio = $this->registrarEntrada($this->clase, $this->crearAlumno('23303002'));
        $this->pdo()->exec("UPDATE asistencias SET hora_salida = NOW() WHERE id = $salio");

        $dentro = $this->ok($this->con('docente')->get('asistencias/activas?laboratorio=programacion'));

        $this->assertSame([['23303001', 'PC-1', $this->clase]], array_map(fn ($a) => [$a['matricula'], $a['equipo'], $a['claseId']], $dentro));
    }

    public function test_el_historial_se_filtra_y_se_pagina(): void
    {
        $ana = $this->crearAlumno('23303001', carrera: 'ISIC');
        $beto = $this->crearAlumno('23303002', carrera: 'IIND');
        $this->registrarEntrada($this->clase, $ana);
        $this->registrarEntrada($this->clase, $beto);
        $this->pdo()->exec('UPDATE asistencias SET hora_salida = NOW()');
        $antigua = $this->registrarEntrada($this->clase, $ana);
        $this->pdo()->exec("UPDATE asistencias SET hora_entrada = '2026-01-15 09:00:00', hora_salida = '2026-01-15 11:00:00' WHERE id = $antigua");
        $docente = $this->con('docente');

        $todo = $this->ok($docente->get('asistencias?laboratorio=programacion'));
        $deIsic = $this->ok($docente->get('asistencias?carrera=ISIC'));
        $deEnero = $this->ok($docente->get('asistencias?desde=2026-01-01&hasta=2026-01-31'));
        $deAna = $this->ok($docente->get('asistencias?alumno=23303001&porPagina=1'));

        $this->assertSame(3, $todo['total']);
        $this->assertSame(['23303001', '23303001'], array_column($deIsic['asistencias'], 'matricula'));
        $this->assertSame([$antigua], array_column($deEnero['asistencias'], 'id'));
        $this->assertSame([2, 1], [$deAna['total'], count($deAna['asistencias'])]);
        $this->assertSame(['clave' => 'ISIC', 'nombre' => 'Ingeniería en Sistemas Computacionales'], $deIsic['asistencias'][0]['carrera']);
    }

    public function test_el_laboratorista_registra_el_uso_fuera_de_clase(): void
    {
        $this->crearAlumno('23303001');
        $laboratorista = $this->con('laboratorista');

        $alumno = $this->ok($laboratorista->post('asistencias', ['laboratorio' => 'redes', 'matricula' => '23303001', 'equipoPropio' => true]), 201);
        $visita = $this->ok($laboratorista->post('asistencias', ['laboratorio' => 'redes', 'nombre' => 'Visita', 'apellido' => 'Externa', 'equipoPropio' => true]), 201);

        $this->assertSame([null, 'redes'], [$alumno['claseId'], $alumno['laboratorio']]);
        $this->assertSame('invitado', $visita['tipo']);
        $this->assertSame($this->laboratorista, (int)$this->valor('SELECT registrada_por FROM asistencias WHERE id = ?', [$alumno['id']]));
        $this->assertSame(403, $this->con('docente')->post('asistencias', ['laboratorio' => 'redes', 'nombre' => 'X', 'apellido' => 'Y', 'equipoPropio' => true])->estado);
        $this->assertSame(2, $this->ok($laboratorista->get('asistencias?fueraDeClase=1'))['total']);
    }

    public function test_quien_lleva_la_clase_registra_la_salida_de_alguien(): void
    {
        $asistencia = $this->registrarEntrada($this->clase, $this->crearAlumno('23303001'), $this->equipo('PC-1'));
        $docente = $this->con('docente');

        $salida = $this->ok($docente->post("asistencias/$asistencia/salida"));
        $otraVez = $docente->post("asistencias/$asistencia/salida");

        $this->assertNotNull($salida['horaSalida']);
        $this->assertSame([409, 'ya_salio'], [$otraVez->estado, $otraVez->json()['codigo']]);
        $this->assertSame('libre', $this->ok($this->anonimo()->get('laboratorios/programacion/equipos'))[0]['estado']);
    }

    public function test_quitar_una_entrada_equivocada_queda_en_la_bitacora(): void
    {
        $asistencia = $this->registrarEntrada($this->clase, $this->crearAlumno('23303001'));
        $this->crearDocente('D200');

        $this->assertSame(403, $this->con('docente', 'D200')->delete("asistencias/$asistencia")->estado);
        $this->ok($this->con('docente')->delete("asistencias/$asistencia"), 204);

        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM asistencias'));
        $this->assertSame("23303001, clase $this->clase", $this->valor("SELECT detalles FROM bitacora WHERE accion = 'quitar_asistencia'"));
    }
}
