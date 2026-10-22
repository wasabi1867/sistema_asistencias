<?php
// ==============================================================
// REGLAS DEL LABORATORIO: clases, registro de entrada y salida
// ==============================================================
// Las usan asistencias.php, clases.php y solicitudes.php. Antes estas reglas solo
// las revisaba la página en el navegador, así que bastaba con llamar directo a la
// base para saltárselas.

const NOMBRES_LABORATORIO = [
    'programacion' => 'Taller de Programación',
    'redes' => 'Laboratorio de Redes',
    'laboratorio_a' => 'Laboratorio A',
    'laboratorio_c' => 'Laboratorio C',
];

function nombreDeLaboratorio(string $laboratorio): string {
    return NOMBRES_LABORATORIO[$laboratorio] ?? $laboratorio;
}

function laboratorioValido(string $laboratorio): string {
    if (!array_key_exists($laboratorio, NOMBRES_LABORATORIO)) {
        throw new ErrorDeRegla(400, "Laboratorio desconocido: $laboratorio");
    }
    return $laboratorio;
}

/** Las clases abiertas de un laboratorio: a lo más una regular y una de maestro invitado. */
function clasesAbiertas(PDO $pdo, string $laboratorio): array {
    // La de invitado primero: si hay de las dos, el registro va a esa, como hacía la
    // página de registro.
    $stmt = $pdo->prepare("SELECT * FROM clases WHERE laboratorio = ? AND cerrada_en IS NULL ORDER BY tipo = 'invitado' DESC, id");
    $stmt->execute([$laboratorio]);
    return $stmt->fetchAll();
}

