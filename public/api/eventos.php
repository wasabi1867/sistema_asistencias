<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reglas.php';

// Eventos del laboratorio (EventosLaboratorio en Firebase): talleres, concursos,
// visitas... Los registra el personal del laboratorio.

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
        $stmt = $pdo->prepare("SELECT * FROM eventos_laboratorio WHERE laboratorio = ? ORDER BY creado_en DESC, id DESC");
        $stmt->execute([$_GET['laboratorio'] ?? 'programacion']);
        $eventos = $stmt->fetchAll();
        foreach ($eventos as &$e) {
            $e['participantes'] = (int)$e['participantes'];
        }
        sendResponse(["success" => true, "data" => $eventos]);
        break;

    case 'POST':
        requerirRol(ROLES_PERSONAL);
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);
        $nombre = trim($data['nombre'] ?? '');
        $fecha = trim($data['fecha'] ?? '');
        if (!$nombre) {
            sendResponse(["success" => false, "error" => "El nombre del evento es obligatorio"], 400);
        }
        if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            sendResponse(["success" => false, "error" => "La fecha debe tener el formato AAAA-MM-DD"], 400);
        }
        $valores = [
            $nombre,
            trim($data['tipo'] ?? ''),
            trim($data['organizador'] ?? ''),
            max(0, (int)($data['participantes'] ?? 0)),
            $fecha ?: null,
            trim($data['hora'] ?? ''),
            trim($data['duracion'] ?? ''),
            trim($data['descripcion'] ?? ''),
            trim($data['resultados'] ?? ''),
        ];

        if ($id) {
            $stmt = $pdo->prepare("UPDATE eventos_laboratorio SET nombre = ?, tipo = ?, organizador = ?, participantes = ?, fecha = ?, hora = ?, duracion = ?, descripcion = ?, resultados = ? WHERE id = ?");
            $stmt->execute([...$valores, $id]);
        } else {
            $laboratorio = laboratorioValido(trim($data['laboratorio'] ?? 'programacion'));
            $stmt = $pdo->prepare("INSERT INTO eventos_laboratorio (nombre, tipo, organizador, participantes, fecha, hora, duracion, descripcion, resultados, laboratorio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([...$valores, $laboratorio]);
            $id = (int)$pdo->lastInsertId();
        }
        registrarEnBitacora($pdo, "Guardar Evento", $nombre, $data['laboratorio'] ?? null);
        sendResponse(["success" => true, "message" => "Evento guardado", "id" => $id]);
        break;

    case 'DELETE':
        requerirRol(ROLES_PERSONAL);
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            sendResponse(["success" => false, "error" => "ID requerido"], 400);
        }
        $pdo->prepare("DELETE FROM eventos_laboratorio WHERE id = ?")->execute([$id]);
        registrarEnBitacora($pdo, "Eliminar Evento", "Evento $id");
        sendResponse(["success" => true, "message" => "Evento eliminado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
