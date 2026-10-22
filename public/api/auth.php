<?php
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
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

try {
    if ($role === 'admin') {
        $stmt = $pdo->prepare("SELECT id, email, nombre, password, rol FROM administradores WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $user['password'] === $password)) {
            unset($user['password']);
            sendResponse([
                "success" => true,
                "user" => $user,
                "role" => "admin"
            ]);
        }
    } elseif ($role === 'laboratorista') {
        $stmt = $pdo->prepare("SELECT matricula, nombre, apellido, email, password FROM laboratoristas WHERE email = ? OR matricula = ? LIMIT 1");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $user['password'] === $password)) {
            unset($user['password']);
            sendResponse([
                "success" => true,
                "user" => $user,
                "role" => "laboratorista"
            ]);
        }
    } elseif ($role === 'docente') {
        $stmt = $pdo->prepare("SELECT matricula, nombre, apellido, email, password, departamento FROM docentes WHERE email = ? OR matricula = ? LIMIT 1");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $user['password'] === $password)) {
            unset($user['password']);
            sendResponse([
                "success" => true,
                "user" => $user,
                "role" => "docente"
            ]);
        }
    }

    sendResponse(["success" => false, "error" => "Credenciales incorrectas"], 401);
} catch (Exception $e) {
    sendResponse(["success" => false, "error" => $e->getMessage()], 500);
}
