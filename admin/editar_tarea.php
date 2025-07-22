<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') { header("Location: login.php"); exit(); }
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) { header("Location: tareas.php"); exit(); }
$id_tarea = $_GET['id'];

$mensaje = '';
$error = '';

// --- INICIO: LÓGICA DE PROCESAMIENTO DE FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_tarea'])) {
        $nombre_tarea = trim($_POST['nombre_tarea']);
        $descripcion = trim($_POST['descripcion']);
        $fecha_vencimiento = $_POST['fecha_vencimiento'];
        $prioridad = $_POST['prioridad'];
        $miembros_asignados = isset($_POST['miembros_asignados']) ? $_POST['miembros_asignados'] : [];
        if (empty($nombre_tarea) || empty($fecha_vencimiento) || empty($prioridad)) {
            $error = 'El nombre, la fecha de vencimiento y la prioridad son obligatorios.';
        } else {
            $pdo->beginTransaction();
            try {
                $stmt_update = $pdo->prepare("UPDATE tareas SET nombre_tarea = ?, descripcion = ?, fecha_vencimiento = ?, prioridad = ? WHERE id_tarea = ?");
                $stmt_update->execute([$nombre_tarea, $descripcion, $fecha_vencimiento, $prioridad, $id_tarea]);
                $stmt_delete_asignados = $pdo->prepare("DELETE FROM tareas_asignadas WHERE id_tarea = ?");
                $stmt_delete_asignados->execute([$id_tarea]);
                if (!empty($miembros_asignados)) {
                    $stmt_insert_asignados = $pdo->prepare("INSERT INTO tareas_asignadas (id_tarea, id_usuario) VALUES (?, ?)");
                    foreach ($miembros_asignados as $id_miembro) {
                        $stmt_insert_asignados->execute([$id_tarea, $id_miembro]);
                    }
                }
                if (!empty($_FILES['recursos']['name'][0])) {
                    $stmt_recurso = $pdo->prepare("INSERT INTO recursos_tarea (id_tarea, nombre_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $upload_dir = __DIR__ . '/../uploads/';
                    foreach ($_FILES['recursos']['name'] as $key => $name) {
                        $tmp_name = $_FILES['recursos']['tmp_name'][$key];
                        $file_name = time() . '_' . basename($name);
                        $target_file = $upload_dir . $file_name;
                        if (move_uploaded_file($tmp_name, $target_file)) {
                            $stmt_recurso->execute([$id_tarea, $name, 'uploads/' . $file_name]);
                        }
                    }
                }
                $pdo->commit();
                $mensaje = "¡Tarea actualizada exitosamente!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al actualizar la tarea: " . $e->getMessage();
            }
        }
    }
    if (isset($_POST['agregar_comentario'])) {
        $comentario = trim($_POST['comentario']);
        if (!empty($comentario)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO comentarios_tarea (id_tarea, id_usuario, comentario) VALUES (?, ?, ?)");
                $stmt->execute([$id_tarea, $_SESSION['user_id'], $comentario]);
                $mensaje = "Comentario agregado exitosamente.";
                $stmt_task_info = $pdo->prepare("SELECT nombre_tarea FROM tareas WHERE id_tarea = ?");
                $stmt_task_info->execute([$id_tarea]);
                $nombre_tarea = $stmt_task_info->fetchColumn();
                $stmt_miembros = $pdo->prepare("SELECT u.email, u.nombre_completo FROM usuarios u JOIN tareas_asignadas ta ON u.id_usuario = ta.id_usuario WHERE ta.id_tarea = ? AND u.rol = 'miembro'");
                $stmt_miembros->execute([$id_tarea]);
                $miembros_a_notificar = $stmt_miembros->fetchAll();
                if ($nombre_tarea && !empty($miembros_a_notificar)) {
                    $asunto = "Nuevo comentario del administrador en la tarea: " . $nombre_tarea;
                    $cuerpo_html = "<h1>Nuevo Comentario</h1><p>Hola, el administrador ha dejado un nuevo comentario en una de tus tareas asignadas.</p><hr><p><strong>Tarea:</strong> " . e($nombre_tarea) . "</p><p><strong>Comentario del administrador:</strong></p><blockquote style='border-left: 3px solid #eee; padding-left: 15px; margin-left: 10px; font-style: italic;'>" . nl2br(e($comentario)) . "</blockquote><hr><p>Puedes ver la conversación completa y responder haciendo clic en el siguiente enlace:</p><p><a href='" . BASE_URL . "/miembro/tarea.php?id=" . $id_tarea . "' style='display:inline-block; padding:10px 15px; background-color:#007bff; color:white; text-decoration:none; border-radius:5px;'>Ver Tarea Ahora</a></p>";
                    foreach ($miembros_a_notificar as $miembro) {
                        enviar_email($miembro['email'], $miembro['nombre_completo'], $asunto, $cuerpo_html);
                    }
                }
            } catch (PDOException $e) { $error = "Error al agregar el comentario: " . $e->getMessage(); }
        } else { $error = "El comentario no puede estar vacío."; }
    }
}

