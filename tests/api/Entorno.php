<?php

namespace Tests\Api;

use PDO;
use RuntimeException;

/**
 * Prepara lo que necesitan las pruebas: una base de datos nueva, creada igual que en
 * producción (db/esquema.sql, db/datos-iniciales.sql y las migraciones), y el servidor
 * de PHP atendiendo la API contra esa base. Las pruebas llaman a la API por HTTP,
 * igual que el navegador. Al terminar se borra todo.
 *
 * Conexión a MySQL o MariaDB (variables de entorno, con estos valores por omisión):
 *   ASISTENCIAS_PRUEBAS_DB_HOST=127.0.0.1  ASISTENCIAS_PRUEBAS_DB_PORT=3306
 *   ASISTENCIAS_PRUEBAS_DB_USER=root       ASISTENCIAS_PRUEBAS_DB_PASS=
 */
final class Entorno
{
    public const ORIGEN_PERMITIDO = 'http://frontend-permitido.test';

    private static ?PDO $pdo = null;
    private static string $base = '';
    private static string $raiz = '';
    private static string $carpetaTemporal = '';
    /** @var array<string, array{url: string, proceso: resource}> servidores por configuración */
    private static array $servidores = [];
    /** Variables de entorno con la conexión a la base de pruebas, para la API y los scripts. */
    private static array $entorno = [];
    /** @var list<string> */
    private static array $tablas = [];

