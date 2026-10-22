<?php
require_once __DIR__ . '/db.php';

// Consulta de la bitácora (tabla logs). La llena el servidor en cada acción
// importante; aquí solo se lee. Solo el administrador.

requerirRol(ROLES_ADMIN);
$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}

$sql = "SELECT id, accion, usuario, detalles, laboratorio, ip, fecha FROM logs WHERE 1 = 1";
$params = [];
if (!empty($_GET['desde'])) {
    $sql .= " AND fecha >= ?";
    $params[] = $_GET['desde'];
}
if (!empty($_GET['hasta'])) {
    $sql .= " AND fecha < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $_GET['hasta'];
}
if (!empty($_GET['accion'])) {
    $sql .= " AND accion = ?";
    $params[] = $_GET['accion'];
}
if (!empty($_GET['laboratorio'])) {
    $sql .= " AND laboratorio = ?";
    $params[] = $_GET['laboratorio'];
}
if (!empty($_GET['q'])) {
    $sql .= " AND (usuario LIKE ? OR detalles LIKE ?)";
    $params[] = "%{$_GET['q']}%";
    $params[] = "%{$_GET['q']}%";
}
$limite = max(1, min(1000, (int)($_GET['limite'] ?? 200)));
$sql .= " ORDER BY fecha DESC, id DESC LIMIT $limite";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
