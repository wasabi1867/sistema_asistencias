<?php
// Aplica a la base de datos las migraciones de db/migraciones/ que falten, en orden.
// Se corre en una terminal, con la misma conexión que la API:
//
//   php scripts/migrar.php
//
// Sin terminal en el servidor, se pega cada archivo pendiente en phpMyAdmin, en
// orden; cada uno anota en la tabla `migraciones` que ya se aplicó.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../public/api/config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$aplicadas = [];
if ($pdo->query("SHOW TABLES LIKE 'migraciones'")->fetchColumn()) {
    $aplicadas = $pdo->query("SELECT nombre FROM migraciones")->fetchAll(PDO::FETCH_COLUMN);
}

$archivos = glob(__DIR__ . '/../db/migraciones/*.sql');
sort($archivos);
$pendientes = 0;

foreach ($archivos as $archivo) {
    $nombre = basename($archivo, '.sql');
    if (in_array($nombre, $aplicadas, true)) {
        continue;
    }
    $pendientes++;
    echo "Aplicando $nombre... ";
    try {
        $pdo->exec(file_get_contents($archivo));
        echo "listo.\n";
    } catch (PDOException $e) {
        // Los cambios de estructura no se pueden deshacer en MySQL: lo que se alcanzó
        // a aplicar se queda. Hay que revisar a mano antes de volver a intentar.
        echo "ERROR\n" . $e->getMessage() . "\n";
        exit(1);
    }
}

echo $pendientes ? "Base de datos al día.\n" : "No había migraciones pendientes.\n";
