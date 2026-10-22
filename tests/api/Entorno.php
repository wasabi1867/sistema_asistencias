<?php

namespace Tests\Api;

use PDO;
use RuntimeException;

/**
 * Prepara lo que necesitan las pruebas: una base de datos nueva creada con schema.sql
 * y el servidor de desarrollo de PHP sirviendo public/ contra esa base. Las pruebas
 * llaman a la API por HTTP, igual que el navegador. Al terminar se borra todo.
 *
 * Conexión a MariaDB (variables de entorno, con estos valores por omisión):
 *   ASISTENCIAS_PRUEBAS_DB_HOST=127.0.0.1  ASISTENCIAS_PRUEBAS_DB_PORT=3306
 *   ASISTENCIAS_PRUEBAS_DB_USER=root       ASISTENCIAS_PRUEBAS_DB_PASS=
 */
final class Entorno
{
    public const ORIGEN_PERMITIDO = 'http://frontend-permitido.test';

    private const TABLAS = [
        'alumnos', 'docentes', 'laboratoristas', 'administradores', 'materias', 'practicas',
        'equipos', 'asistencias', 'asistencias_historico', 'solicitudes_ingreso',
        'notificaciones_admin', 'logs',
    ];

    private static ?PDO $pdo = null;
    private static string $base = '';
    private static string $url = '';
    /** @var resource|null */
    private static $servidor = null;
    private static string $carpetaTemporal = '';

    public static function iniciar(): void
    {
        $raiz = dirname(__DIR__, 2);
        $host = getenv('ASISTENCIAS_PRUEBAS_DB_HOST') ?: '127.0.0.1';
        $puertoDb = getenv('ASISTENCIAS_PRUEBAS_DB_PORT') ?: '3306';
        $usuario = getenv('ASISTENCIAS_PRUEBAS_DB_USER') ?: 'root';
        $contrasena = getenv('ASISTENCIAS_PRUEBAS_DB_PASS') ?: '';

        self::$base = 'asistencias_pruebas_' . bin2hex(random_bytes(4));
        $servidorDb = new PDO("mysql:host=$host;port=$puertoDb;charset=utf8mb4", $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $servidorDb->exec('CREATE DATABASE `' . self::$base . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        self::$pdo = new PDO("mysql:host=$host;port=$puertoDb;dbname=" . self::$base . ';charset=utf8mb4', $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec(file_get_contents($raiz . '/schema.sql'));

        self::$carpetaTemporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::$base;
        mkdir(self::$carpetaTemporal . DIRECTORY_SEPARATOR . 'sesiones', 0777, true);

        $puerto = self::puertoLibre();
        self::$url = "http://127.0.0.1:$puerto";

        $entorno = getenv() + [
            'ASISTENCIAS_DB_HOST' => $host,
            'ASISTENCIAS_DB_PORT' => $puertoDb,
            'ASISTENCIAS_DB_NAME' => self::$base,
            'ASISTENCIAS_DB_USER' => $usuario,
            'ASISTENCIAS_DB_PASS' => $contrasena,
            'ASISTENCIAS_ORIGENES_PERMITIDOS' => self::ORIGEN_PERMITIDO,
        ];
        $salida = self::$carpetaTemporal . DIRECTORY_SEPARATOR . 'servidor.log';
        self::$servidor = proc_open(
            [
                PHP_BINARY,
                '-d', 'xdebug.mode=off',
                '-d', 'session.save_path=' . self::$carpetaTemporal . DIRECTORY_SEPARATOR . 'sesiones',
                '-d', 'error_log=' . self::$carpetaTemporal . DIRECTORY_SEPARATOR . 'errores.log',
                '-S', "127.0.0.1:$puerto",
                '-t', $raiz . DIRECTORY_SEPARATOR . 'public',
            ],
            [0 => ['pipe', 'r'], 1 => ['file', $salida, 'a'], 2 => ['file', $salida, 'a']],
            $pipes,
            $raiz,
            $entorno
        );
        if (!is_resource(self::$servidor)) {
            throw new RuntimeException('No se pudo iniciar el servidor de PHP');
        }

        $limite = microtime(true) + 10;
        while (!@fsockopen('127.0.0.1', $puerto, $codigo, $mensaje, 0.2)) {
            if (microtime(true) > $limite) {
                throw new RuntimeException("El servidor de PHP no respondió en el puerto $puerto");
            }
            usleep(100_000);
        }
    }

    public static function terminar(): void
    {
        if (is_resource(self::$servidor)) {
            proc_terminate(self::$servidor);
            proc_close(self::$servidor);
        }
        if (self::$pdo !== null) {
            self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::$base . '`');
            self::$pdo = null;
        }
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }

    public static function url(): string
    {
        return self::$url;
    }

    /** Lo que la API escribió en el registro de errores; útil cuando una prueba falla. */
    public static function erroresDelServidor(): string
    {
        $archivo = self::$carpetaTemporal . DIRECTORY_SEPARATOR . 'errores.log';
        return is_file($archivo) ? (string)file_get_contents($archivo) : '';
    }

    /** Deja todas las tablas vacías; cada prueba crea los datos que usa. */
    public static function vaciarTablas(): void
    {
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLAS as $tabla) {
            self::$pdo->exec("TRUNCATE TABLE `$tabla`");
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private static function puertoLibre(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $nombre = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int)substr($nombre, strrpos($nombre, ':') + 1);
    }
}
