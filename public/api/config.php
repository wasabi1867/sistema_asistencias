<?php
// ==============================================================
// CONFIGURACIÓN DE CONEXIÓN A BASE DE DATOS EN HOSTINGER
// ==============================================================

// En Hostinger, el host casi siempre es 'localhost'
define('DB_HOST', 'localhost');

// REEMPLAZA ESTOS DATOS CON LOS QUE CREASTE EN TU PANEL DE HOSTINGER:
define('DB_NAME', 'u568725086_asistencias'); // Nombre de la BD en Hostinger
define('DB_USER', 'u568725086_admin_asis');       // Usuario de la BD en Hostinger
define('DB_PASS', 'Basededatosasistencias2134!'); // Contraseña de la BD

// Configuración de encabezados CORS para permitir peticiones HTTP seguras
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Responder inmediatamente a peticiones preflight OPTIONS del navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
