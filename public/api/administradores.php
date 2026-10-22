<?php
require_once __DIR__ . '/db.php';

// Cuentas de administrador. Solo un administrador las maneja.

$usuario = requerirRol(ROLES_ADMIN);
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT id, matricula, nombre, apellido, email, rol FROM administradores ORDER BY apellido, nombre");
        sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        break;

    case 'POST':
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '') ?: null;
        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $password = trim($data['Contraseña'] ?? $data['password'] ?? '');

        if (!$nombre || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(["success" => false, "error" => "Nombre y un correo válido son requeridos"], 400);
        }

        // Igual que con docentes: el nuevo necesita contraseña y el que se edita sin
        // mandarla conserva la suya.
        if (!$id || $password !== '') {
            $problema = problemaConContrasena($password);
            if ($problema) {
                sendResponse(["success" => false, "error" => $problema], 400);
            }
        }

        try {
            if (!$id) {
                $stmt = $pdo->prepare("INSERT INTO administradores (matricula, nombre, apellido, email, password, rol) VALUES (?, ?, ?, ?, ?, 'admin')");
                $stmt->execute([$matricula, $nombre, $apellido, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)$pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare("UPDATE administradores SET matricula = ?, nombre = ?, apellido = ?, email = ? WHERE id = ?");
                $stmt->execute([$matricula, $nombre, $apellido, $email, $id]);
                if ($password !== '') {
                    $pdo->prepare("UPDATE administradores SET password = ? WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                sendResponse(["success" => false, "error" => "Ya hay otro administrador con ese correo o matrícula"], 409);
            }
            throw $e;
        }

        registrarEnBitacora($pdo, "Guardar Administrador", "$nombre $apellido ($email)");
        sendResponse(["success" => true, "message" => "Administrador guardado", "id" => $id]);
        break;

    case 'DELETE':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            sendResponse(["success" => false, "error" => "ID requerido"], 400);
        }
        // Que nunca se quede el sistema sin nadie que lo administre.
        if ((string)$id === $usuario['id']) {
            sendResponse(["success" => false, "error" => "No puedes borrar tu propia cuenta"], 409);
        }
        if ((int)$pdo->query("SELECT COUNT(*) FROM administradores")->fetchColumn() <= 1) {
            sendResponse(["success" => false, "error" => "Debe quedar al menos un administrador"], 409);
        }
        $stmt = $pdo->prepare("SELECT email FROM administradores WHERE id = ?");
        $stmt->execute([$id]);
        $email = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM administradores WHERE id = ?")->execute([$id]);
        if ($email !== false) {
            registrarEnBitacora($pdo, "Eliminar Administrador", $email);
        }
        sendResponse(["success" => true, "message" => "Administrador eliminado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
