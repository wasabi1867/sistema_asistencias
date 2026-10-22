<?php

declare(strict_types=1);

// Punto de entrada de la API: toda petición a api/ llega aquí (ver .htaccess). En
// desarrollo: php -S localhost:8000 api/index.php

require __DIR__ . '/src/autocarga.php';

Asistencias\Aplicacion::crear(Asistencias\Configuracion::cargar(__DIR__))
    ->atender(Asistencias\Http\Peticion::desdeElServidor());
