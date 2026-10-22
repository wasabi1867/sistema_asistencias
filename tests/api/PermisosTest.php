<?php

namespace Tests\Api;

use Asistencias\Rutas;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Fija quién puede usar cada ruta. Si se cambia un permiso, se cambia aquí también; y
 * una ruta nueva que no esté en esta tabla hace fallar la prueba, así ningún cambio de
 * permisos pasa sin que alguien lo vea.
 *
 * "Puede" significa que la API no responde 401 ni 403 (puede responder 404 o 409 si los
 * datos de la prueba no dan para más). "No puede": 401 sin sesión y 403 con ella.
 */
final class PermisosTest extends PruebaDeApi
{
    private const TODOS = ['anonimo', 'docente', 'laboratorista', 'admin'];
    private const SESION = ['docente', 'laboratorista', 'admin'];
    private const PERSONAL = ['laboratorista', 'admin'];
    private const ADMIN = ['admin'];
    private const ADMIN_Y_SU_DOCENTE = ['docente', 'admin'];

    /** @var array<string, int> los id de los datos de la prueba, para las rutas */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $materia = $this->crearMateria();
        $curso = $this->crearCurso($materia);
        $a1 = $this->crearAlumno('23303001');
        $this->inscribir($curso, $a1);
        $a2 = $this->crearAlumno('23303002');
        $clase = $this->abrirClase($curso);
        $this->ids = [
            'laboratorio' => $this->laboratorio('redes'),
            'carrera' => $this->carrera('ISIC'),
            'carreraLibre' => $this->insertar('carreras', ['clave' => 'LIBRE', 'nombre' => 'Sin alumnos']),
            'periodo' => (int)$this->valor('SELECT periodo_id FROM cursos WHERE id = ?', [$curso]),
            'periodoLibre' => $this->insertar('periodos', ['nombre' => 'Libre', 'fecha_inicio' => '2031-01-01', 'fecha_fin' => '2031-06-30']),
            'docente' => $this->docente,
            'otraCuenta' => $this->crearDocente('D900'),
            'materia' => $materia,
            'materiaLibre' => $this->crearMateria('Sin cursos'),
            'practicaPropia' => $this->crearPractica($materia, 1, 'Propia', $this->docente),
            'curso' => $curso,
            'cursoLibre' => $this->crearCurso($materia, '9Z'),
            'clase' => $clase,
            'asistencia' => $this->registrarEntrada($clase, $a1, $this->equipo('PC-1')),
            'pc24' => $this->equipo('PC-24'),
            'pc25' => $this->equipo('PC-25'),
            'reporte' => $this->insertar('reportes_de_equipo', ['equipo_id' => $this->equipo('PC-24'), 'descripcion' => 'Falla']),
            'solicitudDeClase' => $this->insertar('solicitudes_ingreso', ['laboratorio_id' => $this->laboratorio(), 'clase_id' => $clase, 'alumno_id' => $a2]),
            'solicitudFuera' => $this->insertar('solicitudes_ingreso', ['laboratorio_id' => $this->laboratorio('redes'), 'alumno_id' => $a2]),
            'evento' => $this->insertar('eventos', ['laboratorio_id' => $this->laboratorio(), 'nombre' => 'Hackatón']),
            'aviso' => $this->insertar('avisos', ['tipo' => 'sistema', 'titulo' => 'Aviso']),
            'horario' => $this->insertar('horarios', ['curso_id' => $curso, 'laboratorio_id' => $this->laboratorio(), 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']),
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: ?array, 4: list<string>}> forma, método, ruta, cuerpo, quién puede */
    public static function rutas(): array
    {
        return [
            'iniciar sesión' => ['/sesion', 'POST', 'sesion', [], self::TODOS],
            'quién tiene la sesión' => ['/sesion', 'GET', 'sesion', null, self::SESION],
            'cerrar sesión' => ['/sesion', 'DELETE', 'sesion', null, self::TODOS],
            'cambiar la contraseña propia' => ['/sesion/contrasena', 'PUT', 'sesion/contrasena', ['actual' => 'x', 'nueva' => 'y'], self::SESION],

            'ver los laboratorios' => ['/laboratorios', 'GET', 'laboratorios', null, self::TODOS],
            'crear un laboratorio' => ['/laboratorios', 'POST', 'laboratorios', ['clave' => 'nuevo', 'nombre' => 'Nuevo'], self::ADMIN],
            'editar un laboratorio' => ['/laboratorios/{id}', 'PUT', 'laboratorios/{laboratorio}', ['nombre' => 'Redes'], self::ADMIN],
            'ver las carreras' => ['/carreras', 'GET', 'carreras', null, self::SESION],
            'crear una carrera' => ['/carreras', 'POST', 'carreras', ['clave' => 'NUEVA', 'nombre' => 'Nueva'], self::ADMIN],
            'editar una carrera' => ['/carreras/{id}', 'PUT', 'carreras/{carrera}', ['activa' => true], self::ADMIN],
            'borrar una carrera' => ['/carreras/{id}', 'DELETE', 'carreras/{carreraLibre}', null, self::ADMIN],
            'ver los periodos' => ['/periodos', 'GET', 'periodos', null, self::SESION],
            'ver el periodo actual' => ['/periodos/actual', 'GET', 'periodos/actual', null, self::SESION],
            'crear un periodo' => ['/periodos', 'POST', 'periodos', ['nombre' => 'Futuro', 'fechaInicio' => '2030-01-01', 'fechaFin' => '2030-06-30'], self::ADMIN],
            'editar un periodo' => ['/periodos/{id}', 'PUT', 'periodos/{periodo}', ['nombre' => 'X'], self::ADMIN],
            'borrar un periodo' => ['/periodos/{id}', 'DELETE', 'periodos/{periodoLibre}', null, self::ADMIN],

            'ver las cuentas' => ['/usuarios', 'GET', 'usuarios', null, self::SESION],
            'ver una cuenta' => ['/usuarios/{id}', 'GET', 'usuarios/{docente}', null, self::ADMIN],
            'crear una cuenta' => ['/usuarios', 'POST', 'usuarios', ['rol' => 'docente', 'matricula' => 'D800', 'nombre' => 'X', 'contrasena' => 'contrasena-larga'], self::ADMIN],
            'editar una cuenta' => ['/usuarios/{id}', 'PUT', 'usuarios/{otraCuenta}', ['nombre' => 'X'], self::ADMIN],
            'restablecer una contraseña' => ['/usuarios/{id}/contrasena', 'PUT', 'usuarios/{otraCuenta}/contrasena', ['contrasena' => 'contrasena-larga'], self::ADMIN],
            'borrar una cuenta' => ['/usuarios/{id}', 'DELETE', 'usuarios/{otraCuenta}', null, self::ADMIN],

            'buscar alumnos' => ['/alumnos', 'GET', 'alumnos', null, self::SESION],
            'ver un alumno' => ['/alumnos/{matricula}', 'GET', 'alumnos/23303001', null, self::SESION],
            'dar de alta un alumno' => ['/alumnos', 'POST', 'alumnos', ['matricula' => '23309999', 'nombre' => 'X', 'apellido' => 'Y'], self::PERSONAL],
            'cargar alumnos en lote' => ['/alumnos/lote', 'POST', 'alumnos/lote', ['alumnos' => [['matricula' => '23309998', 'nombre' => 'X', 'apellido' => 'Y']]], self::PERSONAL],
            'cambio masivo de alumnos' => ['/alumnos/cambio-masivo', 'POST', 'alumnos/cambio-masivo', ['carrera' => 'ISIC', 'semestre' => 5, 'nuevoGrupo' => 'B'], self::PERSONAL],
            'editar un alumno' => ['/alumnos/{matricula}', 'PUT', 'alumnos/23303002', ['grupo' => 'C'], self::PERSONAL],
            'borrar un alumno' => ['/alumnos/{matricula}', 'DELETE', 'alumnos/23303002', null, self::ADMIN],

            'ver las materias' => ['/materias', 'GET', 'materias', null, self::SESION],
            'ver una materia' => ['/materias/{id}', 'GET', 'materias/{materia}', null, self::SESION],
            'crear una materia' => ['/materias', 'POST', 'materias', ['nombre' => 'Nueva'], self::ADMIN],
            'editar una materia' => ['/materias/{id}', 'PUT', 'materias/{materia}', ['semestre' => 6], self::ADMIN],
            'borrar una materia' => ['/materias/{id}', 'DELETE', 'materias/{materiaLibre}', null, self::ADMIN],
            'ver las prácticas' => ['/materias/{id}/practicas', 'GET', 'materias/{materia}/practicas', null, self::SESION],
            'capturar una práctica' => ['/materias/{id}/practicas', 'POST', 'materias/{materia}/practicas', ['titulo' => 'Nueva'], self::ADMIN_Y_SU_DOCENTE],
            'editar una práctica propia' => ['/practicas/{id}', 'PUT', 'practicas/{practicaPropia}', ['titulo' => 'Editada'], self::ADMIN_Y_SU_DOCENTE],
            'borrar una práctica propia' => ['/practicas/{id}', 'DELETE', 'practicas/{practicaPropia}', null, self::ADMIN_Y_SU_DOCENTE],

            'ver los cursos' => ['/cursos', 'GET', 'cursos', null, self::SESION],
            'ver un curso' => ['/cursos/{id}', 'GET', 'cursos/{curso}', null, self::SESION],
            'crear un curso' => ['/cursos', 'POST', 'cursos', ['materiaId' => '{materia}', 'periodoId' => '{periodo}', 'grupo' => '8A'], self::ADMIN],
            'editar un curso' => ['/cursos/{id}', 'PUT', 'cursos/{curso}', ['turno' => 'vespertino'], self::ADMIN],
            'borrar un curso' => ['/cursos/{id}', 'DELETE', 'cursos/{cursoLibre}', null, self::ADMIN],
            'ver los inscritos' => ['/cursos/{id}/alumnos', 'GET', 'cursos/{curso}/alumnos', null, self::SESION],
            'inscribir en su curso' => ['/cursos/{id}/alumnos', 'POST', 'cursos/{curso}/alumnos', ['matriculas' => ['23303002']], self::SESION],
            'dar de baja de su curso' => ['/cursos/{id}/alumnos/{matricula}', 'DELETE', 'cursos/{curso}/alumnos/23303001', null, self::SESION],
            'ver la asistencia del curso' => ['/cursos/{id}/asistencia', 'GET', 'cursos/{curso}/asistencia', null, self::SESION],

            'ver el horario' => ['/horarios', 'GET', 'horarios', null, self::SESION],
            'agregar al horario' => ['/horarios', 'POST', 'horarios', ['cursoId' => '{curso}', 'laboratorio' => 'redes', 'dia' => 2, 'horaInicio' => '08:00', 'horaFin' => '09:00'], self::PERSONAL],
            'editar el horario' => ['/horarios/{id}', 'PUT', 'horarios/{horario}', ['horaFin' => '11:00'], self::PERSONAL],
            'quitar del horario' => ['/horarios/{id}', 'DELETE', 'horarios/{horario}', null, self::PERSONAL],

            'ver los equipos' => ['/laboratorios/{clave}/equipos', 'GET', 'laboratorios/programacion/equipos', null, self::TODOS],
            'agregar un equipo' => ['/laboratorios/{clave}/equipos', 'POST', 'laboratorios/programacion/equipos', ['etiqueta' => 'PC-99'], self::ADMIN],
            'configurar los equipos' => ['/laboratorios/{clave}/equipos/configurar', 'POST', 'laboratorios/programacion/equipos/configurar', ['cantidad' => 25], self::PERSONAL],
            'marcar un equipo' => ['/equipos/{id}', 'PUT', 'equipos/{pc25}', ['notas' => 'Revisar'], self::PERSONAL],
            'retirar un equipo' => ['/equipos/{id}', 'DELETE', 'equipos/{pc25}', null, self::ADMIN],
            'ver los reportes de equipo' => ['/reportes-de-equipo', 'GET', 'reportes-de-equipo', null, self::SESION],
            'reportar una falla' => ['/equipos/{id}/reportes', 'POST', 'equipos/{pc24}/reportes', ['descripcion' => 'Falla'], self::SESION],
            'atender un reporte' => ['/reportes-de-equipo/{id}', 'PUT', 'reportes-de-equipo/{reporte}', ['estado' => 'en_proceso'], self::PERSONAL],

            'ver las clases abiertas' => ['/clases/abiertas', 'GET', 'clases/abiertas?laboratorio=programacion', null, self::TODOS],
            'ver las clases' => ['/clases', 'GET', 'clases', null, self::SESION],
            'ver una clase' => ['/clases/{id}', 'GET', 'clases/{clase}', null, self::SESION],
            'abrir la clase de su curso' => ['/clases', 'POST', 'clases', ['cursoId' => '{curso}', 'laboratorio' => 'redes'], self::ADMIN_Y_SU_DOCENTE],
            'abrir una clase de invitado' => ['/clases', 'POST', 'clases', ['tipo' => 'invitado', 'laboratorio' => 'redes', 'materia' => 'Taller'], self::PERSONAL],
            'cerrar la clase' => ['/clases/{id}/cerrar', 'POST', 'clases/{clase}/cerrar', [], self::SESION],
            'registrar a mano' => ['/clases/{id}/asistencias', 'POST', 'clases/{clase}/asistencias', ['matricula' => '23303002', 'equipoPropio' => true], self::SESION],
            'registrar la entrada' => ['/registro/entrada', 'POST', 'registro/entrada', ['laboratorio' => 'programacion', 'matricula' => '23303002', 'equipoPropio' => true], self::TODOS],

            'ver quién está dentro' => ['/asistencias/activas', 'GET', 'asistencias/activas?laboratorio=programacion', null, self::SESION],
            'ver el historial' => ['/asistencias', 'GET', 'asistencias', null, self::SESION],
            'registrar uso fuera de clase' => ['/asistencias', 'POST', 'asistencias', ['laboratorio' => 'redes', 'nombre' => 'X', 'apellido' => 'Y', 'equipoPropio' => true], self::PERSONAL],
            'registrar una salida' => ['/asistencias/{id}/salida', 'POST', 'asistencias/{asistencia}/salida', [], self::SESION],
            'quitar una asistencia' => ['/asistencias/{id}', 'DELETE', 'asistencias/{asistencia}', null, self::SESION],

            'pedir ingreso' => ['/solicitudes', 'POST', 'solicitudes', ['laboratorio' => 'redes', 'matricula' => '23303001'], self::TODOS],
            'ver las solicitudes' => ['/solicitudes', 'GET', 'solicitudes', null, self::SESION],
            'resolver una de clase' => ['/solicitudes/{id}/resolver', 'POST', 'solicitudes/{solicitudDeClase}/resolver', ['estado' => 'rechazada'], self::ADMIN],
            'resolver una de fuera de clase' => ['/solicitudes/{id}/resolver', 'POST', 'solicitudes/{solicitudFuera}/resolver', ['estado' => 'rechazada'], self::PERSONAL],
            'marcar una solicitud' => ['/solicitudes/{id}', 'PUT', 'solicitudes/{solicitudDeClase}', ['leida' => true], self::PERSONAL],

            'ver los avisos' => ['/avisos', 'GET', 'avisos', null, self::PERSONAL],
            'marcar un aviso' => ['/avisos/{id}', 'PUT', 'avisos/{aviso}', ['leido' => true], self::PERSONAL],
            'marcar todos los avisos' => ['/avisos/leidos', 'POST', 'avisos/leidos', [], self::PERSONAL],
            'ver los eventos' => ['/eventos', 'GET', 'eventos', null, self::SESION],
            'crear un evento' => ['/eventos', 'POST', 'eventos', ['laboratorio' => 'programacion', 'nombre' => 'Taller'], self::PERSONAL],
            'editar un evento' => ['/eventos/{id}', 'PUT', 'eventos/{evento}', ['nombre' => 'Otro'], self::PERSONAL],
            'borrar un evento' => ['/eventos/{id}', 'DELETE', 'eventos/{evento}', null, self::PERSONAL],
            'consultar la bitácora' => ['/bitacora', 'GET', 'bitacora', null, self::PERSONAL],
            'ver las estadísticas' => ['/estadisticas', 'GET', 'estadisticas', null, self::SESION],
        ];
    }

    #[DataProvider('rutas')]
    public function test_quien_puede_usar_la_ruta(string $forma, string $metodo, string $ruta, ?array $cuerpo, array $pueden): void
    {
        $ruta = $this->conIds($ruta);
        $cuerpo = $cuerpo === null ? null : array_map(fn ($v) => is_string($v) ? $this->conIds($v) : $v, $cuerpo);
        // Primero los que no pueden: así una baja de quien sí puede no cambia lo que ellos reciben.
        $quienes = self::TODOS;
        usort($quienes, static fn (string $a, string $b) => in_array($a, $pueden, true) <=> in_array($b, $pueden, true));

        foreach ($quienes as $quien) {
            $cliente = $quien === 'anonimo' ? $this->anonimo() : $this->con($quien);
            $respuesta = $cliente->pedir($metodo, $ruta, $cuerpo);
            if (in_array($quien, $pueden, true)) {
                $this->assertNotContains($respuesta->estado, [401, 403], "$quien debería poder: $metodo $ruta. $respuesta");
            } else {
                $this->assertSame($quien === 'anonimo' ? 401 : 403, $respuesta->estado, "$quien no debería poder: $metodo $ruta. $respuesta");
            }
        }
    }

    public function test_todas_las_rutas_estan_en_la_tabla(): void
    {
        $registradas = array_map(static fn ($r) => "$r->metodo $r->forma", Rutas::todas()->rutas());
        $enLaTabla = array_unique(array_map(static fn (array $fila) => "$fila[1] $fila[0]", self::rutas()));

        $this->assertEqualsCanonicalizing($registradas, array_values($enLaTabla));
    }

    private function conIds(string $texto): string|int
    {
        if (preg_match('/^\{(\w+)\}$/', $texto, $m)) {
            return $this->ids[$m[1]];
        }
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string)$this->ids[$m[1]], $texto);
    }
}
