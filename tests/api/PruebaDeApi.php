<?php

namespace Tests\Api;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas: deja la base recién instalada antes de cada prueba y ofrece
 * datos y clientes con sesión iniciada para cada rol.
 *
 * Los datos iniciales traen los cuatro laboratorios, las carreras ISIC, IIND, ICIV y
 * LADM, y los equipos PC-1 a PC-25 del laboratorio `programacion`.
 */
abstract class PruebaDeApi extends TestCase
{
    protected const CONTRASENA = 'contrasena-de-prueba';
    protected const CORREO_ADMIN = 'admin@pruebas.test';
    protected const MATRICULA_LABORATORISTA = 'L100';
    protected const MATRICULA_DOCENTE = 'D100';

    protected int $admin = 0;
    protected int $laboratorista = 0;
    protected int $docente = 0;

    protected function setUp(): void
    {
        Entorno::reiniciarBase();
    }

    protected function onNotSuccessfulTest(\Throwable $t): never
    {
        $errores = Entorno::erroresDelServidor();
        if ($errores !== '') {
            fwrite(STDERR, "\n--- Registro de errores de la API ---\n" . substr($errores, -4000) . "\n");
        }
        parent::onNotSuccessfulTest($t);
    }

    protected function pdo(): PDO
    {
        return Entorno::pdo();
    }

    /** Inserta directo en la base y devuelve el id. */
    protected function insertar(string $tabla, array $fila): int
    {
        $columnas = implode(', ', array_map(fn ($c) => "`$c`", array_keys($fila)));
        $marcas = implode(', ', array_fill(0, count($fila), '?'));
        $this->pdo()->prepare("INSERT INTO `$tabla` ($columnas) VALUES ($marcas)")->execute(array_values($fila));
        return (int)$this->pdo()->lastInsertId();
    }

