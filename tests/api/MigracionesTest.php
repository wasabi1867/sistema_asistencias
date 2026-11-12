<?php

namespace Tests\Api;

/**
 * Las migraciones de db/migraciones/ llevan la base instalada en producción al esquema
 * de db/esquema.sql, sin perder sus datos. migraciones/esquema-instalado-2026-10-22.sql
 * es el esquema con que se instaló la base de producción.
 */
final class MigracionesTest extends PruebaDeApi
{
    public function test_las_migraciones_dejan_la_base_de_produccion_igual_que_el_esquema(): void
    {
        $migrada = $this->baseConElEsquemaInstalado();
        $nueva = 'asistencias_nueva_' . bin2hex(random_bytes(3));
        try {
            $this->crearBase($nueva, (string)file_get_contents(dirname(__DIR__, 2) . '/db/esquema.sql'));
            [$codigo, $salida] = Entorno::correrScript('migrar.php', variables: ['ASISTENCIAS_DB_NAME' => $migrada]);
            $this->assertSame(0, $codigo, $salida);

            $this->assertCount(21, $this->estructura($nueva));
            $this->assertSame($this->estructura($nueva), $this->estructura($migrada));
            $this->assertSame(
                count(glob(dirname(__DIR__, 2) . '/db/migraciones/*.sql')),
                (int)$this->valor("SELECT COUNT(*) FROM `$migrada`.migraciones")
            );
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$migrada`");
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$nueva`");
        }
    }

    public function test_cada_cuenta_conserva_su_rol_al_pasar_a_cuentas_con_varios_roles(): void
    {
        $base = $this->baseConElEsquemaInstalado();
        try {
            $this->pdo()->exec(
                "INSERT INTO `$base`.usuarios (id, rol, email, matricula, nombre, password_hash) VALUES
                 (1, 'admin', 'jefa@pruebas.test', NULL, 'Juana', 'x'),
                 (2, 'docente', 'diego@pruebas.test', 'D100', 'Diego', 'x'),
                 (3, 'laboratorista', NULL, 'L100', 'Laura', 'x')"
            );
            $this->pdo()->exec("INSERT INTO `$base`.bitacora (usuario_id, usuario, rol, accion) VALUES (1, 'Juana', 'admin', 'iniciar_sesion')");

            [$codigo, $salida] = Entorno::correrScript('migrar.php', variables: ['ASISTENCIAS_DB_NAME' => $base]);
            $this->assertSame(0, $codigo, $salida);

            $this->assertSame('1 admin,2 docente,3 laboratorista', $this->valor("SELECT GROUP_CONCAT(id, ' ', roles ORDER BY id) FROM `$base`.usuarios"));
            $this->assertSame('admin', $this->valor("SELECT roles FROM `$base`.bitacora"));
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$base`");
        }
    }

    public function test_los_laboratoristas_que_ya_existian_quedan_con_todos_los_laboratorios(): void
    {
        $base = $this->baseConElEsquemaInstalado();
        try {
            $this->pdo()->exec(
                "INSERT INTO `$base`.usuarios (id, rol, email, matricula, nombre, password_hash) VALUES
                 (1, 'admin', 'jefa@pruebas.test', NULL, 'Juana', 'x'),
                 (2, 'laboratorista', NULL, 'L100', 'Laura', 'x')"
            );
            $this->pdo()->exec("INSERT INTO `$base`.laboratorios (clave, nombre) VALUES ('a', 'Laboratorio A'), ('b', 'Laboratorio B')");

            [$codigo, $salida] = Entorno::correrScript('migrar.php', variables: ['ASISTENCIAS_DB_NAME' => $base]);
            $this->assertSame(0, $codigo, $salida);

            $this->assertSame('2 a,2 b', $this->valor(
                "SELECT GROUP_CONCAT(lp.usuario_id, ' ', l.clave ORDER BY l.clave) FROM `$base`.laboratorios_del_personal lp JOIN `$base`.laboratorios l ON l.id = lp.laboratorio_id"
            ));
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$base`");
        }
    }

