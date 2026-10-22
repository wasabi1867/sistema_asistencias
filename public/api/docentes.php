<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $matricula = $_GET['matricula'] ?? null;
        if ($matricula) {
            $stmt = $pdo->prepare("SELECT matricula, nombre, apellido, email, departamento FROM docentes WHERE matricula = ?");
            $stmt->execute([$matricula]);
            $docente = $stmt->fetch();
            if ($docente) {
                sendResponse(["success" => true, "data" => $docente]);
            } else {
                sendResponse(["success" => false, "error" => "Docente no encontrado"], 404);
            }
        } else {
            $stmt = $pdo->query("SELECT matricula, nombre, apellido, email, departamento FROM docentes ORDER BY apellido, nombre");
            sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        }
        break;

    case 'POST':
        $data = getJsonInput();
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '');
        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $password = trim($data['Contraseña'] ?? $data['password'] ?? '123456');
        $departamento = trim($data['Departamento'] ?? $data['departamento'] ?? '');

        if (!$matricula || !$nombre || !$apellido) {
            sendResponse(["success" => false, "error" => "Matrícula, nombre y apellido son requeridos"], 400);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO docentes (matricula, nombre, apellido, email, password, departamento) 
            VALUES (?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            nombre = VALUES(nombre), apellido = VALUES(apellido), email = VALUES(email), 
            password = VALUES(password), departamento = VALUES(departamento)");
        $stmt->execute([$matricula, $nombre, $apellido, $email, $passwordHash, $departamento]);
        sendResponse(["success" => true, "message" => "Docente guardado"]);
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
        $stmt = $pdo->prepare("DELETE FROM docentes WHERE matricula = ?");
        $stmt->execute([$matricula]);
        sendResponse(["success" => true, "message" => "Docente eliminado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
