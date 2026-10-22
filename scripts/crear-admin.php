<?php

declare(strict_types=1);

// Crea un administrador, o le pone contraseña nueva si ya existe (y lo reactiva). Se
// corre en una terminal, con la conexión de api/config.local.php; la contraseña se
// escribe al correrlo, para que no quede en el historial de la terminal:
//
//   php scripts/crear-admin.php admin@puertopenasco.tecnm.mx "Nombre" "Apellido"
//
// Si el servidor no da terminal, con --solo-hash imprime la contraseña cifrada, para
// crear la cuenta desde phpMyAdmin (ver docs/cambio-de-firebase.md):
//
//   php scripts/crear-admin.php --solo-hash

require __DIR__ . '/comun.php';

use Asistencias\Http\ErrorHttp;
use Asistencias\Seguridad\Contrasenas;

function contrasenaNueva(): string
{
    fwrite(STDOUT, 'Contraseña nueva (mínimo ' . Contrasenas::LONGITUD_MINIMA . ' caracteres): ');
    try {
        return Contrasenas::cifrarNueva(trim((string)fgets(STDIN)));
    } catch (ErrorHttp $e) {
        fwrite(STDERR, $e->getMessage() . ".\n");
        exit(1);
    }
}

if (($argv[1] ?? '') === '--solo-hash') {
    echo contrasenaNueva(), PHP_EOL;
    exit(0);
}

$email = mb_strtolower(trim($argv[1] ?? ''));
$nombre = trim($argv[2] ?? 'Administrador');
$apellido = trim($argv[3] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/crear-admin.php correo@dominio [\"Nombre\"] [\"Apellido\"]\n");
    exit(1);
}

$hash = contrasenaNueva();
$pdo = conexion();
$existente = $pdo->prepare("SELECT id FROM usuarios WHERE rol = 'admin' AND email = ?");
$existente->execute([$email]);
$id = $existente->fetchColumn();

if ($id === false) {
    $pdo->prepare("INSERT INTO usuarios (rol, email, nombre, apellido, password_hash) VALUES ('admin', ?, ?, ?, ?)")
        ->execute([$email, $nombre, $apellido, $hash]);
    $pdo->prepare("INSERT INTO bitacora (accion, entidad, entidad_id, usuario, detalles) VALUES ('crear', 'usuarios', ?, 'Terminal del servidor', ?)")
        ->execute([$pdo->lastInsertId(), "Admin $email"]);
    echo "Administrador $email creado.\n";
} else {
    $pdo->prepare('UPDATE usuarios SET password_hash = ?, activo = TRUE WHERE id = ?')->execute([$hash, $id]);
    $pdo->prepare("INSERT INTO bitacora (accion, entidad, entidad_id, usuario, detalles) VALUES ('restablecer_contrasena', 'usuarios', ?, 'Terminal del servidor', ?)")
        ->execute([$id, "Admin $email"]);
    echo "Contraseña de $email cambiada.\n";
}
