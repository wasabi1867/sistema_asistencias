<?php

namespace Tests\Api;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Fija quién puede usar cada ruta. Si se cambia un permiso, se cambia aquí también;
 * así ningún cambio de permisos pasa sin que alguien lo vea.
 */
final class PermisosTest extends PruebaDeApi
{
    private const PUBLICA = ['anonimo', 'admin', 'laboratorista', 'docente'];
    private const TODOS = ['admin', 'laboratorista', 'docente'];
    private const PERSONAL = ['admin', 'laboratorista'];
    private const ADMIN = ['admin'];
    private const ADMIN_Y_SU_DOCENTE = ['admin', 'docente'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearCuentas();
        $this->crearAlumno('A1');
        $this->crearAlumno('A2');
        $this->crearEquipos();
        $this->insertar('materias', ['id' => 'M1', 'nombre_materia' => 'Programación Web', 'docente_id' => self::MATRICULA_DOCENTE]);
        $this->insertar('practicas', ['id' => 1, 'materia_id' => 'M1', 'titulo' => 'Práctica 1']);
        $this->insertar('asistencias', ['id' => 1, 'alumno_id' => 'A2', 'nombre' => 'Ana', 'apellido' => 'Alumna', 'equipo_id' => 'PC-1']);
        $this->insertar('solicitudes_ingreso', ['id' => 1, 'alumno_id' => 'A1', 'nombre' => 'Ana', 'motivo' => 'Terminar práctica']);
        $this->insertar('notificaciones_admin', ['id' => 1, 'tipo' => 'general', 'titulo' => 'Aviso', 'mensaje' => 'Mensaje']);
    }

    public static function rutas(): array
    {
        $alumno = ['matricula' => 'A9', 'nombre' => 'Nuevo', 'apellido' => 'Alumno'];
        $cuenta = ['matricula' => 'X9', 'nombre' => 'Nueva', 'apellido' => 'Cuenta', 'password' => 'contrasena-segura'];

        return [
            'consultar alumnos' => ['GET', 'alumnos.php', null, self::TODOS],
            'dar de alta un alumno' => ['POST', 'alumnos.php', $alumno, self::PERSONAL],
            'editar un alumno' => ['PUT', 'alumnos.php', ['matricula' => 'A1', 'nombre' => 'Ana', 'apellido' => 'Editada'], self::PERSONAL],
            'borrar un alumno' => ['DELETE', 'alumnos.php?matricula=A1', null, self::ADMIN],

            'consultar docentes' => ['GET', 'docentes.php', null, self::TODOS],
            'guardar un docente' => ['POST', 'docentes.php', $cuenta, self::ADMIN],
            'borrar un docente' => ['DELETE', 'docentes.php?matricula=X9', null, self::ADMIN],

            'consultar laboratoristas' => ['GET', 'laboratoristas.php', null, self::TODOS],
            'guardar un laboratorista' => ['POST', 'laboratoristas.php', $cuenta, self::ADMIN],
            'borrar un laboratorista' => ['DELETE', 'laboratoristas.php?matricula=X9', null, self::ADMIN],

            'consultar materias' => ['GET', 'materias.php', null, self::TODOS],
            'guardar una materia' => ['POST', 'materias.php', ['id' => 'M9', 'nombre_materia' => 'Redes'], self::ADMIN],
            'borrar una materia' => ['DELETE', 'materias.php?id=M9', null, self::ADMIN],
            'crear una práctica' => ['POST', 'materias.php?action=crear_practica', ['materia_id' => 'M1', 'titulo' => 'Práctica 2'], self::ADMIN_Y_SU_DOCENTE],
            'borrar una práctica' => ['DELETE', 'materias.php?action=practica&id=1', null, self::ADMIN_Y_SU_DOCENTE],

            'consultar equipos' => ['GET', 'equipos.php', null, self::PUBLICA],
            'cambiar un equipo' => ['POST', 'equipos.php', ['id' => 'PC-2', 'fueraDeServicio' => true], self::PERSONAL],

            'consultar asistencias activas' => ['GET', 'asistencias.php', null, self::TODOS],
            'consultar el histórico' => ['GET', 'asistencias.php?action=historico', null, self::TODOS],
            'registrar entrada' => ['POST', 'asistencias.php?action=entrada', ['AlumnoId' => 'A1', 'Equipo' => 'personal'], self::PUBLICA],
            'registrar salida' => ['POST', 'asistencias.php?action=salida', ['id' => 1], self::TODOS],
            'quitar una asistencia' => ['DELETE', 'asistencias.php?id=1', null, self::TODOS],

            'consultar solicitudes' => ['GET', 'solicitudes.php', null, self::TODOS],
            'enviar una solicitud' => ['POST', 'solicitudes.php', ['alumno_id' => 'A1', 'motivo' => 'Terminar práctica'], self::PUBLICA],
            'resolver una solicitud' => ['PUT', 'solicitudes.php', ['id' => 1, 'estado' => 'aprobada'], self::ADMIN],

            'consultar notificaciones' => ['GET', 'notificaciones.php', null, self::ADMIN],
            'crear una notificación' => ['POST', 'notificaciones.php', ['titulo' => 'Equipo dañado', 'mensaje' => 'PC-3'], self::TODOS],
            'marcar notificaciones leídas' => ['PUT', 'notificaciones.php', ['id' => 1], self::ADMIN],

            'consultar estadísticas' => ['GET', 'stats.php', null, self::TODOS],
        ];
    }

    #[DataProvider('rutas')]
    public function test_cada_ruta_solo_la_usa_quien_debe(string $metodo, string $ruta, ?array $cuerpo, array $permitidos): void
    {
        foreach (['anonimo', 'docente', 'laboratorista', 'admin'] as $quien) {
            $cliente = $quien === 'anonimo' ? $this->anonimo() : $this->con($quien);
            $respuesta = $cliente->pedir($metodo, $ruta, $cuerpo);

            if (in_array($quien, $permitidos, true)) {
                $this->assertNotContains($respuesta->estado, [401, 403], "$quien debería poder: $metodo $ruta. $respuesta");
                $this->assertLessThan(500, $respuesta->estado, "$quien: $metodo $ruta. $respuesta");
            } else {
                $esperado = $quien === 'anonimo' ? 401 : 403;
                $this->assertSame($esperado, $respuesta->estado, "$quien no debería poder: $metodo $ruta. $respuesta");
            }
        }
    }

    public function test_un_docente_no_toca_las_practicas_de_una_materia_ajena(): void
    {
        $this->insertar('materias', ['id' => 'M2', 'nombre_materia' => 'Redes', 'docente_id' => 'OTRO']);
        $this->insertar('practicas', ['id' => 2, 'materia_id' => 'M2', 'titulo' => 'Práctica de redes']);
        $docente = $this->con('docente');

        $crear = $docente->post('materias.php?action=crear_practica', ['materia_id' => 'M2', 'titulo' => 'Intrusa']);
        $borrar = $docente->delete('materias.php?action=practica&id=2');

        $this->assertSame(403, $crear->estado, (string)$crear);
        $this->assertSame(403, $borrar->estado, (string)$borrar);
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM practicas WHERE materia_id = 'M2'"));
    }
}
