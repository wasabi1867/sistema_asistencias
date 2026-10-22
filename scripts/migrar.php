<?php

declare(strict_types=1);

// Aplica a una base ya instalada los cambios de db/migraciones/ que falten, en orden.
// Se corre en una terminal, con la conexión de api/config.local.php:
//
//   php scripts/migrar.php
//
// Cada cambio a la base va en un archivo nuevo de db/migraciones/ (001-algo.sql, ...)
// y, en el mismo commit, también en db/esquema.sql, que siempre describe la base
// completa. Sin terminal, se pega en phpMyAdmin cada archivo pendiente y se anota en
// la tabla `migraciones`.

require __DIR__ . '/comun.php';

$pdo = conexion();
if (!$pdo->query("SHOW TABLES LIKE 'migraciones'")->fetchColumn()) {
    fwrite(STDERR, "La base no está instalada. Primero: php scripts/instalar.php\n");
    exit(1);
}
$aplicadas = $pdo->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);

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
        correrArchivoSql($pdo, $archivo);
        $pdo->prepare('INSERT INTO migraciones (nombre) VALUES (?)')->execute([$nombre]);
        echo "listo.\n";
    } catch (PDOException $e) {
        // Los cambios de estructura no se deshacen en MySQL: lo que alcanzó a
        // aplicarse se queda. Hay que revisar a mano antes de volver a intentar.
        echo "ERROR\n" . $e->getMessage() . "\n";
        exit(1);
    }
}

echo $pendientes ? "Base de datos al día.\n" : "No había cambios pendientes.\n";
