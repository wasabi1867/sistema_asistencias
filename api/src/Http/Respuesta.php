<?php

declare(strict_types=1);

namespace Asistencias\Http;

/**
 * Lo que devuelve una ruta cuando no basta con los datos y el 200: creado (201) o
 * nada que devolver (204). Una ruta que devuelve un arreglo responde 200 con él.
 */
final class Respuesta
{
    private function __construct(public readonly int $estado, public readonly mixed $datos)
    {
    }

    public static function creado(mixed $datos): self
    {
        return new self(201, $datos);
    }

    public static function vacia(): self
    {
        return new self(204, null);
    }
}
