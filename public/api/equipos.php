<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $lab = $_GET['laboratorio'] ?? 'programacion';
            $stmt = $pdo->prepare("SELECT id, laboratorio, fuera_de_servicio, en_uso, usuario_actual, notas, estado_reparacion, comentario_estado, actualizado_en 
                                   FROM equipos 
                                   WHERE laboratorio = ? 
                                   ORDER BY 
                                   CASE 
                                     WHEN id = 'personal' THEN 0 
                                     ELSE 1 
                                   END, 
                                   id");
            $stmt->execute([$lab]);
            $equipos = $stmt->fetchAll();

            // Si no existen equipos para este laboratorio, inicializamos automáticamente PC-1 a PC-25
            if (empty($equipos)) {
                $insert = $pdo->prepare("INSERT INTO equipos (id, laboratorio, fuera_de_servicio, en_uso) VALUES (?, ?, 0, 0) ON DUPLICATE KEY UPDATE laboratorio = laboratorio");
                $insert->execute(['personal', $lab]);
                for ($i = 1; $i <= 25; $i++) {
                    $insert->execute(["PC-$i", $lab]);
                }
                $stmt->execute([$lab]);
                $equipos = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            sendResponse(["success" => false, "error" => $e->getMessage()], 500);
        }

        // Formatear booleanos para compatibilidad con la app React
        $formateados = array_map(function($e) {
            return [
                "id" => $e['id'],
                "laboratorio" => $e['laboratorio'],
                "fueraDeServicio" => (bool)$e['fuera_de_servicio'],
                "enUso" => (bool)$e['en_uso'],
                "usuarioActual" => $e['usuario_actual'],
                "notas" => $e['notas'] ?? '',
                "estadoReparacion" => $e['estado_reparacion'] ?? 'ninguno',
                "comentarioEstado" => $e['comentario_estado'] ?? '',
                "ultimaActualizacion" => $e['actualizado_en']
            ];
        }, $equipos);

        sendResponse(["success" => true, "data" => $formateados]);
        break;

    case 'POST':
    case 'PUT':
        $data = getJsonInput();
        $id = $data['id'] ?? '';
        $lab = $data['laboratorio'] ?? 'programacion';

        if (!$id) {
            sendResponse(["success" => false, "error" => "ID de equipo requerido"], 400);
        }

        $fields = [];
        $params = [];

        if (isset($data['fueraDeServicio'])) {
            $fields[] = "fuera_de_servicio = ?";
            $params[] = $data['fueraDeServicio'] ? 1 : 0;
        }
        if (isset($data['enUso'])) {
            $fields[] = "en_uso = ?";
            $params[] = $data['enUso'] ? 1 : 0;
        }
        if (array_key_exists('usuarioActual', $data)) {
            $fields[] = "usuario_actual = ?";
            $params[] = $data['usuarioActual'];
        }
        if (array_key_exists('notas', $data)) {
            $fields[] = "notas = ?";
            $params[] = $data['notas'];
        }
        if (isset($data['estadoReparacion'])) {
            $fields[] = "estado_reparacion = ?";
            $params[] = $data['estadoReparacion'];
        }
        if (array_key_exists('comentarioEstado', $data)) {
            $fields[] = "comentario_estado = ?";
            $params[] = $data['comentarioEstado'];
        }

        if (empty($fields)) {
            sendResponse(["success" => false, "error" => "No se enviaron campos para actualizar"], 400);
        }

        $sql = "UPDATE equipos SET " . implode(", ", $fields) . " WHERE id = ? AND laboratorio = ?";
        $params[] = $id;
        $params[] = $lab;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        sendResponse(["success" => true, "message" => "Equipo actualizado correctamente"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
