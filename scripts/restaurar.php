<?php

declare(strict_types=1);

// Restaura un respaldo hecho con scripts/exportar-datos.php en una base vacía: crea las
// tablas con db/esquema.sql y carga los datos. Se corre en una terminal, con la conexión
// de api/config.local.php o de las variables ASISTENCIAS_DB_*:
//
//   php scripts/restaurar.php respaldo.sql
//
// Antes de tocar la base revisa que el archivo esté completo y que sea de la misma
// versión del esquema (las mismas migraciones). Nunca escribe sobre una base con tablas:
// para restaurar producción, primero se vacía la base a mano (ver
// docs/cambio-de-firebase.md, "Respaldos").

require __DIR__ . '/comun.php';

$archivo = $argv[1] ?? '';
if ($archivo === '' || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php scripts/restaurar.php RESPALDO.sql\n");
    exit(1);
}

$contenido = (string)file_get_contents($archivo);
if (!preg_match('/^-- Migraciones aplicadas: (.*)$/m', $contenido, $encabezado)) {
    fwrite(STDERR, "$archivo no es un respaldo de scripts/exportar-datos.php: le falta la línea de las migraciones.\n");
    exit(1);
}
if (!preg_match('/^-- Fin del respaldo: (\d+) renglones de (\d+) tablas\.\s*$/m', $contenido, $fin)) {
    fwrite(STDERR, "$archivo está incompleto: le falta la línea final \"-- Fin del respaldo\". No se restauró nada.\n");
    exit(1);
}

$delRespaldo = trim($encabezado[1]) === '(ninguna)' ? [] : array_map('trim', explode(',', $encabezado[1]));
$delRepositorio = array_map(static fn (string $f) => basename($f, '.sql'), glob(__DIR__ . '/../db/migraciones/*.sql'));
sort($delRespaldo);
sort($delRepositorio);
if ($delRespaldo !== $delRepositorio) {
    $lista = static fn (array $m) => $m === [] ? '(ninguna)' : implode(', ', $m);
    fwrite(STDERR, "El respaldo es de otra versión del esquema.\n"
        . '  Migraciones del respaldo:    ' . $lista($delRespaldo) . "\n"
        . '  Migraciones del repositorio: ' . $lista($delRepositorio) . "\n"
        . "Restáuralo desde un commit cuyo db/migraciones/ tenga exactamente las del respaldo,\n"
        . "y luego, ya en la versión actual, corre php scripts/migrar.php.\n");
    exit(1);
}

$pdo = conexion();
$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if ($tablas !== []) {
    fwrite(STDERR, 'La base ya tiene ' . count($tablas) . " tablas. Este script solo restaura en una base vacía.\n");
    exit(1);
}

echo "Creando las tablas... ";
correrArchivoSql($pdo, __DIR__ . '/../db/esquema.sql');
echo "listo.\nCargando los datos... ";
// Los datos van en una transacción: si un renglón falla, las tablas quedan vacías y no a
// medias. Las tablas sí se quedan (MySQL no deshace su creación).
$pdo->beginTransaction();
try {
    correrArchivoSql($pdo, $archivo);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "ERROR\n" . $e->getMessage() . "\nLas tablas quedaron creadas pero vacías: bórralas antes de volver a intentar.\n";
    exit(1);
}
echo "listo.\n";

$cargados = 0;
foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN) as $tabla) {
    $cargados += (int)$pdo->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
}
if ($cargados !== (int)$fin[1]) {
    echo "AVISO: el respaldo dice {$fin[1]} renglones y se cargaron $cargados. Revisa antes de usar esta base.\n";
    exit(1);
}
echo "Restaurados $cargados renglones de {$fin[2]} tablas.\n";
