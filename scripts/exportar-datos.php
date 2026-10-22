<?php

declare(strict_types=1);

// Exporta los datos de la base (sin crear tablas) a un archivo .sql que se puede cargar
// en phpMyAdmin sobre una base creada con db/esquema.sql:
//
//   php scripts/exportar-datos.php respaldo-para-hostinger.sql
//
// Sirve para preparar la base en una computadora (instalar e importar de Firebase) y
// subirla a un servidor sin terminal. No se usa mysqldump porque el de MariaDB escribe
// los valores de las columnas calculadas y MySQL los rechaza al cargarlos; este archivo
// las omite y carga igual en los dos.
//
// El archivo tiene datos personales: no se sube al repositorio y se borra al terminar.

require __DIR__ . '/comun.php';

$archivo = $argv[1] ?? '';
if ($archivo === '') {
    fwrite(STDERR, "Uso: php scripts/exportar-datos.php ARCHIVO.sql\n");
    exit(1);
}

$pdo = conexion();
$salida = fopen($archivo, 'w');
fwrite($salida, '-- Datos exportados el ' . date('Y-m-d H:i') . " con scripts/exportar-datos.php\n"
    . "-- Se cargan sobre una base creada con db/esquema.sql.\n"
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

fwrite($salida, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
fclose($salida);
echo "$total renglones de " . count($tablas) . " tablas exportados a $archivo\n";
