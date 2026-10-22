<?php

require __DIR__ . '/../../vendor/autoload.php';

Tests\Api\Entorno::iniciar();
register_shutdown_function([Tests\Api\Entorno::class, 'terminar']);
