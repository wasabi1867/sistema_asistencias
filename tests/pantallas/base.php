<?php

declare(strict_types=1);

// Crea o borra la base de la prueba de pantallas, con la misma conexión que las pruebas
// de la API (ASISTENCIAS_PRUEBAS_DB_HOST, _PORT, _USER y _PASS):
//
//   php tests/pantallas/base.php crear asistencias_pantallas_x
//   php tests/pantallas/base.php borrar asistencias_pantallas_x

[, $accion, $nombre] = $argv + [null, '', ''];
if (!in_array($accion, ['crear', 'borrar'], true) || !preg_match('/^asistencias_pantallas_\w+$/', $nombre)) {
    fwrite(STDERR, "Uso: php tests/pantallas/base.php crear|borrar asistencias_pantallas_<algo>\n");
    exit(1);
}

$entorno = static fn (string $variable, string $porOmision) => getenv($variable) !== false ? (string)getenv($variable) : $porOmision;
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $entorno('ASISTENCIAS_PRUEBAS_DB_HOST', '127.0.0.1'), $entorno('ASISTENCIAS_PRUEBAS_DB_PORT', '3306')),
    $entorno('ASISTENCIAS_PRUEBAS_DB_USER', 'root'),
    $entorno('ASISTENCIAS_PRUEBAS_DB_PASS', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec($accion === 'crear'
    ? "CREATE DATABASE `$nombre` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    : "DROP DATABASE IF EXISTS `$nombre`");
