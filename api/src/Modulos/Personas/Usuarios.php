<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Personas;

use Asistencias\Contexto;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Http\Enrutador;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Contrasenas;

/**
 * Las cuentas del personal. Las maneja el administrador; los demás solo ven la lista
 * de docentes y laboratoristas activos (para elegir al docente de un curso, por
 * ejemplo). Una cuenta con historial no se borra: se desactiva.
 */
final class Usuarios
{
    private const ROLES = ['admin', 'laboratorista', 'docente'];

    private const DUPLICADOS = [
        'uq_usuarios_matricula' => 'Ya hay una cuenta de ese rol con esa matrícula',
        'uq_usuarios_email' => 'Ya hay una cuenta de ese rol con ese correo',
        'ck_usuarios_identificacion' => 'La cuenta necesita matrícula o correo: con uno de los dos se inicia sesión',
    ];

    public static function registrar(Enrutador $r): void
    {
        $r->get('/usuarios', Acceso::Sesion, static fn (Contexto $c) => self::listar($c));
        $r->get('/usuarios/{id}', Acceso::Admin, static fn (Contexto $c) => self::uno($c, $c->peticion->id()));
        $r->post('/usuarios', Acceso::Admin, static fn (Contexto $c) => self::crear($c));
        $r->put('/usuarios/{id}', Acceso::Admin, static fn (Contexto $c) => self::editar($c));
        $r->put('/usuarios/{id}/contrasena', Acceso::Admin, static fn (Contexto $c) => self::restablecerContrasena($c));
        $r->delete('/usuarios/{id}', Acceso::Admin, static fn (Contexto $c) => self::eliminar($c));
    }

    private static function formato(array $fila): array
    {
        return [
            'id' => $fila['id'],
            'rol' => $fila['rol'],
            'matricula' => $fila['matricula'],
            'nombre' => $fila['nombre'],
            'apellido' => $fila['apellido'],
            'email' => $fila['email'],
            'departamento' => $fila['departamento'],
            'activo' => (bool)$fila['activo'],
        ];
    }

    private static function uno(Contexto $c, int $id): array
    {
        return self::formato($c->bd->fila('SELECT * FROM usuarios WHERE id = ?', [$id]) ?? throw ErrorHttp::noExiste('La cuenta'));
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $rol = $q->opcion('rol', 'el rol', self::ROLES, false);
        $condiciones = [];
        $parametros = [];
        if ($rol !== null) {
            $condiciones[] = 'rol = ?';
            $parametros[] = $rol;
        }
        if (!$c->usuarioConSesion()->esAdmin()) {
            $condiciones[] = "rol IN ('docente', 'laboratorista') AND activo";
        } elseif ($q->tiene('activo')) {
            $condiciones[] = 'activo = ?';
            $parametros[] = $q->booleano('activo');
        }
        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return array_map(self::formato(...), $c->bd->filas("SELECT * FROM usuarios$donde ORDER BY apellido, nombre", $parametros));
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $valores = ['rol' => $e->opcion('rol', 'el rol', self::ROLES)] + self::datos($e, nueva: true);
        $valores['password_hash'] = Contrasenas::cifrarNueva((string)$e->crudo('contrasena'));
        $id = ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear('usuarios', $valores, self::descripcion($valores)));
        return Respuesta::creado(self::uno($c, $id));
    }

    private static function editar(Contexto $c): array
    {
        $id = $c->peticion->id();
        $actual = self::uno($c, $id);
        $e = $c->peticion->cuerpo();
        $valores = self::datos($e, nueva: false);
        if ($e->tiene('activo')) {
            $valores['activo'] = $e->booleano('activo');
            if (!$valores['activo']) {
                self::revisarQueNoSeQuedenSinAdministrador($c, $id, $actual['rol']);
            }
        }
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('usuarios', $id, $valores, self::descripcion($actual)));
        return self::uno($c, $id);
    }

    private static function restablecerContrasena(Contexto $c): Respuesta
    {
        $id = $c->peticion->id();
        $cuenta = self::uno($c, $id);
        $hash = Contrasenas::cifrarNueva((string)$c->peticion->cuerpo()->crudo('contrasena'));
        $c->bd->ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [$hash, $id]);
        $c->bitacora->registrar('restablecer_contrasena', 'usuarios', $id, self::descripcion($cuenta));
        return Respuesta::vacia();
    }

    private static function eliminar(Contexto $c): Respuesta
    {
        $id = $c->peticion->id();
        $cuenta = self::uno($c, $id);
        self::revisarQueNoSeQuedenSinAdministrador($c, $id, $cuenta['rol']);
        $c->cambios->eliminar('usuarios', $id, self::descripcion($cuenta));
        return Respuesta::vacia();
    }

    /**
     * Nadie puede desactivarse ni borrarse a sí mismo, y siempre queda al menos un
     * administrador activo: si no, nadie podría volver a administrar el sistema.
     */
    private static function revisarQueNoSeQuedenSinAdministrador(Contexto $c, int $id, string $rol): void
    {
        if ($id === $c->usuarioConSesion()->id) {
            throw ErrorHttp::conflicto('No puedes desactivar ni eliminar tu propia cuenta');
        }
        if ($rol === 'admin' && (int)$c->bd->valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo AND id <> ?", [$id]) === 0) {
            throw ErrorHttp::conflicto('Debe quedar al menos un administrador activo');
        }
    }

    /** Los datos editables de una cuenta. En una nueva todos se leen; al editar, solo los que vinieron. */
    private static function datos(Entrada $e, bool $nueva): array
    {
        $valores = [];
        $leer = static fn (string $campo) => $nueva || $e->tiene($campo);
        if ($leer('matricula')) {
            $valores['matricula'] = $e->texto('matricula', 'la matrícula', 30, false);
        }
        if ($leer('email')) {
            $email = $e->texto('email', 'el correo', 150, false);
            if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw ErrorHttp::datosInvalidos('Revisa el correo: no parece una dirección válida', 'email');
            }
            $valores['email'] = $email === null ? null : mb_strtolower($email);
        }
        if ($leer('nombre')) {
            $valores['nombre'] = $e->texto('nombre', 'el nombre', 100);
        }
        if ($leer('apellido')) {
            $valores['apellido'] = $e->texto('apellido', 'el apellido', 100, false) ?? '';
        }
        if ($leer('departamento')) {
            $valores['departamento'] = $e->texto('departamento', 'el departamento', 150, false);
        }
        if ($nueva && $valores['matricula'] === null && $valores['email'] === null) {
            throw ErrorHttp::datosInvalidos('Falta la matrícula o el correo: con uno de los dos se inicia sesión', 'matricula');
        }
        return $valores;
    }

    private static function descripcion(array $cuenta): string
    {
        return ucfirst($cuenta['rol']) . ' ' . ($cuenta['matricula'] ?? $cuenta['email']);
    }
}
