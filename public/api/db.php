<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sesion.php';

// Un aviso de PHP impreso en la respuesta rompe el JSON y puede mostrar rutas del
// servidor; los errores van al registro, no al cliente.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cualquier excepción que una ruta no atrape termina aquí: se registra completa y al
// cliente solo le llega un mensaje genérico (antes se devolvía el mensaje de la base
// de datos, con nombres de tablas y columnas).
set_exception_handler(function (Throwable $e) {
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
