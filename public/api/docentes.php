<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
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
        requerirRol(ROLES_ADMIN);
        $data = getJsonInput();
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '');
        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $email = trim($data['Email'] ?? $data['email'] ?? '');
        $password = trim($data['Contraseña'] ?? $data['password'] ?? '');
        $departamento = trim($data['Departamento'] ?? $data['departamento'] ?? '');

        if (!$matricula || !$nombre || !$apellido) {
            sendResponse(["success" => false, "error" => "Matrícula, nombre y apellido son requeridos"], 400);
        }

        $existe = $pdo->prepare("SELECT COUNT(*) FROM docentes WHERE matricula = ?");
        $existe->execute([$matricula]);
        $esNuevo = !$existe->fetchColumn();

        // Antes, si no llegaba contraseña se ponía '123456', también al editar: cambiar
        // el departamento de un docente le reemplazaba su contraseña por una conocida.
        // Ahora el docente nuevo la necesita, y al editar sin contraseña se conserva.
        if ($esNuevo || $password !== '') {
            $problema = problemaConContrasena($password);
            if ($problema) {
                sendResponse(["success" => false, "error" => $problema], 400);
            }
        }

        if ($esNuevo) {
            $stmt = $pdo->prepare("INSERT INTO docentes (matricula, nombre, apellido, email, password, departamento) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$matricula, $nombre, $apellido, $email, password_hash($password, PASSWORD_DEFAULT), $departamento]);
        } else {
            $stmt = $pdo->prepare("UPDATE docentes SET nombre = ?, apellido = ?, email = ?, departamento = ? WHERE matricula = ?");
            $stmt->execute([$nombre, $apellido, $email, $departamento, $matricula]);
            if ($password !== '') {
                $stmt = $pdo->prepare("UPDATE docentes SET password = ? WHERE matricula = ?");
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $matricula]);
            }
        }
        sendResponse(["success" => true, "message" => "Docente guardado"]);
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
        $stmt = $pdo->prepare("DELETE FROM docentes WHERE matricula = ?");
        $stmt->execute([$matricula]);
        sendResponse(["success" => true, "message" => "Docente eliminado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
