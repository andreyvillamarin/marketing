<?php
// Configuración de la zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de la Base de Datos
define('DB_HOST', 'localhost');
define('DB_USER', 'qdosnetw_webmaster');
define('DB_PASS', 'tRVy8pvXVAz8');
define('DB_NAME', 'qdosnetw_marketing');

// URL base del proyecto
define('BASE_URL', 'https://qdos.network/demos/marketing');



// Configuración de Sesiones
ini_set('session.save_path', __DIR__ . '/../sessions');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>