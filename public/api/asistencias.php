<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'activas';

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
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
            // Registrar salida de un usuario. La registra el personal desde la lista de
            // asistencia; sin sesión, cualquiera podía sacar a otro del laboratorio.
            requerirRol(ROLES_TODOS);
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
            // Registrar entrada (check-in). Es pública: el alumno no inicia sesión.
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
            if (!in_array($tipoUsuario, ['estudiante', 'docente', 'invitado'], true)) {
                sendResponse(["success" => false, "error" => "Tipo de usuario no válido"], 400);
            }

            // Los datos del alumno o docente salen de la base, no de lo que mande el
            // navegador: si no, cualquiera podía registrar asistencia con el nombre que
            // quisiera. Solo el invitado, que no está en la base, da su nombre.
            if ($tipoUsuario === 'estudiante') {
                $stmt = $pdo->prepare("SELECT nombre, apellido, carrera, semestre, turno, grupo FROM alumnos WHERE matricula = ?");
                $stmt->execute([$alumnoId]);
                $registrado = $stmt->fetch();
                if (!$registrado) {
                    sendResponse(["success" => false, "error" => "Matrícula no encontrada"], 404);
                }
                ['nombre' => $nombre, 'apellido' => $apellido, 'carrera' => $carrera,
                 'semestre' => $semestre, 'turno' => $turno, 'grupo' => $grupo] = $registrado;
            } elseif ($tipoUsuario === 'docente') {
                $stmt = $pdo->prepare("SELECT nombre, apellido FROM docentes WHERE matricula = ?");
                $stmt->execute([$alumnoId]);
                $registrado = $stmt->fetch();
                if (!$registrado) {
                    sendResponse(["success" => false, "error" => "Docente no encontrado"], 404);
                }
                ['nombre' => $nombre, 'apellido' => $apellido] = $registrado;
                $carrera = $semestre = $turno = $grupo = '';
            } elseif (!$nombre || !$apellido) {
                sendResponse(["success" => false, "error" => "Nombre y apellido son obligatorios para un invitado"], 400);
            }

            // Verificar que el equipo exista y no esté fuera de servicio. Antes, un equipo
            // que no existía se creaba al registrarse: cualquiera podía inventar equipos.
            $eqCheck = $pdo->prepare("SELECT fuera_de_servicio, en_uso FROM equipos WHERE id = ? AND laboratorio = ?");
            $eqCheck->execute([$equipoId, $lab]);
            $eq = $eqCheck->fetch();

            if (!$eq) {
                sendResponse(["success" => false, "error" => "El equipo $equipoId no existe en este laboratorio"], 400);
            }
            if ($eq['fuera_de_servicio']) {
                sendResponse(["success" => false, "error" => "El equipo $equipoId está fuera de servicio"], 400);
            }

            // Registrar asistencia activa
            $insStmt = $pdo->prepare("INSERT INTO asistencias (alumno_id, nombre, apellido, carrera, semestre, turno, grupo, equipo_id, laboratorio, tipo_usuario, materia_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insStmt->execute([$alumnoId, $nombre, $apellido, $carrera, $semestre, $turno, $grupo, $equipoId, $lab, $tipoUsuario, $materiaId ?: null]);
            $nuevoId = $pdo->lastInsertId();

            // Marcar equipo como en uso. 'personal' es el equipo propio de cada quien:
            // lo comparten muchos a la vez, así que no se marca.
            $nombreUsuario = trim("$nombre $apellido ($alumnoId)");
            $updEq = $pdo->prepare("UPDATE equipos SET en_uso = 1, usuario_actual = ? WHERE id = ? AND laboratorio = ? AND id <> 'personal'");
            $updEq->execute([$nombreUsuario, $equipoId, $lab]);

            sendResponse([
                "success" => true,
                "message" => "Asistencia registrada correctamente",
                "id" => $nuevoId
            ]);
        }
        break;

    case 'DELETE':
        requerirRol(ROLES_TODOS);
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
