<?php
// Configuracin de la zona horaria
date_default_timezone_set('America/Bogota');

// Configuracin de la Base de Datos
define('DB_HOST', 'localhost');
define('DB_USER', 'qdosnetw_webmaster');
define('DB_PASS', 'tRVy8pvXVAz8');
define('DB_NAME', 'qdosnetw_marketing');

// URL base del proyecto
define('BASE_URL', 'https://qdos.network/demos/marketing');

// Configuracin de Email (Brevo SMTP)
define('SMTP_HOST', '');
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_PORT', );
define('SMTP_SECURE', '');
define('EMAIL_FROM', '');
define('EMAIL_FROM_NAME', '');

// Configuracin de Sesiones
ini_set('session.save_path', __DIR__ . '/../sessions');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>