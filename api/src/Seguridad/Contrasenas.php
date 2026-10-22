<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

use Asistencias\Http\ErrorHttp;

final class Contrasenas
{
    public const LONGITUD_MINIMA = 8;

    // Hash de una contraseña al azar que nadie conoce. Se compara contra él cuando la
    // cuenta no existe, para que la respuesta tarde lo mismo y no delate qué correos o
    // matrículas están registrados.
    private const HASH_SIN_CUENTA = '$2y$10$1YLOyolUXqdygSxyqSavF.zUqKcQ9K23cYsO7f5Bnlq3wRX7zFxJS';

    /** El hash de una contraseña nueva. Responde 400 si no cumple las reglas. */
    public static function cifrarNueva(string $contrasena, string $campo = 'contrasena'): string
    {
        if (mb_strlen($contrasena) < self::LONGITUD_MINIMA) {
            throw ErrorHttp::datosInvalidos('La contraseña debe tener al menos ' . self::LONGITUD_MINIMA . ' caracteres', $campo);
        }
        if (mb_strlen($contrasena) > 200) {
            throw ErrorHttp::datosInvalidos('La contraseña es demasiado larga', $campo);
        }
        return password_hash($contrasena, PASSWORD_DEFAULT);
    }

    /**
     * Si la contraseña corresponde al hash. Con $hash null (la cuenta no existe) gasta
     * el mismo tiempo y responde que no.
     */
    public static function coincide(string $contrasena, ?string $hash): bool
    {
        if ($hash === null) {
            password_verify($contrasena, self::HASH_SIN_CUENTA);
            return false;
        }
        return password_verify($contrasena, $hash);
    }

    /**
     * Si hay que volver a cifrarla al entrar: los hash de bcryptjs que vienen de
     * Firebase ($2a$, $2b$) PHP los verifica, pero conviene pasarlos a su formato.
     */
    public static function hayQueRecifrar(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
