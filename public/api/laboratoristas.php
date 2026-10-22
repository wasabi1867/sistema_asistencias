<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT matricula, nombre, apellido, email FROM laboratoristas ORDER BY apellido, nombre");
        sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        break;

    case 'POST':
        $data = getJsonInput();
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '');
        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $password = trim($data['Contraseña'] ?? $data['password'] ?? '123456');

        if (!$matricula || !$nombre || !$apellido) {
            sendResponse(["success" => false, "error" => "Matrícula, nombre y apellido son requeridos"], 400);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO laboratoristas (matricula, nombre, apellido, email, password) 
            VALUES (?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            nombre = VALUES(nombre), apellido = VALUES(apellido), email = VALUES(email), password = VALUES(password)");
        $stmt->execute([$matricula, $nombre, $apellido, $email, $passwordHash]);
        sendResponse(["success" => true, "message" => "Laboratorista guardado"]);
        break;

    case 'DELETE':
        $matricula = $_GET['matricula'] ?? null;
        if (!$matricula) {
            $data = getJsonInput();
            $matricula = $data['matricula'] ?? null;
        }
        if (!$matricula) {
            sendResponse(["success" => false, "error" => "Matrícula requerida"], 400);
        }
        $stmt = $pdo->prepare("DELETE FROM laboratoristas WHERE matricula = ?");
        $stmt->execute([$matricula]);
        sendResponse(["success" => true, "message" => "Laboratorista eliminado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
