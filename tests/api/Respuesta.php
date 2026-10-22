<?php

namespace Tests\Api;

final class Respuesta
{
    /** @param array<string, list<string>> $encabezados nombres en minúsculas */
    public function __construct(
        public readonly int $estado,
        public readonly array $encabezados,
        public readonly string $texto,
    ) {
    }

    public function json(): array
    {
        $datos = json_decode($this->texto, true);
        return is_array($datos) ? $datos : [];
    }

    public function encabezado(string $nombre): ?string
    {
        return $this->encabezados[strtolower($nombre)][0] ?? null;
    }

    /** Estado y cuerpo, para que el mensaje de una prueba fallida diga qué respondió la API. */
    public function __toString(): string
    {
        return "HTTP {$this->estado}: {$this->texto}";
    }
}
