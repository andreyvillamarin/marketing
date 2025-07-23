<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_rol'], ['admin', 'analista'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) { header("Location: tareas.php"); exit(); }
$id_tarea = $_GET['id'];

$mensaje = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['actualizar_tarea'])) {
        $nombre_tarea = trim($_POST['nombre_tarea']);
        $descripcion = trim($_POST['descripcion']);
        $fecha_vencimiento = $_POST['fecha_vencimiento'];
        $prioridad = $_POST['prioridad'];
        $miembros_asignados_nuevos = isset($_POST['miembros_asignados']) ? $_POST['miembros_asignados'] : [];
        if (empty($nombre_tarea) || empty($fecha_vencimiento) || empty($prioridad)) {
            $error = 'El nombre, la fecha de vencimiento y la prioridad son obligatorios.';
        } else {
            $stmt_originales = $pdo->prepare("SELECT id_usuario FROM tareas_asignadas WHERE id_tarea = ?");
            $stmt_originales->execute([$id_tarea]);
            $ids_miembros_originales = $stmt_originales->fetchAll(PDO::FETCH_COLUMN);
            $pdo->beginTransaction();
            try {
                $stmt_update = $pdo->prepare("UPDATE tareas SET nombre_tarea = ?, descripcion = ?, fecha_vencimiento = ?, prioridad = ? WHERE id_tarea = ?");
                $stmt_update->execute([$nombre_tarea, $descripcion, $fecha_vencimiento, $prioridad, $id_tarea]);
                $stmt_delete_asignados = $pdo->prepare("DELETE FROM tareas_asignadas WHERE id_tarea = ?");
                $stmt_delete_asignados->execute([$id_tarea]);
                if (!empty($miembros_asignados_nuevos)) {
                    $stmt_insert_asignados = $pdo->prepare("INSERT INTO tareas_asignadas (id_tarea, id_usuario) VALUES (?, ?)");
                    foreach ($miembros_asignados_nuevos as $id_miembro) { $stmt_insert_asignados->execute([$id_tarea, $id_miembro]); }
                }
                if (!empty($_FILES['recursos']['name'][0])) {
                    $stmt_recurso = $pdo->prepare("INSERT INTO recursos_tarea (id_tarea, nombre_archivo, ruta_archivo) VALUES (?, ?, ?)");
                    $upload_dir = __DIR__ . '/../uploads/';
                    foreach ($_FILES['recursos']['name'] as $key => $name) {
                        $tmp_name = $_FILES['recursos']['tmp_name'][$key]; $file_name = time() . '_' . basename($name); $target_file = $upload_dir . $file_name;
                        if (move_uploaded_file($tmp_name, $target_file)) { $stmt_recurso->execute([$id_tarea, $name, 'uploads/' . $file_name]); }
                    }
                }
                $pdo->commit();
                $mensaje = "¡Tarea actualizada exitosamente!";
                $miembros_anadidos = array_diff($miembros_asignados_nuevos, $ids_miembros_originales);
                $miembros_eliminados = array_diff($ids_miembros_originales, $miembros_asignados_nuevos);
                $stmt_usuario_info = $pdo->prepare("SELECT email, nombre_completo FROM usuarios WHERE id_usuario = ?");
                if (!empty($miembros_anadidos)) {
                    $asunto = "Nueva Tarea Asignada: " . $nombre_tarea;
                    $cuerpo = "<h1>Nueva Tarea</h1><p>Hola, se te ha asignado una nueva tarea o has sido añadido a una existente:</p><p><strong>".e($nombre_tarea)."</strong></p><p>Puedes ver los detalles en la plataforma.</p>";
                    foreach ($miembros_anadidos as $id_anadido) {
                        $stmt_usuario_info->execute([$id_anadido]);
                        $usuario = $stmt_usuario_info->fetch();
                        if ($usuario) { enviar_email($usuario['email'], $usuario['nombre_completo'], $asunto, $cuerpo); }
                    }
                }
                if (!empty($miembros_eliminados)) {
                    $asunto = "Se te ha quitado de una tarea: " . $nombre_tarea;
                    $cuerpo = "<h1>Tarea Reasignada</h1><p>Hola, te informamos que ya no estás asignado a la tarea '".e($nombre_tarea)."'.</p>";
                    foreach ($miembros_eliminados as $id_eliminado) {
                        $stmt_usuario_info->execute([$id_eliminado]);
                        $usuario = $stmt_usuario_info->fetch();
                        if ($usuario) { enviar_email($usuario['email'], $usuario['nombre_completo'], $asunto, $cuerpo); }
                    }
                }
            } catch (Exception $e) { $pdo->rollBack(); $error = "Error al actualizar la tarea: " . $e->getMessage(); }
        }
    }
    if (isset($_POST['agregar_comentario'])) {
        $comentario = trim($_POST['comentario']);
        if (!empty($comentario)) {
            try { $stmt = $pdo->prepare("INSERT INTO comentarios_tarea (id_tarea, id_usuario, comentario) VALUES (?, ?, ?)"); $stmt->execute([$id_tarea, $_SESSION['user_id'], $comentario]); $mensaje = "Comentario agregado."; } catch (PDOException $e) { $error = "Error al agregar el comentario."; }
        }
    }
    if (isset($_POST['cerrar_tarea'])) {
        if ($_SESSION['user_rol'] === 'admin') {
            try { $stmt = $pdo->prepare("UPDATE tareas SET estado = 'completada' WHERE id_tarea = ?"); $stmt->execute([$id_tarea]); $mensaje = "¡Tarea marcada como completada!"; }
            catch (PDOException $e) { $error = "Error al completar la tarea."; }
        } else { $error = "No tienes permiso para esta acción."; }
    }
    if (isset($_POST['reabrir_tarea'])) {
        if ($_SESSION['user_rol'] === 'admin') {
            try { $stmt = $pdo->prepare("UPDATE tareas SET estado = 'pendiente' WHERE id_tarea = ?"); $stmt->execute([$id_tarea]); $mensaje = "La tarea ha sido reabierta."; }
            catch (PDOException $e) { $error = "Error al reabrir la tarea."; }
        } else { $error = "No tienes permiso para esta acción."; }
    }
}
if (isset($_GET['eliminar_recurso'])) { /* ... */ }
if(isset($_GET['msg']) && $_GET['msg'] == 'recurso_eliminado') { $mensaje = "Recurso eliminado."; }
$stmt = $pdo->prepare("SELECT * FROM tareas WHERE id_tarea = ?"); $stmt->execute([$id_tarea]); $tarea = $stmt->fetch();
if (!$tarea) { header("Location: tareas.php"); exit(); }
$page_title = 'Editando Tarea: ' . e($tarea['nombre_tarea']);
$usuarios_asignables = $pdo->query("SELECT id_usuario, nombre_completo, rol FROM usuarios WHERE rol IN ('miembro', 'analista') ORDER BY nombre_completo ASC")->fetchAll();
$stmt_asignados = $pdo->prepare("SELECT id_usuario FROM tareas_asignadas WHERE id_tarea = ?"); $stmt_asignados->execute([$id_tarea]); $ids_miembros_asignados = $stmt_asignados->fetchAll(PDO::FETCH_COLUMN);
$stmt_comentarios = $pdo->prepare("SELECT c.*, u.nombre_completo, u.rol FROM comentarios_tarea c JOIN usuarios u ON c.id_usuario = u.id_usuario WHERE c.id_tarea = ? ORDER BY c.fecha_comentario ASC"); $stmt_comentarios->execute([$id_tarea]); $lista_comentarios = $stmt_comentarios->fetchAll();
$recursos_stmt = $pdo->prepare("SELECT * FROM recursos_tarea WHERE id_tarea = ?"); $recursos_stmt->execute([$id_tarea]); $recursos = $recursos_stmt->fetchAll();
include '../includes/header_admin.php';
?>
<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<div style="margin-bottom: 20px; text-align: right;"><a href="generar_historial_pdf.php?id_tarea=<?php echo $id_tarea; ?>" class="btn btn-secondary" target="_blank"><i class="fas fa-file-pdf"></i> Descargar Historial</a></div>
<div class="grid-container">
    <div class="card">
        <h3>Detalles de la Tarea</h3>
        <?php if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin'): ?>
        <div style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; background-color: #f9f9f9;">
            <h4 style="margin-top:0;">Acciones de Estado</h4>
            <p><strong>Estado Actual:</strong> <span class="estado-<?php echo e($tarea['estado']); ?>"><?php echo e(ucfirst(str_replace('_', ' ', $tarea['estado']))); ?></span></p>
            <?php if ($tarea['estado'] === 'pendiente' || $tarea['estado'] === 'finalizada_usuario'): ?>
                <form action="editar_tarea.php?id=<?php echo $id_tarea; ?>" method="POST" onsubmit="return confirm('¿Seguro?');" style="margin:0;"><button type="submit" name="cerrar_tarea" class="btn btn-success"><i class="fas fa-check-double"></i> Confirmar y Completar</button></form>
            <?php elseif ($tarea['estado'] === 'completada'): ?>
                <form action="editar_tarea.php?id=<?php echo $id_tarea; ?>" method="POST" onsubmit="return confirm('¿Seguro?');" style="margin:0;"><button type="submit" name="reabrir_tarea" class="btn btn-secondary"><i class="fas fa-undo"></i> Reabrir Tarea</button></form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <form action="editar_tarea.php?id=<?php echo $id_tarea; ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group"><label>Nombre (*)</label><input type="text" name="nombre_tarea" value="<?php echo e($tarea['nombre_tarea']); ?>" required></div>
            <div class="form-group"><label>Descripción</label><textarea name="descripcion" rows="5"><?php echo e($tarea['descripcion']); ?></textarea></div>
            <div class="form-group"><label>Fecha Vencimiento (*)</label><input type="datetime-local" name="fecha_vencimiento" value="<?php echo date('Y-m-d\TH:i', strtotime($tarea['fecha_vencimiento'])); ?>" required></div>
            <div class="form-group"><label>Prioridad (*)</label><select name="prioridad" required><option value="baja" <?php if($tarea['prioridad'] == 'baja') echo 'selected'; ?>>Baja</option><option value="media" <?php if($tarea['prioridad'] == 'media') echo 'selected'; ?>>Media</option><option value="alta" <?php if($tarea['prioridad'] == 'alta') echo 'selected'; ?>>Alta</option></select></div>
            <hr><h4>Usuarios Asignados</h4>
            <div class="form-group"><div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">
                <?php foreach ($usuarios_asignables as $usuario): ?><div><input type="checkbox" name="miembros_asignados[]" value="<?php echo $usuario['id_usuario']; ?>" id="usuario_<?php echo $usuario['id_usuario']; ?>" <?php if(in_array($usuario['id_usuario'], $ids_miembros_asignados)) echo 'checked'; ?>><label for="usuario_<?php echo $usuario['id_usuario']; ?>"><?php echo e($usuario['nombre_completo']); ?> (<?php echo e(ucfirst($usuario['rol'])); ?>)</label></div><?php endforeach; ?>
            </div></div>
            <hr><h4>Recursos</h4>
            <div class="form-group"><label>Recursos Actuales:</label><div class="resource-list"><?php if (empty($recursos)): ?><p style="grid-column: 1 / -1; text-align:center; color: #777;">No hay recursos.</p><?php else: foreach ($recursos as $recurso): /* ... */ endforeach; endif; ?></div></div>
            <div class="form-group"><label>Añadir Nuevos:</label><input type="file" name="recursos[]" multiple></div>
            <hr style="margin-top: 2rem;"><button type="submit" name="actualizar_tarea" class="btn btn-success"><i class="fas fa-save"></i> Guardar Cambios</button>
        </form>
    </div>
    <div class="card">
        <h3>Comentarios</h3>
        <?php if (!empty($lista_comentarios)): ?><div class="chat-box"><?php foreach ($lista_comentarios as $comentario): /* ... */ endforeach; ?></div><?php else: ?><p style="text-align: center; color: #777;">No hay comentarios.</p><?php endif; ?>
        <form action="editar_tarea.php?id=<?php echo $id_tarea; ?>" method="POST" style="margin-top:20px;"><div class="form-group"><label>Añadir comentario:</label><textarea name="comentario" required rows="4"></textarea></div><button type="submit" name="agregar_comentario" class="btn">Enviar</button></form>
    </div>
</div>
<?php include '../includes/footer_admin.php'; ?>