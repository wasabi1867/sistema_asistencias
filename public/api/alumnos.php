<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
        $matricula = $_GET['matricula'] ?? null;
        $search = $_GET['q'] ?? null;

        if ($matricula) {
            $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE matricula = ?");
            $stmt->execute([$matricula]);
            $alumno = $stmt->fetch();
            if ($alumno) {
                $alumno['materias'] = json_decode($alumno['materias'] ?? '[]', true);
                sendResponse(["success" => true, "data" => $alumno]);
            } else {
                sendResponse(["success" => false, "error" => "Alumno no encontrado"], 404);
            }
        } elseif ($search) {
            $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE matricula LIKE ? OR nombre LIKE ? OR apellido LIKE ? ORDER BY apellido, nombre");
            $term = "%$search%";
            $stmt->execute([$term, $term, $term]);
            $list = $stmt->fetchAll();
            foreach ($list as &$item) {
                $item['materias'] = json_decode($item['materias'] ?? '[]', true);
            }
            sendResponse(["success" => true, "data" => $list]);
        } else {
            $stmt = $pdo->query("SELECT * FROM alumnos ORDER BY apellido, nombre");
            $list = $stmt->fetchAll();
            foreach ($list as &$item) {
                $item['materias'] = json_decode($item['materias'] ?? '[]', true);
            }
            sendResponse(["success" => true, "data" => $list]);
        }
        break;

    case 'POST':
        requerirRol(ROLES_PERSONAL);
        $data = getJsonInput();

        if (($_GET['action'] ?? '') === 'cambio_masivo') {
            // Al pasar de semestre: cambia el semestre (o el grupo) de todos los alumnos
            // de una carrera y semestre de una sola vez.
            $carrera = trim($data['carrera'] ?? '');
            $semestre = trim((string)($data['semestre'] ?? ''));
            $grupo = trim($data['grupo'] ?? '');
            $nuevoSemestre = trim((string)($data['nuevo_semestre'] ?? ''));
            $nuevoGrupo = trim($data['nuevo_grupo'] ?? '');
            if (!$carrera || $semestre === '' || ($nuevoSemestre === '' && $nuevoGrupo === '')) {
                sendResponse(["success" => false, "error" => "Carrera, semestre y el nuevo semestre o grupo son requeridos"], 400);
            }
            $sql = "UPDATE alumnos SET " . ($nuevoSemestre !== '' ? "semestre = ?" : "grupo = ?") . " WHERE carrera = ? AND semestre = ?";
            $params = [$nuevoSemestre !== '' ? $nuevoSemestre : $nuevoGrupo, $carrera, $semestre];
            if ($grupo !== '') {
                $sql .= " AND grupo = ?";
                $params[] = $grupo;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cambiados = $stmt->rowCount();
            registrarEnBitacora($pdo, "Cambio Masivo de Alumnos",
                "$carrera, semestre $semestre" . ($grupo ? ", grupo $grupo" : "") . " -> " . ($nuevoSemestre !== '' ? "semestre $nuevoSemestre" : "grupo $nuevoGrupo") . ". Alumnos: $cambiados");
            sendResponse(["success" => true, "message" => "Se actualizaron $cambiados alumnos", "actualizados" => $cambiados]);
        }
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '');
        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $carrera = trim($data['Carrera'] ?? $data['carrera'] ?? '');
        $semestre = trim($data['Semestre'] ?? $data['semestre'] ?? '');
        $turno = trim($data['Turno'] ?? $data['turno'] ?? '');
        $grupo = trim($data['Grupo'] ?? $data['grupo'] ?? '');
        $materias = json_encode($data['Materias'] ?? $data['materias'] ?? []);

        if (!$matricula || !$nombre || !$apellido) {
            sendResponse(["success" => false, "error" => "Matrícula, nombre y apellido son requeridos"], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO alumnos (matricula, nombre, apellido, carrera, semestre, turno, grupo, materias) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            nombre = VALUES(nombre), apellido = VALUES(apellido), carrera = VALUES(carrera), 
            semestre = VALUES(semestre), turno = VALUES(turno), grupo = VALUES(grupo), materias = VALUES(materias)");
        $stmt->execute([$matricula, $nombre, $apellido, $carrera, $semestre, $turno, $grupo, $materias]);
        sendResponse(["success" => true, "message" => "Alumno guardado correctamente", "matricula" => $matricula]);
        break;

    case 'PUT':
        requerirRol(ROLES_PERSONAL);
        $data = getJsonInput();
        $matricula = trim($data['Matricula'] ?? $data['matricula'] ?? '');
        if (!$matricula) {
            sendResponse(["success" => false, "error" => "Matrícula requerida"], 400);
        }

        $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
        $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
        $carrera = trim($data['Carrera'] ?? $data['carrera'] ?? '');
        $semestre = trim($data['Semestre'] ?? $data['semestre'] ?? '');
        $turno = trim($data['Turno'] ?? $data['turno'] ?? '');
        $grupo = trim($data['Grupo'] ?? $data['grupo'] ?? '');
        $materias = json_encode($data['Materias'] ?? $data['materias'] ?? []);

        $stmt = $pdo->prepare("UPDATE alumnos SET nombre=?, apellido=?, carrera=?, semestre=?, turno=?, grupo=?, materias=? WHERE matricula=?");
        $stmt->execute([$nombre, $apellido, $carrera, $semestre, $turno, $grupo, $materias, $matricula]);
        sendResponse(["success" => true, "message" => "Alumno actualizado correctamente"]);
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

        $stmt = $pdo->prepare("DELETE FROM alumnos WHERE matricula = ?");
        $stmt->execute([$matricula]);
        if ($stmt->rowCount()) {
            registrarEnBitacora($pdo, "Eliminar Alumno", (string)$matricula);
        }
        sendResponse(["success" => true, "message" => "Alumno eliminado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
