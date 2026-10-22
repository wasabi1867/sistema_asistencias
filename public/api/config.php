<?php
// ==============================================================
// CONFIGURACIÓN DE LA API
// ==============================================================
// Los datos reales de conexión NO se escriben aquí: este archivo está en git y el
// repositorio es público. Se leen, en este orden:
//   1. Variables de entorno (ASISTENCIAS_DB_HOST, ASISTENCIAS_DB_NAME, ...).
//   2. config.local.php, junto a este archivo. Solo existe en el servidor y en cada
//      máquina de desarrollo; git lo ignora. Ver config.ejemplo.php.

$configLocal = is_file(__DIR__ . '/config.local.php') ? require __DIR__ . '/config.local.php' : [];

function leerConfiguracion(array $local, string $clave, string $variable, $porOmision) {
    $valor = getenv($variable);
    if ($valor !== false) {
        return $valor;
    }
    return $local[$clave] ?? $porOmision;
}

// En Hostinger, el host casi siempre es 'localhost'
define('DB_HOST', leerConfiguracion($configLocal, 'db_host', 'ASISTENCIAS_DB_HOST', 'localhost'));
define('DB_PORT', (int)leerConfiguracion($configLocal, 'db_port', 'ASISTENCIAS_DB_PORT', 3306));
define('DB_NAME', leerConfiguracion($configLocal, 'db_name', 'ASISTENCIAS_DB_NAME', ''));
define('DB_USER', leerConfiguracion($configLocal, 'db_user', 'ASISTENCIAS_DB_USER', ''));
define('DB_PASS', leerConfiguracion($configLocal, 'db_pass', 'ASISTENCIAS_DB_PASS', ''));

// Sitios que pueden llamar a la API desde otro dominio, separados por comas.
// El frontend publicado en el mismo dominio no necesita estar aquí.
$origenes = leerConfiguracion($configLocal, 'origenes_permitidos', 'ASISTENCIAS_ORIGENES_PERMITIDOS', '');
define('ORIGENES_PERMITIDOS', array_values(array_filter(array_map('trim', explode(',', $origenes)))));

unset($configLocal, $origenes);
