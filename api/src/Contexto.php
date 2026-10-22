<?php

declare(strict_types=1);

namespace Asistencias;

use Asistencias\Auditoria\Bitacora;
use Asistencias\Auditoria\Cambios;
use Asistencias\Datos\BaseDeDatos;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Peticion;
use Asistencias\Seguridad\Sesion;
use Asistencias\Seguridad\Usuario;

/** Lo que necesita una ruta para atender una petición. */
final class Contexto
{
    public readonly Bitacora $bitacora;
    public readonly Cambios $cambios;
    private ?Usuario $usuario = null;
    private bool $usuarioLeido = false;

    public function __construct(
        public readonly Peticion $peticion,
        public readonly BaseDeDatos $bd,
        public readonly Configuracion $config,
        public readonly Sesion $sesion,
    ) {
        $this->bitacora = new Bitacora($bd, fn () => $this->usuario(), $peticion->ip);
        $this->cambios = new Cambios($bd, $this->bitacora);
    }

    /** La cuenta con sesión, o null. Una cuenta desactivada ya no tiene sesión. */
    public function usuario(): ?Usuario
    {
        if (!$this->usuarioLeido) {
            $this->usuarioLeido = true;
            $id = $this->sesion->idDeUsuario();
            if ($id !== null) {
                $fila = $this->bd->fila('SELECT * FROM usuarios WHERE id = ? AND activo', [$id]);
                if ($fila === null) {
                    $this->sesion->cerrar();
                } else {
                    $this->usuario = Usuario::desdeFila($fila);
                }
            }
        }
        return $this->usuario;
    }

    public function usuarioConSesion(): Usuario
    {
        return $this->usuario() ?? throw ErrorHttp::sinSesion();
    }

    /** Al iniciar o cerrar sesión, la cuenta de esta petición cambia. */
    public function cambiarUsuario(?Usuario $usuario): void
    {
        $this->usuario = $usuario;
        $this->usuarioLeido = true;
    }
}
