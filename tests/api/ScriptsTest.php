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

    public function test_crear_admin_rechaza_una_contrasena_corta(): void
    {
        [$codigo, $salida] = Entorno::correrScript('crear-admin.php', ['jefa@pruebas.test'], "corta\n");

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('al menos 8 caracteres', $salida);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM usuarios'));
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
