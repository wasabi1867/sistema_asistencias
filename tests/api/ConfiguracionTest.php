<?php

namespace Tests\Api;

use Asistencias\Configuracion;
use Asistencias\Datos\BaseDeDatos;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Cómo se lee la configuración, sin base de datos. La conexión cifrada (db_tls) se
 * comprobó a mano contra la base de Hostinger: aquí no hay un servidor con TLS fijo.
 */
final class ConfiguracionTest extends TestCase
{
    private const VARIABLES = ['ASISTENCIAS_DB_TLS', 'ASISTENCIAS_DB_TLS_CA', 'ASISTENCIAS_DB_NAME'];

    private string $carpeta;

    protected function setUp(): void
    {
        $this->carpeta = sys_get_temp_dir() . '/config-' . bin2hex(random_bytes(4));
        mkdir($this->carpeta);
    }

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $variable) {
            putenv($variable);
        }
        @unlink("$this->carpeta/config.local.php");
        rmdir($this->carpeta);
    }

    private function conArchivo(array $valores): Configuracion
    {
        file_put_contents("$this->carpeta/config.local.php", '<?php return ' . var_export($valores, true) . ';');
        return Configuracion::cargar($this->carpeta);
    }

    public function test_sin_db_tls_la_conexion_no_se_cifra(): void
    {
        $config = $this->conArchivo(['db_name' => 'asistencias']);

        $this->assertFalse($config->dbCifrada);
        $this->assertSame('', $config->dbAutoridades);
    }

    public function test_lee_db_tls_y_sus_autoridades_del_archivo(): void
    {
        $config = $this->conArchivo(['db_tls' => true, 'db_tls_ca' => '/ruta/ca.pem']);

        $this->assertTrue($config->dbCifrada);
        $this->assertSame('/ruta/ca.pem', $config->dbAutoridades);
    }

    public function test_las_variables_de_entorno_ganan_al_archivo(): void
    {
        putenv('ASISTENCIAS_DB_TLS=1');
        putenv('ASISTENCIAS_DB_TLS_CA=/otra/ca.pem');
        putenv('ASISTENCIAS_DB_NAME=de_hostinger');

        $config = $this->conArchivo(['db_tls' => false, 'db_tls_ca' => '/ruta/ca.pem', 'db_name' => 'local']);

        $this->assertTrue($config->dbCifrada);
        $this->assertSame('/otra/ca.pem', $config->dbAutoridades);
        $this->assertSame('de_hostinger', $config->dbNombre);
    }

    public function test_un_archivo_de_autoridades_que_no_existe_se_rechaza_antes_de_conectar(): void
    {
        $config = new Configuracion('192.0.2.1', 3306, 'x', 'x', 'x', dbCifrada: true, dbAutoridades: "$this->carpeta/no-existe.pem");

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('No existe el archivo de autoridades');
        (new BaseDeDatos($config))->pdo();
    }
}
