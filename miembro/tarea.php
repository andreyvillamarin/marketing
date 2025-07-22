<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'miembro') { header("Location: login.php"); exit(); }
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) { header("Location: index.php"); exit(); }
$id_tarea = $_GET['id'];
$id_miembro = $_SESSION['user_id'];

$stmt_check = $pdo->prepare("SELECT notificacion_dias_antes FROM tareas_asignadas WHERE id_tarea = ? AND id_usuario = ?");
$stmt_check->execute([$id_tarea, $id_miembro]);
$asignacion = $stmt_check->fetch();
if (!$asignacion) { header("Location: index.php?error=permiso_denegado"); exit(); }

$dias_notificacion_actual = $asignacion['notificacion_dias_antes'];
$mensaje = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar_comentario'])) {
        $comentario = trim($_POST['comentario']);
        if (!empty($comentario)) {
            try { $stmt_insert = $pdo->prepare("INSERT INTO comentarios_tarea (id_tarea, id_usuario, comentario) VALUES (?, ?, ?)"); $stmt_insert->execute([$id_tarea, $id_miembro, $comentario]); $mensaje = "Comentario enviado."; } catch (PDOException $e) { $error = "No se pudo enviar el comentario."; }
        }
    }
    if (isset($_POST['notificar_finalizacion'])) {
        try { $stmt_update = $pdo->prepare("UPDATE tareas SET estado = 'finalizada_usuario' WHERE id_tarea = ?"); $stmt_update->execute([$id_tarea]); $mensaje = "¡Excelente! Se ha notificado al administrador que finalizaste la tarea."; } catch(PDOException $e) { $error = "No se pudo notificar la finalización."; }
    }
    if (isset($_POST['configurar_recordatorio'])) {
        $dias_antes = $_POST['dias_notificacion'] ?? null;
        $valor_db = ($dias_antes === 'ninguno') ? null : (int)$dias_antes;
        try {
            $stmt_update = $pdo->prepare("UPDATE tareas_asignadas SET notificacion_dias_antes = ? WHERE id_tarea = ? AND id_usuario = ?");
            $stmt_update->execute([$valor_db, $id_tarea, $id_miembro]);
            if (is_null($valor_db)) { $mensaje = "Recordatorio por correo electrónico eliminado."; } else { $mensaje = "Se te recordará " . e($valor_db) . " día(s) antes del vencimiento."; }
            $dias_notificacion_actual = $valor_db;
        } catch (PDOException $e) { $error = "No se pudo configurar el recordatorio."; }
    }
}

$stmt_tarea = $pdo->prepare("SELECT * FROM tareas WHERE id_tarea = ?"); $stmt_tarea->execute([$id_tarea]); $tarea = $stmt_tarea->fetch();
$stmt_comentarios = $pdo->prepare("SELECT c.*, u.nombre_completo, u.rol FROM comentarios_tarea c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE c.id_tarea = ? ORDER BY c.fecha_comentario ASC"); $stmt_comentarios->execute([$id_tarea]); $lista_comentarios = $stmt_comentarios->fetchAll();
$recursos_stmt = $pdo->prepare("SELECT * FROM recursos_tarea WHERE id_tarea = ?"); $recursos_stmt->execute([$id_tarea]); $recursos = $recursos_stmt->fetchAll();

include '../includes/header_miembro.php';
?>
<h2>Detalle de Tarea: <?php echo e($tarea['nombre_tarea']); ?></h2>

<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div style="margin-bottom: 20px; text-align: right;">
    <a href="../admin/generar_historial_pdf.php?id_tarea=<?php echo $id_tarea; ?>" class="btn btn-secondary" target="_blank">
        <i class="fas fa-file-pdf"></i> Descargar Historial
    </a>
</div>

