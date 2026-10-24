<?php

declare(strict_types=1);

// Exporta los datos de la base (sin crear tablas) a un archivo .sql que se carga sobre
// una base creada con db/esquema.sql:
//
//   php scripts/exportar-datos.php respaldo.sql
//
// Sirve para los respaldos de la base de producción (se restauran con
// scripts/restaurar.php) y para preparar una base en una computadora y subirla por
// phpMyAdmin. No se usa mysqldump porque el de MariaDB escribe los valores de las
// columnas calculadas y MySQL los rechaza al cargarlos; este archivo las omite y carga
// igual en los dos.
//
// El encabezado anota las migraciones que tenía la base: el archivo solo carga sobre el
// esquema de esa misma versión.
//
// El archivo tiene datos personales: no se sube al repositorio.

require __DIR__ . '/comun.php';

$archivo = $argv[1] ?? '';
if ($archivo === '') {
    fwrite(STDERR, "Uso: php scripts/exportar-datos.php ARCHIVO.sql\n");
    exit(1);
}

$pdo = conexion();
// Todas las tablas se leen como estaban en un mismo instante, aunque alguien registre su
// entrada mientras se exporta. Hostinger no usa REPEATABLE READ por omisión, y MariaDB
// solo acepta la instantánea con ese nivel: se fija para esta transacción.
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
$migraciones = $pdo->query('SELECT nombre FROM migraciones ORDER BY nombre')->fetchAll(PDO::FETCH_COLUMN);
$salida = fopen($archivo, 'w');
fwrite($salida, '-- Datos exportados el ' . date('Y-m-d H:i') . " con scripts/exportar-datos.php\n"
    . "-- Se cargan sobre una base creada con db/esquema.sql.\n"
    . '-- Migraciones aplicadas: ' . ($migraciones === [] ? '(ninguna)' : implode(', ', $migraciones)) . "\n"
    . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n"
    . "SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_BACKSLASH_ESCAPES', '');\n\n");

$tablas = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")
    ->fetchAll(PDO::FETCH_COLUMN);
// Las columnas calculadas tienen generation_expression (vacía o NULL en las demás). No
// se usa `extra`: MySQL también pone "DEFAULT_GENERATED" en las que tienen
// DEFAULT CURRENT_TIMESTAMP.
$columnasDe = $pdo->prepare(
    "SELECT column_name FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = ? AND COALESCE(generation_expression, '') = ''
     ORDER BY ordinal_position"
);

$total = 0;
foreach ($tablas as $tabla) {
    $columnasDe->execute([$tabla]);
    $columnas = $columnasDe->fetchAll(PDO::FETCH_COLUMN);
    $lista = implode(', ', array_map(static fn (string $c) => "`$c`", $columnas));
    $filas = $pdo->query("SELECT $lista FROM `$tabla`");
    $lote = [];
    foreach ($filas->fetchAll(PDO::FETCH_NUM) as $fila) {
        $lote[] = '(' . implode(', ', array_map(static fn ($valor) => match (true) {
            $valor === null => 'NULL',
            is_int($valor), is_float($valor) => (string)$valor,
            default => $pdo->quote((string)$valor),
        }, $fila)) . ')';
        if (count($lote) === 200) {
            fwrite($salida, "INSERT INTO `$tabla` ($lista) VALUES\n" . implode(",\n", $lote) . ";\n");
            $total += count($lote);
            $lote = [];
        }
    }
    if ($lote !== []) {
        fwrite($salida, "INSERT INTO `$tabla` ($lista) VALUES\n" . implode(",\n", $lote) . ";\n");
        $total += count($lote);
    }
}

// La transacción se abrió con exec(): PDO no la conoce y su commit() fallaría.
$pdo->exec('COMMIT');
// scripts/restaurar.php revisa esta última línea: si falta, el archivo quedó cortado.
fwrite($salida, "\nSET FOREIGN_KEY_CHECKS = 1;\n-- Fin del respaldo: $total renglones de " . count($tablas) . " tablas.\n");
fclose($salida);
echo "$total renglones de " . count($tablas) . " tablas exportados a $archivo\n";
