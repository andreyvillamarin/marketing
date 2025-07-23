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

// ===================================================================
// INICIO DE LA SECCIÓN DEL ERROR
// Asegúrate de que esta sección quede exactamente así
// ===================================================================
// Configuración de Email (Brevo SMTP)
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_USER', 'andreyvillamarin@gmail.com');      // Línea 18
define('SMTP_PASS', 'xsmtpsib-5b2c4f62cf924e54e63d3914168bab52403f3b183783100593ba2d38a4276508-YEszkjQfOxVM9CqT');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM', 'andreyvillamarin@gmail.com');
define('EMAIL_FROM_NAME', 'Gestor de Tareas Comfamiliar');
// ===================================================================
// FIN DE LA SECCIÓN DEL ERROR
// ===================================================================


// Configuración de Sesiones
ini_set('session.save_path', __DIR__ . '/../sessions');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>