<div class="grid-container">
    <div class="card">
        <h3>Información Principal</h3>
        <p><strong>Descripción:</strong> <?php echo nl2br(e($tarea['descripcion'])); ?></p>
        <p><strong>Fecha de Vencimiento:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_vencimiento'])); ?></p>
        <p><strong>Prioridad:</strong> <span class="icon-text icon-prioridad-<?php echo e($tarea['prioridad']); ?>"><?php echo e(ucfirst($tarea['prioridad'])); ?></span></p>
        <p><strong>Estado Actual:</strong> <span class="icon-text icon-estado-<?php echo e($tarea['estado']); ?>"><?php echo e(ucfirst(str_replace('_', ' ', $tarea['estado']))); ?></span></p>
        <h4>Recursos Adjuntos:</h4>
        <div class="resource-list">
            <?php if (empty($recursos)): ?><p style="grid-column: 1 / -1; text-align:center; color: #777;">No hay recursos adjuntos para esta tarea.</p><?php else: ?><?php foreach ($recursos as $recurso): $ruta_archivo = e($recurso['ruta_archivo']); $nombre_archivo = e($recurso['nombre_archivo']); $extension = strtolower(pathinfo($ruta_archivo, PATHINFO_EXTENSION));?><div class="resource-item"><a href="../<?php echo $ruta_archivo; ?>" target="_blank" class="resource-link" title="<?php echo $nombre_archivo; ?>" download><?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?><img src="../<?php echo $ruta_archivo; ?>" alt="<?php echo $nombre_archivo; ?>" class="preview-image"><?php elseif (in_array($extension, ['mp4', 'webm'])): ?><video class="preview-video" muted><source src="../<?php echo $ruta_archivo; ?>" type="video/<?php echo $extension; ?>"></video><?php elseif ($extension == 'pdf'): ?><div class="file-icon"><i class="fas fa-file-pdf"></i></div><?php elseif (in_array($extension, ['doc', 'docx'])): ?><div class="file-icon"><i class="fas fa-file-word"></i></div><?php else: ?><div class="file-icon"><i class="fas fa-file"></i></div><?php endif; ?><span class="file-name"><?php echo $nombre_archivo; ?></span></a></div><?php endforeach; ?><?php endif; ?>
        </div>
        <hr>
        <h4>Acción Requerida</h4>
        <?php if($tarea['estado'] === 'pendiente'): ?><form action="tarea.php?id=<?php echo $id_tarea; ?>" method="POST"><button type="submit" name="notificar_finalizacion" class="btn btn-success"><i class="fas fa-check"></i> He Finalizado esta Tarea</button></form><?php elseif($tarea['estado'] === 'finalizada_usuario'): ?><p>Ya has notificado la finalización. Esperando revisión del administrador.</p><?php endif; ?>
        <hr>
        <h4><i class="fas fa-bell"></i> Recordatorio por Correo</h4>
        <?php if (!is_null($dias_notificacion_actual)): ?><div class="alert alert-info">Actualmente tienes un recordatorio programado para <strong><?php echo e($dias_notificacion_actual); ?> día(s)</strong> antes de la fecha de vencimiento.</div><?php else: ?><p>Puedes solicitar que te enviemos un recordatorio por correo antes de que venza la tarea.</p><?php endif; ?>
        <form action="tarea.php?id=<?php echo $id_tarea; ?>" method="POST"><div class="form-group"><label for="dias_notificacion">Notificarme:</label><select name="dias_notificacion" id="dias_notificacion"><option value="5" <?php if($dias_notificacion_actual == 5) echo 'selected'; ?>>5 días antes</option><option value="2" <?php if($dias_notificacion_actual == 2) echo 'selected'; ?>>2 días antes</option><option value="1" <?php if($dias_notificacion_actual == 1) echo 'selected'; ?>>1 día antes</option><option value="ninguno">Quitar recordatorio</option></select></div><button type="submit" name="configurar_recordatorio" class="btn"><i class="fas fa-save"></i> Guardar Preferencia</button></form>
    </div>
    <div class="card">
        <h3>Interacción y Comentarios</h3>
        <?php if (!empty($lista_comentarios)): ?><div class="chat-box"><?php foreach ($lista_comentarios as $comentario): ?><div class="comment <?php echo ($comentario['rol'] === 'admin') ? 'comment-admin' : 'comment-miembro'; ?>"><p><strong><?php echo e($comentario['nombre_completo']); ?>:</strong></p><p><?php echo nl2br(e($comentario['comentario'])); ?></p><div class="meta"><?php echo date('d/m/Y H:i', strtotime($comentario['fecha_comentario'])); ?></div></div><?php endforeach; ?></div><?php else: ?><p style="text-align: center; color: #777; padding: 20px 0;">No hay comentarios aún. ¡Sé el primero!</p><?php endif; ?>
        <form action="tarea.php?id=<?php echo $id_tarea; ?>" method="POST" style="margin-top:20px;"><div class="form-group"><label for="comentario">¿Tienes alguna duda? Escribe un comentario:</label><textarea name="comentario" id="comentario" required rows="4"></textarea></div><button type="submit" name="agregar_comentario" class="btn">Enviar Comentario</button></form>
    </div>
</div>
<?php include '../includes/footer_miembro.php'; ?>