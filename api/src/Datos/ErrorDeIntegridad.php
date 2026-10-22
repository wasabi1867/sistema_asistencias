<?php

declare(strict_types=1);

namespace Asistencias\Datos;

use Asistencias\Http\ErrorHttp;
use PDOException;

/**
 * La base rechazó un cambio por una de sus reglas: un duplicado, un registro del que
 * dependen otros, una referencia a algo que no existe o un CHECK. Llega al cliente con
 * un mensaje general; quien conozca la regla rota (`restriccion`) puede atraparlo y
 * dar uno más preciso, como "el equipo ya está ocupado".
 */
final class ErrorDeIntegridad extends ErrorHttp
{
    private const MENSAJES = [
        'duplicado' => [409, 'Ya existe un registro con esos datos'],
        'referenciado' => [409, 'No se puede eliminar porque otros registros dependen de él. Desactívalo en su lugar'],
        'sin_referencia' => [400, 'Uno de los registros relacionados no existe'],
        'regla' => [400, 'Los datos no cumplen las reglas del sistema'],
    ];

    private function __construct(public readonly string $tipo, public readonly string $restriccion)
    {
        [$estado, $mensaje] = self::MENSAJES[$tipo];
        parent::__construct($estado, $mensaje);
    }

    /** El error de integridad que corresponde a la excepción de PDO, o null si es otra cosa. */
    public static function desde(PDOException $e): ?self
    {
        $codigo = (int)($e->errorInfo[1] ?? 0);
        $tipo = match ($codigo) {
            1062 => 'duplicado',
            1451, 1217 => 'referenciado',
            1452, 1216 => 'sin_referencia',
            3819, 4025 => 'regla', // CHECK: 3819 en MySQL, 4025 en MariaDB
            default => null,
        };
        if ($tipo === null) {
            return null;
        }
        // El nombre de la regla viene en el mensaje, con formato distinto en MySQL y
        // en MariaDB: "for key 'tabla.uq_x'", "for key 'uq_x'", "CONSTRAINT `fk_x`",
        // "Check constraint 'ck_x'".
        $mensaje = (string)($e->errorInfo[2] ?? $e->getMessage());
        preg_match("/(?:for key '(?:[^.']+\\.)?|CONSTRAINT [`']|constraint ')([A-Za-z0-9_]+)/i", $mensaje, $m);
        return new self($tipo, $m[1] ?? '');
    }

    /**
     * El mismo error con un mensaje más preciso si la regla rota está en $mensajes.
     * @param array<string, string> $mensajes nombre de la regla => mensaje
     */
    public function explicado(array $mensajes): ErrorHttp
    {
        return isset($mensajes[$this->restriccion]) ? new ErrorHttp($this->estado, $mensajes[$this->restriccion]) : $this;
    }

    /**
     * Corre $trabajo y, si la base rechaza el cambio por una de las reglas de
     * $mensajes, responde con ese mensaje.
     *
     * @template T
     * @param array<string, string> $mensajes
     * @param callable(): T $trabajo
     * @return T
     */
    public static function explicar(array $mensajes, callable $trabajo): mixed
    {
        try {
            return $trabajo();
        } catch (ErrorDeIntegridad $e) {
            throw $e->explicado($mensajes);
        }
    }
}
