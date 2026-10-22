<?php

namespace Tests\Api;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas: vacía la base antes de cada prueba y ofrece datos y clientes
 * con sesión iniciada para cada rol.
 */
abstract class PruebaDeApi extends TestCase
{
    protected const CONTRASENA = 'contrasena-de-prueba';
    protected const CORREO_ADMIN = 'admin@pruebas.test';
    protected const MATRICULA_LABORATORISTA = 'L100';
    protected const MATRICULA_DOCENTE = 'D100';

    protected function setUp(): void
    {
        Entorno::vaciarTablas();
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

    protected function insertar(string $tabla, array $fila): void
    {
        $columnas = implode(', ', array_map(fn ($c) => "`$c`", array_keys($fila)));
        $marcas = implode(', ', array_fill(0, count($fila), '?'));
        $this->pdo()->prepare("INSERT INTO `$tabla` ($columnas) VALUES ($marcas)")->execute(array_values($fila));
    }

    protected function valor(string $sql, array $parametros = []): mixed
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchColumn();
    }

    protected function crearCuentas(): void
    {
        $hash = password_hash(self::CONTRASENA, PASSWORD_DEFAULT);
        $this->insertar('administradores', ['email' => self::CORREO_ADMIN, 'nombre' => 'Admin de Pruebas', 'password' => $hash]);
        $this->insertar('laboratoristas', ['matricula' => self::MATRICULA_LABORATORISTA, 'nombre' => 'Laura', 'apellido' => 'Laboratorista', 'email' => 'laura@pruebas.test', 'password' => $hash]);
        $this->insertar('docentes', ['matricula' => self::MATRICULA_DOCENTE, 'nombre' => 'Diego', 'apellido' => 'Docente', 'email' => 'diego@pruebas.test', 'password' => $hash]);
    }

    protected function crearAlumno(string $matricula = '23303001', string $nombre = 'Ana', string $apellido = 'Alumna'): void
    {
        $this->insertar('alumnos', [
            'matricula' => $matricula, 'nombre' => $nombre, 'apellido' => $apellido,
            'carrera' => 'Sistemas', 'semestre' => '5', 'turno' => 'Matutino', 'grupo' => 'A',
        ]);
    }

    protected function crearEquipos(string $laboratorio = 'programacion', int $cuantos = 3): void
    {
        $this->insertar('equipos', ['id' => 'personal', 'laboratorio' => $laboratorio]);
        for ($i = 1; $i <= $cuantos; $i++) {
            $this->insertar('equipos', ['id' => "PC-$i", 'laboratorio' => $laboratorio]);
        }
    }

    protected function anonimo(): Cliente
    {
        return new Cliente();
    }

    /** Un cliente con la sesión de 'admin', 'laboratorista' o 'docente' ya iniciada. */
    protected function con(string $rol): Cliente
    {
        $usuario = ['admin' => self::CORREO_ADMIN, 'laboratorista' => self::MATRICULA_LABORATORISTA, 'docente' => self::MATRICULA_DOCENTE][$rol];
        $cliente = new Cliente();
        $respuesta = $cliente->post('auth.php', ['email' => $usuario, 'password' => self::CONTRASENA, 'role' => $rol]);
        $this->assertSame(200, $respuesta->estado, "No se pudo iniciar sesión como $rol. $respuesta");
        return $cliente;
    }
}
