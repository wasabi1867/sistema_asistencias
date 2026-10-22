<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sesion.php';

// Un aviso de PHP impreso en la respuesta rompe el JSON y puede mostrar rutas del
// servidor; los errores van al registro, no al cliente.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set(ZONA_HORARIA);

// Cualquier excepción que una ruta no atrape termina aquí: se registra completa y al
// cliente solo le llega un mensaje genérico (antes se devolvía el mensaje de la base
// de datos, con nombres de tablas y columnas).
/**
 * Una regla de negocio que no se cumple (no hay clase abierta, el equipo está
 * ocupado...). Se lanza desde cualquier punto, incluso dentro de una transacción,
 * y llega al cliente con su estado HTTP y su mensaje.
 */
class ErrorDeRegla extends RuntimeException {
    public function __construct(public readonly int $estado, string $mensaje, public readonly array $extra = []) {
        parent::__construct($mensaje);
    }
}

set_exception_handler(function (Throwable $e) {
    if ($e instanceof ErrorDeRegla) {
        http_response_code($e->estado);
        echo json_encode(["success" => false, "error" => $e->getMessage()] + $e->extra, JSON_UNESCAPED_UNICODE);
        return;
    }
    error_log('API asistencias: ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
    }
    echo json_encode(["success" => false, "error" => "Error interno del servidor"], JSON_UNESCAPED_UNICODE);
});

header("Content-Type: application/json; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");

// CORS solo para los orígenes configurados. Con '*' cualquier página de internet podía
// llamar a la API; ahora que hay sesiones, eso permitiría actuar con la sesión ajena.
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origen !== '' && in_array($origen, ORIGENES_PERMITIDOS, true)) {
    header("Access-Control-Allow-Origin: $origen");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
    header("Vary: Origin");
}
unset($origen);

// Responder inmediatamente a peticiones preflight OPTIONS del navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Que CURRENT_TIMESTAMP y NOW() de la base den la misma hora que PHP.
            $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
        } catch (PDOException $e) {
            error_log('API asistencias: no se pudo conectar a la base de datos: ' . $e->getMessage());
            sendResponse(["success" => false, "error" => "Error de conexión a la base de datos"], 500);
        }
    }
    return $pdo;
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

/**
 * Corre $trabajo dentro de una transacción: si algo falla a la mitad, no queda nada a
 * medias (por ejemplo, una asistencia pasada al histórico pero sin borrar de las
 * activas). Devuelve lo que devuelva $trabajo.
 */
function enTransaccion(PDO $pdo, callable $trabajo) {
    // Dentro de otra transacción (por ejemplo, cerrar una clase registra la salida de
    // cada alumno), el trabajo se vuelve parte de la de afuera.
    if ($pdo->inTransaction()) {
        return $trabajo($pdo);
    }
    $pdo->beginTransaction();
    try {
        $resultado = $trabajo($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Anota una acción en la bitácora (tabla logs) con quién la hizo y desde dónde.
 * La bitácora la llena el servidor, no el navegador: así no se puede omitir ni
 * inventar una entrada.
 */
function registrarEnBitacora(PDO $pdo, string $accion, string $detalles, ?string $laboratorio = null, ?string $usuario = null): void {
    if ($usuario === null) {
        $sesion = usuarioEnSesion();
        $usuario = $sesion ? trim("{$sesion['nombre']} ({$sesion['rol']})") : 'Sin sesión';
    }
    $stmt = $pdo->prepare("INSERT INTO logs (accion, usuario, detalles, laboratorio, ip) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        mb_substr($accion, 0, 100),
        mb_substr($usuario, 0, 100),
        $detalles,
        $laboratorio,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Crea un aviso para el administrador (tabla notificaciones_admin). */
function notificarAdministrador(PDO $pdo, string $tipo, string $titulo, string $mensaje, ?string $laboratorio, string $prioridad = 'media', ?array $datos = null): void {
    $stmt = $pdo->prepare("INSERT INTO notificaciones_admin (tipo, titulo, mensaje, laboratorio, prioridad, datos) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tipo, mb_substr($titulo, 0, 150), $mensaje, $laboratorio, $prioridad, $datos === null ? null : json_encode($datos, JSON_UNESCAPED_UNICODE)]);
}
