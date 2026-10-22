<?php
require_once __DIR__ . '/db.php';

requerirRol(ROLES_TODOS);

$pdo = getDBConnection();
$lab = $_GET['laboratorio'] ?? 'programacion';

try {
    $alumnosCount = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
    $docentesCount = $pdo->query("SELECT COUNT(*) FROM docentes")->fetchColumn();
    $materiasCount = $pdo->query("SELECT COUNT(*) FROM materias")->fetchColumn();
    $laboratoristasCount = $pdo->query("SELECT COUNT(*) FROM laboratoristas")->fetchColumn();
    $administradoresCount = $pdo->query("SELECT COUNT(*) FROM administradores")->fetchColumn();

    $asistenciasActivas = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE laboratorio = ?");
    $asistenciasActivas->execute([$lab]);
    $activasCount = $asistenciasActivas->fetchColumn();

    $eqStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN fuera_de_servicio = 1 THEN 1 ELSE 0 END) as fueraDeServicio,
        SUM(CASE WHEN en_uso = 1 THEN 1 ELSE 0 END) as enUso,
        SUM(CASE WHEN fuera_de_servicio = 0 AND en_uso = 0 THEN 1 ELSE 0 END) as enServicio
        FROM equipos WHERE laboratorio = ?");
    $eqStmt->execute([$lab]);
    $eqStats = $eqStmt->fetch();

    sendResponse([
        "success" => true,
        "data" => [
            "alumnos" => (int)$alumnosCount,
            "docentes" => (int)$docentesCount,
            "materias" => (int)$materiasCount,
            "laboratoristas" => (int)$laboratoristasCount,
            "administradores" => (int)$administradoresCount,
            "asistenciasActivas" => (int)$activasCount,
            "equipos" => [
                "total" => (int)($eqStats['total'] ?? 0),
                "enServicio" => (int)($eqStats['enServicio'] ?? 0),
                "enUso" => (int)($eqStats['enUso'] ?? 0),
                "fueraDeServicio" => (int)($eqStats['fueraDeServicio'] ?? 0)
            ]
        ]
    ]);
} catch (Exception $e) {
    error_log('API asistencias: ' . $e);
    sendResponse(["success" => false, "error" => "No se pudieron calcular las estadísticas"], 500);
}
