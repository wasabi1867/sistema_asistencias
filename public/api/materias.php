<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Las prácticas las maneja el administrador o el docente que imparte la materia.
 * Corta con 403 a cualquier otro, y con 404 si la materia no existe.
 */
function requerirPermisoSobrePracticas(PDO $pdo, array $usuario, string $materiaId): void {
    $stmt = $pdo->prepare("SELECT docente_id FROM materias WHERE id = ?");
    $stmt->execute([$materiaId]);
    $materia = $stmt->fetch();
    if (!$materia) {
        sendResponse(["success" => false, "error" => "Materia no encontrada"], 404);
    }
    $esSuDocente = $usuario['rol'] === 'docente' && $materia['docente_id'] === $usuario['id'];
    if ($usuario['rol'] !== 'admin' && !$esSuDocente) {
        sendResponse(["success" => false, "error" => "Solo el administrador o el docente de la materia pueden cambiar sus prácticas"], 403);
    }
}

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
        $materiaId = $_GET['id'] ?? null;
        $docenteId = $_GET['docente_id'] ?? null;
        $includePracticas = isset($_GET['con_practicas']);

        if ($materiaId) {
            $stmt = $pdo->prepare("SELECT m.*, CONCAT(d.nombre, ' ', d.apellido) as nombre_docente 
                                   FROM materias m 
                                   LEFT JOIN docentes d ON m.docente_id = d.matricula 
                                   WHERE m.id = ?");
            $stmt->execute([$materiaId]);
            $materia = $stmt->fetch();
            if ($materia) {
                $pStmt = $pdo->prepare("SELECT * FROM practicas WHERE materia_id = ? ORDER BY numero_practica");
                $pStmt->execute([$materiaId]);
                $materia['practicas'] = $pStmt->fetchAll();
                sendResponse(["success" => true, "data" => $materia]);
            } else {
                sendResponse(["success" => false, "error" => "Materia no encontrada"], 404);
            }
        } elseif ($docenteId) {
            $stmt = $pdo->prepare("SELECT m.*, CONCAT(d.nombre, ' ', d.apellido) as nombre_docente 
                                   FROM materias m 
                                   LEFT JOIN docentes d ON m.docente_id = d.matricula 
                                   WHERE m.docente_id = ? 
                                   ORDER BY m.nombre_materia");
            $stmt->execute([$docenteId]);
            $list = $stmt->fetchAll();
            sendResponse(["success" => true, "data" => $list]);
        } else {
            $stmt = $pdo->query("SELECT m.*, CONCAT(d.nombre, ' ', d.apellido) as nombre_docente 
                                 FROM materias m 
                                 LEFT JOIN docentes d ON m.docente_id = d.matricula 
                                 ORDER BY m.nombre_materia");
            $materias = $stmt->fetchAll();

            if ($includePracticas) {
                foreach ($materias as &$mat) {
                    $pStmt = $pdo->prepare("SELECT * FROM practicas WHERE materia_id = ? ORDER BY numero_practica");
                    $pStmt->execute([$mat['id']]);
                    $mat['practicas'] = $pStmt->fetchAll();
                }
            }

            sendResponse(["success" => true, "data" => $materias]);
        }
        break;

    case 'POST':
        $data = getJsonInput();
        $action = $_GET['action'] ?? 'crear_materia';

        if ($action === 'crear_practica') {
            $usuario = requerirRol(['admin', 'docente']);
            $materiaId = (string)($data['materia_id'] ?? '');
            $num = $data['numero_practica'] ?? '';
            $titulo = $data['titulo'] ?? '';
            $desc = $data['descripcion'] ?? '';
            $duracion = $data['duracion'] ?? '';

            if (!$materiaId || !$titulo) {
                sendResponse(["success" => false, "error" => "Materia y título requeridos"], 400);
            }
            requerirPermisoSobrePracticas($pdo, $usuario, $materiaId);
            $stmt = $pdo->prepare("INSERT INTO practicas (materia_id, numero_practica, titulo, descripcion, duracion) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$materiaId, $num, $titulo, $desc, $duracion]);
            sendResponse(["success" => true, "message" => "Práctica creada"]);
        } else {
            requerirRol(ROLES_ADMIN);
            $id = trim($data['id'] ?? $data['ID'] ?? uniqid('mat_'));
            $nombre = trim($data['nombre_materia'] ?? $data['NombreMateria'] ?? '');
            $docenteId = trim($data['docente_id'] ?? $data['MaestroID'] ?? '');
            $semestre = trim($data['semestre'] ?? $data['Semestre'] ?? '');
            $ciclo = trim($data['ciclo_escolar'] ?? $data['CicloEscolar'] ?? '');
            $lab = trim($data['laboratorio'] ?? $data['Laboratorio'] ?? 'programacion');

            if (!$nombre) {
                sendResponse(["success" => false, "error" => "Nombre de la materia requerido"], 400);
            }

            $stmt = $pdo->prepare("INSERT INTO materias (id, nombre_materia, docente_id, semestre, ciclo_escolar, laboratorio) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                nombre_materia = VALUES(nombre_materia), docente_id = VALUES(docente_id), 
                semestre = VALUES(semestre), ciclo_escolar = VALUES(ciclo_escolar), laboratorio = VALUES(laboratorio)");
            $stmt->execute([$id, $nombre, $docenteId ?: null, $semestre, $ciclo, $lab]);
            sendResponse(["success" => true, "message" => "Materia guardada", "id" => $id]);
        }
        break;

    case 'DELETE':
        $action = $_GET['action'] ?? 'materia';
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $data = getJsonInput();
            $id = $data['id'] ?? null;
        }

        if (!$id) {
            sendResponse(["success" => false, "error" => "ID requerido"], 400);
        }

        if ($action === 'practica') {
            $usuario = requerirRol(['admin', 'docente']);
            $stmt = $pdo->prepare("SELECT materia_id FROM practicas WHERE id = ?");
            $stmt->execute([$id]);
            $materiaId = $stmt->fetchColumn();
            if ($materiaId === false) {
                sendResponse(["success" => false, "error" => "Práctica no encontrada"], 404);
            }
            requerirPermisoSobrePracticas($pdo, $usuario, $materiaId);

            $stmt = $pdo->prepare("DELETE FROM practicas WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(["success" => true, "message" => "Práctica eliminada"]);
        } else {
            requerirRol(ROLES_ADMIN);
            $stmt = $pdo->prepare("DELETE FROM materias WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(["success" => true, "message" => "Materia eliminada"]);
        }
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
