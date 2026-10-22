<?php

declare(strict_types=1);

namespace Asistencias\Http;

use DateTimeImmutable;

/**
 * Los datos que manda el cliente (cuerpo JSON o parámetros de la dirección), con
 * métodos que los leen ya validados. Si un dato no sirve, responde 400 con un mensaje
 * que nombra el campo, para que la pantalla lo muestre junto a él.
 *
 * Las etiquetas llevan artículo ("el nombre", "la matrícula"); los mensajes se arman
 * para que suenen bien con cualquiera: "Falta la matrícula", "Revisa el semestre: ...".
 */
final class Entrada
{
    /** @param array<string, mixed> $datos */
    public function __construct(private readonly array $datos)
    {
    }

    /** Si el campo vino, aunque sea vacío. Sirve para cambios parciales. */
    public function tiene(string $campo): bool
    {
        return array_key_exists($campo, $this->datos);
    }

    public function crudo(string $campo): mixed
    {
        return $this->datos[$campo] ?? null;
    }

    /** Texto sin espacios en los extremos. Vacío cuenta como que no vino. */
    public function texto(string $campo, string $etiqueta, int $maximo = 255, bool $obligatorio = true): ?string
    {
        $valor = $this->datos[$campo] ?? null;
        if ($valor !== null && !is_scalar($valor)) {
            throw ErrorHttp::datosInvalidos("Revisa $etiqueta: debe ser texto", $campo);
        }
        $texto = trim((string)$valor);
        if ($texto === '') {
            if ($obligatorio) {
                throw ErrorHttp::datosInvalidos("Falta $etiqueta", $campo);
            }
            return null;
        }
        if (mb_strlen($texto) > $maximo) {
            throw ErrorHttp::datosInvalidos("Escribe $etiqueta con $maximo caracteres o menos", $campo);
        }
        return $texto;
    }

    public function entero(string $campo, string $etiqueta, ?int $minimo = null, ?int $maximo = null, bool $obligatorio = true): ?int
    {
        $valor = $this->datos[$campo] ?? null;
        if ($valor === null || $valor === '') {
            if ($obligatorio) {
                throw ErrorHttp::datosInvalidos("Falta $etiqueta", $campo);
            }
            return null;
        }
        $numero = filter_var($valor, FILTER_VALIDATE_INT);
        if ($numero === false || is_bool($valor)) {
            throw ErrorHttp::datosInvalidos("Revisa $etiqueta: debe ser un número entero", $campo);
        }
        if (($minimo !== null && $numero < $minimo) || ($maximo !== null && $numero > $maximo)) {
            $rango = match (true) {
                $minimo !== null && $maximo !== null => "entre $minimo y $maximo",
                $minimo !== null => "de $minimo o más",
                default => "de $maximo o menos",
            };
            throw ErrorHttp::datosInvalidos("Revisa $etiqueta: debe ser $rango", $campo);
        }
        return $numero;
    }

    /** Acepta true/false, 1/0 y "true"/"false" (los parámetros de la dirección son texto). */
    public function booleano(string $campo, bool $porOmision = false): bool
    {
        $valor = $this->datos[$campo] ?? null;
        if ($valor === null || $valor === '') {
            return $porOmision;
        }
        $booleano = filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($booleano === null) {
            throw ErrorHttp::datosInvalidos("Revisa $campo: debe ser verdadero o falso", $campo);
        }
        return $booleano;
    }

    /** Fecha como 2026-10-21. */
    public function fecha(string $campo, string $etiqueta, bool $obligatorio = true): ?string
    {
        $texto = $this->texto($campo, $etiqueta, 10, $obligatorio);
        if ($texto === null) {
            return null;
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
            throw ErrorHttp::datosInvalidos("Revisa $etiqueta: debe ser una fecha como 2026-10-21", $campo);
        }
        return $texto;
    }

    /** Hora como 14:05 o 14:05:00. Devuelve 14:05:00. */
    public function hora(string $campo, string $etiqueta, bool $obligatorio = true): ?string
    {
        $texto = $this->texto($campo, $etiqueta, 8, $obligatorio);
        if ($texto === null) {
            return null;
        }
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $texto, $m)) {
            throw ErrorHttp::datosInvalidos("Revisa $etiqueta: debe ser una hora como 14:05", $campo);
        }
        return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
    }

    /** @param list<string> $opciones */
    public function opcion(string $campo, string $etiqueta, array $opciones, bool $obligatorio = true): ?string
    {
        $texto = $this->texto($campo, $etiqueta, 100, $obligatorio);
        if ($texto !== null && !in_array($texto, $opciones, true)) {
            throw ErrorHttp::datosInvalidos("Revisa $etiqueta: debe ser " . implode(', ', $opciones), $campo);
        }
        return $texto;
    }

    /** @return list<mixed> */
    public function lista(string $campo, string $etiqueta, int $maximo): array
    {
        $valor = $this->datos[$campo] ?? null;
        if (!is_array($valor) || !array_is_list($valor)) {
            throw ErrorHttp::datosInvalidos("Falta $etiqueta", $campo);
        }
        if (count($valor) > $maximo) {
            throw ErrorHttp::datosInvalidos("Manda $etiqueta con $maximo elementos o menos", $campo);
        }
        return $valor;
    }
}