function buscarClase(PDO $pdo, int $id, bool $bloquear = false): ?array {
    $stmt = $pdo->prepare("SELECT * FROM clases WHERE id = ?" . ($bloquear ? " FOR UPDATE" : ""));
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** La clase abierta donde se registra quien llega al laboratorio. */
function claseParaRegistrarse(PDO $pdo, string $laboratorio, ?int $claseId): array {
    if ($claseId) {
        $clase = buscarClase($pdo, $claseId);
        if (!$clase || $clase['cerrada_en'] !== null || $clase['laboratorio'] !== $laboratorio) {
            throw new ErrorDeRegla(409, "Esa clase ya no está abierta");
        }
        return $clase;
    }
    $abiertas = clasesAbiertas($pdo, $laboratorio);
    if (!$abiertas) {
        throw new ErrorDeRegla(409, "No hay ninguna clase iniciada en " . nombreDeLaboratorio($laboratorio));
    }
    return $abiertas[0];
}

/** Las materias en que está inscrito un alumno (columna JSON alumnos.materias). */
function materiasDelAlumno(array $alumno): array {
    $materias = json_decode($alumno['materias'] ?? '[]', true);
    return is_array($materias) ? array_map('strval', $materias) : [];
}

/**
 * Los datos de quien entra. Del alumno y del docente salen de la base; el invitado
 * da su nombre. Revisa que pueda entrar a esa clase.
 */
function personaQueEntra(PDO $pdo, array $datos, array $clase, bool $revisarInscripcion): array {
    $tipo = $datos['tipo_usuario'];

    if ($tipo === 'estudiante') {
        $stmt = $pdo->prepare("SELECT matricula, nombre, apellido, carrera, semestre, turno, grupo, materias FROM alumnos WHERE matricula = ?");
        $stmt->execute([$datos['alumno_id']]);
        $alumno = $stmt->fetch();
        if (!$alumno) {
            throw new ErrorDeRegla(404, "Matrícula no encontrada");
        }
        // En la clase regular solo entran los inscritos en la materia. El que no lo
        // está puede pedir ingreso: el código le dice a la página que lo ofrezca.
        if ($revisarInscripcion && $clase['tipo'] === 'regular'
            && !in_array((string)$clase['materia_id'], materiasDelAlumno($alumno), true)) {
            throw new ErrorDeRegla(403, "No estás inscrito en {$clase['materia']}", [
                "codigo" => "no_inscrito",
                "clase_id" => (int)$clase['id'],
                "materia" => $clase['materia'],
            ]);
        }
        unset($alumno['materias']);
        return ['alumno_id' => $alumno['matricula']] + $alumno;
    }

    if ($tipo === 'docente') {
        $stmt = $pdo->prepare("SELECT matricula, nombre, apellido FROM docentes WHERE matricula = ?");
        $stmt->execute([$datos['alumno_id']]);
        $docente = $stmt->fetch();
        if (!$docente) {
            throw new ErrorDeRegla(404, "Docente no encontrado");
        }
        return ['alumno_id' => $docente['matricula'], 'nombre' => $docente['nombre'], 'apellido' => $docente['apellido'],
                'carrera' => '', 'semestre' => '', 'turno' => '', 'grupo' => ''];
    }

    // Invitado: alguien de fuera que no está en la base. Solo en la clase de maestro
    // invitado; en la regular cada quien entra con su número de control.
    if ($clase['tipo'] !== 'invitado') {
        throw new ErrorDeRegla(409, "En esta clase cada alumno se registra con su número de control");
    }
    if ($datos['nombre'] === '' || $datos['apellido'] === '') {
        throw new ErrorDeRegla(400, "Nombre y apellido son obligatorios para un invitado");
    }
    return [
        'alumno_id' => $datos['alumno_id'] !== '' ? $datos['alumno_id'] : 'INV-' . strtoupper(bin2hex(random_bytes(4))),
        'nombre' => $datos['nombre'], 'apellido' => $datos['apellido'],
        'carrera' => 'Externo', 'semestre' => 'Externo', 'turno' => 'Externo', 'grupo' => 'Externo',
    ];
}

/**
 * Registra la entrada de alguien a la clase abierta del laboratorio y le asigna el
 * equipo. $datos: alumno_id, equipo_id, laboratorio, tipo_usuario ('estudiante',
 * 'docente' o 'invitado'), nombre y apellido (solo invitado), clase_id (opcional).
 * Devuelve la asistencia creada y su clase. Lanza ErrorDeRegla si no puede entrar.
 */
function registrarEntrada(PDO $pdo, array $datos, bool $revisarInscripcion = true): array {
    $laboratorio = $datos['laboratorio'];
    $equipoId = $datos['equipo_id'];
    $clase = claseParaRegistrarse($pdo, $laboratorio, $datos['clase_id'] ?? null);
    $persona = personaQueEntra($pdo, $datos, $clase, $revisarInscripcion);

    return enTransaccion($pdo, function (PDO $pdo) use ($laboratorio, $equipoId, $clase, $persona, $datos) {
        // cerrarClase() bloquea la misma fila: si el docente cierra la clase en este
        // momento, uno espera al otro y nadie queda registrado en una clase cerrada.
        $vigente = buscarClase($pdo, (int)$clase['id'], true);
        if ($vigente['cerrada_en'] !== null) {
            throw new ErrorDeRegla(409, "La clase acaba de cerrarse");
        }

        // Una persona solo está en una clase a la vez.
        $stmt = $pdo->prepare("SELECT laboratorio, clase_id FROM asistencias WHERE alumno_id = ?");
        $stmt->execute([$persona['alumno_id']]);
        if ($previa = $stmt->fetch()) {
            throw new ErrorDeRegla(409, (int)$previa['clase_id'] === (int)$clase['id']
                ? "Ya registraste tu asistencia en esta clase"
                : "Ya estás registrado en una clase en " . nombreDeLaboratorio($previa['laboratorio']) . ". Espera a que termine para registrarte en otra",
                ["codigo" => "ya_registrado"]);
        }

        // Bloquear la fila del equipo hasta terminar: si dos alumnos eligen el mismo
        // equipo al mismo tiempo, el segundo espera y ya lo encuentra ocupado.
        $stmt = $pdo->prepare("SELECT fuera_de_servicio, en_uso FROM equipos WHERE id = ? AND laboratorio = ? FOR UPDATE");
        $stmt->execute([$equipoId, $laboratorio]);
        $equipo = $stmt->fetch();
        // 'personal' (el equipo propio) vale en cualquier laboratorio, aunque nadie
        // haya dado de alta sus equipos.
        if (!$equipo && $equipoId === 'personal') {
            $equipo = ['fuera_de_servicio' => 0, 'en_uso' => 0];
        }
        if (!$equipo) {
            throw new ErrorDeRegla(400, "El equipo $equipoId no existe en este laboratorio");
        }
        if ($equipo['fuera_de_servicio']) {
            throw new ErrorDeRegla(400, "El equipo $equipoId está fuera de servicio");
        }
        if ($equipo['en_uso'] && $equipoId !== 'personal') {
            throw new ErrorDeRegla(409, "El equipo $equipoId ya está ocupado", ["codigo" => "equipo_ocupado"]);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO asistencias (alumno_id, nombre, apellido, carrera, semestre, turno, grupo, equipo_id, laboratorio, tipo_usuario, materia_id, clase_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $persona['alumno_id'], $persona['nombre'], $persona['apellido'], $persona['carrera'], $persona['semestre'],
                $persona['turno'], $persona['grupo'], $equipoId, $laboratorio, $datos['tipo_usuario'], $clase['materia_id'], $clase['id'],
            ]);
        } catch (PDOException $e) {
            // Dos registros de la misma persona al mismo tiempo (doble clic con internet
            // lento): la base deja pasar uno solo.
            if ($e->getCode() === '23000') {
                throw new ErrorDeRegla(409, "Ya registraste tu asistencia en esta clase", ["codigo" => "ya_registrado"]);
            }
            throw $e;
        }
        $id = (int)$pdo->lastInsertId();

        // 'personal' es el equipo propio de cada quien: lo comparten muchos, no se marca.
        $stmt = $pdo->prepare("UPDATE equipos SET en_uso = 1, usuario_actual = ? WHERE id = ? AND laboratorio = ? AND id <> 'personal'");
        $stmt->execute([trim("{$persona['nombre']} {$persona['apellido']} ({$persona['alumno_id']})"), $equipoId, $laboratorio]);

        return ['id' => $id, 'clase' => $clase, 'persona' => $persona];
    });
}

