<?php

namespace Tests\Api;

/**
 * scripts/importar-de-firebase.php con un respaldo de ejemplo (tests/api/firebase/),
 * que tiene el formato de la API REST de Firestore.
 */
final class ImportacionDeFirebaseTest extends PruebaDeApi
{
    private const RESPALDO = __DIR__ . '/firebase';
    private const TABLAS = ['usuarios', 'alumnos', 'materias', 'periodos', 'cursos', 'inscripciones', 'practicas', 'clases', 'asistencias',
        'solicitudes_ingreso', 'reportes_de_equipo', 'eventos', 'bitacora'];

    private function importar(array $argumentos = [], string $respaldo = self::RESPALDO): string
    {
        [$codigo, $salida] = Entorno::correrScript('importar-de-firebase.php', ['--desde=' . $respaldo, ...$argumentos]);
        $this->assertSame(0, $codigo, $salida);
        return $salida;
    }

    private function totales(): array
    {
        $totales = [];
        foreach (self::TABLAS as $tabla) {
            $totales[$tabla] = (int)$this->valor("SELECT COUNT(*) FROM $tabla");
        }
        $totales['equipos activos'] = (int)$this->valor('SELECT COUNT(*) FROM equipos WHERE activo');
        return $totales;
    }

    public function test_importa_cada_coleccion_con_la_forma_nueva(): void
    {
        $this->importar();

        $this->assertSame([
            // Las tres cuentas y la de la importación, a la que se atribuye la clase de invitado.
            'usuarios' => 4, 'alumnos' => 2, 'materias' => 1, 'periodos' => 1, 'cursos' => 1, 'inscripciones' => 1, 'practicas' => 1,
            'clases' => 2, 'asistencias' => 2, 'solicitudes_ingreso' => 1, 'reportes_de_equipo' => 1, 'eventos' => 1, 'bitacora' => 1,
            'equipos activos' => 2,
        ], $this->totales());

        // La materia de Firebase se separó en materia y curso, del periodo de su ciclo.
        $curso = $this->fila("SELECT m.nombre, p.nombre AS periodo, cu.grupo, u.matricula AS docente FROM cursos cu
            JOIN materias m ON m.id = cu.materia_id JOIN periodos p ON p.id = cu.periodo_id JOIN usuarios u ON u.id = cu.docente_id");
        $this->assertSame(['nombre' => 'Programación Web', 'periodo' => 'AGO-DIC 2026', 'grupo' => '5A', 'docente' => 'D100'], $curso);
        $this->assertSame('23303001', $this->valor('SELECT a.matricula FROM inscripciones i JOIN alumnos a ON a.id = i.alumno_id'));

        $practica = $this->fila('SELECT p.numero, p.titulo, p.descripcion, p.duracion_minutos, p.fecha, u.matricula AS docente FROM practicas p JOIN usuarios u ON u.id = p.docente_id');
        $this->assertSame(['numero' => 1, 'titulo' => 'Formularios', 'descripcion' => 'Usar <form> y \\ diagonales', 'duracion_minutos' => 120, 'fecha' => '2026-10-20', 'docente' => 'D100'], $practica);

        $this->assertSame("O'Neil", $this->valor("SELECT apellido FROM usuarios WHERE matricula = 'D100'"));
        $this->assertSame(['matutino', 5], array_values($this->fila("SELECT turno, semestre FROM alumnos WHERE matricula = '23303001'")));
    }

    public function test_las_horas_del_navegador_y_las_de_firebase_quedan_en_hora_de_sonora(): void
    {
        $this->importar();

        $regular = $this->fila("SELECT abierta_en, cerrada_en, cierre FROM clases WHERE tipo = 'regular'");
        $this->assertSame(['abierta_en' => '2026-10-20 21:46:22', 'cerrada_en' => '2026-10-20 23:05:00', 'cierre' => 'normal'], $regular);
        // 04:46 UTC del 21 son las 21:46 del 20 en Puerto Peñasco (UTC-7).
        $this->assertSame('2026-10-20 21:46:22', $this->valor('SELECT fecha FROM bitacora'));
    }

    public function test_la_clase_de_invitado_se_atribuye_a_la_cuenta_de_la_importacion(): void
    {
        $this->importar();

        $invitado = $this->fila("SELECT cl.materia_invitado, cl.docente_invitado, cl.departamento, u.nombre, u.activo FROM clases cl
            JOIN usuarios u ON u.id = cl.abierta_por WHERE cl.tipo = 'invitado'");
        $this->assertSame(['materia_invitado' => 'Cálculo', 'docente_invitado' => 'Iris Invitada', 'departamento' => 'Ciencias Básicas',
            'nombre' => 'Importación de Firebase', 'activo' => 0], $invitado);
    }

    public function test_el_historial_importado_se_ve_en_la_api(): void
    {
        $this->importar();
        $laboratorista = new Cliente();
        $this->ok($laboratorista->post('sesion', ['usuario' => 'L100', 'contrasena' => 'clave-de-firebase', 'rol' => 'laboratorista']));

        $clases = $this->ok($laboratorista->get('clases?estado=cerradas&tipo=regular&conAsistentes=1'))['clases'];
        $curso = $this->ok($laboratorista->get("cursos/{$clases[0]['cursoId']}/asistencia"));

        $this->assertSame('Formularios', $clases[0]['practica']['titulo']);
        $this->assertSame([['Álvarez', '1'], ["D'Angelo", null]], array_map(fn ($p) => [$p['apellido'], $p['equipo']], $clases[0]['personas']));
        $this->assertSame([['23303001', 1, 1]], array_map(fn ($a) => [$a['matricula'], $a['clases'], $a['asistencias']], $curso['alumnos']));
    }

    public function test_las_cuentas_entran_con_su_contrasena_de_firebase(): void
    {
        $this->importar();

        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D100', 'contrasena' => 'clave-de-firebase', 'rol' => 'docente']));
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'ada@itspp.test', 'contrasena' => 'clave-de-firebase', 'rol' => 'admin']));
        $this->assertSame(401, $this->anonimo()->post('sesion', ['usuario' => 'FIREBASE', 'contrasena' => 'x', 'rol' => 'admin'])->estado);
    }

    public function test_los_equipos_de_firebase_reemplazan_a_los_iniciales_y_su_falla_es_un_reporte(): void
    {
        $this->importar();

        $equipos = $this->ok($this->anonimo()->get('laboratorios/programacion/equipos'));
        $this->assertSame(['1' => 'libre', '2' => 'fuera_de_servicio'], array_column($equipos, 'estado', 'etiqueta'));
        $reporte = $this->fila('SELECT descripcion, estado, comentario FROM reportes_de_equipo');
        $this->assertSame(['descripcion' => 'No enciende', 'estado' => 'en_proceso', 'comentario' => 'Se pidió fuente'], $reporte);
    }

    public function test_la_solicitud_queda_ligada_a_la_clase_que_estaba_abierta(): void
    {
        $this->importar();

        $solicitud = $this->fila("SELECT s.estado, s.resuelta_en, cl.tipo FROM solicitudes_ingreso s JOIN clases cl ON cl.id = s.clase_id");
        $this->assertSame(['estado' => 'rechazada', 'resuelta_en' => '2026-10-20 21:50:00', 'tipo' => 'regular'], $solicitud);
    }

    public function test_reporta_lo_que_hay_que_revisar(): void
    {
        $salida = $this->importar();

        $this->assertStringContainsString('La carrera "Sistemas" no está en el catálogo', $salida);
        $this->assertNull($this->valor("SELECT carrera_id FROM alumnos WHERE matricula = '23303001'"));
    }

    public function test_una_carrera_escrita_distinto_se_reconoce(): void
    {
        $this->insertar('carreras', ['clave' => 'SIS', 'nombre' => 'Sistemas Computacionales']);
        $respaldo = $this->respaldoConAlumno('Sistemas Computacionales (plan 2010)');

        $this->importar(respaldo: $respaldo);

        $this->assertSame('SIS', $this->valor("SELECT c.clave FROM alumnos a JOIN carreras c ON c.id = a.carrera_id WHERE a.matricula = '23303099'"));
    }

    public function test_repetirla_no_duplica_nada(): void
    {
        $this->importar();
        $antes = $this->totales();

        $this->importar();

        $this->assertSame($antes, $this->totales());
    }

    public function test_simular_no_guarda_nada(): void
    {
        $antes = $this->totales();

        $salida = $this->importar(['--simular']);

        $this->assertStringContainsString('Simulación: no se guardó nada', $salida);
        $this->assertStringContainsString('clases', $salida);
        $this->assertSame($antes, $this->totales());
    }

    public function test_una_contrasena_que_estaba_en_texto_se_cifra(): void
    {
        $respaldo = sys_get_temp_dir() . '/respaldo-' . bin2hex(random_bytes(4));
        mkdir($respaldo);
        file_put_contents("$respaldo/Docentes.json", json_encode([[
            'name' => 'projects/x/databases/(default)/documents/Docentes/D300',
            'fields' => ['Nombre' => ['stringValue' => 'Rosa'], 'Contraseña' => ['stringValue' => 'contrasena-en-texto']],
        ]]));

        $this->importar(respaldo: $respaldo);

        $this->assertStringStartsWith('$2y$', $this->valor("SELECT password_hash FROM usuarios WHERE matricula = 'D300'"));
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'D300', 'contrasena' => 'contrasena-en-texto', 'rol' => 'docente']));
        unlink("$respaldo/Docentes.json");
        rmdir($respaldo);
    }

    /** Un respaldo con un solo alumno, con la carrera escrita como se indique. */
    private function respaldoConAlumno(string $carrera): string
    {
        $respaldo = sys_get_temp_dir() . '/respaldo-' . bin2hex(random_bytes(4));
        mkdir($respaldo);
        file_put_contents("$respaldo/Alumnos.json", json_encode([[
            'name' => 'projects/x/databases/(default)/documents/Alumnos/23303099',
            'fields' => ['Nombre' => ['stringValue' => 'Ceci'], 'Apellido' => ['stringValue' => 'Luna'], 'Carrera' => ['stringValue' => $carrera]],
        ]]));
        register_shutdown_function(static function () use ($respaldo) {
            @unlink("$respaldo/Alumnos.json");
            @rmdir($respaldo);
        });
        return $respaldo;
    }
}
