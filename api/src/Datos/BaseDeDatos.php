<?php

declare(strict_types=1);

namespace Asistencias\Datos;

use Asistencias\Configuracion;
use DateTimeImmutable;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * La conexión a MySQL o MariaDB. Se conecta hasta la primera consulta, así una
 * petición que no la necesita no la abre. Todas las consultas van con parámetros: no
 * se pega nunca un dato del cliente en el SQL.
 */
final class BaseDeDatos
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Configuracion $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->config->dbHost, $this->config->dbPuerto, $this->config->dbNombre),
                $this->config->dbUsuario,
                $this->config->dbContrasena,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Consultas preparadas en el servidor: los enteros llegan como enteros.
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            // Que NOW() y CURRENT_TIMESTAMP den la hora de Puerto Peñasco aunque el
            // servidor esté en otra zona. Con desfase y no con nombre, porque las tablas
            // de zonas horarias de MySQL no siempre están cargadas.
            $desfase = (new DateTimeImmutable('now', new \DateTimeZone($this->config->zonaHoraria)))->format('P');
            $this->pdo->exec("SET time_zone = '$desfase'");
        }
        return $this->pdo;
    }

    /** @return list<array<string, mixed>> */
    public function filas(string $sql, array $parametros = []): array
    {
        return $this->correr($sql, $parametros)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function fila(string $sql, array $parametros = []): ?array
    {
        $fila = $this->correr($sql, $parametros)->fetch();
        return $fila === false ? null : $fila;
    }

    /** La primera columna del primer renglón, o null si no hay renglones. */
    public function valor(string $sql, array $parametros = []): mixed
    {
        $valor = $this->correr($sql, $parametros)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    /** @return list<mixed> la primera columna de cada renglón */
    public function columna(string $sql, array $parametros = []): array
    {
        return $this->correr($sql, $parametros)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Corre un INSERT, UPDATE o DELETE y devuelve cuántos renglones cambió. */
    public function ejecutar(string $sql, array $parametros = []): int
    {
        return $this->correr($sql, $parametros)->rowCount();
    }

    /**
     * Inserta un renglón y devuelve su id.
     * @param array<string, mixed> $valores columna => valor
     */
    public function insertar(string $tabla, array $valores): int
    {
        $columnas = implode(', ', array_map(static fn (string $c) => "`$c`", array_keys($valores)));
        $marcas = implode(', ', array_fill(0, count($valores), '?'));
        $this->correr("INSERT INTO `$tabla` ($columnas) VALUES ($marcas)", array_values($valores));
        return (int)$this->pdo()->lastInsertId();
    }

    /**
     * Corre $trabajo en una transacción: o se guarda todo o nada. Dentro de otra
     * transacción, el trabajo se vuelve parte de la de afuera.
     *
     * @template T
     * @param callable(): T $trabajo
     * @return T
     */
    public function transaccion(callable $trabajo): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            return $trabajo();
        }
        $pdo->beginTransaction();
        try {
            $resultado = $trabajo();
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function enTransaccion(): bool
    {
        return $this->pdo !== null && $this->pdo->inTransaction();
    }

    public function conectada(): bool
    {
        return $this->pdo !== null;
    }

    private function correr(string $sql, array $parametros): PDOStatement
    {
        try {
            $sentencia = $this->pdo()->prepare($sql);
            foreach (array_values($parametros) as $i => $valor) {
                $sentencia->bindValue($i + 1, is_bool($valor) ? (int)$valor : $valor, match (true) {
                    is_int($valor), is_bool($valor) => PDO::PARAM_INT,
                    $valor === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                });
            }
            $sentencia->execute();
            return $sentencia;
        } catch (PDOException $e) {
            throw ErrorDeIntegridad::desde($e) ?? $e;
        }
    }
}
