<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

use Asistencias\Datos\BaseDeDatos;

/**
 * Una cuenta del personal, como la ven las rutas (sin su contraseña). Una persona tiene
 * una sola cuenta, con uno o varios roles, y puede hacer lo de todos ellos.
 */
final class Usuario
{
    /**
     * @param list<Rol> $roles
     * @param array<int, string> $laboratorios los que atiende como laboratorista: id => clave
     */
    public function __construct(
        public readonly int $id,
        public readonly array $roles,
        public readonly ?string $matricula,
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly ?string $email,
        public readonly ?string $departamento,
        public readonly array $laboratorios = [],
    ) {
    }

    /** @param array<string, mixed> $fila un renglón de la tabla usuarios */
    public static function desdeFila(array $fila, array $laboratorios = []): self
    {
        return new self(
            (int)$fila['id'],
            self::rolesDe($fila['roles']),
            $fila['matricula'],
            $fila['nombre'],
            $fila['apellido'],
            $fila['email'],
            $fila['departamento'],
            $laboratorios,
        );
    }

    /** La cuenta con los laboratorios que atiende, leídos de la base. */
    public static function cargar(BaseDeDatos $bd, array $fila): self
    {
        $laboratorios = [];
        foreach ($bd->filas('SELECT l.id, l.clave FROM laboratorios_del_personal lp JOIN laboratorios l ON l.id = lp.laboratorio_id WHERE lp.usuario_id = ? ORDER BY l.nombre', [$fila['id']]) as $l) {
            $laboratorios[(int)$l['id']] = $l['clave'];
        }
        return self::desdeFila($fila, $laboratorios);
    }

    public function nombreCompleto(): string
    {
        return trim("$this->nombre $this->apellido");
    }

    /** @return list<Rol> los roles de la columna `roles` ("admin,docente"), en el orden de Rol */
    public static function rolesDe(string $roles): array
    {
        $nombres = explode(',', $roles);
        return array_values(array_filter(Rol::cases(), static fn (Rol $r) => in_array($r->value, $nombres, true)));
    }

    public function tiene(Rol $rol): bool
    {
        return in_array($rol, $this->roles, true);
    }

    public function esPersonal(): bool
    {
        return $this->tiene(Rol::Admin) || $this->tiene(Rol::Laboratorista);
    }

    public function esAdmin(): bool
    {
        return $this->tiene(Rol::Admin);
    }

    public function esDocente(): bool
    {
        return $this->tiene(Rol::Docente);
    }

    /**
     * Si atiende ese laboratorio como personal: el administrador, todos; el
     * laboratorista, los que le asignó el administrador (D21).
     */
    public function atiende(int $laboratorioId): bool
    {
        return $this->esAdmin() || ($this->tiene(Rol::Laboratorista) && isset($this->laboratorios[$laboratorioId]));
    }

    /** Los roles como van en la columna `roles` y en la bitácora: "admin,docente". */
    public function rolesComoTexto(): string
    {
        return implode(',', array_map(static fn (Rol $r) => $r->value, $this->roles));
    }

    /** @return array<string, mixed> */
    public function comoJson(): array
    {
        return [
            'id' => $this->id,
            'roles' => array_map(static fn (Rol $r) => $r->value, $this->roles),
            'matricula' => $this->matricula,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'email' => $this->email,
            'departamento' => $this->departamento,
            'laboratorios' => array_values($this->laboratorios),
        ];
    }
}
