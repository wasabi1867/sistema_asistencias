<?php
// ==============================================================
// SESIONES Y PERMISOS
// ==============================================================
// El personal (admin, laboratorista, docente) inicia sesión en auth.php y PHP guarda
// la sesión en el servidor; el navegador solo lleva una cookie con el identificador,
// que JavaScript no puede leer. El alumno no inicia sesión: registra su entrada con
// su matrícula.
//
// Cada ruta declara quién puede usarla con requerirRol(). Mientras el instituto no
// defina los permisos, se usa el reparto más restrictivo que deja operar: todos
// consultan, el laboratorista además edita alumnos y equipos, y solo el administrador
// administra cuentas, materias y borra.

const ROLES_TODOS = ['admin', 'laboratorista', 'docente'];
const ROLES_PERSONAL = ['admin', 'laboratorista'];
const ROLES_ADMIN = ['admin'];

const NOMBRE_COOKIE_SESION = 'asistencias_sesion';

// Una clase dura unas horas y el docente puede no tocar la pantalla en todo ese
// tiempo; más allá de esto, la sesión se da por abandonada.
const MINUTOS_SESION_INACTIVA = 240;

function abrirSesionPhp(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    // Modo estricto: PHP rechaza identificadores que él no creó, así nadie puede
    // fijarle a otro una sesión conocida.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(NOMBRE_COOKIE_SESION);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        // Lax: el navegador no manda la cookie en un POST que venga de otro sitio.
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * El usuario de la sesión, o null si no hay sesión o ya venció.
 * Devuelve ['rol' => ..., 'id' => ..., 'nombre' => ..., 'email' => ...].
 */
function usuarioEnSesion(): ?array {
    static $usuario = false;
    if ($usuario !== false) {
        return $usuario;
    }

    // Sin cookie no hay sesión que buscar; así las peticiones anónimas (el registro
    // del alumno) no crean archivos de sesión en el servidor.
    if (empty($_COOKIE[NOMBRE_COOKIE_SESION])) {
        return $usuario = null;
    }

    abrirSesionPhp();
    $datos = $_SESSION['usuario'] ?? null;
    $ultimoUso = $_SESSION['ultimo_uso'] ?? 0;

    if (!$datos || time() - $ultimoUso > MINUTOS_SESION_INACTIVA * 60) {
        cerrarSesion();
        return $usuario = null;
    }

    $_SESSION['ultimo_uso'] = time();
    // Liberar el archivo de sesión: PHP lo deja bloqueado hasta terminar la petición
    // y las demás peticiones del mismo usuario tendrían que esperar.
    session_write_close();
    return $usuario = $datos;
}

/**
 * Corta la petición con 401 si no hay sesión, o con 403 si el rol no está en $roles.
 * Devuelve el usuario de la sesión.
 */
function requerirRol(array $roles): array {
    $usuario = usuarioEnSesion();
    if ($usuario === null) {
        sendResponse(["success" => false, "error" => "Inicia sesión para continuar"], 401);
    }
    if (!in_array($usuario['rol'], $roles, true)) {
        sendResponse(["success" => false, "error" => "No tienes permiso para esta acción"], 403);
    }
    return $usuario;
}

function iniciarSesion(string $rol, string $id, string $nombre, string $email): void {
    abrirSesionPhp();
    // Identificador nuevo al iniciar sesión, para que uno obtenido antes no sirva.
    session_regenerate_id(true);
    $_SESSION['usuario'] = ['rol' => $rol, 'id' => $id, 'nombre' => $nombre, 'email' => $email];
    $_SESSION['ultimo_uso'] = time();
    session_write_close();
}

const LONGITUD_MINIMA_CONTRASENA = 8;

/** El motivo por el que no sirve una contraseña nueva, o null si sirve. */
function problemaConContrasena(string $password): ?string {
    if (mb_strlen($password) < LONGITUD_MINIMA_CONTRASENA) {
        return "La contraseña debe tener al menos " . LONGITUD_MINIMA_CONTRASENA . " caracteres";
    }
    return null;
}

function cerrarSesion(): void {
    if (empty($_COOKIE[NOMBRE_COOKIE_SESION]) && session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    abrirSesionPhp();
    $_SESSION = [];
    session_destroy();
    $parametros = session_get_cookie_params();
    setcookie(NOMBRE_COOKIE_SESION, '', [
        'expires' => time() - 3600,
        'path' => $parametros['path'],
        'secure' => $parametros['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
