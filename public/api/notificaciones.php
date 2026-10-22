<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $lab = $_GET['laboratorio'] ?? null;
        $tipo = $_GET['tipo'] ?? null;
        // Los avisos de equipos dañados también los sigue el laboratorista, que es quien
        // atiende la reparación. Los demás avisos son solo para el administrador.
        requerirRol($tipo === 'equipo' ? ROLES_PERSONAL : ROLES_ADMIN);

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
        // El estado de la reparación vive en el equipo, no en el aviso: se agrega aquí
        // el que tiene el equipo ahora, para que todos los avisos de ese equipo lo muestren igual.
        $estadoDeEquipo = $pdo->prepare("SELECT estado_reparacion, comentario_estado, actualizado_en FROM equipos WHERE id = ? AND laboratorio = ?");
        foreach ($notifs as &$n) {
            $n['leida'] = (bool)$n['leida'];
            $n['datos'] = json_decode($n['datos'] ?? 'null', true);
            if ($n['tipo'] === 'equipo' && isset($n['datos']['equipoId'])) {
                $estadoDeEquipo->execute([(string)$n['datos']['equipoId'], $n['laboratorio'] ?? 'programacion']);
                if ($equipo = $estadoDeEquipo->fetch()) {
                    $n['estadoEquipo'] = $equipo['estado_reparacion'] === 'ninguno' ? null : $equipo['estado_reparacion'];
                    $n['comentarioEstado'] = $equipo['comentario_estado'];
                    $n['fechaActualizacionEstado'] = $equipo['actualizado_en'];
                }
            }
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
        // Por omisión se marca como leída; con "leida": false vuelve a no leída.
        $leida = array_key_exists('leida', $data) && !$data['leida'] ? 0 : 1;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE notificaciones_admin SET leida = ? WHERE id = ?");
            $stmt->execute([$leida, $id]);
        } else {
            // Marcar todas como leídas
            $pdo->query("UPDATE notificaciones_admin SET leida = 1");
        }

        sendResponse(["success" => true, "message" => "Notificación marcada como leída"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
