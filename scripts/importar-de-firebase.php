<?php
// ==============================================================
// IMPORTAR LOS DATOS DE FIREBASE A LA BASE DE LA API
// ==============================================================
// Se corre una vez, al cambiar el sistema de Firebase a la API, en una terminal y con
// la base ya migrada (php scripts/migrar.php):
//
//   php scripts/importar-de-firebase.php --proyecto=integrador-7b39d --respaldo=respaldo-firebase
//
// Opciones:
//   --proyecto=ID      Proyecto de Firebase (el projectId de la configuración web).
//   --clave=CLAVE      apiKey de la configuración web, si Firestore la pide.
//   --respaldo=CARPETA Guarda cada colección tal como venía de Firebase (JSON) antes
//                      de importar. Conviene siempre: es la copia de seguridad.
//   --desde=CARPETA    Lee un respaldo hecho antes en lugar de descargar de Firebase.
//   --sql=ARCHIVO      No toca la base: escribe las instrucciones en un archivo para
//                      pegarlo en phpMyAdmin (cuando el servidor no da terminal).
//
// Lee con la API REST de Firestore, la misma a la que llegaba el navegador: no hacen
// falta contraseñas de Google. Se puede repetir: cada registro se agrega o se
// actualiza, sin duplicarse. Las contraseñas se copian tal cual (ya estaban cifradas
// con bcrypt) y cada quien entra con la suya de siempre.
//
// No se copian las asistencias en curso (Asistencias, AsistenciasInvitado) ni el estado
// de las clases (EstadoClase): el cambio se hace sin clases abiertas. Tampoco
// HistoricalAttendance, que repite lo que ya está en ClassInformation.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$opciones = getopt('', ['proyecto:', 'clave:', 'respaldo:', 'desde:', 'sql:']);
if (empty($opciones['proyecto']) && empty($opciones['desde'])) {
    fwrite(STDERR, "Uso: php scripts/importar-de-firebase.php --proyecto=ID [--respaldo=CARPETA] [--sql=ARCHIVO]\n");
    fwrite(STDERR, "  o: php scripts/importar-de-firebase.php --desde=CARPETA [--sql=ARCHIVO]\n");
    exit(1);
}

require __DIR__ . '/../public/api/config.php';
date_default_timezone_set(ZONA_HORARIA);

// --------------------------------------------------------------------------
// Lectura de Firestore
// --------------------------------------------------------------------------

