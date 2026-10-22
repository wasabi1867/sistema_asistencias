<?php

declare(strict_types=1);

namespace Asistencias\Http;

use Asistencias\Seguridad\Acceso;
use Closure;

final class Ruta
{
    public function __construct(
        public readonly string $metodo,
        public readonly string $forma,
        public readonly string $patron,
        public readonly Acceso $acceso,
        public readonly Closure $accion,
    ) {
    }
}
