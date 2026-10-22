<?php

declare(strict_types=1);

// Crea las tablas en una base vacía y carga los datos iniciales. Se corre una sola vez,
// con la conexión de api/config.local.php:
//
//   php scripts/instalar.php
//
// db/esquema.sql siempre está al día (incluye lo de todas las migraciones), así que
// las migraciones que ya existen solo se anotan como aplicadas.
//
// Sin terminal en el servidor: pegar en phpMyAdmin db/esquema.sql y luego
// db/datos-iniciales.sql (ver docs/cambio-de-firebase.md).

require __DIR__ . '/comun.php';

$pdo = conexion();
$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($tablas !== []) {
    fwrite(STDERR, 'La base ya tiene ' . count($tablas) . " tablas. Este script solo trabaja sobre una base vacía.\n"
        . "Para aplicar cambios a una base instalada: php scripts/migrar.php\n");
    exit(1);
}

echo "Creando las tablas... ";
correrArchivoSql($pdo, __DIR__ . '/../db/esquema.sql');
echo "listo.\nCargando los datos iniciales... ";
correrArchivoSql($pdo, __DIR__ . '/../db/datos-iniciales.sql');
echo "listo.\n";

$migraciones = glob(__DIR__ . '/../db/migraciones/*.sql');
$anotar = $pdo->prepare('INSERT INTO migraciones (nombre) VALUES (?)');
foreach ($migraciones as $migracion) {
    $anotar->execute([basename($migracion, '.sql')]);
}

echo "Base instalada. Falta crear el primer administrador:\n  php scripts/crear-admin.php correo@puertopenasco.tecnm.mx \"Nombre\" \"Apellido\"\n";
