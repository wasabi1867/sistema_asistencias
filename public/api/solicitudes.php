<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reglas.php';

// Solicitudes de ingreso: el alumno que no está inscrito en la materia de la clase
// abierta pide entrar. El administrador la aprueba por esa sola vez (temporal: queda
// registrado en la clase), inscribiéndolo en la materia (permanente), o la rechaza.

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        requerirRol(ROLES_TODOS);
        $lab = $_GET['laboratorio'] ?? 'programacion';
        $estado = $_GET['estado'] ?? null;

        $sql = "SELECT s.*, c.materia AS materia_nombre, c.docente AS docente_nombre, a.apellido AS alumno_apellido,
                       a.semestre AS alumno_semestre, a.grupo AS alumno_grupo, a.turno AS alumno_turno
                FROM solicitudes_ingreso s
                LEFT JOIN clases c ON c.id = s.clase_id
                LEFT JOIN alumnos a ON a.matricula = s.alumno_id
                WHERE s.laboratorio = ?";
        $params = [$lab];

        if ($estado) {
            $sql .= " AND s.estado = ?";
            $params[] = $estado;
        }

        $sql .= " ORDER BY s.fecha_solicitud DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $solicitudes = $stmt->fetchAll();
        foreach ($solicitudes as &$s) {
            $s['leida'] = (bool)$s['leida'];
        }
        sendResponse(["success" => true, "data" => $solicitudes]);
        break;

    case 'POST':
        // Pública: la manda el alumno desde la página de registro.
        $data = getJsonInput();
        $alumnoId = trim($data['alumno_id'] ?? $data['AlumnoId'] ?? '');
        $lab = laboratorioValido(trim($data['laboratorio'] ?? 'programacion'));
        $equipo = trim($data['equipo'] ?? $data['equipo_id'] ?? '');
        $motivo = trim($data['motivo'] ?? '');

        if (!$alumnoId) {
            sendResponse(["success" => false, "error" => "Matrícula requerida"], 400);
        }

        // Los datos del alumno y de la clase salen de la base, no del navegador.
        $stmt = $pdo->prepare("SELECT nombre, apellido, carrera FROM alumnos WHERE matricula = ?");
        $stmt->execute([$alumnoId]);
        $alumno = $stmt->fetch();
        if (!$alumno) {
            sendResponse(["success" => false, "error" => "Matrícula no encontrada"], 404);
        }
        $clase = claseParaRegistrarse($pdo, $lab, !empty($data['clase_id']) ? (int)$data['clase_id'] : null);
        if ($clase['tipo'] !== 'regular') {
            sendResponse(["success" => false, "error" => "En la clase de maestro invitado no hace falta solicitar ingreso"], 409);
        }

        // Una sola solicitud pendiente por alumno y clase: volver a apretar el botón no
        // le llena la bandeja al administrador.
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_ingreso WHERE alumno_id = ? AND clase_id = ? AND estado = 'pendiente'");
        $stmt->execute([$alumnoId, $clase['id']]);
        if ($stmt->fetchColumn()) {
            sendResponse(["success" => false, "error" => "Ya enviaste una solicitud para esta clase; espera la respuesta del administrador"], 409);
        }

        $nombre = trim("{$alumno['nombre']} {$alumno['apellido']}");
        $motivo = $motivo ?: "Solicita ingreso a la clase de {$clase['materia']}";
        $stmt = $pdo->prepare("INSERT INTO solicitudes_ingreso (alumno_id, nombre, carrera, motivo, laboratorio, clase_id, materia_id, equipo_id, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')");
        $stmt->execute([$alumnoId, $nombre, $alumno['carrera'], $motivo, $lab, $clase['id'], $clase['materia_id'], $equipo]);
        $solicitudId = (int)$pdo->lastInsertId();

        // Crear notificación admin automática
        notificarAdministrador($pdo, 'solicitud', "Nueva solicitud de acceso: $alumnoId",
            "$nombre solicitó acceso a la clase de {$clase['materia']}: $motivo", $lab, 'alta',
            ["solicitudId" => $solicitudId, "claseId" => (int)$clase['id']]);

        sendResponse(["success" => true, "message" => "Solicitud enviada al administrador", "id" => $solicitudId]);
        break;

    case 'PUT':
        requerirRol(ROLES_ADMIN);
        $data = getJsonInput();
        $id = $data['id'] ?? null;
        $estado = $data['estado'] ?? null;

        if (!$id) {
            sendResponse(["success" => false, "error" => "ID requerido"], 400);
        }
        $stmt = $pdo->prepare("SELECT * FROM solicitudes_ingreso WHERE id = ?");
        $stmt->execute([$id]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            sendResponse(["success" => false, "error" => "Solicitud no encontrada"], 404);
        }

        // Solo marcarla como leída.
        if (!$estado && isset($data['leida'])) {
            $pdo->prepare("UPDATE solicitudes_ingreso SET leida = ? WHERE id = ?")->execute([$data['leida'] ? 1 : 0, $id]);
            sendResponse(["success" => true, "message" => "Solicitud actualizada"]);
        }

        // 'aprobada' es el nombre de antes; equivale a la aprobación temporal.
        if ($estado === 'aprobada') {
            $estado = 'aprobado_temporal';
        }
        if (!in_array($estado, ['aprobado_temporal', 'aprobado_permanente', 'rechazada'], true)) {
            sendResponse(["success" => false, "error" => "Estado no válido"], 400);
        }
        if ($solicitud['estado'] !== 'pendiente') {
            sendResponse(["success" => false, "error" => "La solicitud ya fue resuelta"], 409);
        }

        enTransaccion($pdo, function (PDO $pdo) use ($solicitud, $estado) {
            if ($estado === 'aprobado_temporal') {
                // Entra a la clase por esta vez, con el equipo que eligió; si ya no
                // está libre o no eligió, con su equipo propio.
                $datos = [
                    'alumno_id' => $solicitud['alumno_id'],
                    'equipo_id' => $solicitud['equipo_id'] ?: 'personal',
                    'laboratorio' => $solicitud['laboratorio'],
                    'tipo_usuario' => 'estudiante',
                    'nombre' => '', 'apellido' => '',
                    'clase_id' => $solicitud['clase_id'] ? (int)$solicitud['clase_id'] : null,
                ];
                try {
                    registrarEntrada($pdo, $datos, false);
                } catch (ErrorDeRegla $e) {
                    if (($e->extra['codigo'] ?? '') !== 'equipo_ocupado') {
                        throw $e;
                    }
                    registrarEntrada($pdo, ['equipo_id' => 'personal'] + $datos, false);
                }
            } elseif ($estado === 'aprobado_permanente') {
                // Lo inscribe en la materia; la próxima vez entra sin pedir permiso.
                $stmt = $pdo->prepare("SELECT materias FROM alumnos WHERE matricula = ? FOR UPDATE");
                $stmt->execute([$solicitud['alumno_id']]);
                $alumno = $stmt->fetch();
                if (!$alumno) {
                    throw new ErrorDeRegla(404, "El alumno ya no existe");
                }
                $materias = materiasDelAlumno($alumno);
                if ($solicitud['materia_id'] && !in_array($solicitud['materia_id'], $materias, true)) {
                    $materias[] = $solicitud['materia_id'];
                    $pdo->prepare("UPDATE alumnos SET materias = ? WHERE matricula = ?")
                        ->execute([json_encode(array_values($materias)), $solicitud['alumno_id']]);
                }
            }
            $pdo->prepare("UPDATE solicitudes_ingreso SET estado = ?, leida = 1 WHERE id = ?")->execute([$estado, $solicitud['id']]);
        });

        registrarEnBitacora($pdo, "Resolver Solicitud", "{$solicitud['nombre']} ({$solicitud['alumno_id']}): $estado", $solicitud['laboratorio']);
        sendResponse(["success" => true, "message" => "Estado de solicitud actualizado a: $estado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