function liberarEquipo(PDO $pdo, string $equipoId, string $laboratorio): void {
    $stmt = $pdo->prepare("UPDATE equipos SET en_uso = 0, usuario_actual = NULL WHERE id = ? AND laboratorio = ?");
    $stmt->execute([$equipoId, $laboratorio]);
}

/** Registra la salida: pasa la asistencia al histórico, la quita de las activas y libera el equipo. */
function registrarSalida(PDO $pdo, array $asistencia): void {
    enTransaccion($pdo, function (PDO $pdo) use ($asistencia) {
        $clase = $asistencia['clase_id'] ? buscarClase($pdo, (int)$asistencia['clase_id']) : null;
        $materia = $clase['materia'] ?? '';
        if ($materia === '' && $asistencia['materia_id']) {
            $stmt = $pdo->prepare("SELECT nombre_materia FROM materias WHERE id = ?");
            $stmt->execute([$asistencia['materia_id']]);
            $materia = (string)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("INSERT INTO asistencias_historico
            (clase_id, alumno_id, nombre_completo, nombre, apellido, carrera, semestre, turno, grupo, materia, docente, equipo_id, laboratorio, tipo_usuario, hora_entrada, hora_salida, fecha)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([
            $asistencia['clase_id'],
            $asistencia['alumno_id'],
            trim($asistencia['nombre'] . ' ' . $asistencia['apellido']),
            $asistencia['nombre'],
            $asistencia['apellido'],
            $asistencia['carrera'],
            $asistencia['semestre'],
            $asistencia['turno'],
            $asistencia['grupo'],
            $materia,
            $clase['docente'] ?? '',
            $asistencia['equipo_id'],
            $asistencia['laboratorio'],
            $asistencia['tipo_usuario'],
            $asistencia['hora_entrada'],
            // La fecha es la del día de la clase (el de la entrada), no la de la salida.
            substr($asistencia['hora_entrada'], 0, 10),
        ]);

        $pdo->prepare("DELETE FROM asistencias WHERE id = ?")->execute([$asistencia['id']]);
        liberarEquipo($pdo, $asistencia['equipo_id'], $asistencia['laboratorio']);
    });
}

/**
 * Cierra una clase: registra la salida de todos los que seguían dentro, libera sus
 * equipos y guarda cuántos asistieron. $cierre: 'normal', 'externo' o 'automatico'.
 */
function cerrarClase(PDO $pdo, int $claseId, string $cierre): array {
    return enTransaccion($pdo, function (PDO $pdo) use ($claseId, $cierre) {
        $clase = buscarClase($pdo, $claseId, true);
        if (!$clase) {
            throw new ErrorDeRegla(404, "Clase no encontrada");
        }
        if ($clase['cerrada_en'] !== null) {
            throw new ErrorDeRegla(409, "La clase ya estaba cerrada");
        }

        $stmt = $pdo->prepare("SELECT * FROM asistencias WHERE clase_id = ?");
        $stmt->execute([$claseId]);
        foreach ($stmt->fetchAll() as $asistencia) {
            registrarSalida($pdo, $asistencia);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM asistencias_historico WHERE clase_id = ?");
        $stmt->execute([$claseId]);
        $total = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE clases SET cerrada_en = NOW(), cierre = ?, total_asistencias = ? WHERE id = ?");
        $stmt->execute([$cierre, $total, $claseId]);

        return buscarClase($pdo, $claseId);
    });
}
