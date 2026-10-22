<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'activas';

switch ($method) {
    case 'GET':
        if ($action === 'historico') {
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $carrera = $_GET['carrera'] ?? null;
            $materia = $_GET['materia'] ?? null;
            $laboratorio = $_GET['laboratorio'] ?? 'programacion';

            $sql = "SELECT * FROM asistencias_historico WHERE laboratorio = ?";
            $params = [$laboratorio];

            if ($fechaInicio && $fechaFin) {
                $sql .= " AND fecha BETWEEN ? AND ?";
                $params[] = $fechaInicio;
                $params[] = $fechaFin;
            } elseif ($fechaInicio) {
                $sql .= " AND fecha = ?";
                $params[] = $fechaInicio;
            }

            if ($carrera) {
                $sql .= " AND carrera = ?";
                $params[] = $carrera;
            }
            if ($materia) {
                $sql .= " AND materia LIKE ?";
                $params[] = "%$materia%";
            }

            $sql .= " ORDER BY hora_entrada DESC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        } else {
            // Asistencias activas en este momento
            $lab = $_GET['laboratorio'] ?? 'programacion';
            $stmt = $pdo->prepare("SELECT a.*, 
                                   m.nombre_materia 
                                   FROM asistencias a 
                                   LEFT JOIN materias m ON a.materia_id = m.id 
                                   WHERE a.laboratorio = ? 
                                   ORDER BY a.hora_entrada DESC");
            $stmt->execute([$lab]);
            $activas = $stmt->fetchAll();

            $formateadas = array_map(function($a) {
                return [
                    "id" => (string)$a['id'],
                    "AlumnoId" => $a['alumno_id'],
                    "Nombre" => $a['nombre'],
                    "Apellido" => $a['apellido'],
                    "Carrera" => $a['carrera'],
                    "Semestre" => $a['semestre'],
                    "Turno" => $a['turno'],
                    "Grupo" => $a['grupo'],
                    "Equipo" => $a['equipo_id'],
                    "Materia" => $a['nombre_materia'] ?? '',
                    "TipoUsuario" => $a['tipo_usuario'],
                    "Laboratorio" => $a['laboratorio'],
                    "Fecha" => $a['hora_entrada']
                ];
            }, $activas);

            sendResponse(["success" => true, "data" => $formateadas]);
        }
        break;

    case 'POST':
        $data = getJsonInput();

        if ($action === 'salida') {
            // Registrar salida de un usuario
            $asistenciaId = $data['id'] ?? null;
            $equipoId = $data['equipo'] ?? $data['equipo_id'] ?? null;
            $lab = $data['laboratorio'] ?? 'programacion';

            $asistencia = null;
            if ($asistenciaId) {
                $stmt = $pdo->prepare("SELECT * FROM asistencias WHERE id = ?");
                $stmt->execute([$asistenciaId]);
                $asistencia = $stmt->fetch();
            } elseif ($equipoId) {
                $stmt = $pdo->prepare("SELECT * FROM asistencias WHERE equipo_id = ? AND laboratorio = ? ORDER BY hora_entrada DESC LIMIT 1");
                $stmt->execute([$equipoId, $lab]);
                $asistencia = $stmt->fetch();
            }

            if (!$asistencia) {
                sendResponse(["success" => false, "error" => "No se encontró registro de asistencia activo"], 404);
            }

            // Pasar a histórico
            $nombreCompleto = trim($asistencia['nombre'] . ' ' . $asistencia['apellido']);
            $fechaActual = date('Y-m-d');
            $horaSalida = date('Y-m-d H:i:s');

            $histStmt = $pdo->prepare("INSERT INTO asistencias_historico 
                (alumno_id, nombre_completo, carrera, semestre, turno, grupo, materia, docente, equipo_id, laboratorio, tipo_usuario, hora_entrada, hora_salida, fecha) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $histStmt->execute([
                $asistencia['alumno_id'],
                $nombreCompleto,
                $asistencia['carrera'],
                $asistencia['semestre'],
                $asistencia['turno'],
                $asistencia['grupo'],
                $asistencia['materia_id'] ?? '',
                '', // Docente
                $asistencia['equipo_id'],
                $asistencia['laboratorio'],
                $asistencia['tipo_usuario'],
                $asistencia['hora_entrada'],
                $horaSalida,
                $fechaActual
            ]);

            // Eliminar de asistencias activas
            $delStmt = $pdo->prepare("DELETE FROM asistencias WHERE id = ?");
            $delStmt->execute([$asistencia['id']]);

            // Liberar equipo
            $eqStmt = $pdo->prepare("UPDATE equipos SET en_uso = 0, usuario_actual = NULL WHERE id = ? AND laboratorio = ?");
            $eqStmt->execute([$asistencia['equipo_id'], $asistencia['laboratorio']]);

            sendResponse(["success" => true, "message" => "Salida registrada con éxito"]);
        } else {
            // Registrar entrada (check-in)
            $alumnoId = trim($data['AlumnoId'] ?? $data['alumno_id'] ?? '');
            $nombre = trim($data['Nombre'] ?? $data['nombre'] ?? '');
            $apellido = trim($data['Apellido'] ?? $data['apellido'] ?? '');
            $carrera = trim($data['Carrera'] ?? $data['carrera'] ?? '');
            $semestre = trim($data['Semestre'] ?? $data['semestre'] ?? '');
            $turno = trim($data['Turno'] ?? $data['turno'] ?? '');
            $grupo = trim($data['Grupo'] ?? $data['grupo'] ?? '');
            $equipoId = trim($data['Equipo'] ?? $data['equipo_id'] ?? '');
            $materiaId = trim($data['MateriaId'] ?? $data['materia_id'] ?? '');
            $lab = trim($data['Laboratorio'] ?? $data['laboratorio'] ?? 'programacion');
            $tipoUsuario = trim($data['TipoUsuario'] ?? $data['tipo_usuario'] ?? 'estudiante');

            if (!$alumnoId || !$equipoId) {
                sendResponse(["success" => false, "error" => "Matrícula/ID y Equipo son obligatorios"], 400);
            }

            // Verificar si el equipo está fuera de servicio
            $eqCheck = $pdo->prepare("SELECT fuera_de_servicio, en_uso FROM equipos WHERE id = ? AND laboratorio = ?");
            $eqCheck->execute([$equipoId, $lab]);
            $eq = $eqCheck->fetch();

            if ($eq && $eq['fuera_de_servicio']) {
                sendResponse(["success" => false, "error" => "El equipo $equipoId está fuera de servicio"], 400);
            }

            // Registrar asistencia activa
            $insStmt = $pdo->prepare("INSERT INTO asistencias (alumno_id, nombre, apellido, carrera, semestre, turno, grupo, equipo_id, laboratorio, tipo_usuario, materia_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insStmt->execute([$alumnoId, $nombre, $apellido, $carrera, $semestre, $turno, $grupo, $equipoId, $lab, $tipoUsuario, $materiaId ?: null]);
            $nuevoId = $pdo->lastInsertId();

            // Marcar equipo como en uso
            $nombreUsuario = trim("$nombre $apellido ($alumnoId)");
            $updEq = $pdo->prepare("INSERT INTO equipos (id, laboratorio, fuera_de_servicio, en_uso, usuario_actual) 
                VALUES (?, ?, 0, 1, ?) 
                ON DUPLICATE KEY UPDATE en_uso = 1, usuario_actual = VALUES(usuario_actual)");
            $updEq->execute([$equipoId, $lab, $nombreUsuario]);

            sendResponse([
                "success" => true,
                "message" => "Asistencia registrada correctamente",
                "id" => $nuevoId
            ]);
        }
        break;

    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendResponse(["success" => false, "error" => "ID requerido"], 400);
        }
        $stmt = $pdo->prepare("DELETE FROM asistencias WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(["success" => true, "message" => "Asistencia eliminada"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
