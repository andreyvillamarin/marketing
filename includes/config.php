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
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_USER', '');
define('SMTP_PASS', '');   // Tu clave SMTP v3 de Brevo
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM', '');
define('EMAIL_FROM_NAME', 'Gestor de Tareas Comfamiliar');

// Configuracin de Sesiones
ini_set('session.save_path', __DIR__ . '/../sessions');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>