/** Una colección: lista de documentos crudos de la API REST ({name, fields}). */
function leerColeccion(string $ruta, array $opciones): array {
    $archivo = isset($opciones['desde']) || isset($opciones['respaldo'])
        ? rtrim($opciones['desde'] ?? $opciones['respaldo'], '/\\') . '/' . str_replace('/', '__', $ruta) . '.json'
        : null;

    if (isset($opciones['desde'])) {
        return is_file($archivo) ? json_decode(file_get_contents($archivo), true) : [];
    }

    $documentos = [];
    $pagina = null;
    do {
        $url = "https://firestore.googleapis.com/v1/projects/" . rawurlencode($opciones['proyecto'])
            . "/databases/(default)/documents/" . implode('/', array_map('rawurlencode', explode('/', $ruta)))
            . "?pageSize=300" . ($pagina ? "&pageToken=" . urlencode($pagina) : "")
            . (isset($opciones['clave']) ? "&key=" . urlencode($opciones['clave']) : "");
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
        $respuesta = curl_exec($curl);
        $estado = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($estado !== 200) {
            fwrite(STDERR, "No se pudo leer $ruta de Firebase (HTTP $estado): " . substr((string)$respuesta, 0, 300) . "\n");
            exit(1);
        }
        $datos = json_decode($respuesta, true);
        array_push($documentos, ...($datos['documents'] ?? []));
        $pagina = $datos['nextPageToken'] ?? null;
    } while ($pagina);

    if (isset($opciones['respaldo'])) {
        if (!is_dir(dirname($archivo))) {
            mkdir(dirname($archivo), 0777, true);
        }
        file_put_contents($archivo, json_encode($documentos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $documentos;
}

/** Convierte un valor de la API REST de Firestore ({stringValue: ...}) a PHP. */
function valorDeFirestore(array $valor) {
    if (array_key_exists('stringValue', $valor)) return $valor['stringValue'];
    if (array_key_exists('integerValue', $valor)) return (int)$valor['integerValue'];
    if (array_key_exists('doubleValue', $valor)) return (float)$valor['doubleValue'];
    if (array_key_exists('booleanValue', $valor)) return (bool)$valor['booleanValue'];
    if (array_key_exists('nullValue', $valor)) return null;
    if (array_key_exists('timestampValue', $valor)) {
        return (new DateTime($valor['timestampValue']))->setTimezone(new DateTimeZone(ZONA_HORARIA));
    }
    if (array_key_exists('arrayValue', $valor)) return array_map('valorDeFirestore', $valor['arrayValue']['values'] ?? []);
    if (array_key_exists('mapValue', $valor)) return array_map('valorDeFirestore', $valor['mapValue']['fields'] ?? []);
    return $valor['referenceValue'] ?? null;
}

/** [id, campos] de un documento crudo. */
function documento(array $crudo): array {
    $partes = explode('/', $crudo['name']);
    return [urldecode(end($partes)), array_map('valorDeFirestore', $crudo['fields'] ?? [])];
}

// --------------------------------------------------------------------------
// Conversión de fechas y horas como las guardaba el navegador
// --------------------------------------------------------------------------

function textoDe($valor): string {
    if ($valor instanceof DateTime) return $valor->format('Y-m-d H:i:s');
    if (is_array($valor)) return json_encode($valor, JSON_UNESCAPED_UNICODE);
    return trim((string)($valor ?? ''));
}

function fechaHoraDe($valor): ?string {
    return $valor instanceof DateTime ? $valor->format('Y-m-d H:i:s') : null;
}

/** "21/10/2026" (como lo escribía toLocaleDateString en español) → "2026-10-21". */
function fechaDeTexto($valor): ?string {
    if ($valor instanceof DateTime) return $valor->format('Y-m-d');
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim((string)$valor), $m)) {
        [$d, $mes] = [(int)$m[1], (int)$m[2]];
        if ($mes > 12) {
            [$d, $mes] = [$mes, $d]; // venía en formato mes/día
        }
        return checkdate($mes, $d, (int)$m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $mes, $d) : null;
    }
    return preg_match('#^\d{4}-\d{2}-\d{2}$#', trim((string)$valor)) ? trim($valor) : null;
}

/** "9:46:22 p. m.", "9:46:22 PM" o "21:46:22" (toLocaleTimeString) → "21:46:22". */
function horaDeTexto($valor): ?string {
    if (!preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([ap])?/i', (string)$valor, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $tarde = strtolower($m[4] ?? '');
    if ($tarde === 'p' && $h < 12) $h += 12;
    if ($tarde === 'a' && $h === 12) $h = 0;
    return sprintf('%02d:%02d:%02d', $h, $m[2], $m[3] ?? 0);
}

// --------------------------------------------------------------------------
// Instrucciones SQL (las mismas se ejecutan o se escriben al archivo)
// --------------------------------------------------------------------------

/** Un valor como literal de SQL, para MySQL y MariaDB. */
function sqlValor($v): string {
    if ($v === null) return 'NULL';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_int($v) || is_float($v)) return (string)$v;
    return "'" . strtr((string)$v, ["\\" => "\\\\", "'" => "\\'", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]) . "'";
}

function sqlFila(array $valores): string {
    return '(' . implode(', ', array_map('sqlValor', $valores)) . ')';
}

$instrucciones = ["SET NAMES utf8mb4", "SET SESSION sql_mode = REPLACE(@@sql_mode, 'NO_BACKSLASH_ESCAPES', '')"];
$cuenta = [];
$sql = function (string $instruccion) use (&$instrucciones) { $instrucciones[] = $instruccion; };
$contar = function (string $que) use (&$cuenta) { $cuenta[$que] = ($cuenta[$que] ?? 0) + 1; };

// Cuentas del personal. Las contraseñas vienen ya cifradas con bcrypt.
foreach (leerColeccion('Administrador', $opciones) as $crudo) {
    [$id, $a] = documento($crudo);
    $email = textoDe($a['Email'] ?? '');
    if ($email === '' || textoDe($a['Contraseña'] ?? '') === '') continue;
    $sql("INSERT INTO administradores (matricula, nombre, apellido, email, password, rol) VALUES "
        . sqlFila([$id, textoDe($a['Nombre'] ?? 'Administrador'), textoDe($a['Apellido'] ?? ''), $email, textoDe($a['Contraseña']), 'admin'])
        . " ON DUPLICATE KEY UPDATE matricula = VALUES(matricula), nombre = VALUES(nombre), apellido = VALUES(apellido), password = VALUES(password)");
    $contar('administradores');
}

foreach (['Docentes' => 'docentes', 'Laboratoristas' => 'laboratoristas'] as $coleccion => $tabla) {
    foreach (leerColeccion($coleccion, $opciones) as $crudo) {
        [$id, $p] = documento($crudo);
        $columnas = ['matricula', 'nombre', 'apellido', 'email', 'password'];
        $valores = [$id, textoDe($p['Nombre'] ?? ''), textoDe($p['Apellido'] ?? ''), textoDe($p['Email'] ?? ''), textoDe($p['Contraseña'] ?? '')];
        if ($tabla === 'docentes') {
            $columnas[] = 'departamento';
            $valores[] = textoDe($p['Departamento'] ?? '');
        }
        $actualizar = implode(', ', array_map(fn ($c) => "$c = VALUES($c)", array_slice($columnas, 1)));
        $sql("INSERT INTO $tabla (" . implode(', ', $columnas) . ") VALUES " . sqlFila($valores) . " ON DUPLICATE KEY UPDATE $actualizar");
        $contar($tabla);
    }
}

// Materias y sus prácticas
foreach (leerColeccion('Materias', $opciones) as $crudo) {
    [$id, $m] = documento($crudo);
    $sql("INSERT INTO materias (id, nombre_materia, docente_id, semestre, turno, ciclo_escolar, laboratorio) VALUES "
        . sqlFila([$id, textoDe($m['NombreMateria'] ?? ''), textoDe($m['MaestroID'] ?? $m['DocenteId'] ?? '') ?: null,
            textoDe($m['Semestre'] ?? ''), textoDe($m['Turno'] ?? ''), textoDe($m['CicloEscolar'] ?? ''), textoDe($m['Laboratorio'] ?? '')])
        . " ON DUPLICATE KEY UPDATE nombre_materia = VALUES(nombre_materia), docente_id = VALUES(docente_id), semestre = VALUES(semestre),"
        . " turno = VALUES(turno), ciclo_escolar = VALUES(ciclo_escolar), laboratorio = VALUES(laboratorio)");
    $contar('materias');

    foreach (leerColeccion("Materias/$id/Practicas", $opciones) as $crudoPractica) {
        [, $p] = documento($crudoPractica);
        $titulo = textoDe($p['Titulo'] ?? '');
        if ($titulo === '') continue;
        $sql("INSERT INTO practicas (materia_id, numero_practica, titulo, descripcion, duracion, fecha) SELECT "
            . implode(', ', array_map('sqlValor', [$id, textoDe($p['Numero'] ?? ''), $titulo, textoDe($p['Descripcion'] ?? ''), textoDe($p['Duracion'] ?? ''), fechaDeTexto($p['fecha'] ?? null)]))
            . " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM practicas WHERE materia_id = " . sqlValor($id) . " AND titulo = " . sqlValor($titulo) . ")");
        $contar('practicas');
    }
}

// Alumnos, con las materias en que están inscritos
foreach (leerColeccion('Alumnos', $opciones) as $crudo) {
    [$id, $a] = documento($crudo);
    $materias = array_values(array_map('strval', is_array($a['Materias'] ?? null) ? $a['Materias'] : []));
    $sql("INSERT INTO alumnos (matricula, nombre, apellido, carrera, semestre, turno, grupo, materias) VALUES "
        . sqlFila([$id, textoDe($a['Nombre'] ?? ''), textoDe($a['Apellido'] ?? ''), textoDe($a['Carrera'] ?? ''), textoDe($a['Semestre'] ?? ''),
            textoDe($a['Turno'] ?? ''), textoDe($a['Grupo'] ?? ''), json_encode($materias)])
        . " ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), carrera = VALUES(carrera), semestre = VALUES(semestre),"
        . " turno = VALUES(turno), grupo = VALUES(grupo), materias = VALUES(materias)");
    $contar('alumnos');
}

// Equipos: los de Firebase reemplazan a los que hubiera en ese laboratorio
$estadosDeReparacion = ['reportado', 'en_proceso', 'resuelto'];
foreach (leerColeccion('Numero de equipos', $opciones) as $crudo) {
    [$laboratorio, $datos] = documento($crudo);
    $sql("DELETE FROM equipos WHERE laboratorio = " . sqlValor($laboratorio) . " AND en_uso = 0");
    $sql("INSERT IGNORE INTO equipos (id, laboratorio) VALUES " . sqlFila(['personal', $laboratorio]));
    foreach (($datos['Equipos'] ?? []) as $e) {
        if (!is_array($e) || textoDe($e['id'] ?? '') === '') continue;
        $estado = in_array($e['estadoReparacion'] ?? '', $estadosDeReparacion, true) ? $e['estadoReparacion'] : 'ninguno';
        $sql("INSERT INTO equipos (id, laboratorio, fuera_de_servicio, en_uso, notas, estado_reparacion, comentario_estado) VALUES "
            . sqlFila([textoDe($e['id']), $laboratorio, (bool)($e['fueraDeServicio'] ?? false), false, textoDe($e['notas'] ?? ''), $estado, textoDe($e['comentarioEstado'] ?? '')])
            . " ON DUPLICATE KEY UPDATE fuera_de_servicio = VALUES(fuera_de_servicio), notas = VALUES(notas),"
            . " estado_reparacion = VALUES(estado_reparacion), comentario_estado = VALUES(comentario_estado)");
        $contar('equipos');
    }
}

// Historial de clases (ClassInformation): cada una pasa a `clases`, ya cerrada, y
// quienes asistieron a `asistencias_historico`. Se marcan con su id de Firebase en
// abierta_por para no importarlas dos veces.
foreach (leerColeccion('ClassInformation', $opciones) as $crudo) {
    [$id, $c] = documento($crudo);
    $fecha = fechaDeTexto($c['fecha'] ?? null);
    if (!$fecha) continue;
    $inicio = "$fecha " . (horaDeTexto($c['horaInicio'] ?? '') ?? '00:00:00');
    $fin = isset($c['horaFin']) && horaDeTexto($c['horaFin']) ? "$fecha " . horaDeTexto($c['horaFin']) : $inicio;
    $esDeInvitado = ($c['tipoClase'] ?? '') === 'invitado' || !empty($c['departamento']);
    $docente = trim(textoDe($c['maestroNombre'] ?? '') . ' ' . textoDe($c['maestroApellido'] ?? ''));
    $alumnos = is_array($c['alumnos'] ?? null) ? $c['alumnos'] : [];
    $laboratorio = textoDe($c['laboratorio'] ?? '') ?: 'programacion';
    $marca = "Firebase $id";

    $sql("SET @clase_previa := (SELECT id FROM clases WHERE abierta_por = " . sqlValor($marca) . " LIMIT 1)");
    $sql("INSERT INTO clases (laboratorio, tipo, materia, practica, docente_id, docente, departamento, grupo, abierta_por, abierta_en, cerrada_en, cierre, total_asistencias) SELECT "
        . implode(', ', array_map('sqlValor', [$laboratorio, $esDeInvitado ? 'invitado' : 'regular', textoDe($c['materia'] ?? ''), textoDe($c['practica'] ?? ''),
            textoDe($c['maestroId'] ?? '') ?: null, $docente, textoDe($c['departamento'] ?? ''), textoDe($c['grupo'] ?? ''), $marca, $inicio, $fin,
            !empty($c['finalizadaAutomaticamente']) ? 'automatico' : 'normal', (int)($c['totalAsistencias'] ?? count($alumnos))]))
        . " FROM DUAL WHERE @clase_previa IS NULL");
    $sql("SET @clase_nueva := IF(@clase_previa IS NULL, LAST_INSERT_ID(), NULL)");
    $contar('clases');

    foreach ($alumnos as $a) {
        if (!is_array($a)) continue;
        $nombre = textoDe($a['nombre'] ?? $a['Nombre'] ?? '');
        $apellido = textoDe($a['apellido'] ?? $a['Apellido'] ?? '');
        $sql("INSERT INTO asistencias_historico (clase_id, alumno_id, nombre_completo, nombre, apellido, carrera, semestre, turno, grupo, materia, docente, equipo_id, laboratorio, tipo_usuario, hora_entrada, hora_salida, fecha) SELECT @clase_nueva, "
            . implode(', ', array_map('sqlValor', [textoDe($a['id'] ?? ''), trim("$nombre $apellido"), $nombre, $apellido, textoDe($a['carrera'] ?? ''),
                textoDe($a['semestre'] ?? ''), textoDe($a['turno'] ?? ''), textoDe($a['grupo'] ?? ''), textoDe($c['materia'] ?? ''), $docente,
                textoDe($a['equipo'] ?? ''), $laboratorio, $esDeInvitado && str_starts_with(textoDe($a['id'] ?? ''), 'INV-') ? 'invitado' : 'estudiante', $inicio, $fin, $fecha]))
            . " FROM DUAL WHERE @clase_nueva IS NOT NULL");
        $contar('asistencias_historico');
    }
}

// Solicitudes de ingreso
$estadosDeSolicitud = ['pendiente' => 'pendiente', 'aprobado_temporal' => 'aprobado_temporal', 'aprobado_permanente' => 'aprobado_permanente',
    'rechazado' => 'rechazada', 'rechazada' => 'rechazada', 'aprobada' => 'aprobado_temporal'];
foreach (leerColeccion('SolicitudesIngreso', $opciones) as $crudo) {
    [, $s] = documento($crudo);
    $fecha = fechaHoraDe($s['fechaSolicitud'] ?? null) ?? date('Y-m-d H:i:s');
    $alumno = textoDe($s['alumnoId'] ?? '');
    if ($alumno === '') continue;
    $materia = textoDe($s['materiaNombre'] ?? '');
    $sql("INSERT INTO solicitudes_ingreso (alumno_id, nombre, carrera, motivo, laboratorio, materia_id, equipo_id, estado, leida, fecha_solicitud) SELECT "
        . implode(', ', array_map('sqlValor', [$alumno, trim(textoDe($s['alumnoNombre'] ?? '') . ' ' . textoDe($s['alumnoApellido'] ?? '')),
            textoDe($s['alumnoCarrera'] ?? ''), $materia ? "Solicita ingreso a la clase de $materia" : 'Solicita ingreso a la clase',
            textoDe($s['laboratorio'] ?? '') ?: 'programacion', textoDe($s['materiaId'] ?? '') ?: null, textoDe($s['equipo'] ?? ''),
            $estadosDeSolicitud[$s['estado'] ?? ''] ?? 'pendiente', (bool)($s['leida'] ?? false), $fecha]))
        . " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM solicitudes_ingreso WHERE alumno_id = " . sqlValor($alumno) . " AND fecha_solicitud = " . sqlValor($fecha) . ")");
    $contar('solicitudes_ingreso');
}

// Avisos para el administrador
foreach (leerColeccion('NotificacionesAdmin', $opciones) as $crudo) {
    [, $n] = documento($crudo);
    $fecha = fechaHoraDe($n['fecha'] ?? null) ?? date('Y-m-d H:i:s');
    $titulo = mb_substr(textoDe($n['titulo'] ?? ''), 0, 150);
    $sql("INSERT INTO notificaciones_admin (tipo, titulo, mensaje, laboratorio, prioridad, leida, datos, fecha) SELECT "
        . implode(', ', array_map('sqlValor', [textoDe($n['tipo'] ?? 'general'), $titulo, textoDe($n['mensaje'] ?? ''), textoDe($n['laboratorio'] ?? '') ?: null,
            textoDe($n['prioridad'] ?? 'media'), (bool)($n['leida'] ?? false), isset($n['datos']) ? json_encode($n['datos'], JSON_UNESCAPED_UNICODE) : null, $fecha]))
        . " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM notificaciones_admin WHERE titulo = " . sqlValor($titulo) . " AND fecha = " . sqlValor($fecha) . ")");
    $contar('notificaciones_admin');
}

// Bitácora
foreach (leerColeccion('logs', $opciones) as $crudo) {
    [, $l] = documento($crudo);
    $fecha = fechaHoraDe($l['timestamp'] ?? null);
    if (!$fecha) continue;
    $accion = mb_substr(textoDe($l['action'] ?? ''), 0, 100);
    $detalles = textoDe($l['details'] ?? '');
    $sql("INSERT INTO logs (accion, usuario, detalles, laboratorio, fecha) SELECT "
        . implode(', ', array_map('sqlValor', [$accion, mb_substr(textoDe($l['user'] ?? '') ?: 'Firebase', 0, 100), $detalles, textoDe($l['laboratorio'] ?? '') ?: null, $fecha]))
        . " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM logs WHERE accion = " . sqlValor($accion) . " AND fecha = " . sqlValor($fecha) . " AND detalles = " . sqlValor($detalles) . ")");
    $contar('logs');
}

// Eventos del laboratorio
foreach (leerColeccion('EventosLaboratorio', $opciones) as $crudo) {
    [, $e] = documento($crudo);
    $nombre = textoDe($e['nombre'] ?? '');
    if ($nombre === '') continue;
    $laboratorio = textoDe($e['laboratorio'] ?? '') ?: 'programacion';
    $fecha = fechaDeTexto($e['fecha'] ?? null);
    $sql("INSERT INTO eventos_laboratorio (nombre, tipo, organizador, participantes, fecha, hora, duracion, descripcion, resultados, laboratorio, creado_en) SELECT "
        . implode(', ', array_map('sqlValor', [$nombre, textoDe($e['tipo'] ?? ''), textoDe($e['organizador'] ?? ''), (int)($e['participantes'] ?? 0), $fecha,
            textoDe($e['hora'] ?? ''), textoDe($e['duracion'] ?? ''), textoDe($e['descripcion'] ?? ''), textoDe($e['resultados'] ?? ''), $laboratorio,
            fechaHoraDe($e['createdAt'] ?? null) ?? date('Y-m-d H:i:s')]))
        . " FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM eventos_laboratorio WHERE nombre = " . sqlValor($nombre) . " AND laboratorio = " . sqlValor($laboratorio)
        . " AND fecha <=> " . sqlValor($fecha) . ")");
    $contar('eventos_laboratorio');
}

// --------------------------------------------------------------------------
// Aplicar o escribir
// --------------------------------------------------------------------------

if (isset($opciones['sql'])) {
    file_put_contents($opciones['sql'], "-- Importación de Firebase generada el " . date('Y-m-d H:i') . "\n" . implode(";\n", $instrucciones) . ";\n");
    echo "Instrucciones escritas en {$opciones['sql']}.\n";
} else {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    $pdo->beginTransaction();
    try {
        foreach ($instrucciones as $instruccion) {
            $pdo->exec($instruccion);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "La importación falló y no se guardó nada: " . $e->getMessage() . "\n");
        exit(1);
    }
    echo "Importación terminada.\n";
}

foreach ($cuenta as $que => $cuantos) {
    echo str_pad($que, 24) . $cuantos . "\n";
}