    protected function valor(string $sql, array $parametros = []): mixed
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchColumn();
    }

    protected function fila(string $sql, array $parametros = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetch() ?: null;
    }

    // ------------------------------------------------------------------
    // Datos
    // ------------------------------------------------------------------

    /** Un administrador, un laboratorista y un docente, todos con self::CONTRASENA. */
    protected function crearCuentas(): void
    {
        $hash = password_hash(self::CONTRASENA, PASSWORD_DEFAULT);
        $this->admin = $this->insertar('usuarios', ['rol' => 'admin', 'email' => self::CORREO_ADMIN, 'nombre' => 'Ada', 'apellido' => 'Admin', 'password_hash' => $hash]);
        $this->laboratorista = $this->insertar('usuarios', ['rol' => 'laboratorista', 'matricula' => self::MATRICULA_LABORATORISTA, 'email' => 'laura@pruebas.test', 'nombre' => 'Laura', 'apellido' => 'Laboratorista', 'password_hash' => $hash]);
        $this->docente = $this->insertar('usuarios', ['rol' => 'docente', 'matricula' => self::MATRICULA_DOCENTE, 'email' => 'diego@pruebas.test', 'nombre' => 'Diego', 'apellido' => 'Docente', 'departamento' => 'Sistemas', 'password_hash' => $hash]);
    }

    protected function crearDocente(string $matricula, string $nombre = 'Otro', string $apellido = 'Docente'): int
    {
        return $this->insertar('usuarios', ['rol' => 'docente', 'matricula' => $matricula, 'nombre' => $nombre, 'apellido' => $apellido, 'password_hash' => password_hash(self::CONTRASENA, PASSWORD_DEFAULT)]);
    }

    protected function laboratorio(string $clave = 'programacion'): int
    {
        return (int)$this->valor('SELECT id FROM laboratorios WHERE clave = ?', [$clave]);
    }

    protected function carrera(string $clave = 'ISIC'): int
    {
        return (int)$this->valor('SELECT id FROM carreras WHERE clave = ?', [$clave]);
    }

    protected function equipo(string $etiqueta, string $laboratorio = 'programacion'): int
    {
        return (int)$this->valor('SELECT id FROM equipos WHERE etiqueta = ? AND laboratorio_id = ? AND activo', [$etiqueta, $this->laboratorio($laboratorio)]);
    }

    /** Un periodo que incluye la fecha de hoy. */
    protected function crearPeriodoActual(string $nombre = 'Actual'): int
    {
        return $this->insertar('periodos', [
            'nombre' => $nombre,
            'fecha_inicio' => date('Y-m-d', strtotime('-60 days')),
            'fecha_fin' => date('Y-m-d', strtotime('+60 days')),
        ]);
    }

    protected function crearMateria(string $nombre = 'Programación Web', ?string $carrera = 'ISIC'): int
    {
        return $this->insertar('materias', ['nombre' => $nombre, 'carrera_id' => $carrera === null ? null : $this->carrera($carrera), 'semestre' => 5]);
    }

    /** Un curso del periodo actual (lo crea si no hay), del docente de pruebas, en `programacion`. */
    protected function crearCurso(?int $materia = null, string $grupo = '5A', ?int $docente = null, ?int $periodo = null, ?string $laboratorio = 'programacion'): int
    {
        $periodo ??= ((int)$this->valor('SELECT id FROM periodos WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin') ?: $this->crearPeriodoActual());
        return $this->insertar('cursos', [
            'materia_id' => $materia ?? ((int)$this->valor("SELECT id FROM materias WHERE nombre = 'Programación Web'") ?: $this->crearMateria()),
            'periodo_id' => $periodo,
            'grupo' => $grupo,
            'docente_id' => $docente ?? ($this->docente ?: null),
            'laboratorio_id' => $laboratorio === null ? null : $this->laboratorio($laboratorio),
        ]);
    }

    protected function crearAlumno(string $matricula = '23303001', string $nombre = 'Ana', string $apellido = 'Alumna', ?string $carrera = 'ISIC'): int
    {
        return $this->insertar('alumnos', [
            'matricula' => $matricula, 'nombre' => $nombre, 'apellido' => $apellido,
            'carrera_id' => $carrera === null ? null : $this->carrera($carrera), 'semestre' => 5, 'turno' => 'matutino', 'grupo' => 'A',
        ]);
    }

    protected function inscribir(int $curso, int ...$alumnos): void
    {
        foreach ($alumnos as $alumno) {
            $this->insertar('inscripciones', ['curso_id' => $curso, 'alumno_id' => $alumno, 'inscrito_en' => date('Y-m-d H:i:s', strtotime('-30 days'))]);
        }
    }

    protected function crearPractica(int $materia, int $numero = 1, string $titulo = 'Práctica 1', ?int $docente = null): int
    {
        return $this->insertar('practicas', ['materia_id' => $materia, 'docente_id' => $docente, 'numero' => $numero, 'titulo' => $titulo]);
    }

    /** Abre directo en la base una clase regular del curso y devuelve su id. */
    protected function abrirClase(int $curso, string $laboratorio = 'programacion', ?int $practica = null, ?int $abiertaPor = null, ?string $codigo = null): int
    {
        return $this->insertar('clases', [
            'laboratorio_id' => $this->laboratorio($laboratorio), 'tipo' => 'regular', 'curso_id' => $curso,
            'practica_id' => $practica, 'abierta_por' => $abiertaPor ?? $this->docente, 'codigo' => $codigo,
        ]);
    }

    /** Abre directo en la base una clase de maestro invitado y devuelve su id. */
    protected function abrirClaseDeInvitado(string $laboratorio = 'programacion', string $materia = 'Taller de Arduino'): int
    {
        return $this->insertar('clases', [
            'laboratorio_id' => $this->laboratorio($laboratorio), 'tipo' => 'invitado', 'materia_invitado' => $materia,
            'docente_invitado' => 'Iván Invitado', 'abierta_por' => $this->laboratorista,
        ]);
    }

    /** Registra directo en la base la entrada de un alumno a una clase. */
    protected function registrarEntrada(int $clase, int $alumno, ?int $equipo = null): int
    {
        $laboratorio = (int)$this->valor('SELECT laboratorio_id FROM clases WHERE id = ?', [$clase]);
        return $this->insertar('asistencias', ['laboratorio_id' => $laboratorio, 'clase_id' => $clase, 'equipo_id' => $equipo, 'tipo_persona' => 'alumno', 'alumno_id' => $alumno]);
    }

    // ------------------------------------------------------------------
    // Clientes
    // ------------------------------------------------------------------

    protected function anonimo(): Cliente
    {
        return new Cliente();
    }

    /** Un cliente con la sesión de 'admin', 'laboratorista' o 'docente' ya iniciada. */
    protected function con(string $rol, ?string $usuario = null): Cliente
    {
        $usuario ??= ['admin' => self::CORREO_ADMIN, 'laboratorista' => self::MATRICULA_LABORATORISTA, 'docente' => self::MATRICULA_DOCENTE][$rol];
        $cliente = new Cliente();
        $respuesta = $cliente->post('sesion', ['usuario' => $usuario, 'contrasena' => self::CONTRASENA, 'rol' => $rol]);
        $this->assertSame(200, $respuesta->estado, "No se pudo iniciar sesión como $rol. $respuesta");
        return $cliente;
    }

    /** Revisa el estado de una respuesta y devuelve su JSON. */
    protected function ok(Respuesta $respuesta, int $estado = 200): array
    {
        $this->assertSame($estado, $respuesta->estado, (string)$respuesta);
        return $respuesta->json();
    }
}
