<?php $page_title = 'Crear Nueva Tarea'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recoger y sanear datos
    $nombre_tarea = trim($_POST['nombre_tarea']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $prioridad = $_POST['prioridad'];
    $miembros_asignados = isset($_POST['miembros_asignados']) ? $_POST['miembros_asignados'] : [];

    if (empty($nombre_tarea) || empty($fecha_vencimiento) || empty($prioridad) || empty($miembros_asignados)) {
        $error = 'Todos los campos marcados con * son obligatorios.';
    } else {
        $pdo->beginTransaction();
        try {
            // 1. Insertar la tarea principal
            $stmt = $pdo->prepare("INSERT INTO tareas (nombre_tarea, descripcion, fecha_vencimiento, prioridad, id_admin_creador) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nombre_tarea, $descripcion, $fecha_vencimiento, $prioridad, $_SESSION['user_id']]);
            $id_tarea = $pdo->lastInsertId();

            // 2. Asignar la tarea a los miembros
            $stmt_asignar = $pdo->prepare("INSERT INTO tareas_asignadas (id_tarea, id_usuario) VALUES (?, ?)");
            foreach ($miembros_asignados as $id_miembro) {
                $stmt_asignar->execute([$id_tarea, $id_miembro]);
            }
            
            // 3. Subir recursos multimedia si existen
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
            
            // 4. Enviar notificación por email a los asignados
            $stmt_miembros = $pdo->prepare("SELECT email, nombre_completo FROM usuarios WHERE id_usuario = ?");
            foreach ($miembros_asignados as $id_miembro) {
                $stmt_miembros->execute([$id_miembro]);
                $miembro = $stmt_miembros->fetch();
                if ($miembro) {
                    $asunto = "Nueva Tarea Asignada: " . $nombre_tarea;
                    $cuerpo = "<h1>Nueva Tarea</h1>
                               <p>Hola ".e($miembro['nombre_completo']).",</p>
                               <p>Se te ha asignado una nueva tarea en el gestor de proyectos.</p>
                               <p><strong>Tarea:</strong> ".e($nombre_tarea)."</p>
                               <p><strong>Fecha de Vencimiento:</strong> ".date('d/m/Y', strtotime($fecha_vencimiento))."</p>
                               <p>Puedes ver los detalles ingresando a la plataforma.</p>
                               <a href='".BASE_URL."/miembro/login.php'>Ir a la plataforma</a>";
                    enviar_email($miembro['email'], $miembro['nombre_completo'], $asunto, $cuerpo);
                }
            }

            $mensaje = "Tarea creada y notificada exitosamente.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al crear la tarea: " . $e->getMessage();
        }
    }
}

// Obtener lista de miembros para el formulario
$stmt_miembros = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'miembro' ORDER BY nombre_completo ASC");
$miembros = $stmt_miembros->fetchAll();

include '../includes/header_admin.php';
?>

<h2>Crear Nueva Tarea</h2>

<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<form action="crear_tarea.php" method="POST" enctype="multipart/form-data" class="form-container">
    <div class="form-group">
        <label for="nombre_tarea">Nombre de la Tarea (*)</label>
        <input type="text" id="nombre_tarea" name="nombre_tarea" required>
    </div>
    <div class="form-group">
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion"></textarea>
    </div>
    <div class="form-group">
        <label for="fecha_vencimiento">Fecha de Vencimiento (*)</label>
        <input type="datetime-local" id="fecha_vencimiento" name="fecha_vencimiento" required>
    </div>
    <div class="form-group">
        <label for="prioridad">Prioridad (*)</label>
        <select id="prioridad" name="prioridad" required>
            <option value="baja">Baja</option>
            <option value="media" selected>Media</option>
            <option value="alta">Alta</option>
        </select>
    </div>
    <div class="form-group">
        <label>Asignar a Miembros del Equipo (*)</label>
        <?php foreach ($miembros as $miembro): ?>
            <div>
                <input type="checkbox" name="miembros_asignados[]" value="<?php echo $miembro['id_usuario']; ?>" id="miembro_<?php echo $miembro['id_usuario']; ?>">
                <label for="miembro_<?php echo $miembro['id_usuario']; ?>"><?php echo e($miembro['nombre_completo']); ?></label>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="form-group">
        <label for="recursos">Recursos Multimedia (opcional)</label>
        <input type="file" id="recursos" name="recursos[]" multiple>
    </div>
    <button type="submit" class="btn btn-success">Crear Tarea</button>
</form>

<?php include '../includes/footer_admin.php'; ?>