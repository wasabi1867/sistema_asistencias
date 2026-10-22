<?php

declare(strict_types=1);

// ==============================================================
// IMPORTAR LOS DATOS DE FIREBASE
// ==============================================================
// Se corre una vez, al dejar Firebase, sobre una base ya instalada
// (php scripts/instalar.php):
//
//   php scripts/importar-de-firebase.php --proyecto=integrador-7b39d --respaldo=CARPETA
//
// Opciones:
//   --proyecto=ID      Proyecto de Firebase (el projectId de la configuración web).
//   --clave=CLAVE      apiKey de la configuración web, si Firestore la pide.
//   --respaldo=CARPETA Guarda cada colección tal como venía de Firebase (JSON) antes
//                      de importar. Conviene siempre: es la copia de seguridad.
//   --desde=CARPETA    Lee un respaldo hecho antes en lugar de descargar de Firebase.
//   --simular          Hace todo y al final lo deshace: sirve para revisar el reporte
//                      con los datos reales sin guardar nada.
//
// Todo va en una transacción: si algo falla no se guarda nada. Se puede repetir: lo
// que ya estaba se actualiza o se salta, sin duplicarse.
//
// Firebase guardaba la información con otra forma; así se convierte:
// - Cada "materia" de Firebase (que era materia, docente y ciclo a la vez) se separa
//   en la materia del catálogo y su curso; el ciclo "2026-2027-1" es el periodo
//   AGO-DIC 2026 y "2026-2027-2", ENE-JUL 2027.
// - Las materias de cada alumno se vuelven inscripciones a esos cursos.
// - Las prácticas quedan como propias del docente de esa materia.
// - Las clases cerradas (ClassInformation) y sus asistentes, con su hora.
// - Las contraseñas cifradas (bcrypt) se copian tal cual; las que estuvieran en texto
//   se cifran aquí. Cada quien entra con la suya de siempre.
// Lo que no se puede convertir sin adivinar (una carrera que no está en el catálogo,
// un semestre que no es número) queda vacío, y el reporte del final lo dice.
//
// No se copian: las asistencias en curso ni el estado de las clases (el cambio se hace
// sin clases abiertas), HistoricalAttendance (repite lo de ClassInformation) ni los
// avisos viejos (NotificacionesAdmin): las solicitudes y las fallas de equipo sí.

require __DIR__ . '/comun.php';

$opciones = getopt('', ['proyecto:', 'clave:', 'respaldo:', 'desde:', 'simular']);
if (empty($opciones['proyecto']) && empty($opciones['desde'])) {
    fwrite(STDERR, "Uso: php scripts/importar-de-firebase.php --proyecto=ID [--respaldo=CARPETA] [--simular]\n");
    fwrite(STDERR, "  o: php scripts/importar-de-firebase.php --desde=CARPETA [--simular]\n");
    exit(1);
}

$pdo = conexion();
$importacion = new Importacion($pdo, new Firestore($opciones));
$pdo->beginTransaction();
try {
    $importacion->todo();
    isset($opciones['simular']) ? $pdo->rollBack() : $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'La importación falló y no se guardó nada: ' . $e->getMessage() . "\n");
    exit(1);
}

echo isset($opciones['simular']) ? "Simulación: no se guardó nada.\n\n" : "Importación terminada.\n\n";
foreach ($importacion->cuentas as $que => $cuantos) {
    // str_pad cuenta bytes: con acentos desalinearía la columna.
    echo $que . str_repeat(' ', max(1, 44 - mb_strlen($que))) . $cuantos . "\n";
}
if ($importacion->avisos !== []) {
    echo "\nRevisar:\n";
    foreach (array_count_values($importacion->avisos) as $aviso => $veces) {
        echo "- $aviso" . ($veces > 1 ? " ($veces veces)" : '') . "\n";
    }
}

// --------------------------------------------------------------------------
// Lectura de Firestore
// --------------------------------------------------------------------------

/** Las colecciones de Firestore, de su API REST o de un respaldo en JSON. */
final class Firestore
{
    public function __construct(private readonly array $opciones)
    {
    }

    /**
     * Los documentos de una colección como [id, campos].
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    public function coleccion(string $ruta): array
    {
        return array_map(static function (array $crudo) {
            $partes = explode('/', $crudo['name']);
            return [urldecode(end($partes)), array_map([self::class, 'valor'], $crudo['fields'] ?? [])];
        }, $this->crudos($ruta));
    }

    /** Un valor de la API REST ({stringValue: ...}) como valor de PHP. */
    public static function valor(array $valor): mixed
    {
        return match (true) {
            array_key_exists('stringValue', $valor) => $valor['stringValue'],
            array_key_exists('integerValue', $valor) => (int)$valor['integerValue'],
            array_key_exists('doubleValue', $valor) => (float)$valor['doubleValue'],
            array_key_exists('booleanValue', $valor) => (bool)$valor['booleanValue'],
            array_key_exists('nullValue', $valor) => null,
            array_key_exists('timestampValue', $valor) => (new DateTimeImmutable($valor['timestampValue']))->setTimezone(new DateTimeZone(date_default_timezone_get())),
            array_key_exists('arrayValue', $valor) => array_map([self::class, 'valor'], $valor['arrayValue']['values'] ?? []),
            array_key_exists('mapValue', $valor) => array_map([self::class, 'valor'], $valor['mapValue']['fields'] ?? []),
            default => $valor['referenceValue'] ?? null,
        };
    }

