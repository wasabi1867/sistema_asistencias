<?php

declare(strict_types=1);

// Carga las clases de Asistencias\ desde src/ (Asistencias\Http\Peticion está en
// src/Http/Peticion.php). La API no usa paquetes de Composer: en Hostinger basta con
// subir los archivos.
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'Asistencias\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $archivo = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
