<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

/** Los roles de las cuentas del personal. El alumno no tiene cuenta. */
enum Rol: string
{
    case Admin = 'admin';
    case Laboratorista = 'laboratorista';
    case Docente = 'docente';
}
