<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página y obtener ID
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') { header("Location: login.php"); exit(); }
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) { header("Location: miembros.php"); exit(); }
$id_miembro = $_GET['id'];

$mensaje = '';
$error = '';

// Lógica para procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre_completo']);
    $email = trim($_POST['email']);
    $id_area = $_POST['id_area'];
    $password = $_POST['password'];

    if (empty($nombre_completo) || empty($email) || empty($id_area)) {
        $error = "Nombre, email y área son campos obligatorios.";
    } else {
        try {
            // Construir la consulta base
            $sql = "UPDATE usuarios SET nombre_completo = ?, email = ?, id_area = ?";
            $params = [$nombre_completo, $email, $id_area];

            // Si el campo de contraseña no está vacío, añadirlo a la consulta
            if (!empty($password)) {
                $sql .= ", password = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            // Finalizar la consulta y añadir el ID del miembro
            $sql .= " WHERE id_usuario = ?";
            $params[] = $id_miembro;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $mensaje = "¡Miembro actualizado exitosamente!";

        } catch (PDOException $e) {
            $error = "Error al actualizar el miembro. El email ya podría estar en uso por otro usuario.";
        }
    }
}

// Obtener los datos actuales del miembro para pre-rellenar el formulario
$stmt_miembro = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ? AND rol = 'miembro'");
$stmt_miembro->execute([$id_miembro]);
$miembro = $stmt_miembro->fetch();

// Si el miembro no existe, redirigir
if (!$miembro) { header("Location: miembros.php"); exit(); }

$page_title = 'Editando a: ' . e($miembro['nombre_completo']);

// Obtener todas las áreas para el menú desplegable
$areas = $pdo->query("SELECT * FROM areas ORDER BY nombre_area")->fetchAll();

include '../includes/header_admin.php';
?>

<h2><i class="fas fa-user-edit"></i> Editar Miembro</h2>
<p>Modifica los datos del miembro del equipo. <a href="miembros.php" class="btn btn-secondary btn-sm">Volver a la lista</a></p>

<div class="card form-card">
    <h3>Datos de <?php echo e($miembro['nombre_completo']); ?></h3>
    
    <?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

    <form action="editar_miembro.php?id=<?php echo $id_miembro; ?>" method="POST">
        <div class="form-group">
            <label for="nombre_completo">Nombre Completo</label>
            <input type="text" name="nombre_completo" value="<?php echo e($miembro['nombre_completo']); ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" name="email" value="<?php echo e($miembro['email']); ?>" required>
        </div>
        <div class="form-group">
            <label for="id_area">Área</label>
            <select name="id_area" required>
                <option value="">Seleccionar área...</option>
                <?php foreach ($areas as $area): ?>
                <option value="<?php echo $area['id_area']; ?>" <?php if($area['id_area'] == $miembro['id_area']) echo 'selected'; ?>>
                    <?php echo e($area['nombre_area']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <hr>
        <div class="form-group">
            <label for="password">Nueva Contraseña</label>
            <input type="text" name="password" placeholder="Dejar en blanco para no cambiar">
            <small>Si dejas este campo vacío, la contraseña actual del usuario no se modificará.</small>
        </div>
        <button type="submit" class="btn btn-success">Guardar Cambios</button>
    </form>
</div>

<?php include '../includes/footer_admin.php'; ?>