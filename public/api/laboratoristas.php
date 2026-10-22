<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
        $stmt = $pdo->query("SELECT matricula, nombre, apellido, email FROM laboratoristas ORDER BY apellido, nombre");
        sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        break;

    case 'POST':
        requerirRol(ROLES_ADMIN);
        $data = getJsonInput();
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '');
        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $password = trim($data['Contraseña'] ?? $data['password'] ?? '');

        if (!$matricula || !$nombre || !$apellido) {
            sendResponse(["success" => false, "error" => "Matrícula, nombre y apellido son requeridos"], 400);
        }

        $existe = $pdo->prepare("SELECT COUNT(*) FROM laboratoristas WHERE matricula = ?");
        $existe->execute([$matricula]);
        $esNuevo = !$existe->fetchColumn();

        // Igual que en docentes.php: sin contraseña, el nuevo no se crea y el que se
        // edita conserva la suya (antes se le ponía '123456').
        if ($esNuevo || $password !== '') {
            $problema = problemaConContrasena($password);
            if ($problema) {
                sendResponse(["success" => false, "error" => $problema], 400);
            }
        }

        if ($esNuevo) {
            $stmt = $pdo->prepare("INSERT INTO laboratoristas (matricula, nombre, apellido, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$matricula, $nombre, $apellido, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } else {
            $stmt = $pdo->prepare("UPDATE laboratoristas SET nombre = ?, apellido = ?, email = ? WHERE matricula = ?");
            $stmt->execute([$nombre, $apellido, $email, $matricula]);
            if ($password !== '') {
                $stmt = $pdo->prepare("UPDATE laboratoristas SET password = ? WHERE matricula = ?");
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $matricula]);
            }
        }
        sendResponse(["success" => true, "message" => "Laboratorista guardado"]);
        break;

    case 'DELETE':
        requerirRol(ROLES_ADMIN);
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
