<?php

require __DIR__ . '/../../vendor/autoload.php';

// Las pruebas calculan "hoy" con date(), y la API guarda en hora de Puerto Peñasco. Con
// PHP en UTC (la integración continua, y muchas computadoras), de 5 p.m. a medianoche
// ya es el día siguiente y fallaban los filtros por fecha.
date_default_timezone_set('America/Hermosillo');

Tests\Api\Entorno::iniciar();
register_shutdown_function([Tests\Api\Entorno::class, 'terminar']);
