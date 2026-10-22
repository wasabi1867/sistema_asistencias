<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

/**
 * La sesión del personal. PHP la guarda en el servidor y el navegador solo lleva una
 * cookie con su identificador, que JavaScript no puede leer. El alumno no inicia
 * sesión.
 *
 * La sesión guarda solo el id de la cuenta: en cada petición se vuelve a leer de la
 * base, así que una cuenta desactivada deja de servir de inmediato.
 */
final class Sesion
{
    public const COOKIE = 'asistencias_sesion';

    // Una clase dura unas horas y el docente puede no tocar la pantalla en todo ese
    // tiempo; más allá de esto la sesión se da por abandonada.
    private const MINUTOS_INACTIVA = 240;

    public function __construct(private readonly bool $https)
    {
    }

    /** El id de la cuenta con sesión, o null si no hay sesión o ya venció. */
    public function idDeUsuario(): ?int
    {
        // Sin cookie no hay nada que buscar; así las peticiones sin sesión (el registro
        // del alumno) no crean archivos de sesión en el servidor.
        if (empty($_COOKIE[self::COOKIE])) {
            return null;
        }
        $this->abrir();
        $id = $_SESSION['usuario_id'] ?? null;
        $ultimoUso = (int)($_SESSION['ultimo_uso'] ?? 0);
        if ($id === null || time() - $ultimoUso > self::MINUTOS_INACTIVA * 60) {
            $this->cerrar();
            return null;
        }
        $_SESSION['ultimo_uso'] = time();
        // PHP deja el archivo de sesión bloqueado hasta terminar la petición, y las
        // demás peticiones del mismo navegador tendrían que esperar.
        session_write_close();
        return (int)$id;
    }

    public function iniciar(int $idDeUsuario): void
    {
        $this->abrir();
        // Identificador nuevo al iniciar sesión, para que uno obtenido antes no sirva.
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $idDeUsuario;
        $_SESSION['ultimo_uso'] = time();
        session_write_close();
    }

    public function cerrar(): void
    {
        if (empty($_COOKIE[self::COOKIE]) && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $this->abrir();
        $_SESSION = [];
        session_destroy();
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $this->https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function abrir(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // Modo estricto: PHP rechaza identificadores que él no creó, así nadie puede
        // fijarle a otro una sesión conocida.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name(self::COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->https,
            'httponly' => true,
            // Lax: el navegador no manda la cookie en peticiones que vengan de otro
            // sitio (salvo al abrir un enlace), lo que cierra el paso a CSRF.
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
