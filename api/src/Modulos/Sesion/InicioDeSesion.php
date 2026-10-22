<?php

declare(strict_types=1);

namespace Asistencias\Modulos\Sesion;

use Asistencias\Contexto;
use Asistencias\Http\Enrutador;
use Asistencias\Http\ErrorHttp;
use Asistencias\Http\Respuesta;
use Asistencias\Seguridad\Acceso;
use Asistencias\Seguridad\Contrasenas;
use Asistencias\Seguridad\Usuario;

/** Iniciar y cerrar sesión, saber quién la tiene y cambiar la contraseña propia. */
final class InicioDeSesion
{
    // Intentos fallidos permitidos desde una misma dirección antes de hacerla esperar:
    // alcanza para equivocarse varias veces, pero no para probar contraseñas en masa.
    private const INTENTOS_PERMITIDOS = 10;
    private const MINUTOS_DE_ESPERA = 15;

    public static function registrar(Enrutador $r): void
    {
        $r->post('/sesion', Acceso::Publica, static fn (Contexto $c) => self::iniciar($c));
        $r->get('/sesion', Acceso::Sesion, static fn (Contexto $c) => $c->usuarioConSesion()->comoJson());
        $r->delete('/sesion', Acceso::Publica, static fn (Contexto $c) => self::cerrar($c));
        $r->put('/sesion/contrasena', Acceso::Sesion, static fn (Contexto $c) => self::cambiarContrasena($c));
    }

    private static function iniciar(Contexto $c): array
    {
        $e = $c->peticion->cuerpo();
        $usuario = $e->texto('usuario', 'el correo o la matrícula', 150);
        $contrasena = (string)$e->crudo('contrasena');
        if ($contrasena === '') {
            throw ErrorHttp::datosInvalidos('Falta la contraseña', 'contrasena');
        }
        $rol = $e->opcion('rol', 'el rol', ['admin', 'laboratorista', 'docente']);

        $fallidos = (int)$c->bd->valor(
            "SELECT COUNT(*) FROM bitacora WHERE accion = 'sesion_fallida' AND ip = ? AND fecha > NOW() - INTERVAL " . self::MINUTOS_DE_ESPERA . ' MINUTE',
            [$c->peticion->ip]
        );
        if ($fallidos >= self::INTENTOS_PERMITIDOS) {
            throw new ErrorHttp(429, 'Demasiados intentos fallidos. Espera ' . self::MINUTOS_DE_ESPERA . ' minutos e inténtalo de nuevo');
        }

        // Se entra con el correo o con la matrícula; si los dos coinciden con cuentas
        // distintas, gana el correo.
        $fila = $c->bd->fila(
            'SELECT * FROM usuarios WHERE rol = ? AND (email = ? OR matricula = ?) ORDER BY email = ? DESC LIMIT 1',
            [$rol, $usuario, $usuario, $usuario]
        );
        if (!Contrasenas::coincide($contrasena, $fila['password_hash'] ?? null)) {
            $c->bitacora->registrarAunqueFalle('sesion_fallida', "Rol $rol", null, $usuario);
            throw new ErrorHttp(401, 'Usuario o contraseña incorrectos');
        }
        if (!$fila['activo']) {
            throw ErrorHttp::sinPermiso('Tu cuenta está desactivada. Pide al administrador que la active');
        }
        if (Contrasenas::hayQueRecifrar($fila['password_hash'])) {
            $c->bd->ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($contrasena, PASSWORD_DEFAULT), $fila['id']]);
        }

        $cuenta = Usuario::desdeFila($fila);
        $c->sesion->iniciar($cuenta->id);
        $c->cambiarUsuario($cuenta);
        $c->bitacora->registrar('iniciar_sesion', 'usuarios', $cuenta->id);
        return $cuenta->comoJson();
    }

    private static function cerrar(Contexto $c): Respuesta
    {
        if ($cuenta = $c->usuario()) {
            $c->bitacora->registrar('cerrar_sesion', 'usuarios', $cuenta->id);
        }
        $c->sesion->cerrar();
        return Respuesta::vacia();
    }

    private static function cambiarContrasena(Contexto $c): Respuesta
    {
        $cuenta = $c->usuarioConSesion();
        $e = $c->peticion->cuerpo();
        $actual = (string)$e->crudo('actual');
        $hash = $c->bd->valor('SELECT password_hash FROM usuarios WHERE id = ?', [$cuenta->id]);
        if (!Contrasenas::coincide($actual, $hash)) {
            throw ErrorHttp::datosInvalidos('La contraseña actual no es correcta', 'actual');
        }
        $nuevo = Contrasenas::cifrarNueva((string)$e->crudo('nueva'), 'nueva');
        $c->bd->ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [$nuevo, $cuenta->id]);
        $c->bitacora->registrar('cambiar_contrasena', 'usuarios', $cuenta->id);
        return Respuesta::vacia();
    }
}