    /** @return list<array<string, mixed>> */
    private function crudos(string $ruta): array
    {
        $carpeta = $this->opciones['desde'] ?? $this->opciones['respaldo'] ?? null;
        $archivo = $carpeta === null ? null : rtrim($carpeta, '/\\') . '/' . str_replace('/', '__', $ruta) . '.json';
        if (isset($this->opciones['desde'])) {
            return is_file($archivo) ? json_decode((string)file_get_contents($archivo), true) : [];
        }

        $documentos = [];
        $pagina = null;
        do {
            $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($this->opciones['proyecto'])
                . '/databases/(default)/documents/' . implode('/', array_map('rawurlencode', explode('/', $ruta)))
                . '?pageSize=300' . ($pagina ? '&pageToken=' . urlencode($pagina) : '')
                . (isset($this->opciones['clave']) ? '&key=' . urlencode($this->opciones['clave']) : '');
            $curl = curl_init($url);
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
            $respuesta = curl_exec($curl);
            $estado = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            if ($estado !== 200) {
                throw new RuntimeException("No se pudo leer $ruta de Firebase (HTTP $estado): " . substr((string)$respuesta, 0, 300));
            }
            $datos = json_decode((string)$respuesta, true);
            array_push($documentos, ...($datos['documents'] ?? []));
            $pagina = $datos['nextPageToken'] ?? null;
        } while ($pagina);

        if ($archivo !== null) {
            if (!is_dir(dirname($archivo))) {
                mkdir(dirname($archivo), 0777, true);
            }
            file_put_contents($archivo, json_encode($documentos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return $documentos;
    }
}

// --------------------------------------------------------------------------
// Conversión de los datos
// --------------------------------------------------------------------------

/** Texto sin espacios en los extremos; vacío si no es texto. */
function texto(mixed $valor): string
{
    return match (true) {
        $valor instanceof DateTimeInterface => $valor->format('Y-m-d H:i:s'),
        is_scalar($valor) => trim((string)$valor),
        default => '',
    };
}

function textoONulo(mixed $valor, int $maximo = 255): ?string
{
    $texto = texto($valor);
    return $texto === '' ? null : mb_substr($texto, 0, $maximo);
}

/** "21/10/2026" (como lo escribía toLocaleDateString), "2026-10-21" o una fecha de Firestore → "2026-10-21". */
function fechaDe(mixed $valor): ?string
{
    if ($valor instanceof DateTimeInterface) {
        return $valor->format('Y-m-d');
    }
    $texto = texto($valor);
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
        [$dia, $mes] = [(int)$m[1], (int)$m[2]];
        if ($mes > 12) {
            [$dia, $mes] = [$mes, $dia]; // venía como mes/día
        }
        return checkdate($mes, $dia, (int)$m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $mes, $dia) : null;
    }
    return preg_match('#^\d{4}-\d{2}-\d{2}$#', $texto) && checkdate((int)substr($texto, 5, 2), (int)substr($texto, 8, 2), (int)substr($texto, 0, 4))
        ? $texto : null;
}

function fechaHoraDe(mixed $valor): ?string
{
    return $valor instanceof DateTimeInterface ? $valor->format('Y-m-d H:i:s') : null;
}

/** "9:46:22 p. m.", "9:46:22 PM" o "21:46:22" (toLocaleTimeString) → "21:46:22". */
function horaDe(mixed $valor): ?string
{
    if (!preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?\s*([ap])?/i', texto($valor), $m)) {
        return null;
    }
    $hora = (int)$m[1];
    $tarde = strtolower($m[4] ?? '');
    if ($tarde === 'p' && $hora < 12) {
        $hora += 12;
    }
    if ($tarde === 'a' && $hora === 12) {
        $hora = 0;
    }
    return $hora > 23 ? null : sprintf('%02d:%02d:%02d', $hora, $m[2], $m[3] ?? 0);
}

/** "5", "5to", "Quinto" → 5. */
function semestreDe(mixed $valor): ?int
{
    $texto = mb_strtolower(texto($valor));
    $ordinales = ['primer' => 1, 'segund' => 2, 'tercer' => 3, 'cuart' => 4, 'quint' => 5, 'sext' => 6, 'septim' => 7, 'séptim' => 7,
        'octav' => 8, 'noven' => 9, 'decim' => 10, 'décim' => 10];
    if (preg_match('/\d+/', $texto, $m)) {
        $numero = (int)$m[0];
    } else {
        $numero = null;
        foreach ($ordinales as $raiz => $valorOrdinal) {
            if (str_starts_with($texto, $raiz)) {
                $numero = $valorOrdinal;
            }
        }
    }
    return $numero !== null && $numero >= 1 && $numero <= 15 ? $numero : null;
}

function turnoDe(mixed $valor): ?string
{
    $texto = mb_strtolower(texto($valor));
    return match (true) {
        str_starts_with($texto, 'mat') => 'matutino',
        str_starts_with($texto, 'ves') => 'vespertino',
        default => null,
    };
}

/** "2 horas", "90 minutos", "1.5 hrs", "1:30" → minutos. Un número solo es ambiguo: null. */
function minutosDe(mixed $valor): ?int
{
    $texto = mb_strtolower(str_replace(',', '.', texto($valor)));
    $minutos = match (true) {
        (bool)preg_match('/^(\d+):(\d{2})/', $texto, $m) => (int)$m[1] * 60 + (int)$m[2],
        (bool)preg_match('/(\d+(?:\.\d+)?)\s*(h|hr|hrs|hora|horas)\b/', $texto, $m) => (int)round((float)$m[1] * 60)
            + (preg_match('/(\d+)\s*(m|min|mins|minutos?)\b/', $texto, $n) ? (int)$n[1] : 0),
        (bool)preg_match('/(\d+)\s*(m|min|mins|minutos?)\b/', $texto, $m) => (int)$m[1],
        default => null,
    };
    return $minutos !== null && $minutos >= 1 && $minutos <= 1440 ? $minutos : null;
}

// --------------------------------------------------------------------------
// La importación
// --------------------------------------------------------------------------

final class Importacion
{
    /** @var array<string, int> cuántos registros de cada cosa */
    public array $cuentas = [];
    /** @var list<string> lo que hay que revisar a mano */
    public array $avisos = [];

    /** @var array<string, int> "rol:matrícula de Firebase" => id */
    private array $usuarios = [];
    /** @var array<string, int> id de la materia en Firebase => id del curso */
    private array $cursosDeFirebase = [];
    /** @var array<string, array{0: int, 1: int}> id de la materia en Firebase => [materia, docente] */
    private array $materiasDeFirebase = [];
    /** @var array<string, int> */
    private array $laboratorios = [];
    /** @var array<string, ?int> texto de la carrera en Firebase => id en el catálogo */
    private array $carreras = [];
    private ?int $cuentaDeImportacion = null;
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    private array $alumnosDeFirebase = [];

    public function __construct(private readonly PDO $pdo, private readonly Firestore $firestore)
    {
    }

    public function todo(): void
    {
        foreach ($this->pdo->query('SELECT clave, id FROM laboratorios') as $fila) {
            $this->laboratorios[$fila['clave']] = (int)$fila['id'];
        }
        $this->alumnosDeFirebase = $this->firestore->coleccion('Alumnos');

        $this->cuentasDelPersonal();
        $this->materiasYCursos();
        $this->alumnos();
        $this->equipos();
        $this->clases();
        $this->solicitudes();
        $this->bitacora();
        $this->eventos();
    }

    private function cuentasDelPersonal(): void
    {
        foreach (['Administrador' => 'admin', 'Docentes' => 'docente', 'Laboratoristas' => 'laboratorista'] as $coleccion => $rol) {
            foreach ($this->firestore->coleccion($coleccion) as [$id, $d]) {
                $contrasena = texto($d['Contraseña'] ?? '');
                if ($contrasena === '') {
                    $this->avisos[] = "La cuenta de $rol $id no tiene contraseña en Firebase y no se importó";
                    continue;
                }
                $email = mb_strtolower(texto($d['Email'] ?? ''));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $this->avisos[] = "El correo de la cuenta de $rol $id no es válido y se dejó vacío";
                    $email = '';
                }
                $datos = [
                    'nombre' => textoONulo($d['Nombre'] ?? null, 100) ?? $id,
                    'apellido' => textoONulo($d['Apellido'] ?? null, 100) ?? '',
                    'email' => $email === '' ? null : $email,
                    'departamento' => textoONulo($d['Departamento'] ?? null, 150),
                ];
                $existente = $this->valor(
                    'SELECT id FROM usuarios WHERE rol = ? AND (matricula = ? OR (email IS NOT NULL AND email = ?)) LIMIT 1',
                    [$rol, $id, $datos['email']]
                );
                if ($existente !== null) {
                    // Al repetir la importación no se toca la contraseña: pudo cambiarse después.
                    $this->actualizar('usuarios', (int)$existente, $datos);
                    $this->usuarios["$rol:$id"] = (int)$existente;
                    continue;
                }
                // Las de bcryptjs ($2a$, $2b$) se copian; en texto, se cifran ya.
                $hash = str_starts_with($contrasena, '$') ? $contrasena : password_hash($contrasena, PASSWORD_DEFAULT);
                $this->usuarios["$rol:$id"] = $this->insertar('usuarios', ['rol' => $rol, 'matricula' => mb_substr($id, 0, 30), 'password_hash' => $hash] + $datos);
                $this->contar("cuentas ($rol)");
            }
        }
    }

    private function materiasYCursos(): void
    {
        // El grupo del curso no estaba en Firebase: se toma el de la mayoría de sus alumnos.
        $grupos = [];
        foreach ($this->alumnosDeFirebase as [, $a]) {
            $grupo = mb_strtoupper(texto($a['Semestre'] ?? '') . texto($a['Grupo'] ?? ''));
            foreach ((array)($a['Materias'] ?? []) as $materia) {
                if ($grupo !== '' && is_string($materia)) {
                    $grupos[$materia][$grupo] = ($grupos[$materia][$grupo] ?? 0) + 1;
                }
            }
        }

        foreach ($this->firestore->coleccion('Materias') as [$id, $m]) {
            $nombre = textoONulo($m['NombreMateria'] ?? null, 150);
            if ($nombre === null) {
                $this->avisos[] = "La materia $id de Firebase no tiene nombre y no se importó";
                continue;
            }
            $materia = $this->materiaPorNombre($nombre, semestreDe($m['Semestre'] ?? null));
            $docente = $this->usuarioDeFirebase('docente', texto($m['MaestroID'] ?? $m['DocenteId'] ?? ''), "la materia $nombre");
            $periodo = $this->periodoDeCiclo(texto($m['CicloEscolar'] ?? ''));
            if ($periodo === null) {
                $this->avisos[] = "La materia $nombre no tiene un ciclo escolar reconocible; quedó en el periodo actual";
                $periodo = $this->periodoPara(date('Y-m-d'));
            }
            if (isset($grupos[$id])) {
                arsort($grupos[$id]);
            }
            $grupo = mb_substr((string)(array_key_first($grupos[$id] ?? []) ?? 'G1'), 0, 20);
            $laboratorio = $this->laboratorios[texto($m['Laboratorio'] ?? '')] ?? null;

            $curso = $this->cursoPara($materia, $periodo, $docente, $grupo, $laboratorio, turnoDe($m['Turno'] ?? null));
            $this->cursosDeFirebase[$id] = $curso;
            $this->materiasDeFirebase[$id] = [$materia, $docente];
            $this->practicas($id, $materia, $docente);
        }
    }

    private function practicas(string $materiaDeFirebase, int $materia, ?int $docente): void
    {
        foreach ($this->firestore->coleccion("Materias/$materiaDeFirebase/Practicas") as [$id, $p]) {
            $titulo = textoONulo($p['Titulo'] ?? null, 150);
            if ($titulo === null) {
                continue;
            }
            if ($this->valor('SELECT 1 FROM practicas WHERE materia_id = ? AND docente_id <=> ? AND titulo = ?', [$materia, $docente, $titulo]) !== null) {
                continue;
            }
            $numero = (int)texto($p['Numero'] ?? '') ?: null;
            $ocupado = $numero !== null && $this->valor('SELECT 1 FROM practicas WHERE materia_id = ? AND docente_id <=> ? AND numero = ? AND activa', [$materia, $docente, $numero]) !== null;
            if ($numero === null || $ocupado) {
                if ($ocupado) {
                    $this->avisos[] = "Dos prácticas de la misma materia tenían el número $numero; se renumeró \"$titulo\"";
                }
                $numero = 1 + (int)$this->valor('SELECT MAX(numero) FROM practicas WHERE materia_id = ? AND docente_id <=> ? AND activa', [$materia, $docente]);
            }
            $duracion = texto($p['Duracion'] ?? '');
            $minutos = minutosDe($duracion);
            if ($duracion !== '' && $minutos === null) {
                $this->avisos[] = "No se entendió la duración \"$duracion\" de una práctica; quedó vacía";
            }
            $this->insertar('practicas', [
                'materia_id' => $materia,
                'docente_id' => $docente,
                'numero' => $numero,
                'titulo' => $titulo,
                'descripcion' => textoONulo($p['Descripcion'] ?? null, 5000),
                'duracion_minutos' => $minutos,
                'fecha' => fechaDe($p['fecha'] ?? null),
            ]);
            $this->contar('prácticas');
        }
    }

    private function alumnos(): void
    {
        foreach ($this->alumnosDeFirebase as [$matricula, $a]) {
            if (!preg_match('/^[A-Za-z0-9-]{1,20}$/', $matricula)) {
                $this->avisos[] = "La matrícula \"$matricula\" no es válida y ese alumno no se importó";
                continue;
            }
            $alumno = $this->alumnoPorMatricula($matricula, $a, activo: true);
            foreach ((array)($a['Materias'] ?? []) as $materia) {
                $curso = is_string($materia) ? ($this->cursosDeFirebase[$materia] ?? null) : null;
                if ($curso === null) {
                    $this->avisos[] = 'Un alumno estaba inscrito en una materia que ya no existe en Firebase';
                    continue;
                }
                // Sin la fecha real, cuenta desde el inicio del periodo del curso.
                $nueva = $this->ejecutar(
                    'INSERT IGNORE INTO inscripciones (curso_id, alumno_id, inscrito_en)
                     SELECT ?, ?, p.fecha_inicio FROM cursos cu JOIN periodos p ON p.id = cu.periodo_id WHERE cu.id = ?',
                    [$curso, $alumno, $curso]
                );
                if ($nueva > 0) {
                    $this->contar('inscripciones');
                }
            }
        }
    }

    /** Los equipos de Firebase reemplazan a los del laboratorio (los de los datos iniciales). */
    private function equipos(): void
    {
        foreach ($this->firestore->coleccion('Numero de equipos') as [$clave, $datos]) {
            $laboratorio = $this->laboratorios[$clave] ?? null;
            if ($laboratorio === null) {
                $this->avisos[] = "Hay equipos del laboratorio \"$clave\", que no está en el catálogo; no se importaron";
                continue;
            }
            $equipos = array_filter((array)($datos['Equipos'] ?? []), static fn ($e) => is_array($e) && !in_array(texto($e['id'] ?? ''), ['', 'personal'], true));
            if ($equipos === []) {
                continue;
            }
            $etiquetas = array_map(static fn (array $e) => mb_substr(texto($e['id']), 0, 30), $equipos);

            foreach ($this->filas('SELECT id, etiqueta FROM equipos WHERE laboratorio_id = ? AND activo', [$laboratorio]) as $actual) {
                if (!in_array($actual['etiqueta'], $etiquetas, true)) {
                    $usado = $this->valor('SELECT 1 FROM asistencias WHERE equipo_id = ? UNION SELECT 1 FROM reportes_de_equipo WHERE equipo_id = ? LIMIT 1', [$actual['id'], $actual['id']]);
                    $usado === null
                        ? $this->ejecutar('DELETE FROM equipos WHERE id = ?', [$actual['id']])
                        : $this->ejecutar('UPDATE equipos SET activo = FALSE WHERE id = ?', [$actual['id']]);
                }
            }
            foreach ($equipos as $e) {
                $etiqueta = mb_substr(texto($e['id']), 0, 30);
                $valores = ['fuera_de_servicio' => (bool)($e['fueraDeServicio'] ?? false), 'notas' => textoONulo($e['notas'] ?? null, 2000)];
                $id = $this->valor('SELECT id FROM equipos WHERE laboratorio_id = ? AND etiqueta = ? AND activo', [$laboratorio, $etiqueta]);
                if ($id === null) {
                    $id = $this->insertar('equipos', ['laboratorio_id' => $laboratorio, 'etiqueta' => $etiqueta] + $valores);
                    $this->contar('equipos');
                } else {
                    $this->actualizar('equipos', (int)$id, $valores);
                }
                // El estado de reparación pasa a ser un reporte de falla, con su historial.
                $estado = texto($e['estadoReparacion'] ?? '');
                if (in_array($estado, ['reportado', 'en_proceso', 'resuelto'], true)
                    && $this->valor('SELECT 1 FROM reportes_de_equipo WHERE equipo_id = ?', [$id]) === null) {
                    $this->insertar('reportes_de_equipo', [
                        'equipo_id' => $id,
                        'descripcion' => $valores['notas'] ?? 'Falla registrada en el sistema anterior',
                        'estado' => $estado,
                        'comentario' => textoONulo($e['comentarioEstado'] ?? null, 2000),
                        'resuelto_en' => $estado === 'resuelto' ? date('Y-m-d H:i:s') : null,
                    ]);
                    $this->contar('reportes de equipo');
                }
            }
        }
    }

    /** Las clases ya cerradas (ClassInformation), con quiénes asistieron. */
    private function clases(): void
    {
        foreach ($this->firestore->coleccion('ClassInformation') as [$id, $c]) {
            $fecha = fechaDe($c['fecha'] ?? null) ?? (isset($c['fechaInicio']) ? fechaDe($c['fechaInicio']) : null);
            $laboratorio = $this->laboratorios[texto($c['laboratorio'] ?? '') ?: 'programacion'] ?? null;
            if ($fecha === null || $laboratorio === null) {
                $this->avisos[] = 'Una clase de Firebase no tiene fecha o laboratorio reconocible y no se importó';
                continue;
            }
            $inicio = $fecha . ' ' . (horaDe($c['horaInicio'] ?? null) ?? '00:00:00');
            $fin = $fecha . ' ' . (horaDe($c['horaFin'] ?? null) ?? substr($inicio, 11));
            if ($fin < $inicio) {
                $fin = $inicio;
            }
            $deInvitado = texto($c['tipoClase'] ?? '') === 'invitado' || texto($c['departamento'] ?? '') !== '';
            $tipo = $deInvitado ? 'invitado' : 'regular';
            // Una clase es la misma si empezó a la misma hora en el mismo laboratorio:
            // así repetir la importación no la duplica.
            if ($this->valor('SELECT 1 FROM clases WHERE laboratorio_id = ? AND abierta_en = ? AND tipo = ?', [$laboratorio, $inicio, $tipo]) !== null) {
                continue;
            }
            $materia = textoONulo($c['materia'] ?? null, 150);
            $practica = textoONulo($c['practica'] ?? null, 150);
            $docenteNombre = textoONulo(texto($c['maestroNombre'] ?? '') . ' ' . texto($c['maestroApellido'] ?? ''), 200);
            $grupo = textoONulo($c['grupo'] ?? null, 20);

            $clase = ['laboratorio_id' => $laboratorio, 'tipo' => $tipo, 'abierta_en' => $inicio, 'cerrada_en' => $fin,
                'cierre' => !empty($c['finalizadaAutomaticamente']) ? 'automatico' : 'normal'];
            if ($deInvitado) {
                $clase += ['materia_invitado' => $materia ?? 'Clase de maestro invitado', 'practica_invitado' => $practica,
                    'docente_invitado' => $docenteNombre, 'departamento' => textoONulo($c['departamento'] ?? null, 150),
                    'grupo_invitado' => $grupo, 'abierta_por' => $this->cuentaDeImportacion()];
            } else {
                $docente = $this->usuarioDeFirebase('docente', texto($c['maestroId'] ?? ''), "una clase de $materia");
                $materiaId = $this->materiaPorNombre($materia ?? 'Sin materia', null);
                $curso = $this->cursoPara($materiaId, $this->periodoPara($fecha), $docente, mb_strtoupper($grupo ?? 'G1'), $laboratorio, null);
                // La práctica, si es una de las de la materia; si no, su título tal cual.
                $practicaId = $practica === null ? null : $this->valor(
                    'SELECT id FROM practicas WHERE materia_id = ? AND (docente_id IS NULL OR docente_id <=> ?) AND titulo = ? LIMIT 1',
                    [$materiaId, $docente, $practica]
                );
                $clase += ['curso_id' => $curso, 'practica_id' => $practicaId, 'practica_invitado' => $practicaId === null ? $practica : null,
                    'abierta_por' => $docente ?? $this->cuentaDeImportacion()];
            }
            $claseId = $this->insertar('clases', $clase);
            $this->contar('clases');
            $this->asistentes($claseId, $laboratorio, $deInvitado, (array)($c['alumnos'] ?? []), $inicio, $fin);
        }
    }

    private function asistentes(int $clase, int $laboratorio, bool $deInvitado, array $alumnos, string $entrada, string $salida): void
    {
        $vistos = [];
        foreach ($alumnos as $a) {
            if (!is_array($a)) {
                continue;
            }
            $matricula = texto($a['id'] ?? '');
            $persona = ['tipo_persona' => 'alumno'];
            $docente = $matricula === '' ? null
                : $this->valor("SELECT u.id FROM usuarios u WHERE u.rol = 'docente' AND u.matricula = ? AND NOT EXISTS (SELECT 1 FROM alumnos WHERE matricula = ?)", [$matricula, $matricula]);
            if ($docente !== null) {
                $persona = ['tipo_persona' => 'docente', 'usuario_id' => $docente];
            } elseif ($matricula !== '' && !str_starts_with($matricula, 'INV-') && preg_match('/^[A-Za-z0-9-]{1,20}$/', $matricula)) {
                // Si ya no estaba en Firebase, se crea desactivado para no perder su historial.
                $persona['alumno_id'] = $this->alumnoPorMatricula($matricula, [
                    'Nombre' => $a['nombre'] ?? $a['Nombre'] ?? '', 'Apellido' => $a['apellido'] ?? $a['Apellido'] ?? '',
                    'Carrera' => $a['carrera'] ?? '', 'Semestre' => $a['semestre'] ?? '', 'Turno' => $a['turno'] ?? '', 'Grupo' => $a['grupo'] ?? '',
                ], activo: false);
            } elseif ($deInvitado) {
                $persona = ['tipo_persona' => 'invitado', 'invitado_nombre' => textoONulo($a['nombre'] ?? null, 100) ?? 'Invitado',
                    'invitado_apellido' => textoONulo($a['apellido'] ?? null, 100)];
            } else {
                continue;
            }
            $clave = json_encode($persona);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $etiqueta = texto($a['equipo'] ?? '');
            $equipo = $etiqueta === '' || $etiqueta === 'personal' ? null
                : $this->valor('SELECT id FROM equipos WHERE laboratorio_id = ? AND etiqueta = ? ORDER BY activo DESC, id DESC LIMIT 1', [$laboratorio, $etiqueta]);
            if ($etiqueta !== '' && $etiqueta !== 'personal' && $equipo === null) {
                $this->avisos[] = "Una asistencia era del equipo \"$etiqueta\", que no existe; quedó como equipo propio";
            }
            $this->insertar('asistencias', $persona + [
                'laboratorio_id' => $laboratorio, 'clase_id' => $clase, 'equipo_id' => $equipo, 'hora_entrada' => $entrada, 'hora_salida' => $salida,
            ]);
            $this->contar('asistencias');
        }
    }

    private function solicitudes(): void
    {
        $estados = ['aprobado_temporal' => 'aprobada_temporal', 'aprobada_temporal' => 'aprobada_temporal', 'aprobada' => 'aprobada_temporal',
            'aprobado_permanente' => 'aprobada_permanente', 'aprobada_permanente' => 'aprobada_permanente',
            'rechazado' => 'rechazada', 'rechazada' => 'rechazada'];
        foreach ($this->firestore->coleccion('SolicitudesIngreso') as [, $s]) {
            $alumno = $this->valor('SELECT id FROM alumnos WHERE matricula = ?', [texto($s['alumnoId'] ?? '')]);
            $laboratorio = $this->laboratorios[texto($s['laboratorio'] ?? '') ?: 'programacion'] ?? null;
            $fecha = fechaHoraDe($s['fechaSolicitud'] ?? null);
            if ($alumno === null || $laboratorio === null || $fecha === null) {
                $this->avisos[] = 'Una solicitud de ingreso no tiene alumno, laboratorio o fecha reconocible y no se importó';
                continue;
            }
            if ($this->valor('SELECT 1 FROM solicitudes_ingreso WHERE alumno_id = ? AND laboratorio_id = ? AND creada_en = ?', [$alumno, $laboratorio, $fecha]) !== null) {
                continue;
            }
            // La clase a la que pidió entrar: la de esa materia que estaba abierta a esa hora.
            [$materia, $docente] = $this->materiasDeFirebase[texto($s['materiaId'] ?? '')] ?? [null, null];
            $clase = $materia === null ? null : $this->valor(
                'SELECT cl.id FROM clases cl JOIN cursos cu ON cu.id = cl.curso_id
                 WHERE cl.laboratorio_id = ? AND cu.materia_id = ? AND cu.docente_id <=> ? AND ? BETWEEN cl.abierta_en AND cl.cerrada_en LIMIT 1',
                [$laboratorio, $materia, $docente, $fecha]
            );
            $original = texto($s['estado'] ?? '');
            // Las que quedaron sin resolver eran de clases que ya terminaron.
            $estado = $estados[$original] ?? 'rechazada';
            $comentario = isset($estados[$original]) ? null : 'Quedó sin resolver en el sistema anterior';
            if ($estado === 'aprobada_permanente' && $clase === null) {
                $estado = 'aprobada_temporal';
                $comentario = 'En el sistema anterior se aprobó inscribiéndolo en la materia';
            }
            $equipo = $this->valor('SELECT id FROM equipos WHERE laboratorio_id = ? AND etiqueta = ? ORDER BY activo DESC LIMIT 1', [$laboratorio, texto($s['equipo'] ?? '')]);
            $this->insertar('solicitudes_ingreso', [
                'laboratorio_id' => $laboratorio, 'clase_id' => $clase, 'alumno_id' => $alumno, 'equipo_id' => $equipo,
                'motivo' => textoONulo($s['motivo'] ?? null, 1000), 'estado' => $estado, 'leida' => (bool)($s['leida'] ?? true),
                'creada_en' => $fecha, 'resuelta_en' => $fecha, 'comentario' => $comentario,
            ]);
            $this->contar('solicitudes');
        }
    }

    private function bitacora(): void
    {
        foreach ($this->firestore->coleccion('logs') as [, $l]) {
            $fecha = fechaHoraDe($l['timestamp'] ?? null);
            $accion = textoONulo($l['action'] ?? null, 60);
            if ($fecha === null || $accion === null) {
                continue;
            }
            $detalles = textoONulo($l['details'] ?? null, 65000);
            if ($this->valor('SELECT 1 FROM bitacora WHERE accion = ? AND fecha = ? AND detalles <=> ?', [$accion, $fecha, $detalles]) !== null) {
                continue;
            }
            $this->insertar('bitacora', [
                'fecha' => $fecha, 'accion' => $accion, 'detalles' => $detalles,
                'usuario' => textoONulo($l['user'] ?? null, 150) ?? 'Sistema anterior',
                'laboratorio_id' => $this->laboratorios[texto($l['laboratorio'] ?? '')] ?? null,
            ]);
            $this->contar('bitácora');
        }
    }

    private function eventos(): void
    {
        foreach ($this->firestore->coleccion('EventosLaboratorio') as [, $e]) {
            $nombre = textoONulo($e['nombre'] ?? null, 150);
            $laboratorio = $this->laboratorios[texto($e['laboratorio'] ?? '') ?: 'programacion'] ?? null;
            if ($nombre === null || $laboratorio === null) {
                continue;
            }
            $fecha = fechaDe($e['fecha'] ?? null);
            if ($this->valor('SELECT 1 FROM eventos WHERE laboratorio_id = ? AND nombre = ? AND fecha <=> ?', [$laboratorio, $nombre, $fecha]) !== null) {
                continue;
            }
            $participantes = (int)texto($e['participantes'] ?? '');
            $this->insertar('eventos', [
                'laboratorio_id' => $laboratorio, 'nombre' => $nombre, 'tipo' => textoONulo($e['tipo'] ?? null, 50),
                'organizador' => textoONulo($e['organizador'] ?? null, 150), 'participantes' => $participantes > 0 ? min($participantes, 10000) : null,
                'fecha' => $fecha, 'hora' => horaDe($e['hora'] ?? null), 'duracion' => textoONulo($e['duracion'] ?? null, 50),
                'descripcion' => textoONulo($e['descripcion'] ?? null, 5000), 'resultados' => textoONulo($e['resultados'] ?? null, 5000),
                'creado_en' => fechaHoraDe($e['createdAt'] ?? null) ?? date('Y-m-d H:i:s'),
            ]);
            $this->contar('eventos');
        }
    }

    // ---------------------------------------------------------------
    // Buscar o crear
    // ---------------------------------------------------------------

    private function usuarioDeFirebase(string $rol, string $matricula, string $donde): ?int
    {
        if ($matricula === '') {
            return null;
        }
        $id = $this->usuarios["$rol:$matricula"] ?? $this->valor('SELECT id FROM usuarios WHERE rol = ? AND matricula = ?', [$rol, $matricula]);
        if ($id === null) {
            $this->avisos[] = "El $rol $matricula de $donde no existe; quedó sin $rol";
        }
        return $id === null ? null : (int)$id;
    }

    /**
     * Las clases de maestro invitado no dicen quién las abrió: se atribuyen a esta cuenta,
     * desactivada, para que el historial diga de dónde vienen.
     */
    private function cuentaDeImportacion(): int
    {
        return $this->cuentaDeImportacion ??= (int)($this->valor("SELECT id FROM usuarios WHERE rol = 'admin' AND matricula = 'FIREBASE'")
            ?? $this->insertar('usuarios', [
                'rol' => 'admin', 'matricula' => 'FIREBASE', 'nombre' => 'Importación de Firebase', 'apellido' => '',
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'activo' => false,
            ]));
    }

    private function materiaPorNombre(string $nombre, ?int $semestre): int
    {
        $id = $this->valor('SELECT id FROM materias WHERE nombre = ? ORDER BY carrera_id IS NULL DESC LIMIT 1', [$nombre]);
        if ($id !== null) {
            return (int)$id;
        }
        $this->contar('materias');
        return $this->insertar('materias', ['nombre' => $nombre, 'semestre' => $semestre]);
    }

    /** "2026-2027-1" es agosto–diciembre de 2026; "2026-2027-2", enero–julio de 2027. */
    private function periodoDeCiclo(string $ciclo): ?int
    {
        if (!preg_match('/^(\d{4})-(\d{4})-([12])$/', $ciclo, $m)) {
            return null;
        }
        return $this->periodoPara($m[3] === '1' ? "$m[1]-09-01" : "$m[2]-03-01");
    }

    /** El periodo que contiene la fecha; si no hay, se crea el semestre (ENE-JUL o AGO-DIC) de esa fecha. */
    private function periodoPara(string $fecha): int
    {
        $id = $this->valor('SELECT id FROM periodos WHERE ? BETWEEN fecha_inicio AND fecha_fin LIMIT 1', [$fecha]);
        if ($id !== null) {
            return (int)$id;
        }
        $anio = substr($fecha, 0, 4);
        [$nombre, $inicio, $fin] = (int)substr($fecha, 5, 2) >= 8
            ? ["AGO-DIC $anio", "$anio-08-01", "$anio-12-31"]
            : ["ENE-JUL $anio", "$anio-01-01", "$anio-07-31"];
        $this->contar('periodos');
        return $this->insertar('periodos', ['nombre' => $nombre, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin]);
    }

    /**
     * El curso de esa materia, periodo y docente. Si el grupo ya lo tiene otro docente
     * (dos materias de Firebase con el mismo nombre), se le agrega un número.
     */
    private function cursoPara(int $materia, int $periodo, ?int $docente, string $grupo, ?int $laboratorio, ?string $turno): int
    {
        $suyo = $this->valor('SELECT id FROM cursos WHERE materia_id = ? AND periodo_id = ? AND docente_id <=> ? ORDER BY grupo = ? DESC LIMIT 1', [$materia, $periodo, $docente, $grupo]);
        if ($suyo !== null) {
            return (int)$suyo;
        }
        $base = $grupo;
        for ($n = 2; $this->valor('SELECT 1 FROM cursos WHERE materia_id = ? AND periodo_id = ? AND grupo = ?', [$materia, $periodo, $grupo]) !== null; $n++) {
            $grupo = mb_substr($base, 0, 16) . "-$n";
        }
        $this->contar('cursos');
        return $this->insertar('cursos', ['materia_id' => $materia, 'periodo_id' => $periodo, 'grupo' => $grupo, 'docente_id' => $docente,
            'laboratorio_id' => $laboratorio, 'turno' => $turno]);
    }

    /** El alumno con esa matrícula; si no existe se crea con los datos de Firebase. */
    private function alumnoPorMatricula(string $matricula, array $a, bool $activo): int
    {
        $id = $this->valor('SELECT id FROM alumnos WHERE matricula = ?', [$matricula]);
        // Lo que diga el historial de una clase no cambia a un alumno que ya existe.
        if ($id !== null && !$activo) {
            return (int)$id;
        }
        $carreraTexto = texto($a['Carrera'] ?? '');
        $carrera = $carreraTexto === '' ? null : $this->carreraPara($carreraTexto);
        $semestre = semestreDe($a['Semestre'] ?? null);
        if (texto($a['Semestre'] ?? '') !== '' && $semestre === null) {
            $this->avisos[] = 'No se entendió el semestre "' . texto($a['Semestre']) . '" de un alumno; quedó vacío';
        }
        $datos = [
            'nombre' => textoONulo($a['Nombre'] ?? null, 100) ?? $matricula,
            'apellido' => textoONulo($a['Apellido'] ?? null, 100) ?? '',
            'carrera_id' => $carrera,
            'semestre' => $semestre,
            'turno' => turnoDe($a['Turno'] ?? null),
            'grupo' => textoONulo($a['Grupo'] ?? null, 20),
        ];
        if ($id !== null) {
            $this->actualizar('alumnos', (int)$id, $datos + ['activo' => true]);
            return (int)$id;
        }
        if (!$activo) {
            $this->avisos[] = 'Había asistencias de alumnos que ya no están en Firebase; se crearon desactivados';
        }
        $this->contar($activo ? 'alumnos' : 'alumnos desactivados (solo en el historial)');
        return $this->insertar('alumnos', ['matricula' => $matricula, 'activo' => $activo] + $datos);
    }

    /**
     * La carrera por su clave o su nombre (la colación no distingue mayúsculas ni
     * acentos), o una del catálogo cuyo nombre sea el principio del texto
     * ("Licenciatura en Administración de Empresas" → "Licenciatura en Administración").
     */
    private function carreraPara(string $texto): ?int
    {
        if (array_key_exists($texto, $this->carreras)) {
            return $this->carreras[$texto];
        }
        $id = $this->valor('SELECT id FROM carreras WHERE clave = ? OR nombre = ? LIMIT 1', [$texto, $texto])
            ?? $this->valor("SELECT id FROM carreras WHERE ? LIKE CONCAT(nombre, '%') ORDER BY CHAR_LENGTH(nombre) DESC LIMIT 1", [$texto]);
        if ($id === null) {
            $this->avisos[] = "La carrera \"$texto\" no está en el catálogo: sus alumnos quedaron sin carrera (agrégala y vuelve a importar)";
        }
        return $this->carreras[$texto] = $id === null ? null : (int)$id;
    }

    // ---------------------------------------------------------------
    // SQL
    // ---------------------------------------------------------------

    private function valor(string $sql, array $parametros = []): mixed
    {
        $sentencia = $this->pdo->prepare($sql);
        $sentencia->execute($parametros);
        $valor = $sentencia->fetchColumn();
        return $valor === false ? null : $valor;
    }

    private function filas(string $sql, array $parametros = []): array
    {
        $sentencia = $this->pdo->prepare($sql);
        $sentencia->execute($parametros);
        return $sentencia->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return int cuántos renglones cambió */
    private function ejecutar(string $sql, array $parametros = []): int
    {
        $sentencia = $this->pdo->prepare($sql);
        $sentencia->execute(array_map(static fn ($v) => is_bool($v) ? (int)$v : $v, $parametros));
        return $sentencia->rowCount();
    }

    private function insertar(string $tabla, array $valores): int
    {
        $columnas = implode(', ', array_map(static fn (string $c) => "`$c`", array_keys($valores)));
        $this->ejecutar("INSERT INTO `$tabla` ($columnas) VALUES (" . implode(', ', array_fill(0, count($valores), '?')) . ')', array_values($valores));
        return (int)$this->pdo->lastInsertId();
    }

    private function actualizar(string $tabla, int $id, array $valores): void
    {
        $asignaciones = implode(', ', array_map(static fn (string $c) => "`$c` = ?", array_keys($valores)));
        $this->ejecutar("UPDATE `$tabla` SET $asignaciones WHERE id = ?", [...array_values($valores), $id]);
    }

    private function contar(string $que): bool
    {
        $this->cuentas[$que] = ($this->cuentas[$que] ?? 0) + 1;
        return true;
    }
}
