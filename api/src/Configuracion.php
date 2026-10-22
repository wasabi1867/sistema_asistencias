<?php

declare(strict_types=1);

namespace Asistencias;

/**
 * La configuración de la API. Los datos reales no van en git, porque el repositorio
 * es público. Se leen, en este orden:
 *   1. Variables de entorno (ASISTENCIAS_DB_HOST, ASISTENCIAS_DB_NAME...).
 *   2. api/config.local.php, que solo existe en el servidor y en cada máquina de
 *      desarrollo. Ver config.ejemplo.php.
 */
final class Configuracion
{
    /** @param list<string> $origenesPermitidos */
    public function __construct(
        public readonly string $dbHost,
        public readonly int $dbPuerto,
        public readonly string $dbNombre,
        public readonly string $dbUsuario,
        public readonly string $dbContrasena,
        public readonly array $origenesPermitidos = [],
        public readonly string $zonaHoraria = 'America/Hermosillo',
        public readonly bool $exigirCodigoDeClase = false,
    ) {
    }

    public static function cargar(?string $carpeta = null): self
    {
        $archivo = ($carpeta ?? dirname(__DIR__)) . '/config.local.php';
        $local = is_file($archivo) ? require $archivo : [];

        $leer = static function (string $clave, string $variable, mixed $porOmision) use ($local): mixed {
            $valor = getenv($variable);
            return $valor !== false ? $valor : ($local[$clave] ?? $porOmision);
        };

        return new self(
            // En Hostinger el host casi siempre es 'localhost'.
            dbHost: (string)$leer('db_host', 'ASISTENCIAS_DB_HOST', 'localhost'),
            dbPuerto: (int)$leer('db_port', 'ASISTENCIAS_DB_PORT', 3306),
            dbNombre: (string)$leer('db_name', 'ASISTENCIAS_DB_NAME', ''),
            dbUsuario: (string)$leer('db_user', 'ASISTENCIAS_DB_USER', ''),
            dbContrasena: (string)$leer('db_pass', 'ASISTENCIAS_DB_PASS', ''),
            // Sitios que pueden llamar a la API desde otro dominio, separados por comas.
            // El sitio publicado junto a la API no necesita estar aquí.
            origenesPermitidos: array_values(array_filter(array_map(
                'trim',
                explode(',', (string)$leer('origenes_permitidos', 'ASISTENCIAS_ORIGENES_PERMITIDOS', ''))
            ))),
            // Puerto Peñasco: UTC-7 todo el año. El servidor puede estar en otra zona.
            zonaHoraria: (string)$leer('zona_horaria', 'ASISTENCIAS_ZONA_HORARIA', 'America/Hermosillo'),
            exigirCodigoDeClase: filter_var(
                $leer('exigir_codigo_de_clase', 'ASISTENCIAS_EXIGIR_CODIGO_DE_CLASE', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        );
    }
}
