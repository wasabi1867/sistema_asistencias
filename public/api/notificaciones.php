<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requerirRol(ROLES_ADMIN);
        $lab = $_GET['laboratorio'] ?? null;
        $tipo = $_GET['tipo'] ?? null;

        $sql = "SELECT * FROM notificaciones_admin WHERE 1=1";
        $params = [];

        if ($lab) {
            $sql .= " AND (laboratorio = ? OR laboratorio IS NULL)";
            $params[] = $lab;
        }
        if ($tipo) {
            $sql .= " AND tipo = ?";
            $params[] = $tipo;
        }

        $sql .= " ORDER BY fecha DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $notifs = $stmt->fetchAll();
        foreach ($notifs as &$n) {
            $n['leida'] = (bool)$n['leida'];
            $n['datos'] = json_decode($n['datos'] ?? 'null', true);
        }

        sendResponse(["success" => true, "data" => $notifs]);
        break;

    case 'POST':
        requerirRol(ROLES_TODOS);
        $data = getJsonInput();
        $tipo = $data['tipo'] ?? 'general';
        $titulo = $data['titulo'] ?? '';
        $mensaje = $data['mensaje'] ?? '';
        $lab = $data['laboratorio'] ?? 'programacion';
        $prioridad = $data['prioridad'] ?? 'media';
        $datosExtra = json_encode($data['datos'] ?? null);

        if (!$titulo || !$mensaje) {
            sendResponse(["success" => false, "error" => "Título y mensaje requeridos"], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO notificaciones_admin (tipo, titulo, mensaje, laboratorio, prioridad, datos) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tipo, $titulo, $mensaje, $lab, $prioridad, $datosExtra]);

        sendResponse(["success" => true, "message" => "Notificación creada"]);
        break;

    case 'PUT':
        requerirRol(ROLES_ADMIN);
        $data = getJsonInput();
        $id = $data['id'] ?? null;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE notificaciones_admin SET leida = 1 WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            // Marcar todas como leídas
            $pdo->query("UPDATE notificaciones_admin SET leida = 1");
        }

        sendResponse(["success" => true, "message" => "Notificación marcada como leída"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
