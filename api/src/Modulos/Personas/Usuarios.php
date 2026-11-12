<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Personas;

use Asistencias\Contexto;
use Asistencias\Datos\ErrorDeIntegridad;
use Asistencias\Http\Enrutador;
use Asistencias\Http\Entrada;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Modulos\Catalogos\Laboratorios;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Contrasenas;
use Asistencias\Seguridad\Usuario;

/**
 * Las cuentas del personal. Las maneja el administrador; los demás solo ven la lista
 * de docentes y laboratoristas activos (para elegir al docente de un curso, por
 * ejemplo). Una cuenta con historial no se borra: se desactiva.
 *
 * Cada persona tiene una sola cuenta, con uno o varios roles (D20): el correo y la
 * matrícula no se repiten en todo el sistema. Al laboratorista se le asignan los
 * laboratorios que atiende (D21).
 */
final class Usuarios
{
    private const ROLES = ['admin', 'laboratorista', 'docente'];

    /** Las cuentas con las claves de sus laboratorios asignados, separadas por comas. */
    private const SELECCION = "SELECT u.*, (SELECT GROUP_CONCAT(l.clave ORDER BY l.nombre) FROM laboratorios_del_personal lp
            JOIN laboratorios l ON l.id = lp.laboratorio_id WHERE lp.usuario_id = u.id) AS laboratorios
        FROM usuarios u";

    private const DUPLICADOS = [
        'uq_usuarios_matricula' => 'Ya hay una cuenta con esa matrícula. Si es la misma persona, agrégale el rol a esa cuenta',
        'uq_usuarios_email' => 'Ya hay una cuenta con ese correo. Si es la misma persona, agrégale el rol a esa cuenta',
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
            'roles' => explode(',', $fila['roles']),
            'matricula' => $fila['matricula'],
            'nombre' => $fila['nombre'],
            'apellido' => $fila['apellido'],
            'email' => $fila['email'],
            'departamento' => $fila['departamento'],
            'activo' => (bool)$fila['activo'],
            'laboratorios' => $fila['laboratorios'] === null ? [] : explode(',', $fila['laboratorios']),
        ];
    }

    private static function uno(Contexto $c, int $id): array
    {
        return self::formato($c->bd->fila(self::SELECCION . ' WHERE u.id = ?', [$id]) ?? throw ErrorHttp::noExiste('La cuenta'));
    }

    private static function listar(Contexto $c): array
    {
        $q = $c->peticion->consulta();
        $rol = $q->opcion('rol', 'el rol', self::ROLES, false);
        $condiciones = [];
        $parametros = [];
        if ($rol !== null) {
            $condiciones[] = 'FIND_IN_SET(?, u.roles)';
            $parametros[] = $rol;
        }
        if (!$c->usuarioConSesion()->esAdmin()) {
            $condiciones[] = "(FIND_IN_SET('docente', u.roles) OR FIND_IN_SET('laboratorista', u.roles)) AND u.activo";
        } elseif ($q->tiene('activo')) {
            $condiciones[] = 'u.activo = ?';
            $parametros[] = $q->booleano('activo');
        }
        $donde = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
        return array_map(self::formato(...), $c->bd->filas(self::SELECCION . "$donde ORDER BY u.apellido, u.nombre", $parametros));
    }

    private static function crear(Contexto $c): Respuesta
    {
        $e = $c->peticion->cuerpo();
        $valores = ['roles' => self::roles($e)] + self::datos($e, nueva: true);
        $valores['password_hash'] = Contrasenas::cifrarNueva((string)$e->crudo('contrasena'));
        $id = ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->crear('usuarios', $valores, self::descripcion($valores)));
        if ($e->tiene('laboratorios')) {
            self::asignarLaboratorios($c, $id, $valores['roles'], $e, self::descripcion($valores));
        }
        return Respuesta::creado(self::uno($c, $id));
    }

    private static function editar(Contexto $c): array
    {
        $id = $c->peticion->id();
        $actual = self::uno($c, $id);
        $e = $c->peticion->cuerpo();
        $valores = self::datos($e, nueva: false);
        if ($e->tiene('roles')) {
            $valores['roles'] = self::roles($e);
            if (in_array('admin', $actual['roles'], true) && !in_array('admin', explode(',', $valores['roles']), true)) {
                self::revisarQueNoSeQuedenSinAdministrador($c, $id, $actual['roles'], 'quitar el rol de administrador a');
            }
        }
        if ($e->tiene('activo')) {
            $valores['activo'] = $e->booleano('activo');
            if (!$valores['activo']) {
                self::revisarQueNoSeQuedenSinAdministrador($c, $id, $actual['roles'], 'desactivar');
            }
        }
        ErrorDeIntegridad::explicar(self::DUPLICADOS, static fn () => $c->cambios->actualizar('usuarios', $id, $valores, self::descripcion($actual)));
        $roles = $valores['roles'] ?? implode(',', $actual['roles']);
        if ($e->tiene('laboratorios')) {
            self::asignarLaboratorios($c, $id, $roles, $e, self::descripcion($actual));
        } elseif (!in_array('laboratorista', explode(',', $roles), true) && $actual['laboratorios'] !== []) {
            // Si deja de ser laboratorista, deja de atender sus laboratorios.
            $c->bd->ejecutar('DELETE FROM laboratorios_del_personal WHERE usuario_id = ?', [$id]);
            $c->bitacora->registrar('asignar_laboratorios', 'usuarios', $id, self::descripcion($actual) . ': ninguno');
        }
        return self::uno($c, $id);
    }

    private static function restablecerContrasena(Contexto $c): Respuesta
    {
        $id = $c->peticion->id();
        $cuenta = self::uno($c, $id);
        $hash = Contrasenas::cifrarNueva((string)$c->peticion->cuerpo()->crudo('contrasena'));
        $c->bd->ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [$hash, $id]);
        // Las sesiones abiertas de esa cuenta dejan de servir. Si es la propia, esta sigue.
        if ($id === $c->usuarioConSesion()->id) {
            $c->sesion->seguirCon($hash);
        }
        $c->bitacora->registrar('restablecer_contrasena', 'usuarios', $id, self::descripcion($cuenta));
        return Respuesta::vacia();
    }

    private static function eliminar(Contexto $c): Respuesta
    {
        $id = $c->peticion->id();
        $cuenta = self::uno($c, $id);
        self::revisarQueNoSeQuedenSinAdministrador($c, $id, $cuenta['roles'], 'eliminar');
        $c->cambios->eliminar('usuarios', $id, self::descripcion($cuenta));
        return Respuesta::vacia();
    }

    /**
     * Nadie puede desactivarse, borrarse ni quitarse el rol de administrador a sí mismo,
     * y siempre queda al menos un administrador activo: si no, nadie podría volver a
     * administrar el sistema.
     * @param list<string> $roles los que tiene la cuenta ahora
     * @param string $que lo que se intenta: "desactivar", "eliminar"...
     */
    private static function revisarQueNoSeQuedenSinAdministrador(Contexto $c, int $id, array $roles, string $que): void
    {
        if ($id === $c->usuarioConSesion()->id) {
            throw ErrorHttp::conflicto($que === 'quitar el rol de administrador a'
                ? 'No puedes quitarte a ti mismo el rol de administrador'
                : 'No puedes desactivar ni eliminar tu propia cuenta');
        }
        if (in_array('admin', $roles, true) && (int)$c->bd->valor("SELECT COUNT(*) FROM usuarios WHERE FIND_IN_SET('admin', roles) AND activo AND id <> ?", [$id]) === 0) {
            throw ErrorHttp::conflicto('Debe quedar al menos un administrador activo');
        }
    }

    /**
     * Cambia los laboratorios que atiende un laboratorista por los de la lista (sus
     * claves; vacía, ninguno). Solo el laboratorista tiene laboratorios asignados: el
     * administrador atiende todos y el docente da clase en cualquiera.
     */
    private static function asignarLaboratorios(Contexto $c, int $id, string $roles, Entrada $e, string $descripcion): void
    {
        $claves = $e->lista('laboratorios', 'los laboratorios', 100);
        if ($claves !== [] && !in_array('laboratorista', explode(',', $roles), true)) {
            throw ErrorHttp::datosInvalidos('Solo al laboratorista se le asignan laboratorios', 'laboratorios');
        }
        $laboratorios = [];
        foreach ($claves as $clave) {
            if (!is_string($clave)) {
                throw ErrorHttp::datosInvalidos('Revisa los laboratorios: van sus claves', 'laboratorios');
            }
            $laboratorio = Laboratorios::porClave($c->bd, $clave);
            $laboratorios[$laboratorio['id']] = $laboratorio['nombre'];
        }
        $c->bd->ejecutar('DELETE FROM laboratorios_del_personal WHERE usuario_id = ?', [$id]);
        foreach (array_keys($laboratorios) as $laboratorioId) {
            $c->bd->insertar('laboratorios_del_personal', ['usuario_id' => $id, 'laboratorio_id' => $laboratorioId]);
        }
        $c->bitacora->registrar('asignar_laboratorios', 'usuarios', $id, "$descripcion: " . ($laboratorios === [] ? 'ninguno' : implode(', ', $laboratorios)));
    }

    /** Los roles de la cuenta, al menos uno, como van en la columna: "admin,docente". */
    private static function roles(Entrada $e): string
    {
        if (in_array($e->crudo('roles'), [null, []], true)) {
            throw ErrorHttp::datosInvalidos('Elige al menos un rol', 'roles');
        }
        $roles = $e->lista('roles', 'los roles', count(self::ROLES));
        foreach ($roles as $rol) {
            if (!in_array($rol, self::ROLES, true)) {
                throw ErrorHttp::datosInvalidos('Revisa los roles: cada uno debe ser ' . implode(', ', self::ROLES), 'roles');
            }
        }
        return implode(',', array_map(static fn ($r) => $r->value, Usuario::rolesDe(implode(',', $roles))));
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
        return 'Cuenta ' . ($cuenta['matricula'] ?? $cuenta['email']);
    }
}
