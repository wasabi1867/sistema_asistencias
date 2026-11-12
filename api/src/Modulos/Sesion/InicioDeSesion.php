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
    // Intentos fallidos permitidos con una misma cuenta desde una misma dirección antes
    // de hacerla esperar: alcanza para equivocarse varias veces, pero no para probar
    // contraseñas en masa. Es por cuenta porque todo el instituto puede salir a internet
    // con una sola dirección: los fallos de uno no deben dejar fuera a los demás (D25).
    private const INTENTOS_PERMITIDOS = 10;
    // El tope de la dirección, con cualquier cuenta: contra quien prueba una contraseña
    // en muchas cuentas. Alto, para que no lo alcance el uso normal de un instituto.
    private const INTENTOS_POR_DIRECCION = 100;
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
        // Se entra con el correo o con la matrícula; si los dos coinciden con cuentas
        // distintas, gana el correo. El rol no se pregunta: sale de la cuenta (D20).
        $fila = $c->bd->fila(
            'SELECT * FROM usuarios WHERE email = ? OR matricula = ? ORDER BY email = ? DESC LIMIT 1',
            [$usuario, $usuario, $usuario]
        );
        $quien = self::quienIntenta($usuario, $fila);
        $desde = "FROM bitacora WHERE accion = 'sesion_fallida' AND ip = ? AND fecha > NOW() - INTERVAL " . self::MINUTOS_DE_ESPERA . ' MINUTE';
        if (
            (int)$c->bd->valor("SELECT COUNT(*) $desde AND usuario = ?", [$c->peticion->ip, $quien]) >= self::INTENTOS_PERMITIDOS
            || (int)$c->bd->valor("SELECT COUNT(*) $desde", [$c->peticion->ip]) >= self::INTENTOS_POR_DIRECCION
        ) {
            throw new ErrorHttp(429, 'Demasiados intentos fallidos. Espera ' . self::MINUTOS_DE_ESPERA . ' minutos e inténtalo de nuevo');
        }

        if (!Contrasenas::coincide($contrasena, $fila['password_hash'] ?? null)) {
            $c->bitacora->registrarAunqueFalle('sesion_fallida', null, null, $quien);
            throw new ErrorHttp(401, 'Usuario o contraseña incorrectos');
        }
        if (!$fila['activo']) {
            throw ErrorHttp::sinPermiso('Tu cuenta está desactivada. Pide al administrador que la active');
        }
        if (Contrasenas::hayQueRecifrar($fila['password_hash'])) {
            $fila['password_hash'] = password_hash($contrasena, PASSWORD_DEFAULT);
            $c->bd->ejecutar('UPDATE usuarios SET password_hash = ? WHERE id = ?', [$fila['password_hash'], $fila['id']]);
        }

        $cuenta = Usuario::cargar($c->bd, $fila);
        $c->sesion->iniciar($cuenta->id, $fila['password_hash']);
        $c->cambiarUsuario($cuenta);
        $c->bitacora->registrar('iniciar_sesion', 'usuarios', $cuenta->id);
        return $cuenta->comoJson();
    }

    /**
     * Cómo queda en la bitácora quien falló al iniciar sesión, que es también con lo que
     * se cuentan sus intentos. Si la cuenta existe, su correo o su matrícula tal como
     * están guardados. Si no existe, no se guarda lo que se escribió, porque a veces es
     * una contraseña puesta en el campo equivocado: solo una huella, que es la misma
     * cada vez que se escribe lo mismo.
     *
     * @param array<string, mixed>|null $fila la cuenta, si existe
     */
    private static function quienIntenta(string $escrito, ?array $fila): string
    {
        if ($fila !== null) {
            return (string)($fila['email'] ?? $fila['matricula']);
        }
        return 'Cuenta que no existe (' . substr(hash('sha256', mb_strtolower($escrito)), 0, 10) . ')';
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
        // Sus otras sesiones dejan de servir; esta sigue.
        $c->sesion->seguirCon($nuevo);
        $c->bitacora->registrar('cambiar_contrasena', 'usuarios', $cuenta->id);
        return Respuesta::vacia();
    }
}