    public static function iniciar(): void
    {
        self::$raiz = dirname(__DIR__, 2);
        $host = getenv('ASISTENCIAS_PRUEBAS_DB_HOST') ?: '127.0.0.1';
        $puerto = getenv('ASISTENCIAS_PRUEBAS_DB_PORT') ?: '3306';
        $usuario = getenv('ASISTENCIAS_PRUEBAS_DB_USER') ?: 'root';
        $contrasena = getenv('ASISTENCIAS_PRUEBAS_DB_PASS') ?: '';

        self::$base = 'asistencias_pruebas_' . bin2hex(random_bytes(4));
        $servidor = new PDO("mysql:host=$host;port=$puerto;charset=utf8mb4", $usuario, $contrasena, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $servidor->exec('CREATE DATABASE `' . self::$base . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        self::$pdo = new PDO("mysql:host=$host;port=$puerto;dbname=" . self::$base . ';charset=utf8mb4', $usuario, $contrasena, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // La misma zona que usa la API, para que NOW() coincida al comparar horas.
        self::$pdo->exec("SET time_zone = '" . (new \DateTimeImmutable('now', new \DateTimeZone('America/Hermosillo')))->format('P') . "'");

        self::$pdo->exec(file_get_contents(self::$raiz . '/db/esquema.sql'));
        $migraciones = glob(self::$raiz . '/db/migraciones/*.sql');
        sort($migraciones);
        foreach ($migraciones as $migracion) {
            self::$pdo->exec(file_get_contents($migracion));
        }
        self::$tablas = self::$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND table_name <> 'migraciones'")
            ->fetchAll(PDO::FETCH_COLUMN);
        // exec() con varias sentencias puede callar el error de una intermedia: si falta
        // alguna tabla, el esquema no cargó completo.
        $esperadas = preg_match_all('/^CREATE TABLE/m', file_get_contents(self::$raiz . '/db/esquema.sql')) - 1;
        if (count(self::$tablas) !== $esperadas) {
            throw new RuntimeException('El esquema no se cargó completo: hay ' . count(self::$tablas) . " tablas y se esperaban $esperadas");
        }

        self::$carpetaTemporal = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::$base;
        mkdir(self::$carpetaTemporal, 0777, true);

        self::$entorno = getenv() + [
            'ASISTENCIAS_DB_HOST' => $host,
            'ASISTENCIAS_DB_PORT' => $puerto,
            'ASISTENCIAS_DB_NAME' => self::$base,
            'ASISTENCIAS_DB_USER' => $usuario,
            'ASISTENCIAS_DB_PASS' => $contrasena,
            'ASISTENCIAS_ORIGENES_PERMITIDOS' => self::ORIGEN_PERMITIDO,
        ];
    }

    public static function terminar(): void
    {
        foreach (self::$servidores as $servidor) {
            proc_terminate($servidor['proceso']);
            proc_close($servidor['proceso']);
        }
        if (self::$pdo !== null) {
            self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::$base . '`');
            self::$pdo = null;
        }
        self::borrarCarpeta(self::$carpetaTemporal);
    }

    private static function borrarCarpeta(string $carpeta): void
    {
        if ($carpeta === '' || !is_dir($carpeta)) {
            return;
        }
        $contenido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($contenido as $archivo) {
            $archivo->isDir() ? @rmdir($archivo->getPathname()) : @unlink($archivo->getPathname());
        }
        @rmdir($carpeta);
    }

    /**
     * La dirección del servidor de la API. Con $variables (por ejemplo, exigir el código
     * de clase) se levanta otro servidor con esa configuración.
     *
     * @param array<string, string> $variables
     */
    public static function url(array $variables = []): string
    {
        ksort($variables);
        $clave = json_encode($variables);
        if (!isset(self::$servidores[$clave])) {
            self::$servidores[$clave] = self::levantarServidor($variables, count(self::$servidores));
        }
        return self::$servidores[$clave]['url'];
    }

    /**
     * Corre un script de scripts/ contra la base de pruebas. Devuelve [código de salida, lo que imprimió].
     * @param list<string> $argumentos
     */
    public static function correrScript(string $script, array $argumentos = [], string $entrada = ''): array
    {
        $proceso = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', self::$raiz . "/scripts/$script", ...$argumentos],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            self::$raiz,
            self::$entorno
        );
        fwrite($tuberias[0], $entrada);
        fclose($tuberias[0]);
        $salida = stream_get_contents($tuberias[1]) . stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        return [proc_close($proceso), $salida];
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }

    /** Lo que la API escribió en el registro de errores; útil cuando una prueba falla. */
    public static function erroresDelServidor(): string
    {
        $texto = '';
        foreach (glob(self::$carpetaTemporal . '/errores-*.log') ?: [] as $archivo) {
            $texto .= file_get_contents($archivo);
            file_put_contents($archivo, '');
        }
        return $texto;
    }

    /** Deja la base como recién instalada: vacía y con los datos iniciales. */
    public static function reiniciarBase(): void
    {
        // DELETE y no TRUNCATE: en MySQL 8, TRUNCATE es mucho más lento con tablas tan
        // pequeñas. Los id no vuelven a empezar en 1, así que las pruebas los buscan.
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::$tablas as $tabla) {
            self::$pdo->exec("DELETE FROM `$tabla`");
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        self::$pdo->exec(file_get_contents(self::$raiz . '/db/datos-iniciales.sql'));
    }

    /** @return array{url: string, proceso: resource} */
    private static function levantarServidor(array $variables, int $numero): array
    {
        $puerto = self::puertoLibre();
        $sesiones = self::$carpetaTemporal . "/sesiones-$numero";
        mkdir($sesiones);
        $bitacora = self::$carpetaTemporal . "/servidor-$numero.log";
        $proceso = proc_open(
            [
                PHP_BINARY,
                '-d', 'xdebug.mode=off',
                '-d', "session.save_path=$sesiones",
                '-d', 'error_log=' . self::$carpetaTemporal . "/errores-$numero.log",
                '-S', "127.0.0.1:$puerto",
                '-t', self::$raiz,
                self::$raiz . '/api/index.php',
            ],
            [0 => ['pipe', 'r'], 1 => ['file', $bitacora, 'a'], 2 => ['file', $bitacora, 'a']],
            $tuberias,
            self::$raiz,
            // Varios procesos, para que las pruebas de peticiones simultáneas lo sean de
            // verdad. En Windows el servidor de PHP no los admite y atiende de una en una.
            $variables + ['PHP_CLI_SERVER_WORKERS' => '4'] + self::$entorno
        );
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar el servidor de PHP');
        }
        $limite = microtime(true) + 10;
        while (!@fsockopen('127.0.0.1', $puerto, $codigo, $mensaje, 0.2)) {
            if (microtime(true) > $limite) {
                throw new RuntimeException("El servidor de PHP no respondió en el puerto $puerto");
            }
            usleep(100_000);
        }
        return ['url' => "http://127.0.0.1:$puerto", 'proceso' => $proceso];
    }

    private static function puertoLibre(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $nombre = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int)substr($nombre, strrpos($nombre, ':') + 1);
    }
}
