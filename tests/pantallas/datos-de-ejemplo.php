<?php

declare(strict_types=1);

// Los datos de la prueba de pantallas, creados a través de la API con la cuenta del
// administrador: periodos, cuentas, materias, alumnos, cursos, prácticas y horarios. Todo
// es inventado. El periodo actual se calcula alrededor de hoy, para que la prueba pueda
// abrir clases cualquier día del año. Lo corre tests/pantallas/correr.js:
//
//   php tests/pantallas/datos-de-ejemplo.php http://127.0.0.1:8080/api correo contraseña

[, $base, $correo, $contrasena] = $argv + [null, '', '', ''];
$base = rtrim($base, '/');
$galleta = tempnam(sys_get_temp_dir(), 'galleta');

function pedir(string $metodo, string $ruta, ?array $cuerpo = null): mixed
{
    global $base, $galleta;
    $c = curl_init($base . $ruta);
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $galleta,
        CURLOPT_COOKIEFILE => $galleta,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($cuerpo !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }
    $respuesta = curl_exec($c);
    $codigo = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    if ($codigo >= 400 || $respuesta === false) {
        fwrite(STDERR, "$metodo $ruta -> $codigo $respuesta\n");
        exit(1);
    }
    return $respuesta === '' ? null : json_decode($respuesta, true);
}

pedir('POST', '/sesion', ['usuario' => $correo, 'contrasena' => $contrasena, 'rol' => 'admin']);

$hoy = new DateTimeImmutable('today', new DateTimeZone('America/Hermosillo'));
$periodo = pedir('POST', '/periodos', [
    'nombre' => 'SEMESTRE DE PRUEBA',
    'fechaInicio' => $hoy->modify('-45 days')->format('Y-m-d'),
    'fechaFin' => $hoy->modify('+120 days')->format('Y-m-d'),
]);
pedir('POST', '/periodos', [
    'nombre' => 'SEMESTRE ANTERIOR',
    'fechaInicio' => $hoy->modify('-230 days')->format('Y-m-d'),
    'fechaFin' => $hoy->modify('-60 days')->format('Y-m-d'),
]);

$maria = pedir('POST', '/usuarios', ['rol' => 'docente', 'matricula' => 'D001', 'nombre' => 'María', 'apellido' => 'López Ruiz', 'email' => 'maria.lopez@itspp.test', 'departamento' => 'Sistemas Computacionales', 'contrasena' => 'docente-local-2026']);
$jorge = pedir('POST', '/usuarios', ['rol' => 'docente', 'matricula' => 'D002', 'nombre' => 'Jorge', 'apellido' => 'Ramírez Soto', 'email' => 'jorge.ramirez@itspp.test', 'departamento' => 'Sistemas Computacionales', 'contrasena' => 'docente-local-2026']);
pedir('POST', '/usuarios', ['rol' => 'laboratorista', 'matricula' => 'L001', 'nombre' => 'Carlos', 'apellido' => 'Méndez Gil', 'email' => 'carlos.mendez@itspp.test', 'contrasena' => 'laboratorista-local-2026']);

$web = pedir('POST', '/materias', ['clave' => 'AEB-1055', 'nombre' => 'Programación Web', 'carrera' => 'ISIC', 'semestre' => 5]);
$redes = pedir('POST', '/materias', ['clave' => 'SCD-1021', 'nombre' => 'Redes de Computadoras', 'carrera' => 'ISIC', 'semestre' => 5]);
pedir('POST', '/materias', ['clave' => 'AEF-1031', 'nombre' => 'Fundamentos de Base de Datos', 'carrera' => 'ISIC', 'semestre' => 4]);

$nombres = [
    ['Ana Sofía', 'García Torres'], ['Luis Fernando', 'Hernández Peña'], ['Valeria', 'Martínez Cruz'], ['Diego', 'Sánchez Ibarra'],
    ['Camila', 'Romero Vega'], ['Emiliano', 'Flores Castro'], ['Regina', 'Morales Aguilar'], ['Santiago', 'Ortiz Navarro'],
    ['Ximena', 'Delgado Rivas'], ['Mateo', 'Castillo Luna'], ['Renata', 'Vargas Medina'], ['Sebastián', 'Reyes Campos'],
    ['Natalia', 'Guerrero Silva'], ['Leonardo', 'Mendoza Ríos'], ['Fernanda', 'Jiménez Soto'],
];
$alumnos = [];
foreach ($nombres as $i => [$nombre, $apellido]) {
    $alumnos[] = [
        'matricula' => sprintf('2630%04d', 3001 + $i),
        'nombre' => $nombre,
        'apellido' => $apellido,
        'carrera' => $i < 13 ? 'ISIC' : 'IIND',
        'semestre' => 5,
        'grupo' => 'A',
        'turno' => 'matutino',
    ];
}
pedir('POST', '/alumnos/lote', ['alumnos' => $alumnos]);
$matriculas = array_column($alumnos, 'matricula');

$cursoWeb = pedir('POST', '/cursos', ['materiaId' => $web['id'], 'periodoId' => $periodo['id'], 'grupo' => '5A', 'turno' => 'matutino', 'docenteId' => $maria['id'], 'laboratorio' => 'programacion']);
$cursoRedes = pedir('POST', '/cursos', ['materiaId' => $redes['id'], 'periodoId' => $periodo['id'], 'grupo' => '5A', 'turno' => 'matutino', 'docenteId' => $jorge['id'], 'laboratorio' => 'redes']);
pedir('POST', "/cursos/{$cursoWeb['id']}/alumnos", ['matriculas' => array_slice($matriculas, 0, 10)]);
pedir('POST', "/cursos/{$cursoRedes['id']}/alumnos", ['matriculas' => array_slice($matriculas, 0, 12)]);

foreach (['Maquetado con HTML y CSS', 'Formularios y validación', 'Consumo de una API con fetch'] as $i => $titulo) {
    pedir('POST', "/materias/{$web['id']}/practicas", ['numero' => $i + 1, 'titulo' => $titulo, 'duracionMinutos' => 100]);
}
pedir('POST', "/materias/{$redes['id']}/practicas", ['numero' => 1, 'titulo' => 'Cableado estructurado', 'duracionMinutos' => 120]);

pedir('POST', '/horarios', ['cursoId' => $cursoWeb['id'], 'laboratorio' => 'programacion', 'dia' => 1, 'horaInicio' => '08:00', 'horaFin' => '10:00']);
pedir('POST', '/horarios', ['cursoId' => $cursoWeb['id'], 'laboratorio' => 'programacion', 'dia' => 3, 'horaInicio' => '08:00', 'horaFin' => '10:00']);
pedir('POST', '/horarios', ['cursoId' => $cursoRedes['id'], 'laboratorio' => 'redes', 'dia' => 2, 'horaInicio' => '10:00', 'horaFin' => '12:00']);

pedir('POST', '/laboratorios/redes/equipos/configurar', ['cantidad' => 20]);
pedir('DELETE', '/sesion');
@unlink($galleta);

echo "Datos de ejemplo: periodo {$periodo['nombre']}, " . count($alumnos) . " alumnos, 2 cursos.\n";
