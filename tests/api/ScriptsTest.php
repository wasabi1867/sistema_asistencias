<?php

namespace Tests\Api;

/** Los scripts de instalación y administración (scripts/). */
final class ScriptsTest extends PruebaDeApi
{
    public function test_instalar_crea_la_base_desde_cero_una_sola_vez(): void
    {
        $base = 'asistencias_instalar_' . bin2hex(random_bytes(3));
        $this->pdo()->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            [$codigo, $salida] = Entorno::correrScript('instalar.php', variables: ['ASISTENCIAS_DB_NAME' => $base]);
            $this->assertSame(0, $codigo, $salida);
            $this->assertSame(19, (int)$this->valor('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?', [$base]));
            $this->assertSame(4, (int)$this->valor("SELECT COUNT(*) FROM `$base`.laboratorios"));
            $this->assertSame(25, (int)$this->valor("SELECT COUNT(*) FROM `$base`.equipos"));

            [$otraVez, $aviso] = Entorno::correrScript('instalar.php', variables: ['ASISTENCIAS_DB_NAME' => $base]);
            $this->assertSame(1, $otraVez);
            $this->assertStringContainsString('solo trabaja sobre una base vacía', $aviso);
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$base`");
        }
    }

    public function test_crear_admin_crea_la_cuenta_y_luego_cambia_su_contrasena(): void
    {
        [$codigo, $salida] = Entorno::correrScript('crear-admin.php', ['jefa@pruebas.test', 'Juana', 'Jefa'], "contrasena-inicial\n");
        $this->assertSame(0, $codigo, $salida);
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'jefa@pruebas.test', 'contrasena' => 'contrasena-inicial', 'rol' => 'admin']));

        [$codigo, $salida] = Entorno::correrScript('crear-admin.php', ['jefa@pruebas.test'], "contrasena-cambiada\n");
        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('Contraseña de jefa@pruebas.test cambiada', $salida);
        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'jefa@pruebas.test', 'contrasena' => 'contrasena-cambiada', 'rol' => 'admin']));
        $this->assertSame(1, (int)$this->valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin'"));
    }

    public function test_crear_admin_ignora_la_marca_bom_que_antepone_powershell(): void
    {
        // Así llega `"contrasena-de-powershell" | php scripts/crear-admin.php ...` en PowerShell 5.1.
        [$codigo, $salida] = Entorno::correrScript('crear-admin.php', ['jefa@pruebas.test'], "\xEF\xBB\xBFcontrasena-de-powershell\r\n");
        $this->assertSame(0, $codigo, $salida);

        $this->ok($this->anonimo()->post('sesion', ['usuario' => 'jefa@pruebas.test', 'contrasena' => 'contrasena-de-powershell', 'rol' => 'admin']));
    }

    public function test_crear_admin_rechaza_una_contrasena_corta(): void
    {
        [$codigo, $salida] = Entorno::correrScript('crear-admin.php', ['jefa@pruebas.test'], "corta\n");

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('al menos 8 caracteres', $salida);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM usuarios'));
    }

    public function test_los_datos_exportados_cargan_sobre_una_base_creada_con_el_esquema(): void
    {
        [$codigo, $salida] = Entorno::correrScript('importar-de-firebase.php', ['--desde=' . __DIR__ . '/firebase']);
        $this->assertSame(0, $codigo, $salida);
        $archivo = sys_get_temp_dir() . '/exportacion-' . bin2hex(random_bytes(4)) . '.sql';
        $base = 'asistencias_exportada_' . bin2hex(random_bytes(3));
        $deLasPruebas = $this->valor('SELECT DATABASE()');
        try {
            [$codigo, $salida] = Entorno::correrScript('exportar-datos.php', [$archivo]);
            $this->assertSame(0, $codigo, $salida);

            $this->pdo()->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo()->exec("USE `$base`");
            $this->pdo()->exec(file_get_contents(dirname(__DIR__, 2) . '/db/esquema.sql'));
            $this->pdo()->exec(file_get_contents($archivo));
            $copia = $this->pdo()->query('SELECT COUNT(*) FROM asistencias')->fetchColumn() . ' ' . $this->pdo()->query("SELECT GROUP_CONCAT(apellido ORDER BY apellido) FROM alumnos")->fetchColumn();
        } finally {
            $this->pdo()->exec("USE `$deLasPruebas`");
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$base`");
            @unlink($archivo);
        }
        $original = $this->valor('SELECT COUNT(*) FROM asistencias') . ' ' . $this->valor("SELECT GROUP_CONCAT(apellido ORDER BY apellido) FROM alumnos");
        $this->assertSame("2 Álvarez,D'Angelo", $copia);
        $this->assertSame($original, $copia);
    }

    public function test_migrar_no_corre_sobre_una_base_sin_instalar(): void
    {
        $base = 'asistencias_vacia_' . bin2hex(random_bytes(3));
        $this->pdo()->exec("CREATE DATABASE `$base`");
        try {
            [$codigo, $salida] = Entorno::correrScript('migrar.php', variables: ['ASISTENCIAS_DB_NAME' => $base]);

            $this->assertSame(1, $codigo);
            $this->assertStringContainsString('php scripts/instalar.php', $salida);
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$base`");
        }
    }
}
