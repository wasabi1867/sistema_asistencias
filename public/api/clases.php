<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reglas.php';

// Clases del laboratorio. Sustituye a EstadoClase, EstadoClaseInvitado y
// ClassInformation de Firebase.
//
// GET  clases.php?abiertas=1&laboratorio=X   Pública: lo que ve la página de registro
// GET  clases.php?id=N                       La clase y quiénes asistieron
// GET  clases.php?desde=&hasta=&...          Lista para reportes (con_alumnos=1 los incluye)
// POST clases.php?action=abrir               Abre una clase regular o de maestro invitado
// POST clases.php?action=cerrar              Cierra la clase y registra la salida de todos

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/** Los datos de una clase como los recibe el frontend. */
function formatearClase(array $c): array {
    return [
        "id" => (int)$c['id'],
        "laboratorio" => $c['laboratorio'],
        "tipo" => $c['tipo'],
        "materiaId" => $c['materia_id'],
        "materia" => $c['materia'],
        "practicaId" => $c['practica_id'] === null ? null : (int)$c['practica_id'],
        "practica" => $c['practica'],
        "docenteId" => $c['docente_id'],
        "docente" => $c['docente'],
        "departamento" => $c['departamento'],
        "grupo" => $c['grupo'],
        "abiertaEn" => $c['abierta_en'],
        "cerradaEn" => $c['cerrada_en'],
        "abierta" => $c['cerrada_en'] === null,
        "cierre" => $c['cierre'],
        "totalAsistencias" => (int)$c['total_asistencias'],
    ];
}

