<?php

declare(strict_types=1);

namespace Asistencias\Seguridad;

/**
 * La sesión del personal. PHP la guarda en el servidor y el navegador solo lleva una
 * cookie con su identificador, que JavaScript no puede leer. El alumno no inicia
 * sesión.
 *
 * La sesión guarda solo el id de la cuenta: en cada petición se vuelve a leer de la
 * base, así que una cuenta desactivada deja de servir de inmediato. Guarda también una
 * marca de su contraseña: al cambiarla, las sesiones abiertas con la anterior dejan de
 * servir (D25).
 */
final class Sesion
{
    public const COOKIE = 'asistencias_sesion';

    // Una clase dura unas horas y el docente puede no tocar la pantalla en todo ese
    // tiempo; más allá de esto la sesión se da por abandonada.
    private const MINUTOS_INACTIVA = 240;

    private ?string $marca = null;
    // La carpeta de sesiones del servidor, como estaba antes de cambiarla por la propia.
    private static ?string $carpetaDelServidor = null;

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
        $this->marca = isset($_SESSION['marca']) ? (string)$_SESSION['marca'] : null;
        // PHP deja el archivo de sesión bloqueado hasta terminar la petición, y las
        // demás peticiones del mismo navegador tendrían que esperar.
        session_write_close();
        return (int)$id;
    }

    /**
     * La marca de una contraseña: cambia cuando cambia su hash, y de ella no se saca el
     * hash. No es secreta; solo sirve para comparar.
     */
    public static function marcaDe(string $hashDeLaContrasena): string
    {
        return substr(hash('sha256', $hashDeLaContrasena), 0, 32);
    }

    /** Si la sesión se abrió con la contraseña que la cuenta tiene ahora. */
    public function esDeLaContrasena(string $hashDeLaContrasena): bool
    {
        return $this->marca !== null && hash_equals(self::marcaDe($hashDeLaContrasena), $this->marca);
    }

    public function iniciar(int $idDeUsuario, string $hashDeLaContrasena): void
    {
        $this->abrir();
        // Identificador nuevo al iniciar sesión, para que uno obtenido antes no sirva.
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $idDeUsuario;
        $_SESSION['ultimo_uso'] = time();
        $_SESSION['marca'] = $this->marca = self::marcaDe($hashDeLaContrasena);
        session_write_close();
    }

    /** Quien cambia su propia contraseña conserva esta sesión; las demás dejan de servir. */
    public function seguirCon(string $hashDeLaContrasena): void
    {
        $this->abrir();
        $_SESSION['marca'] = $this->marca = self::marcaDe($hashDeLaContrasena);
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
        $this->conservarLoQueDuraLaSesion();
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

    /**
     * PHP borra las sesiones sin uso después de session.gc_maxlifetime, que de fábrica
     * son 24 minutos: menos que una clase. Se sube a lo que dura la sesión, y se guardan
     * en una carpeta propia, porque en una carpeta compartida las borraría cualquier
     * otro sitio de la misma cuenta que conserve el valor de fábrica.
     */
    private function conservarLoQueDuraLaSesion(): void
    {
        ini_set('session.gc_maxlifetime', (string)(self::MINUTOS_INACTIVA * 60));
        if (ini_get('session.save_handler') !== 'files') {
            return;
        }
        $carpeta = self::carpeta();
        if ($carpeta === null) {
            return;
        }
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0700);
        }
        if (is_dir($carpeta) && is_writable($carpeta)) {
            session_save_path($carpeta);
            // Hay servidores que apagan la limpieza de PHP porque limpian su carpeta con
            // una tarea propia, que no conoce esta.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
    }

    /** Dónde se guardan las sesiones, o null si la configuración del servidor no deja saberlo. */
    private static function carpeta(): ?string
    {
        // Se lee una sola vez: después ya apunta a la carpeta propia.
        $base = self::$carpetaDelServidor ??= (string)session_save_path();
        // "N;/ruta" reparte las sesiones en subcarpetas: se deja como está.
        if (str_contains($base, ';')) {
            return null;
        }
        return rtrim($base !== '' ? $base : sys_get_temp_dir(), '/\\') . '/asistencias_sesiones';
    }
}
