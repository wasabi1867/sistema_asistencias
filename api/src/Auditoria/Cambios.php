<?php

declare(strict_types=1);

namespace Asistencias\Auditoria;

use Asistencias\Datos\BaseDeDatos;
use Asistencias\Http\ErrorHttp;

/**
 * Altas, cambios y bajas que quedan en la bitácora desde un solo lugar: ninguna ruta
 * tiene que acordarse de anotarlos. Se anotan los nombres de los campos que
 * cambiaron, no sus valores, para que la bitácora nunca guarde contraseñas ni datos
 * personales.
 *
 * Las asistencias no pasan por aquí: cada una ya es su propio registro.
 */
final class Cambios
{
    public function __construct(private readonly BaseDeDatos $bd, private readonly Bitacora $bitacora)
    {
    }

    /**
     * @param array<string, mixed> $valores
     * @param string|null $descripcion lo que identifica al registro para una persona,
     *                                 como la matrícula o la etiqueta del equipo
     */
    public function crear(string $tabla, array $valores, ?string $descripcion = null, ?int $laboratorioId = null): int
    {
        $id = $this->bd->insertar($tabla, $valores);
        $this->bitacora->registrar('crear', $tabla, $id, $descripcion, $laboratorioId);
        return $id;
    }

    /**
     * Guarda solo los campos que cambiaron y devuelve sus nombres (vacío si no cambió
     * nada, y entonces no se anota).
     *
     * @param array<string, mixed> $valores
     * @return list<string>
     */
    public function actualizar(string $tabla, int $id, array $valores, ?string $descripcion = null, ?int $laboratorioId = null): array
    {
        $actual = $this->bd->fila("SELECT * FROM `$tabla` WHERE id = ? FOR UPDATE", [$id]);
        if ($actual === null) {
            throw ErrorHttp::noExiste('El registro');
        }
        $cambiados = array_filter(
            $valores,
            static fn (mixed $valor, string $columna) => !self::iguales($actual[$columna] ?? null, $valor),
            ARRAY_FILTER_USE_BOTH
        );
        if ($cambiados === []) {
            return [];
        }

        $asignaciones = implode(', ', array_map(static fn (string $c) => "`$c` = ?", array_keys($cambiados)));
        $this->bd->ejecutar("UPDATE `$tabla` SET $asignaciones WHERE id = ?", [...array_values($cambiados), $id]);

        $campos = array_keys($cambiados);
        $detalles = ($descripcion !== null ? "$descripcion. " : '') . 'Campos: ' . implode(', ', $campos);
        $this->bitacora->registrar('editar', $tabla, $id, $detalles, $laboratorioId);
        return $campos;
    }

    public function eliminar(string $tabla, int $id, ?string $descripcion = null, ?int $laboratorioId = null): void
    {
        if ($this->bd->ejecutar("DELETE FROM `$tabla` WHERE id = ?", [$id]) === 0) {
            throw ErrorHttp::noExiste('El registro');
        }
        $this->bitacora->registrar('eliminar', $tabla, $id, $descripcion, $laboratorioId);
    }

    private static function iguales(mixed $guardado, mixed $nuevo): bool
    {
        if ($guardado === null || $nuevo === null) {
            return $guardado === $nuevo;
        }
        if (is_bool($nuevo)) {
            $nuevo = (int)$nuevo;
        }
        return (string)$guardado === (string)$nuevo;
    }
}
