<?php

declare(strict_types=1);

namespace Asistencias\Datos;

use DateTimeImmutable;

/**
 * Cómo salen en JSON las fechas y horas de la base. La base las guarda en hora de
 * Puerto Peñasco, sin zona; la respuesta las lleva con su desfase
 * (2026-10-21T14:05:00-07:00) para que el navegador las interprete bien esté donde
 * esté.
 */
final class Formato
{
    public static function fechaHora(?string $valor): ?string
    {
        return $valor === null ? null : (new DateTimeImmutable($valor))->format(DATE_ATOM);
    }

    /** "14:05:00" → "14:05" */
    public static function hora(?string $valor): ?string
    {
        return $valor === null ? null : substr($valor, 0, 5);
    }

    public static function booleano(mixed $valor): bool
    {
        return (bool)(int)$valor;
    }

    public static function enteroONulo(mixed $valor): ?int
    {
        return $valor === null ? null : (int)$valor;
    }
}