if (isset($_GET['eliminar_recurso'])) {
    $id_recurso = $_GET['eliminar_recurso'];
    $stmt_get_recurso = $pdo->prepare("SELECT ruta_archivo FROM recursos_tarea WHERE id_recurso = ? AND id_tarea = ?");
    $stmt_get_recurso->execute([$id_recurso, $id_tarea]);
    $recurso = $stmt_get_recurso->fetch();
    if ($recurso) {
        if (file_exists('../' . $recurso['ruta_archivo'])) { unlink('../' . $recurso['ruta_archivo']); }
        $stmt_delete_recurso = $pdo->prepare("DELETE FROM recursos_tarea WHERE id_recurso = ?");
        $stmt_delete_recurso->execute([$id_recurso]);
        header("Location: editar_tarea.php?id=" . $id_tarea . "&msg=recurso_eliminado"); exit();
    }
}
if(isset($_GET['msg']) && $_GET['msg'] == 'recurso_eliminado') { $mensaje = "Recurso eliminado correctamente."; }

$stmt = $pdo->prepare("SELECT * FROM tareas WHERE id_tarea = ?"); $stmt->execute([$id_tarea]); $tarea = $stmt->fetch();
if (!$tarea) { header("Location: tareas.php"); exit(); }
$page_title = 'Editando Tarea: ' . e($tarea['nombre_tarea']);
$todos_miembros = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'miembro' ORDER BY nombre_completo ASC")->fetchAll();
$stmt_asignados = $pdo->prepare("SELECT id_usuario FROM tareas_asignadas WHERE id_tarea = ?"); $stmt_asignados->execute([$id_tarea]); $ids_miembros_asignados = $stmt_asignados->fetchAll(PDO::FETCH_COLUMN);
$stmt_comentarios = $pdo->prepare("SELECT c.*, u.nombre_completo, u.rol FROM comentarios_tarea c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE c.id_tarea = ? ORDER BY c.fecha_comentario ASC"); $stmt_comentarios->execute([$id_tarea]); $lista_comentarios = $stmt_comentarios->fetchAll();
$recursos_stmt = $pdo->prepare("SELECT * FROM recursos_tarea WHERE id_tarea = ?"); $recursos_stmt->execute([$id_tarea]); $recursos = $recursos_stmt->fetchAll();

include '../includes/header_admin.php';
?>

<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div style="margin-bottom: 20px; text-align: right;">
    <a href="generar_historial_pdf.php?id_tarea=<?php echo $id_tarea; ?>" class="btn btn-secondary" target="_blank">
        <i class="fas fa-file-pdf"></i> Descargar Historial
    </a>
</div>

