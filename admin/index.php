<?php $page_title = 'Dashboard'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    $stmt_finalizadas = $pdo->prepare("SELECT t.*, GROUP_CONCAT(u_asignado.nombre_completo SEPARATOR ', ') as miembros_asignados FROM tareas t LEFT JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea LEFT JOIN usuarios u_asignado ON ta.id_usuario = u_asignado.id_usuario WHERE t.estado = 'finalizada_usuario' GROUP BY t.id_tarea ORDER BY t.fecha_vencimiento ASC");
    $stmt_finalizadas->execute();
    $tareas_finalizadas = $stmt_finalizadas->fetchAll();
    $stmt_pendientes = $pdo->prepare("SELECT t.*, GROUP_CONCAT(u_asignado.nombre_completo SEPARATOR ', ') as miembros_asignados FROM tareas t LEFT JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea LEFT JOIN usuarios u_asignado ON ta.id_usuario = u_asignado.id_usuario WHERE t.estado = 'pendiente' GROUP BY t.id_tarea ORDER BY t.fecha_vencimiento ASC");
    $stmt_pendientes->execute();
    $tareas_pendientes = $stmt_pendientes->fetchAll();
} catch(PDOException $e) {
    die("Error al recuperar las tareas: " . $e->getMessage());
}
include '../includes/header_admin.php';
?>