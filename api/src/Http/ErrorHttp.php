<?php

declare(strict_types=1);

namespace Asistencias\Http;

use RuntimeException;

/**
 * Algo que el cliente pidió y no se puede hacer: datos inválidos, sin permiso, no
 * existe, choca con una regla. Se lanza desde cualquier punto, incluso dentro de una
 * transacción, y llega al cliente con su estado HTTP y su mensaje en español, que las
 * pantallas muestran tal cual.
 */
class ErrorHttp extends RuntimeException
{
    /** @param array<string, mixed> $extra campos que se agregan a la respuesta, como `codigo` */
    public function __construct(public readonly int $estado, string $mensaje, public readonly array $extra = [])
    {
        parent::__construct($mensaje);
    }

    public static function datosInvalidos(string $mensaje, ?string $campo = null): self
    {
        return new self(400, $mensaje, $campo === null ? [] : ['campo' => $campo]);
    }

    public static function sinSesion(): self
    {
        return new self(401, 'Inicia sesión para continuar');
    }

    public static function sinPermiso(string $mensaje = 'No tienes permiso para esta acción'): self
    {
        return new self(403, $mensaje);
    }

    /** @param string $que con artículo: "El curso", "La clase" */
    public static function noExiste(string $que): self
    {
        return new self(404, "$que no existe");
    }

    /**
     * Choca con el estado actual: duplicado, equipo ocupado, clase cerrada... $codigo es
     * un identificador fijo para que las pantallas reaccionen sin leer el mensaje.
     */
    public static function conflicto(string $mensaje, ?string $codigo = null, array $extra = []): self
    {
        return new self(409, $mensaje, ($codigo === null ? [] : ['codigo' => $codigo]) + $extra);
    }
}
