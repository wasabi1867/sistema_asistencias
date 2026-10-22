<?php
// Crea un administrador, o le pone contraseña nueva si ya existe. Se corre en una
// terminal, nunca desde el navegador (por eso vive fuera de public/):
//
//   php scripts/crear-admin.php admin@puertopenasco.tecnm.mx "Administrador General"
//
// Usa la misma conexión que la API (config.local.php o variables de entorno).
// Si el servidor no da terminal, con --solo-hash imprime la contraseña cifrada para
// pegarla en la columna `password` desde phpMyAdmin:
//
//   php scripts/crear-admin.php --solo-hash

if (PHP_SAPI !== 'cli') {
    exit(1);
}

function pedirContrasena(): string {
    fwrite(STDOUT, 'Contraseña nueva (mínimo 8 caracteres): ');
    $password = trim((string)fgets(STDIN));
    if (mb_strlen($password) < 8) {
        fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
        exit(1);
    }
    return $password;
}

if (($argv[1] ?? '') === '--solo-hash') {
    echo password_hash(pedirContrasena(), PASSWORD_DEFAULT), PHP_EOL;
    exit(0);
}

$email = trim($argv[1] ?? '');
$nombre = trim($argv[2] ?? 'Administrador');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/crear-admin.php correo@dominio [\"Nombre\"]\n");
    exit(1);
}

require __DIR__ . '/../public/api/config.php';

$hash = password_hash(pedirContrasena(), PASSWORD_DEFAULT);
$pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$stmt = $pdo->prepare("INSERT INTO administradores (email, nombre, password, rol) VALUES (?, ?, ?, 'admin')
    ON DUPLICATE KEY UPDATE password = VALUES(password)");
$stmt->execute([$email, $nombre, $hash]);

echo $stmt->rowCount() === 1 ? "Administrador $email creado.\n" : "Contraseña de $email cambiada.\n";