    public function test_lo_ya_registrado_queda_con_el_docente_del_curso_y_los_datos_del_alumno(): void
    {
        $base = $this->baseConElEsquemaInstalado();
        try {
            $this->pdo()->exec(
                "INSERT INTO `$base`.usuarios (id, rol, email, matricula, nombre, password_hash) VALUES
                 (1, 'admin', 'jefa@pruebas.test', NULL, 'Juana', 'x'),
                 (2, 'docente', NULL, 'D100', 'Diego', 'x');
                 INSERT INTO `$base`.laboratorios (id, clave, nombre) VALUES (1, 'a', 'Laboratorio A');
                 INSERT INTO `$base`.carreras (id, clave, nombre) VALUES (1, 'ISIC', 'Sistemas');
                 INSERT INTO `$base`.periodos (id, nombre, fecha_inicio, fecha_fin) VALUES (1, 'Actual', '2026-08-01', '2026-12-31');
                 INSERT INTO `$base`.materias (id, nombre) VALUES (1, 'Programación Web');
                 INSERT INTO `$base`.cursos (id, materia_id, periodo_id, grupo, docente_id) VALUES (1, 1, 1, '5A', 2);
                 INSERT INTO `$base`.alumnos (id, matricula, nombre, apellido, carrera_id, semestre, turno, grupo) VALUES (1, '23303001', 'Ana', 'Alumna', 1, 5, 'matutino', 'A');
                 INSERT INTO `$base`.inscripciones (curso_id, alumno_id) VALUES (1, 1);
                 INSERT INTO `$base`.clases (id, laboratorio_id, tipo, curso_id, abierta_por, cerrada_por, cerrada_en, cierre) VALUES (1, 1, 'regular', 1, 2, 2, NOW(), 'normal');
                 INSERT INTO `$base`.clases (id, laboratorio_id, tipo, materia_invitado, abierta_por) VALUES (2, 1, 'invitado', 'Taller', 1);
                 INSERT INTO `$base`.asistencias (laboratorio_id, clase_id, tipo_persona, alumno_id, hora_salida) VALUES (1, 1, 'alumno', 1, NOW());
                 INSERT INTO `$base`.asistencias (laboratorio_id, clase_id, tipo_persona, invitado_nombre, invitado_apellido) VALUES (1, 2, 'invitado', 'Iván', 'Invitado')"
            );

            [$codigo, $salida] = Entorno::correrScript('migrar.php', variables: ['ASISTENCIAS_DB_NAME' => $base]);
            $this->assertSame(0, $codigo, $salida);

            $this->assertSame('1 2,2 -', $this->valor("SELECT GROUP_CONCAT(id, ' ', COALESCE(docente_id, '-') ORDER BY id) FROM `$base`.clases"));
            $this->assertSame('1 5 matutino A,- - - -', $this->valor(
                "SELECT GROUP_CONCAT(COALESCE(carrera_id, '-'), ' ', COALESCE(semestre, '-'), ' ', COALESCE(turno, '-'), ' ', COALESCE(grupo, '-') ORDER BY id) FROM `$base`.asistencias"
            ));
            $this->assertNull($this->valor("SELECT baja_en FROM `$base`.inscripciones"));
        } finally {
            $this->pdo()->exec("DROP DATABASE IF EXISTS `$base`");
        }
    }

    private function baseConElEsquemaInstalado(): string
    {
        $base = 'asistencias_migrada_' . bin2hex(random_bytes(3));
        $this->crearBase($base, (string)file_get_contents(__DIR__ . '/migraciones/esquema-instalado-2026-10-22.sql'));
        return $base;
    }

    private function crearBase(string $base, string $esquema): void
    {
        $deLasPruebas = $this->valor('SELECT DATABASE()');
        $this->pdo()->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $this->pdo()->exec("USE `$base`");
            $this->pdo()->exec($esquema);
        } finally {
            $this->pdo()->exec("USE `$deLasPruebas`");
        }
    }

    /**
     * Cada tabla como la describe la base, con sus renglones ordenados: una columna o un
     * índice agregado por una migración queda al final, y eso no es una diferencia.
     * @return array<string, list<string>>
     */
    private function estructura(string $base): array
    {
        $tablas = $this->pdo()->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '$base' ORDER BY table_name")->fetchAll(\PDO::FETCH_COLUMN);
        $estructura = [];
        foreach ($tablas as $tabla) {
            $crear = $this->pdo()->query("SHOW CREATE TABLE `$base`.`$tabla`")->fetch(\PDO::FETCH_NUM)[1];
            $renglones = array_map(
                static fn (string $r) => rtrim(preg_replace('/ AUTO_INCREMENT=\d+/', '', trim($r)), ','),
                explode("\n", $crear)
            );
            sort($renglones);
            $estructura[$tabla] = $renglones;
        }
        return $estructura;
    }
}
