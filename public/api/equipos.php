<?php
require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Pública: el alumno elige su equipo sin iniciar sesión. Pero quién ocupa cada
        // equipo y las notas de reparación solo se ven con sesión.
        $usuario = usuarioEnSesion();
        $conSesion = $usuario !== null;
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
                                   LENGTH(id),
                                   id");
            // LENGTH antes que id: PC-2 va antes que PC-10. La página de registro
            // numera los equipos por su posición en la lista.
            $stmt->execute([$lab]);
            $equipos = $stmt->fetchAll();

            // Si no existen equipos para este laboratorio, inicializamos automáticamente PC-1 a PC-25.
            // Solo con sesión del personal: si no, cualquiera podía crear 26 equipos en un
            // laboratorio inventado con solo pedir ?laboratorio=lo-que-sea.
            if (empty($equipos) && $usuario !== null && in_array($usuario['rol'], ROLES_PERSONAL, true)) {
                $insert = $pdo->prepare("INSERT INTO equipos (id, laboratorio, fuera_de_servicio, en_uso) VALUES (?, ?, 0, 0) ON DUPLICATE KEY UPDATE laboratorio = laboratorio");
                $insert->execute(['personal', $lab]);
                for ($i = 1; $i <= 25; $i++) {
                    $insert->execute(["PC-$i", $lab]);
                }
                $stmt->execute([$lab]);
                $equipos = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            error_log('API asistencias: ' . $e);
            sendResponse(["success" => false, "error" => "No se pudieron consultar los equipos"], 500);
        }

        // Formatear booleanos para compatibilidad con la app React
        $formateados = array_map(function($e) use ($conSesion) {
            return [
                "id" => $e['id'],
                "laboratorio" => $e['laboratorio'],
                "fueraDeServicio" => (bool)$e['fuera_de_servicio'],
                "enUso" => (bool)$e['en_uso'],
                "usuarioActual" => $conSesion ? $e['usuario_actual'] : null,
                "notas" => $conSesion ? ($e['notas'] ?? '') : '',
                "estadoReparacion" => $e['estado_reparacion'] ?? 'ninguno',
                "comentarioEstado" => $conSesion ? ($e['comentario_estado'] ?? '') : '',
                "ultimaActualizacion" => $e['actualizado_en']
            ];
        }, $equipos);

        sendResponse(["success" => true, "data" => $formateados]);
        break;

    case 'POST':
    case 'PUT':
        requerirRol(ROLES_PERSONAL);
        $data = getJsonInput();

        if (($_GET['action'] ?? '') === 'configurar') {
            // Fijar cuántos equipos tiene el laboratorio: reemplaza la lista completa por
            // PC-1 a PC-N (más el equipo propio). Como en la versión con Firebase, pero sin
            // dejar fuera a alguien que esté usando uno.
            $lab = trim($data['laboratorio'] ?? 'programacion');
            $cantidad = (int)($data['cantidad'] ?? 0);
            if ($cantidad < 1 || $cantidad > 200) {
                sendResponse(["success" => false, "error" => "La cantidad de equipos debe estar entre 1 y 200"], 400);
            }
            $anteriores = enTransaccion($pdo, function (PDO $pdo) use ($lab, $cantidad) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipos WHERE laboratorio = ? AND en_uso = 1 AND id <> 'personal' FOR UPDATE");
                $stmt->execute([$lab]);
                if ($stmt->fetchColumn()) {
                    throw new ErrorDeRegla(409, "Hay equipos en uso. Espera a que termine la clase para cambiar los equipos del laboratorio");
                }
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipos WHERE laboratorio = ? AND id <> 'personal'");
                $stmt->execute([$lab]);
                $anteriores = (int)$stmt->fetchColumn();

                $pdo->prepare("DELETE FROM equipos WHERE laboratorio = ? AND id <> 'personal'")->execute([$lab]);
                $insertar = $pdo->prepare("INSERT IGNORE INTO equipos (id, laboratorio) VALUES (?, ?)");
                $insertar->execute(['personal', $lab]);
                for ($i = 1; $i <= $cantidad; $i++) {
                    $insertar->execute(["PC-$i", $lab]);
                }
                return $anteriores;
            });
            notificarAdministrador($pdo, 'sistema', "Configuración de equipos actualizada",
                "El laboratorio ahora tiene $cantidad equipos (antes $anteriores).", $lab, 'media',
                ["cantidadEquipos" => $cantidad, "equiposAnteriores" => $anteriores, "accion" => "actualizar_equipos"]);
            registrarEnBitacora($pdo, "Configurar Equipos", "$cantidad equipos (antes $anteriores)", $lab);
            sendResponse(["success" => true, "message" => "Se configuraron $cantidad equipos", "anteriores" => $anteriores]);
        }

        if (($_GET['action'] ?? '') === 'crear') {
            // Agregar un equipo al laboratorio. Solo el administrador.
            requerirRol(ROLES_ADMIN);
            $id = trim($data['id'] ?? '');
            $lab = trim($data['laboratorio'] ?? 'programacion');
            if (!$id || mb_strlen($id) > 50) {
                sendResponse(["success" => false, "error" => "Identificador de equipo requerido (hasta 50 caracteres)"], 400);
            }
            $stmt = $pdo->prepare("INSERT IGNORE INTO equipos (id, laboratorio) VALUES (?, ?)");
            $stmt->execute([$id, $lab]);
            if (!$stmt->rowCount()) {
                sendResponse(["success" => false, "error" => "Ya existe el equipo $id en este laboratorio"], 409);
            }
            registrarEnBitacora($pdo, "Agregar Equipo", $id, $lab);
            sendResponse(["success" => true, "message" => "Equipo agregado"]);
        }
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

        $cambios = [];
        if (isset($data['fueraDeServicio'])) {
            $cambios[] = $data['fueraDeServicio'] ? "fuera de servicio" : "en servicio";
        }
        if (isset($data['estadoReparacion'])) {
            $cambios[] = "reparación: {$data['estadoReparacion']}";
        }
        if (array_key_exists('notas', $data)) {
            $cambios[] = "notas actualizadas";
        }
        if ($cambios) {
            registrarEnBitacora($pdo, "Actualizar Equipo", "Equipo $id: " . implode(", ", $cambios), $lab);
        }
        sendResponse(["success" => true, "message" => "Equipo actualizado correctamente"]);
        break;

    case 'DELETE':
        requerirRol(ROLES_ADMIN);
        $id = $_GET['id'] ?? '';
        $lab = $_GET['laboratorio'] ?? 'programacion';
        $stmt = $pdo->prepare("SELECT en_uso FROM equipos WHERE id = ? AND laboratorio = ?");
        $stmt->execute([$id, $lab]);
        $enUso = $stmt->fetchColumn();
        if ($enUso === false) {
            sendResponse(["success" => false, "error" => "Equipo no encontrado"], 404);
        }
        if ($enUso) {
            sendResponse(["success" => false, "error" => "No se puede quitar un equipo que alguien está usando"], 409);
        }
        $pdo->prepare("DELETE FROM equipos WHERE id = ? AND laboratorio = ?")->execute([$id, $lab]);
        registrarEnBitacora($pdo, "Quitar Equipo", (string)$id, $lab);
        sendResponse(["success" => true, "message" => "Equipo quitado"]);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
