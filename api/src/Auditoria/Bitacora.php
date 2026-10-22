<?php

declare(strict_types=1);

namespace Asistencias\Auditoria;

use Asistencias\Datos\BaseDeDatos;
use Asistencias\Seguridad\Usuario;
use Closure;

/**
 * La bitácora: quién hizo qué, cuándo y desde dónde. La llena la API, nunca el
 * navegador, así que no se puede omitir ni inventar una entrada. Solo se inserta.
 */
final class Bitacora
{
    /** @var list<array<string, mixed>> */
    private array $aunqueFalle = [];

    /** @param Closure(): ?Usuario $usuario quién tiene la sesión en esta petición */
    public function __construct(
        private readonly BaseDeDatos $bd,
        private readonly Closure $usuario,
        private readonly string $ip,
    ) {
    }

    /**
     * Anota una acción dentro de la transacción de la petición: si la petición falla,
     * la entrada se deshace junto con lo demás.
     *
     * @param string|null $quien en lugar del usuario de la sesión; por ejemplo, lo
     *                           que se escribió en un intento fallido de iniciar sesión
     */
    public function registrar(
        string $accion,
        ?string $entidad = null,
        int|string|null $entidadId = null,
        ?string $detalles = null,
        ?int $laboratorioId = null,
        ?string $quien = null,
    ): void {
        $this->bd->insertar('bitacora', $this->renglon($accion, $entidad, $entidadId, $detalles, $laboratorioId, $quien));
    }

    /**
     * Para lo que debe quedar aunque la petición termine en error, como un intento
     * fallido de iniciar sesión: se escribe al final, fuera de la transacción.
     */
    public function registrarAunqueFalle(
        string $accion,
        ?string $detalles = null,
        ?int $laboratorioId = null,
        ?string $quien = null,
    ): void {
        $this->aunqueFalle[] = $this->renglon($accion, null, null, $detalles, $laboratorioId, $quien);
    }

    public function escribirPendientes(): void
    {
        foreach ($this->aunqueFalle as $renglon) {
            $this->bd->insertar('bitacora', $renglon);
        }
        $this->aunqueFalle = [];
    }

    /** @return array<string, mixed> */
    private function renglon(string $accion, ?string $entidad, int|string|null $entidadId, ?string $detalles, ?int $laboratorioId, ?string $quien): array
    {
        $usuario = ($this->usuario)();
        return [
            'usuario_id' => $usuario?->id,
            'usuario' => mb_substr($quien ?? $usuario?->nombreCompleto() ?? 'Sin sesión', 0, 150),
            'rol' => $usuario?->rol->value,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId === null ? null : (string)$entidadId,
            'detalles' => $detalles,
            'laboratorio_id' => $laboratorioId,
            'ip' => $this->ip !== '' ? $this->ip : null,
        ];
    }
}