<div class="grid-container">
    <div class="card">
        <h3>Detalles de la Tarea</h3>
        <form action="editar_tarea.php?id=<?php echo $id_tarea; ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group"><label for="nombre_tarea">Nombre de la Tarea (*)</label><input type="text" id="nombre_tarea" name="nombre_tarea" value="<?php echo e($tarea['nombre_tarea']); ?>" required></div>
            <div class="form-group"><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="5"><?php echo e($tarea['descripcion']); ?></textarea></div>
            <div class="form-group"><label for="fecha_vencimiento">Fecha de Vencimiento (*)</label><input type="datetime-local" id="fecha_vencimiento" name="fecha_vencimiento" value="<?php echo date('Y-m-d\TH:i', strtotime($tarea['fecha_vencimiento'])); ?>" required></div>
            <div class="form-group"><label for="prioridad">Prioridad (*)</label><select id="prioridad" name="prioridad" required><option value="baja" <?php echo ($tarea['prioridad'] == 'baja') ? 'selected' : ''; ?>>Baja</option><option value="media" <?php echo ($tarea['prioridad'] == 'media') ? 'selected' : ''; ?>>Media</option><option value="alta" <?php echo ($tarea['prioridad'] == 'alta') ? 'selected' : ''; ?>>Alta</option></select></div>
            <hr><h4>Gestionar Miembros Asignados</h4><div class="form-group"><div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;"><?php foreach ($todos_miembros as $miembro): ?><div><input type="checkbox" name="miembros_asignados[]" value="<?php echo $miembro['id_usuario']; ?>" id="miembro_<?php echo $miembro['id_usuario']; ?>" <?php echo in_array($miembro['id_usuario'], $ids_miembros_asignados) ? 'checked' : ''; ?>><label for="miembro_<?php echo $miembro['id_usuario']; ?>"><?php echo e($miembro['nombre_completo']); ?></label></div><?php endforeach; ?></div></div>
            <hr>
            <h4>Gestionar Recursos</h4>
            <div class="form-group">
                <label>Recursos Actuales:</label>
                <div class="resource-list">
                    <?php if (empty($recursos)): ?><p style="grid-column: 1 / -1; text-align:center; color: #777;">No hay recursos adjuntos.</p><?php else: ?><?php foreach ($recursos as $recurso): $ruta_archivo = e($recurso['ruta_archivo']); $nombre_archivo = e($recurso['nombre_archivo']); $extension = strtolower(pathinfo($ruta_archivo, PATHINFO_EXTENSION));?><div class="resource-item"><a href="editar_tarea.php?id=<?php echo $id_tarea; ?>&eliminar_recurso=<?php echo $recurso['id_recurso']; ?>" class="btn-delete-resource" title="Eliminar Recurso" onclick="return confirm('¿Estás seguro de que quieres eliminar este archivo?')">&times;</a><a href="../<?php echo $ruta_archivo; ?>" target="_blank" class="resource-link" title="<?php echo $nombre_archivo; ?>"><?php if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?><img src="../<?php echo $ruta_archivo; ?>" alt="<?php echo $nombre_archivo; ?>" class="preview-image"><?php elseif (in_array($extension, ['mp4', 'webm'])): ?><video class="preview-video" muted><source src="../<?php echo $ruta_archivo; ?>" type="video/<?php echo $extension; ?>"></video><?php elseif ($extension == 'pdf'): ?><div class="file-icon"><i class="fas fa-file-pdf"></i></div><?php elseif (in_array($extension, ['doc', 'docx'])): ?><div class="file-icon"><i class="fas fa-file-word"></i></div><?php else: ?><div class="file-icon"><i class="fas fa-file"></i></div><?php endif; ?><span class="file-name"><?php echo $nombre_archivo; ?></span></a></div><?php endforeach; ?><?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <label for="recursos">Añadir Nuevos Recursos:</label>
                <input type="file" id="recursos" name="recursos[]" multiple>
            </div>
            <hr style="margin-top: 2rem;"><button type="submit" name="actualizar_tarea" class="btn btn-success">Guardar Cambios</button>
        </form>
    </div>
    <div class="card">
        <h3>Interacción y Comentarios</h3>
        <?php if (!empty($lista_comentarios)): ?><div class="chat-box"><?php foreach ($lista_comentarios as $comentario): ?><div class="comment <?php echo ($comentario['rol'] === 'admin') ? 'comment-admin' : 'comment-miembro'; ?>"><p><strong><?php echo e($comentario['nombre_completo']); ?>:</strong></p><p><?php echo nl2br(e($comentario['comentario'])); ?></p><div class="meta"><?php echo date('d/m/Y H:i', strtotime($comentario['fecha_comentario'])); ?></div></div><?php endforeach; ?></div><?php else: ?><p style="text-align: center; color: #777; padding: 20px 0;">No hay comentarios en esta tarea todavía.</p><?php endif; ?>
        <form action="editar_tarea.php?id=<?php echo $id_tarea; ?>" method="POST" style="margin-top:20px;"><div class="form-group"><label for="comentario">Añadir un comentario o nota:</label><textarea name="comentario" id="comentario" required rows="4"></textarea></div><button type="submit" name="agregar_comentario" class="btn">Enviar Comentario</button></form>
    </div>
</div>
<?php include '../includes/footer_admin.php'; ?>