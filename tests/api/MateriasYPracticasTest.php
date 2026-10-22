<?php

namespace Tests\Api;

final class MateriasYPracticasTest extends PruebaDeApi
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
    }

    public function test_el_administrador_lleva_el_catalogo_de_materias(): void
    {
        $admin = $this->con('admin');

        $materia = $this->ok($admin->post('materias', ['clave' => 'aed-1286', 'nombre' => 'Programación Web', 'carrera' => 'ISIC', 'semestre' => 5]), 201);
        $repetida = $admin->post('materias', ['nombre' => 'Programación Web', 'carrera' => 'ISIC']);
        $otraCarrera = $admin->post('materias', ['nombre' => 'Programación Web', 'carrera' => 'IIND']);

        $this->assertSame('AED-1286', $materia['clave']);
        $this->assertSame([409, 'Esa carrera ya tiene una materia con ese nombre'], [$repetida->estado, $repetida->json()['error']]);
        $this->assertSame(201, $otraCarrera->estado);
        $this->assertSame(['Programación Web'], array_column($this->ok($this->con('docente')->get('materias?carrera=IIND')), 'nombre'));
        $this->assertSame(403, $this->con('laboratorista')->post('materias', ['nombre' => 'X'])->estado);
    }

    public function test_una_materia_con_cursos_no_se_borra(): void
    {
        $materia = $this->crearMateria();
        $this->crearCurso($materia);

        $this->assertSame(409, $this->con('admin')->delete("materias/$materia")->estado);
    }

    public function test_el_docente_ve_las_practicas_comunes_y_las_suyas(): void
    {
        $materia = $this->crearMateria();
        $otro = $this->crearDocente('D200');
        $this->crearPractica($materia, 1, 'Común');
        $this->crearPractica($materia, 1, 'De Diego', $this->docente);
        $this->crearPractica($materia, 1, 'Del otro', $otro);

        $delDocente = $this->ok($this->con('docente')->get("materias/$materia/practicas"));
        $todas = $this->ok($this->con('laboratorista')->get("materias/$materia/practicas"));

        $this->assertSame(['Común', 'De Diego'], array_column($delDocente, 'titulo'));
        $this->assertSame([true, false], array_column($delDocente, 'comun'));
        $this->assertCount(3, $todas);
    }

    public function test_el_docente_captura_practicas_solo_de_las_materias_que_imparte(): void
    {
        $suya = $this->crearMateria('Programación Web');
        $ajena = $this->crearMateria('Redes');
        $this->crearCurso($suya);
        $docente = $this->con('docente');

        $primera = $this->ok($docente->post("materias/$suya/practicas", ['titulo' => 'HTML', 'duracionMinutos' => 120, 'fecha' => '2026-10-21']), 201);
        $segunda = $this->ok($docente->post("materias/$suya/practicas", ['titulo' => 'CSS']), 201);
        $enAjena = $docente->post("materias/$ajena/practicas", ['titulo' => 'Cableado']);

        $this->assertSame([1, 2], [$primera['numero'], $segunda['numero']]);
        $this->assertSame(['id' => $this->docente, 'nombre' => 'Diego Docente'], $primera['docente']);
        $this->assertSame(120, $primera['duracionMinutos']);
        $this->assertSame(403, $enAjena->estado);
    }

    public function test_el_administrador_captura_las_practicas_comunes(): void
    {
        $materia = $this->crearMateria();

        $comun = $this->ok($this->con('admin')->post("materias/$materia/practicas", ['titulo' => 'Común']), 201);
        $delLaboratorista = $this->con('laboratorista')->post("materias/$materia/practicas", ['titulo' => 'X']);

        $this->assertTrue($comun['comun']);
        $this->assertSame(403, $delLaboratorista->estado);
    }

    public function test_cada_quien_cambia_solo_sus_practicas(): void
    {
        $materia = $this->crearMateria();
        $this->crearCurso($materia);
        $comun = $this->crearPractica($materia, 1, 'Común');
        $suya = $this->crearPractica($materia, 1, 'Suya', $this->docente);
        $docente = $this->con('docente');

        $this->assertSame(403, $docente->put("practicas/$comun", ['titulo' => 'Cambiada'])->estado);
        $this->assertSame('Cambiada', $this->ok($docente->put("practicas/$suya", ['titulo' => 'Cambiada']))['titulo']);
        $this->assertSame('Común editada', $this->ok($this->con('admin')->put("practicas/$comun", ['titulo' => 'Común editada']))['titulo']);
    }

    public function test_una_practica_trabajada_en_clase_se_desactiva_en_lugar_de_borrarse(): void
    {
        $materia = $this->crearMateria();
        $curso = $this->crearCurso($materia);
        $usada = $this->crearPractica($materia, 1, 'Usada', $this->docente);
        $nueva = $this->crearPractica($materia, 2, 'Nueva', $this->docente);
        $this->abrirClase($curso, practica: $usada);
        $docente = $this->con('docente');

        $this->ok($docente->delete("practicas/$usada"), 204);
        $this->ok($docente->delete("practicas/$nueva"), 204);

        $this->assertSame(0, (int)$this->valor('SELECT activa FROM practicas WHERE id = ?', [$usada]));
        $this->assertFalse($this->valor('SELECT id FROM practicas WHERE id = ?', [$nueva]));
        // El número de la desactivada queda libre.
        $this->assertSame(1, $this->ok($docente->post("materias/$materia/practicas", ['titulo' => 'Otra']), 201)['numero']);
    }

    public function test_dos_practicas_activas_de_la_misma_lista_no_comparten_numero(): void
    {
        $materia = $this->crearMateria();
        $this->crearCurso($materia);
        $this->crearPractica($materia, 1, 'Primera', $this->docente);

        $respuesta = $this->con('docente')->post("materias/$materia/practicas", ['numero' => 1, 'titulo' => 'Otra']);

        $this->assertSame([409, 'Ya hay una práctica con ese número en la lista'], [$respuesta->estado, $respuesta->json()['error']]);
    }
}
