<?php

namespace Tests\Api;

/**
 * scripts/importar-de-firebase.php con un respaldo de ejemplo (tests/api/firebase/),
 * que tiene el formato de la API REST de Firestore.
 */
final class ImportacionDeFirebaseTest extends PruebaDeApi
{
    private const RESPALDO = __DIR__ . '/firebase';

    private function importar(array $argumentos = []): string
    {
        [$codigo, $salida] = Entorno::correrScript('importar-de-firebase.php', ['--desde=' . self::RESPALDO, ...$argumentos]);
        $this->assertSame(0, $codigo, $salida);
        return $salida;
    }

    private function totales(): array
    {
        $totales = [];
        foreach (['administradores', 'docentes', 'laboratoristas', 'materias', 'practicas', 'alumnos', 'equipos', 'clases',
                  'asistencias_historico', 'solicitudes_ingreso', 'notificaciones_admin', 'logs', 'eventos_laboratorio'] as $tabla) {
            $totales[$tabla] = (int)$this->valor("SELECT COUNT(*) FROM $tabla");
        }
        return $totales;
    }

    public function test_importa_cada_coleccion(): void
    {
        $this->importar();

        $this->assertSame([
            'administradores' => 1, 'docentes' => 1, 'laboratoristas' => 1, 'materias' => 1, 'practicas' => 1, 'alumnos' => 2,
            'equipos' => 3, 'clases' => 2, 'asistencias_historico' => 2, 'solicitudes_ingreso' => 1, 'notificaciones_admin' => 1,
            'logs' => 1, 'eventos_laboratorio' => 1,
        ], $this->totales());

        $this->assertSame("O'Neil", $this->valor("SELECT apellido FROM docentes WHERE matricula = 'D100'"));
        $this->assertSame(['kM3xQe9TzP0aB1cD2eF3'], json_decode($this->valor("SELECT materias FROM alumnos WHERE matricula = '23303001'"), true));
        $this->assertSame('Usar <form> y \\ diagonales', $this->valor('SELECT descripcion FROM practicas'));
        $equipo2 = $this->pdo()->query("SELECT fuera_de_servicio, en_uso, notas, estado_reparacion FROM equipos WHERE id = '2'")->fetch();
        $this->assertSame(['fuera_de_servicio' => 1, 'en_uso' => 0, 'notas' => 'No enciende', 'estado_reparacion' => 'en_proceso'], $equipo2);
        $this->assertSame('rechazada', $this->valor('SELECT estado FROM solicitudes_ingreso'));
    }

    public function test_las_horas_del_navegador_y_las_de_firebase_quedan_en_hora_de_sonora(): void
    {
        $this->importar();

        $clase = $this->pdo()->query("SELECT tipo, abierta_en, cerrada_en, docente, total_asistencias FROM clases WHERE materia = 'Programación Web'")->fetch();
        $this->assertSame(['tipo' => 'regular', 'abierta_en' => '2026-10-20 21:46:22', 'cerrada_en' => '2026-10-20 23:05:00', 'docente' => "Diego O'Neil", 'total_asistencias' => 2], $clase);
        $this->assertSame('invitado', $this->valor("SELECT tipo FROM clases WHERE materia = 'Cálculo'"));
        // 04:46 UTC del 21 son las 21:46 del 20 en Puerto Peñasco (UTC-7).
        $this->assertSame('2026-10-20 21:46:22', $this->valor('SELECT fecha FROM logs'));
    }

    public function test_el_historial_importado_se_ve_en_los_reportes(): void
    {
        $this->importar();

        // L100 viene en el respaldo: ahora entra con su contraseña de Firebase.
        $laboratorista = new Cliente();
        $laboratorista->post('auth.php', ['email' => 'L100', 'password' => 'clave-de-firebase', 'role' => 'laboratorista']);
        $clases = $laboratorista->get('clases.php?con_alumnos=1&estado=cerradas&laboratorio=programacion')->json()['data'];

        $web = array_values(array_filter($clases, fn ($c) => $c['materia'] === 'Programación Web'))[0];
        $this->assertSame(['Álvarez', "D'Angelo"], array_column($web['alumnos'], 'apellido'));
    }

    public function test_las_cuentas_entran_con_su_contrasena_de_firebase(): void
    {
        $this->importar();

        $docente = (new Cliente())->post('auth.php', ['email' => 'D100', 'password' => 'clave-de-firebase', 'role' => 'docente']);
        $admin = (new Cliente())->post('auth.php', ['email' => 'ADM01', 'password' => 'clave-de-firebase', 'role' => 'admin']);

        $this->assertSame(200, $docente->estado, (string)$docente);
        $this->assertSame(200, $admin->estado, (string)$admin);
    }

    public function test_repetirla_no_duplica_nada(): void
    {
        $this->importar();
        $antes = $this->totales();

        $this->importar();

        $this->assertSame($antes, $this->totales());
    }

    public function test_el_archivo_sql_deja_lo_mismo_que_importar_directo(): void
    {
        $this->importar();
        $directo = $this->totales();
        Entorno::vaciarTablas();

        $archivo = sys_get_temp_dir() . '/importacion-' . bin2hex(random_bytes(4)) . '.sql';
        $this->importar(["--sql=$archivo"]);
        $this->assertSame(0, array_sum($this->totales()), 'Con --sql no debe tocar la base');
        $this->pdo()->exec(file_get_contents($archivo));
        unlink($archivo);

        $this->assertSame($directo, $this->totales());
        $this->assertSame("D'Angelo", $this->valor("SELECT apellido FROM alumnos WHERE matricula = '23303002'"));
    }

    public function test_los_equipos_de_firebase_reemplazan_a_los_del_laboratorio(): void
    {
        $this->crearEquipos('programacion', 5);

        $this->importar();

        $ids = $this->pdo()->query("SELECT id FROM equipos WHERE laboratorio = 'programacion' ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['1', '2', 'personal'], $ids);
    }
}
