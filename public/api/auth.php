<?php
require_once __DIR__ . '/db.php';

// Hash de una contraseña al azar que nadie conoce. Se compara contra él cuando el
// usuario no existe, para que la respuesta tarde lo mismo y no delate qué correos o
// matrículas están registrados.
const HASH_SIN_USUARIO = '$2y$10$1YLOyolUXqdygSxyqSavF.zUqKcQ9K23cYsO7f5Bnlq3wRX7zFxJS';

// Intentos fallidos permitidos desde una misma dirección antes de hacerla esperar.
// Alcanza para equivocarse varias veces, pero no para probar contraseñas en masa.
const MAXIMO_INTENTOS_FALLIDOS = 10;
const MINUTOS_DE_ESPERA = 15;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Quién tiene la sesión abierta; el frontend lo usa al cargar la página.
        $usuario = usuarioEnSesion();
        if ($usuario === null) {
            sendResponse(["success" => false, "error" => "No hay sesión iniciada"], 401);
        }
        sendResponse(["success" => true, "user" => $usuario, "role" => $usuario['rol']]);
        break;

    case 'DELETE':
        if ($usuario = usuarioEnSesion()) {
            registrarEnBitacora(getDBConnection(), "Cerrar Sesión", "{$usuario['nombre']} ({$usuario['rol']})");
        }
        cerrarSesion();
        sendResponse(["success" => true, "message" => "Sesión cerrada"]);
        break;

    case 'POST':
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no permitido"], 405);
}

$data = getJsonInput();
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');
$role = trim($data['role'] ?? 'admin'); // 'admin', 'laboratorista', 'docente'

if (!$email || !$password) {
    sendResponse(["success" => false, "error" => "Email y contraseña requeridos"], 400);
}

$pdo = getDBConnection();
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE accion = 'Inicio de sesión fallido' AND ip = ? AND fecha > NOW() - INTERVAL " . MINUTOS_DE_ESPERA . " MINUTE");
$stmt->execute([$ip]);
if ($stmt->fetchColumn() >= MAXIMO_INTENTOS_FALLIDOS) {
    sendResponse(["success" => false, "error" => "Demasiados intentos fallidos. Espera " . MINUTOS_DE_ESPERA . " minutos e inténtalo de nuevo"], 429);
}

if ($role === 'admin') {
    // Con correo o con matrícula, como en la versión con Firebase.
    $stmt = $pdo->prepare("SELECT id, matricula, email, nombre, apellido, password, rol FROM administradores WHERE email = ? OR matricula = ? LIMIT 1");
    $stmt->execute([$email, $email]);
} elseif ($role === 'laboratorista') {
    $stmt = $pdo->prepare("SELECT matricula, nombre, apellido, email, password FROM laboratoristas WHERE email = ? OR matricula = ? LIMIT 1");
    $stmt->execute([$email, $email]);
} elseif ($role === 'docente') {
    $stmt = $pdo->prepare("SELECT matricula, nombre, apellido, email, password, departamento FROM docentes WHERE email = ? OR matricula = ? LIMIT 1");
    $stmt->execute([$email, $email]);
} else {
    sendResponse(["success" => false, "error" => "Rol no válido"], 400);
}

$user = $stmt->fetch() ?: null;

// Se verifica aunque el usuario no exista (ver HASH_SIN_USUARIO).
$valida = contrasenaCorrecta($pdo, $role, $user, $password);
if (!$user || !$valida) {
    registrarEnBitacora($pdo, "Inicio de sesión fallido", "Rol $role", null, mb_substr($email, 0, 100));
    sendResponse(["success" => false, "error" => "Credenciales incorrectas"], 401);
}

unset($user['password']);
$id = $role === 'admin' ? (string)$user['id'] : $user['matricula'];
$nombre = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''));
iniciarSesion($role, $id, $nombre, $user['email'] ?? '');
registrarEnBitacora($pdo, "Inicio de Sesión", "Rol $role", null, "$nombre ($role)");

sendResponse([
    "success" => true,
    "user" => $user,
    "role" => $role
]);

/**
 * Verifica la contraseña. Si estaba guardada en texto plano (cuentas que vienen de
 * antes de cifrar), la acepta esta única vez y la guarda cifrada, para no dejar fuera
 * a nadie y que después ya no quede ninguna en claro.
 */
function contrasenaCorrecta(PDO $pdo, string $role, ?array $user, string $password): bool {
    $guardada = (string)($user['password'] ?? '');

    // Un hash empieza con '$'. No basta con password_get_info(): los de bcryptjs, que
    // usaba la versión con Firebase ($2a$ y $2b$), PHP los verifica bien pero no los
    // reconoce como suyos, y se habrían tomado por texto plano.
    if (str_starts_with($guardada, '$')) {
        if (!password_verify($password, $guardada)) {
            return false;
        }
        // Los de bcryptjs se vuelven a cifrar en el formato de PHP al entrar.
        if (password_needs_rehash($guardada, PASSWORD_DEFAULT)) {
            guardarContrasena($pdo, $role, $user, $password);
        }
        return true;
    }

    // Texto plano (o cuenta sin contraseña): gastar el mismo tiempo que un hash.
    password_verify($password, HASH_SIN_USUARIO);
    if ($guardada === '' || !hash_equals($guardada, $password)) {
        return false;
    }
    guardarContrasena($pdo, $role, $user, $password);
    return true;
}

function guardarContrasena(PDO $pdo, string $role, array $user, string $password): void {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($role === 'admin') {
        $pdo->prepare("UPDATE administradores SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
    } elseif ($role === 'laboratorista') {
        $pdo->prepare("UPDATE laboratoristas SET password = ? WHERE matricula = ?")->execute([$hash, $user['matricula']]);
    } else {
        $pdo->prepare("UPDATE docentes SET password = ? WHERE matricula = ?")->execute([$hash, $user['matricula']]);
    }
}
