<?php

declare(strict_types=1);

// Lo que comparten los scripts: la configuración y la conexión de la API, y una forma
// segura de correr archivos .sql. Los scripts se corren en una terminal, nunca desde
// el navegador (por eso viven fuera de api/).

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../api/src/autocarga.php';

function conexion(): PDO
{
    $config = Asistencias\Configuracion::cargar(__DIR__ . '/../api');
    date_default_timezone_set($config->zonaHoraria);
    return (new Asistencias\Datos\BaseDeDatos($config))->pdo();
}

/**
 * Las sentencias de un archivo .sql, una por una. Se corren por separado porque
 * PDO::exec() con varias juntas puede callar el error de una sentencia intermedia y
 * dejar la base a medias sin avisar. Cada sentencia termina con ";" al final de una
 * línea, como en los archivos de db/.
 *
 * @return list<string>
 */
function sentenciasSql(string $archivo): array
{
    $sinComentarios = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($archivo));
    return array_values(array_filter(
        array_map('trim', preg_split('/;\s*$/m', $sinComentarios)),
        static fn (string $sentencia) => $sentencia !== ''
    ));
}

function correrArchivoSql(PDO $pdo, string $archivo): void
{
    foreach (sentenciasSql($archivo) as $sentencia) {
        $pdo->exec($sentencia);
    }
}
