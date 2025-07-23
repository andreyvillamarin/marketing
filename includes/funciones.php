<?php
// Usar las clases del namespace de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// --- INICIO DE LA MODIFICACIÓN ---
// En lugar de cargar el autoload de Composer, cargamos los archivos manualmente.
require_once __DIR__ . '/../libs/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../libs/phpmailer/SMTP.php';
require_once __DIR__ . '/../libs/phpmailer/Exception.php';
// --- FIN DE LA MODIFICACIÓN ---


function enviar_email($destinatario_email, $destinatario_nombre, $asunto, $cuerpo_html) {
    // La configuración se obtiene de config.php, que debe estar incluido antes de llamar a esta función
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor (esta parte no cambia)
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Equivalente a 'tls'
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';

        // Remitente y Destinatarios (esta parte no cambia)
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($destinatario_email, $destinatario_nombre);

        // Contenido (esta parte no cambia)
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo_html;
        $mail->AltBody = strip_tags($cuerpo_html);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // En producción, es mejor registrar este error que mostrarlo
        error_log("Error al enviar email a {$destinatario_email}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Función para proteger la salida en HTML y prevenir ataques XSS.
 * @param string $string La cadena a sanear.
 * @return string La cadena saneada.
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function mostrar_estado_tarea($tarea) {
    $estado_clase = e($tarea['estado']);
    $estado_texto = ucfirst(str_replace('_', ' ', $estado_clase));
    $estado_icono = 'fa-clock';
    $color = '';

    if ($estado_clase == 'finalizada_usuario') {
        $estado_icono = 'fa-check';
    } elseif ($estado_clase == 'cerrada') {
        $estado_icono = 'fa-check-double';
    }

    if ($tarea['estado'] === 'pendiente') {
        if (strtotime($tarea['fecha_vencimiento']) < time()) {
            $estado_texto = 'Vencida';
            $color = 'style="color: red;"';
        } else {
            $estado_texto = 'Pendiente';
            $color = 'style="color: orange;"';
        }
    }

    return "<span class='icon-text icon-estado-{$estado_clase}' {$color}><i class='fas {$estado_icono}'></i> {$estado_texto}</span>";
}