<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

/** Una cuenta del personal, como la ven las rutas (sin su contraseña). */
final class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly Rol $rol,
        public readonly ?string $matricula,
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly ?string $email,
        public readonly ?string $departamento,
    ) {
    }

    /** @param array<string, mixed> $fila un renglón de la tabla usuarios */
    public static function desdeFila(array $fila): self
    {
        return new self(
            (int)$fila['id'],
            Rol::from($fila['rol']),
            $fila['matricula'],
            $fila['nombre'],
            $fila['apellido'],
            $fila['email'],
            $fila['departamento'],
        );
    }

    public function nombreCompleto(): string
    {
        return trim("$this->nombre $this->apellido");
    }

    public function esPersonal(): bool
    {
        return $this->rol === Rol::Admin || $this->rol === Rol::Laboratorista;
    }

    public function esAdmin(): bool
    {
        return $this->rol === Rol::Admin;
    }

    /** @return array<string, mixed> */
    public function comoJson(): array
    {
        return [
            'id' => $this->id,
            'rol' => $this->rol->value,
            'matricula' => $this->matricula,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'departamento' => $this->departamento,
        ];
    }
}
