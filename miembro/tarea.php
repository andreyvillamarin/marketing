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

$stmt_tarea_info = $pdo->prepare("SELECT t.*, u.email as creador_email, u.nombre_completo as creador_nombre FROM tareas t JOIN usuarios u ON t.id_admin_creador = u.id_usuario WHERE t.id_tarea = ?");
$stmt_tarea_info->execute([$id_tarea]);
$tarea_info = $stmt_tarea_info->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar_comentario'])) {
        $comentario = trim($_POST['comentario']);
        if (!empty($comentario)) {
            $pdo->beginTransaction();
            try {
                $stmt_insert = $pdo->prepare("INSERT INTO comentarios_tarea (id_tarea, id_usuario, comentario) VALUES (?, ?, ?)");
                $stmt_insert->execute([$id_tarea, $id_miembro, $comentario]);

                $stmt_usuarios = $pdo->prepare("
                    SELECT u.email, u.nombre_completo, u.id_usuario FROM usuarios u
                    JOIN tareas_asignadas ta ON u.id_usuario = ta.id_usuario
                    WHERE ta.id_tarea = ?
                    UNION
                    SELECT u.email, u.nombre_completo, u.id_usuario FROM usuarios u
                    JOIN tareas t ON u.id_usuario = t.id_admin_creador
                    WHERE t.id_tarea = ?
                ");
                $stmt_usuarios->execute([$id_tarea, $id_tarea]);
                $usuarios_a_notificar = $stmt_usuarios->fetchAll(PDO::FETCH_UNIQUE);

                $nombre_tarea = $tarea_info['nombre_tarea'];
                $nombre_comentador = $_SESSION['user_nombre'];
                $asunto = "Nuevo comentario en la tarea: " . $nombre_tarea;
                $cuerpo = "<h1>Nuevo Comentario</h1>
                           <p>Hola,</p>
                           <p>El usuario <strong>".e($nombre_comentador)."</strong> ha añadido un nuevo comentario a la tarea '<strong>".e($nombre_tarea)."</strong>'.</p>
                           <p><strong>Comentario:</strong></p>
                           <blockquote style='border-left: 2px solid #ccc; padding-left: 10px; margin-left: 5px;'><p>".nl2br(e($comentario))."</p></blockquote>
                           <p>Puedes ver la tarea y responder haciendo clic en el siguiente enlace:</p>
                           <p><a href='http://localhost/gestion-proyectos/miembro/tarea.php?id=".urlencode($id_tarea)."'>Ver Tarea</a></p>";

                foreach ($usuarios_a_notificar as $id_usuario => $usuario) {
                    if ($id_usuario != $id_miembro) { 
                        enviar_email($usuario['email'], $usuario['nombre_completo'], $asunto, $cuerpo);
                    }
                }

                $pdo->commit();
                $mensaje = "Comentario enviado y notificado.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "No se pudo enviar el comentario: " . $e->getMessage();
            }
        }
    }
    if (isset($_POST['notificar_finalizacion'])) {
        $pdo->beginTransaction();
        try {
            $stmt_update = $pdo->prepare("UPDATE tareas SET estado = 'finalizada_usuario' WHERE id_tarea = ?");
            $stmt_update->execute([$id_tarea]);

            $creador_email = $tarea_info['creador_email'];
            $creador_nombre = $tarea_info['creador_nombre'];
            $nombre_tarea = $tarea_info['nombre_tarea'];
            $miembro_nombre = $_SESSION['user_nombre'];

            $asunto = "Tarea marcada como finalizada: " . $nombre_tarea;
            $cuerpo = "<h1>Tarea Finalizada por Miembro</h1>
                       <p>Hola ".e($creador_nombre).",</p>
                       <p>El miembro <strong>".e($miembro_nombre)."</strong> ha marcado la tarea '<strong>".e($nombre_tarea)."</strong>' como finalizada.</p>
                       <p>Por favor, revisa la tarea en el panel de administración para confirmarla y marcarla como completada.</p>
                       <p><a href='http://localhost/gestion-proyectos/admin/editar_tarea.php?id=".urlencode($id_tarea)."'>Revisar Tarea</a></p>";
            
            enviar_email($creador_email, $creador_nombre, $asunto, $cuerpo);

            $pdo->commit();
            $mensaje = "¡Excelente! Se ha notificado al creador de la tarea.";
            $tarea_info['estado'] = 'finalizada_usuario';
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "No se pudo notificar la finalización: " . $e->getMessage();
        }
    }
    if (isset($_POST['configurar_recordatorio'])) {
        $dias_antes = $_POST['dias_notificacion'] ?? null;
        $valor_db = ($dias_antes === 'ninguno') ? null : (int)$dias_antes;
        try { $stmt_update = $pdo->prepare("UPDATE tareas_asignadas SET notificacion_dias_antes = ? WHERE id_tarea = ? AND id_usuario = ?"); $stmt_update->execute([$valor_db, $id_tarea, $id_miembro]); if (is_null($valor_db)) { $mensaje = "Recordatorio eliminado."; } else { $mensaje = "Recordatorio guardado."; } $dias_notificacion_actual = $valor_db; } catch (PDOException $e) { $error = "No se pudo configurar el recordatorio."; }
    }
}

