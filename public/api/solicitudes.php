<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $lab = $_GET['laboratorio'] ?? 'programacion';
        $estado = $_GET['estado'] ?? null;

        $sql = "SELECT * FROM solicitudes_ingreso WHERE laboratorio = ?";
        $params = [$lab];

        if ($estado) {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }

        $sql .= " ORDER BY fecha_solicitud DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        break;

    case 'POST':
        $data = getJsonInput();
        $alumnoId = trim($data['alumno_id'] ?? $data['AlumnoId'] ?? '');
        $nombre = trim($data['nombre'] ?? $data['Nombre'] ?? '');
        $carrera = trim($data['carrera'] ?? $data['Carrera'] ?? '');
        $motivo = trim($data['motivo'] ?? '');
        $lab = trim($data['laboratorio'] ?? 'programacion');

        if (!$alumnoId || !$motivo) {
            sendResponse(["success" => false, "error" => "Matrícula y motivo son requeridos"], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO solicitudes_ingreso (alumno_id, nombre, carrera, motivo, laboratorio, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->execute([$alumnoId, $nombre, $carrera, $motivo, $lab]);

        // Crear notificación admin automática
        $notifStmt = $pdo->prepare("INSERT INTO notificaciones_admin (tipo, titulo, mensaje, laboratorio, prioridad) VALUES (?, ?, ?, ?, 'alta')");
        $notifStmt->execute(['solicitud', "Nueva solicitud de acceso: $alumnoId", "El alumno $nombre solicitó acceso al laboratorio por: $motivo", $lab]);

        sendResponse(["success" => true, "message" => "Solicitud enviada al administrador"]);
        break;

    case 'PUT':
        $data = getJsonInput();
        $id = $data['id'] ?? null;
        $estado = $data['estado'] ?? null; // 'aprobada' | 'rechazada'

        if (!$id || !$estado) {
            sendResponse(["success" => false, "error" => "ID y estado son requeridos"], 400);
        }

        $stmt = $pdo->prepare("UPDATE solicitudes_ingreso SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        sendResponse(["success" => true, "message" => "Estado de solicitud actualizado a: $estado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
