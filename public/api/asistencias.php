<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reglas.php';

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
            if (!empty($_GET['alumno_id'])) {
                $sql .= " AND alumno_id = ?";
                $params[] = $_GET['alumno_id'];
            }

            $sql .= " ORDER BY hora_entrada DESC LIMIT 500";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendResponse(["success" => true, "data" => $stmt->fetchAll()]);
        } else {
            // Asistencias activas en este momento, de un laboratorio o de una clase.
            $sql = "SELECT a.*, COALESCE(c.materia, m.nombre_materia) AS nombre_materia, c.practica
                    FROM asistencias a
                    LEFT JOIN clases c ON a.clase_id = c.id
                    LEFT JOIN materias m ON a.materia_id = m.id ";
            if (!empty($_GET['clase_id'])) {
                $stmt = $pdo->prepare($sql . "WHERE a.clase_id = ? ORDER BY a.apellido, a.nombre");
                $stmt->execute([(int)$_GET['clase_id']]);
            } else {
                $stmt = $pdo->prepare($sql . "WHERE a.laboratorio = ? ORDER BY a.apellido, a.nombre");
                $stmt->execute([$_GET['laboratorio'] ?? 'programacion']);
            }
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
                    "Practica" => $a['practica'] ?? '',
                    "ClaseId" => $a['clase_id'] === null ? null : (int)$a['clase_id'],
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

            registrarSalida($pdo, $asistencia);
            sendResponse(["success" => true, "message" => "Salida registrada con éxito"]);
        } else {
            // Registrar entrada (check-in). Es pública: el alumno no inicia sesión. Las
            // reglas (clase abierta, inscripción, equipo libre...) están en reglas.php.
            $alumnoId = trim($data['AlumnoId'] ?? $data['alumno_id'] ?? '');
            $equipoId = trim($data['Equipo'] ?? $data['equipo_id'] ?? '');
            $tipoUsuario = trim($data['TipoUsuario'] ?? $data['tipo_usuario'] ?? 'estudiante');

            if (!in_array($tipoUsuario, ['estudiante', 'docente', 'invitado'], true)) {
                sendResponse(["success" => false, "error" => "Tipo de usuario no válido"], 400);
            }
            // El invitado puede no tener matrícula: se le genera un identificador.
            if ((!$alumnoId && $tipoUsuario !== 'invitado') || !$equipoId) {
                sendResponse(["success" => false, "error" => "Matrícula/ID y Equipo son obligatorios"], 400);
            }

            $registro = registrarEntrada($pdo, [
                'alumno_id' => $alumnoId,
                'equipo_id' => $equipoId,
                'laboratorio' => laboratorioValido(trim($data['Laboratorio'] ?? $data['laboratorio'] ?? 'programacion')),
                'tipo_usuario' => $tipoUsuario,
                'nombre' => trim($data['Nombre'] ?? $data['nombre'] ?? ''),
                'apellido' => trim($data['Apellido'] ?? $data['apellido'] ?? ''),
                'clase_id' => !empty($data['ClaseId'] ?? $data['clase_id'] ?? null) ? (int)($data['ClaseId'] ?? $data['clase_id']) : null,
            ]);

            $persona = $registro['persona'];
            sendResponse([
                "success" => true,
                "message" => "Asistencia registrada correctamente",
                "id" => $registro['id'],
                "nombre" => trim("{$persona['nombre']} {$persona['apellido']}"),
                "clase" => [
                    "id" => (int)$registro['clase']['id'],
                    "tipo" => $registro['clase']['tipo'],
                    "materia" => $registro['clase']['materia'],
                    "practica" => $registro['clase']['practica'],
                    "docente" => $registro['clase']['docente'],
                ],
            ]);
        }
        break;

    case 'DELETE':
        // Quitar una entrada registrada por error: no pasa al histórico, pero queda en
        // la bitácora y el equipo se libera.
        requerirRol(ROLES_TODOS);
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendResponse(["success" => false, "error" => "ID requerido"], 400);
        }
        $stmt = $pdo->prepare("SELECT * FROM asistencias WHERE id = ?");
        $stmt->execute([$id]);
        $asistencia = $stmt->fetch();
        if ($asistencia) {
            enTransaccion($pdo, function (PDO $pdo) use ($asistencia) {
                $pdo->prepare("DELETE FROM asistencias WHERE id = ?")->execute([$asistencia['id']]);
                liberarEquipo($pdo, $asistencia['equipo_id'], $asistencia['laboratorio']);
            });
            registrarEnBitacora($pdo, "Eliminar Asistencia",
                "{$asistencia['nombre']} {$asistencia['apellido']} ({$asistencia['alumno_id']}), equipo {$asistencia['equipo_id']}", $asistencia['laboratorio']);
        }
        sendResponse(["success" => true, "message" => "Asistencia eliminada"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