$stmt_comentarios = $pdo->prepare("SELECT c.*, u.nombre_completo, u.rol FROM comentarios_tarea c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE c.id_tarea = ? ORDER BY c.fecha_comentario ASC");
$stmt_comentarios->execute([$id_tarea]);
$lista_comentarios = $stmt_comentarios->fetchAll();
$recursos_stmt = $pdo->prepare("SELECT * FROM recursos_tarea WHERE id_tarea = ?");
$recursos_stmt->execute([$id_tarea]);
$recursos = $recursos_stmt->fetchAll();

include '../includes/header_miembro.php';
?>

<h2>Detalle de Tarea: <?php echo e($tarea_info['nombre_tarea']); ?></h2>

<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div class="task-detail-layout">
    
    <div class="task-main-content">
        <div class="card">
            <h3>Información Principal</h3>
            <p><strong>Descripción:</strong> <?php echo nl2br(e($tarea_info['descripcion'])); ?></p>
            <p><strong>Fecha de Vencimiento:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea_info['fecha_vencimiento'])); ?></p>
            <p><strong>Prioridad:</strong> <span class="icon-text icon-prioridad-<?php echo e($tarea_info['prioridad']); ?>"><?php echo e(ucfirst($tarea_info['prioridad'])); ?></span></p>
            <p><strong>Estado Actual:</strong> <?php echo mostrar_estado_tarea($tarea_info); ?></p>
            
            <h4>Recursos Adjuntos:</h4>
            <div class="resource-list">
                <?php if (empty($recursos)): ?><p style="grid-column: 1 / -1; text-align:center; color: #777;">No hay recursos adjuntos.</p><?php else: ?><?php foreach ($recursos as $recurso): $ruta_archivo = e($recurso['ruta_archivo']); $nombre_archivo = e($recurso['nombre_archivo']); $extension = strtolower(pathinfo($ruta_archivo, PATHINFO_EXTENSION));?><div class="resource-item"><a href="../<?php echo $ruta_archivo; ?>" target="_blank" class="resource-link" title="<?php echo $nombre_archivo; ?>" download><?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?><img src="../<?php echo $ruta_archivo; ?>" alt="<?php echo $nombre_archivo; ?>" class="preview-image"><?php else: ?><div class="file-icon"><i class="fas fa-file"></i></div><?php endif; ?><span class="file-name"><?php echo $nombre_archivo; ?></span></a></div><?php endforeach; ?><?php endif; ?>
            </div>
        </div>
        <div class="card">
            <h3>Interacción y Comentarios</h3>
            <?php if (!empty($lista_comentarios)): ?><div class="chat-box"><?php foreach ($lista_comentarios as $comentario): ?><div class="comment <?php echo ($comentario['rol'] === 'admin' || $comentario['rol'] === 'analista') ? 'comment-admin' : 'comment-miembro'; ?>"><p><strong><?php echo e($comentario['nombre_completo']); ?>:</strong></p><p><?php echo nl2br(e($comentario['comentario'])); ?></p><div class="meta"><?php echo date('d/m/Y H:i', strtotime($comentario['fecha_comentario'])); ?></div></div><?php endforeach; ?></div><?php else: ?><p style="text-align: center; color: #777; padding: 20px 0;">No hay comentarios aún.</p><?php endif; ?>
            <form action="tarea.php?id=<?php echo $id_tarea; ?>" method="POST" style="margin-top:20px;"><div class="form-group"><label for="comentario">Añadir Comentario:</label><textarea name="comentario" id="comentario" required rows="4"></textarea></div><button type="submit" name="agregar_comentario" class="btn">Enviar Comentario</button></form>
        </div>
    </div>

    <div class="task-sidebar-actions">
        <div class="card">
            <h4><i class="fas fa-bell"></i> Recordatorio por Correo</h4>
            <?php if (!is_null($dias_notificacion_actual)): ?><div class="alert alert-info" style="font-size: 0.9em; padding: 10px;">Recordatorio programado para <strong><?php echo e($dias_notificacion_actual); ?> día(s)</strong> antes.</div><?php else: ?><p>Puedes solicitar un recordatorio por correo.</p><?php endif; ?>
            <form action="tarea.php?id=<?php echo $id_tarea; ?>" method="POST"><div class="form-group"><label for="dias_notificacion">Notificarme:</label><select name="dias_notificacion" id="dias_notificacion"><option value="5" <?php if($dias_notificacion_actual == 5) echo 'selected'; ?>>5 días antes</option><option value="2" <?php if($dias_notificacion_actual == 2) echo 'selected'; ?>>2 días antes</option><option value="1" <?php if($dias_notificacion_actual == 1) echo 'selected'; ?>>1 día antes</option><option value="ninguno">Quitar recordatorio</option></select></div><button type="submit" name="configurar_recordatorio" class="btn btn-sm"><i class="fas fa-save"></i> Guardar</button></form>
        </div>
        <div class="card">
            <h4>Acción Final</h4>
            <?php if($tarea_info['estado'] === 'pendiente'): ?>
                <form action="tarea.php?id=<?php echo $id_tarea; ?>" method="POST">
                    <button type="submit" name="notificar_finalizacion" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 1.1em;"><i class="fas fa-check"></i> He Finalizado esta Tarea</button>
                </form>
            <?php elseif($tarea_info['estado'] === 'finalizada_usuario'): ?>
                <div class="alert alert-info">Ya has notificado la finalización.</div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php include '../includes/footer_miembro.php'; ?>