/** Quienes registraron entrada: los que siguen dentro y los que ya salieron. */
function asistentesDeLaClase(PDO $pdo, array $clase): array {
    $stmt = $pdo->prepare("SELECT alumno_id, nombre, apellido, carrera, semestre, turno, grupo, equipo_id, tipo_usuario, hora_entrada, NULL AS hora_salida
        FROM asistencias WHERE clase_id = ?
        UNION ALL
        SELECT alumno_id, IF(nombre = '', nombre_completo, nombre), apellido, carrera, semestre, turno, grupo, equipo_id, tipo_usuario, hora_entrada, hora_salida
        FROM asistencias_historico WHERE clase_id = ?
        ORDER BY apellido, nombre");
    $stmt->execute([$clase['id'], $clase['id']]);
    return array_map(fn ($a) => [
        "alumnoId" => $a['alumno_id'],
        "nombre" => $a['nombre'],
        "apellido" => $a['apellido'],
        "carrera" => $a['carrera'],
        "semestre" => $a['semestre'],
        "turno" => $a['turno'],
        "grupo" => $a['grupo'],
        "equipo" => $a['equipo_id'],
        "tipoUsuario" => $a['tipo_usuario'],
        "horaEntrada" => $a['hora_entrada'],
        "horaSalida" => $a['hora_salida'],
    ], $stmt->fetchAll());
}

switch ($method) {
    case 'GET':
        if (isset($_GET['abiertas'])) {
            // Pública: la página de registro muestra qué clase hay y con quién.
            $laboratorio = laboratorioValido($_GET['laboratorio'] ?? 'programacion');
            sendResponse(["success" => true, "data" => array_map('formatearClase', clasesAbiertas($pdo, $laboratorio))]);
        }

        requerirRol(ROLES_TODOS);

        if (isset($_GET['id'])) {
            $clase = buscarClase($pdo, (int)$_GET['id']);
            if (!$clase) {
                sendResponse(["success" => false, "error" => "Clase no encontrada"], 404);
            }
            sendResponse(["success" => true, "data" => formatearClase($clase) + ["alumnos" => asistentesDeLaClase($pdo, $clase)]]);
        }

        $sql = "SELECT * FROM clases WHERE 1 = 1";
        $params = [];
        $filtros = [
            'laboratorio' => "laboratorio = ?",
            'tipo' => "tipo = ?",
            'docente_id' => "docente_id = ?",
            'materia_id' => "materia_id = ?",
            'desde' => "abierta_en >= ?",
        ];
        foreach ($filtros as $parametro => $condicion) {
            if (($_GET[$parametro] ?? '') !== '') {
                $sql .= " AND $condicion";
                $params[] = $_GET[$parametro];
            }
        }
        if (($_GET['hasta'] ?? '') !== '') {
            // Hasta el final de ese día.
            $sql .= " AND abierta_en < DATE_ADD(?, INTERVAL 1 DAY)";
            $params[] = $_GET['hasta'];
        }
        if (($_GET['estado'] ?? '') === 'abiertas') {
            $sql .= " AND cerrada_en IS NULL";
        } elseif (($_GET['estado'] ?? '') === 'cerradas') {
            $sql .= " AND cerrada_en IS NOT NULL";
        }
        $sql .= " ORDER BY abierta_en DESC LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clases = $stmt->fetchAll();
        $conAlumnos = isset($_GET['con_alumnos']);

        $datos = array_map(function ($c) use ($pdo, $conAlumnos) {
            $clase = formatearClase($c);
            if ($conAlumnos) {
                $clase['alumnos'] = asistentesDeLaClase($pdo, $c);
            }
            return $clase;
        }, $clases);
        sendResponse(["success" => true, "data" => $datos]);
        break;

    case 'POST':
        $data = getJsonInput();

        if ($action === 'abrir') {
            $laboratorio = laboratorioValido(trim($data['laboratorio'] ?? 'programacion'));
            $tipo = $data['tipo'] ?? 'regular';

            if ($tipo === 'regular') {
                // El docente abre la clase de una de sus materias; el administrador, la
                // de cualquier materia (queda a nombre del docente de la materia).
                $usuario = requerirRol(['admin', 'docente']);
                $materiaId = trim((string)($data['materia_id'] ?? ''));
                $practicaId = (int)($data['practica_id'] ?? 0);

                $stmt = $pdo->prepare("SELECT m.id, m.nombre_materia, m.docente_id, d.nombre, d.apellido
                    FROM materias m LEFT JOIN docentes d ON d.matricula = m.docente_id WHERE m.id = ?");
                $stmt->execute([$materiaId]);
                $materia = $stmt->fetch();
                if (!$materia) {
                    sendResponse(["success" => false, "error" => "Materia no encontrada"], 404);
                }
                if ($usuario['rol'] === 'docente' && $materia['docente_id'] !== $usuario['id']) {
                    sendResponse(["success" => false, "error" => "Solo puedes abrir clases de tus materias"], 403);
                }

                $stmt = $pdo->prepare("SELECT id, titulo FROM practicas WHERE id = ? AND materia_id = ?");
                $stmt->execute([$practicaId, $materiaId]);
                $practica = $stmt->fetch();
                if (!$practica) {
                    sendResponse(["success" => false, "error" => "Selecciona una práctica de la materia antes de iniciar la clase"], 400);
                }

                $docenteId = $materia['docente_id'];
                $docente = $docenteId ? trim("{$materia['nombre']} {$materia['apellido']}") : $usuario['nombre'];

                // Un docente no puede tener dos clases abiertas a la vez.
                if ($docenteId) {
                    $stmt = $pdo->prepare("SELECT laboratorio FROM clases WHERE docente_id = ? AND cerrada_en IS NULL AND tipo = 'regular'");
                    $stmt->execute([$docenteId]);
                    if ($otro = $stmt->fetchColumn()) {
                        sendResponse(["success" => false, "error" => "$docente ya tiene una clase abierta en " . nombreDeLaboratorio($otro)], 409);
                    }
                }

                $fila = [$laboratorio, 'regular', $materiaId, $materia['nombre_materia'], $practica['id'], $practica['titulo'], $docenteId, $docente, '', trim($data['grupo'] ?? '')];
            } elseif ($tipo === 'invitado') {
                // La clase de un maestro de otro departamento la abre el personal del
                // laboratorio, que escribe la materia y la práctica.
                $usuario = requerirRol(ROLES_PERSONAL);
                $docenteId = trim((string)($data['docente_id'] ?? '')) ?: null;
                $docente = trim($data['docente'] ?? '');
                $departamento = trim($data['departamento'] ?? '');
                if ($docenteId) {
                    $stmt = $pdo->prepare("SELECT nombre, apellido, departamento FROM docentes WHERE matricula = ?");
                    $stmt->execute([$docenteId]);
                    $registrado = $stmt->fetch();
                    if (!$registrado) {
                        sendResponse(["success" => false, "error" => "Docente no encontrado"], 404);
                    }
                    $docente = trim("{$registrado['nombre']} {$registrado['apellido']}");
                    $departamento = $departamento ?: $registrado['departamento'];
                }
                $materia = trim($data['materia'] ?? '');
                $practica = trim($data['practica'] ?? '');
                if (!$docente || !$materia || !$practica) {
                    sendResponse(["success" => false, "error" => "Docente, materia y práctica son obligatorios"], 400);
                }
                $fila = [$laboratorio, 'invitado', null, $materia, null, $practica, $docenteId, $docente, $departamento, trim($data['grupo'] ?? '')];
            } else {
                sendResponse(["success" => false, "error" => "Tipo de clase no válido"], 400);
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO clases (laboratorio, tipo, materia_id, materia, practica_id, practica, docente_id, docente, departamento, grupo, abierta_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([...$fila, trim("{$usuario['nombre']} ({$usuario['rol']})")]);
            } catch (PDOException $e) {
                // El índice único de la base admite una sola clase abierta de cada tipo
                // por laboratorio.
                if ($e->getCode() === '23000') {
                    sendResponse(["success" => false, "error" => "Ya hay una clase " . ($tipo === 'invitado' ? "de maestro invitado " : "") . "abierta en " . nombreDeLaboratorio($laboratorio)], 409);
                }
                throw $e;
            }
            $clase = buscarClase($pdo, (int)$pdo->lastInsertId());

            $titulo = $tipo === 'invitado' ? "Clase de maestro invitado iniciada" : "Clase iniciada: \"{$clase['practica']}\"";
            notificarAdministrador($pdo, $tipo === 'invitado' ? 'maestro_invitado' : 'asistencia', $titulo,
                "{$clase['docente']} inició la clase de {$clase['materia']} en " . nombreDeLaboratorio($laboratorio) . ".",
                $laboratorio, $tipo === 'invitado' ? 'media' : 'baja', ["claseId" => (int)$clase['id'], "accion" => "iniciar_clase"]);
            registrarEnBitacora($pdo, "Iniciar Clase", "{$clase['materia']} - {$clase['practica']} con {$clase['docente']}", $laboratorio);

            sendResponse(["success" => true, "message" => "Clase iniciada", "data" => formatearClase($clase)]);
        }

        if ($action === 'cerrar') {
            $usuario = requerirRol(ROLES_TODOS);
            $clase = buscarClase($pdo, (int)($data['id'] ?? 0));
            if (!$clase) {
                sendResponse(["success" => false, "error" => "Clase no encontrada"], 404);
            }

            // La cierra el docente que la imparte, o el personal del laboratorio. Si el
            // personal cierra la clase de un docente, queda como 'externo' y la pantalla
            // del docente se entera.
            $esSuDocente = $usuario['rol'] === 'docente' && $clase['docente_id'] === $usuario['id'];
            if ($usuario['rol'] === 'docente' && !$esSuDocente) {
                sendResponse(["success" => false, "error" => "Solo puedes cerrar tus clases"], 403);
            }
            if (!empty($data['automatico'])) {
                // El navegador de quien la lleva la cerró al cerrar la pestaña.
                $cierre = 'automatico';
            } elseif ($esSuDocente) {
                $cierre = 'normal';
            } else {
                $cierre = $clase['tipo'] === 'regular' ? 'externo' : 'normal';
            }

            $cerrada = cerrarClase($pdo, (int)$clase['id'], $cierre);
            $laboratorio = $cerrada['laboratorio'];
            $total = (int)$cerrada['total_asistencias'];

            $como = ['normal' => 'finalizó', 'externo' => 'fue cerrada por el personal del laboratorio', 'automatico' => 'se cerró al cerrar el navegador'][$cierre];
            notificarAdministrador($pdo, 'asistencia', "Clase finalizada: \"{$cerrada['practica']}\"",
                "La clase de {$cerrada['materia']} con {$cerrada['docente']} $como. Asistencias: $total.",
                $laboratorio, $cierre === 'normal' ? 'baja' : 'media', ["claseId" => (int)$cerrada['id'], "accion" => "finalizar_clase", "cierre" => $cierre]);
            registrarEnBitacora($pdo, "Finalizar Clase", "{$cerrada['materia']} - {$cerrada['practica']}. Asistencias: $total. Cierre: $cierre", $laboratorio);

            sendResponse(["success" => true, "message" => "Clase finalizada", "data" => formatearClase($cerrada)]);
        }

        sendResponse(["success" => false, "error" => "Acción no válida"], 400);
        break;

    default:
        sendResponse(["success" => false, "error" => "Método no soportado"], 405);
}
