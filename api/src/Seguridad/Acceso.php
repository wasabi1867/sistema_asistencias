<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

/**
 * Quién puede usar una ruta. Mientras el instituto no defina los permisos se usa el
 * reparto más restrictivo que deja operar: todos consultan, el laboratorista además
 * atiende alumnos, equipos y clases de invitados, y solo el administrador maneja
 * cuentas y catálogos. Las reglas finas ("el docente de ese curso") se revisan dentro
 * de cada ruta. tests/api/PermisosTest.php fija la tabla completa.
 */
enum Acceso
{
    /** Sin sesión: el registro de entrada del alumno y lo que necesita su pantalla. */
    case Publica;
    /** Cualquier cuenta: administrador, laboratorista o docente. */
    case Sesion;
    /** Administrador o laboratorista: quienes atienden los laboratorios. */
    case Personal;
    case Admin;

    public function permite(?Usuario $usuario): bool
    {
        return match ($this) {
            self::Publica => true,
            self::Sesion => $usuario !== null,
            self::Personal => $usuario !== null && $usuario->esPersonal(),
            self::Admin => $usuario !== null && $usuario->rol === Rol::Admin,
        };
    }
}
