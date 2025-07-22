<?php $page_title = 'Gestionar Equipo'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$mensaje = ''; $error = '';

// Lógica para manejar POST requests (Crear Área, Crear Miembro)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_area'])) {
        $nombre_area = trim($_POST['nombre_area']);
        if (!empty($nombre_area)) {
            try { $stmt = $pdo->prepare("INSERT INTO areas (nombre_area) VALUES (?)"); $stmt->execute([$nombre_area]); $mensaje = "Área creada exitosamente."; } 
            catch (PDOException $e) { $error = "Error al crear el área. Es posible que ya exista."; }
        } else { $error = "El nombre del área no puede estar vacío."; }
    }
    if (isset($_POST['crear_miembro'])) {
        $nombre_completo = trim($_POST['nombre_completo']); $email = trim($_POST['email']); $password = $_POST['password']; $id_area = $_POST['id_area'];
        if (!empty($nombre_completo) && !empty($email) && !empty($password) && !empty($id_area)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre_completo, email, password, rol, id_area) VALUES (?, ?, ?, 'miembro', ?)");
                $stmt->execute([$nombre_completo, $email, $hashed_password, $id_area]); $mensaje = "Miembro creado exitosamente.";
            } catch (PDOException $e) { $error = "Error al crear miembro. El email ya podría estar en uso."; }
        } else { $error = "Todos los campos para crear un miembro son obligatorios."; }
    }
}

// Lógica para manejar GET requests (eliminación)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['eliminar_area'])) {
        try { $stmt = $pdo->prepare("DELETE FROM areas WHERE id_area = ?"); $stmt->execute([$_GET['eliminar_area']]); $mensaje = "Área eliminada."; } 
        catch (PDOException $e) { $error = "Error al eliminar el área."; }
    }
    if (isset($_GET['eliminar_miembro'])) {
        try { $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ? AND rol = 'miembro'"); $stmt->execute([$_GET['eliminar_miembro']]); $mensaje = "Miembro eliminado exitosamente."; } 
        catch (PDOException $e) { $error = "Error al eliminar el miembro."; }
    }
}

// Obtener datos para mostrar
$areas = $pdo->query("SELECT * FROM areas ORDER BY nombre_area")->fetchAll();
$miembros = $pdo->query("SELECT u.*, a.nombre_area FROM usuarios u LEFT JOIN areas a ON u.id_area = a.id_area WHERE u.rol = 'miembro' ORDER BY u.nombre_completo")->fetchAll();

include '../includes/header_admin.php';
?>

<h2><i class="fas fa-users-cog"></i> Gestionar Equipo</h2>

<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div class="grid-container">
    <div class="card">
        <h3>Gestionar Áreas</h3>
        <form action="miembros.php" method="POST" class="form-container" style="padding:0; border:none; margin:0; max-width:none;">
            <div class="form-group">
                <label for="nombre_area">Nombre de la Nueva Área</label>
                <input type="text" name="nombre_area" id="nombre_area" required>
            </div>
            <button type="submit" name="crear_area" class="btn btn-success">Crear Área</button>
        </form>
        <hr>
        <h4>Áreas Existentes</h4>
        <table>
            <tbody>
            <?php foreach ($areas as $area): ?>
                <tr>
                    <td><?php echo e($area['nombre_area']); ?></td>
                    <td class="actions" style="text-align: right;">
                        <a href="miembros.php?eliminar_area=<?php echo $area['id_area']; ?>" class="delete-link" onclick="return confirm('¿Seguro que quieres eliminar esta área?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <h3>Añadir Nuevo Miembro</h3>
        <form action="miembros.php" method="POST" class="form-container" style="padding:0; border:none; margin:0; max-width:none;">
            <div class="form-group">
                <label for="nombre_completo">Nombre Completo</label>
                <input type="text" name="nombre_completo" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña Inicial</label>
                <input type="text" name="password" required>
            </div>
            <div class="form-group">
                <label for="id_area">Área</label>
                <select name="id_area" required>
                    <option value="">Seleccionar área...</option>
                    <?php foreach ($areas as $area): ?>
                    <option value="<?php echo $area['id_area']; ?>"><?php echo e($area['nombre_area']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="crear_miembro" class="btn btn-success">Crear Miembro</button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h3>Lista de Miembros del Equipo</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Nombre</th><th>Email</th><th>Área</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($miembros as $miembro): ?>
                <tr>
                    <td><?php echo e($miembro['nombre_completo']); ?></td>
                    <td><?php echo e($miembro['email']); ?></td>
                    <td><?php echo e($miembro['nombre_area'] ?? 'N/A'); ?></td>
                    <td class="actions">
                        <a href="editar_miembro.php?id=<?php echo $miembro['id_usuario']; ?>" class="btn btn-warning" title="Editar Miembro">
                            <i class="fas fa-pencil-alt"></i>
                        </a>
                        <a href="miembros.php?eliminar_miembro=<?php echo $miembro['id_usuario']; ?>" class="btn btn-danger" title="Eliminar Miembro" onclick="return confirm('¿Estás seguro de que quieres eliminar a este miembro? Esta acción también eliminará sus tareas asignadas.')">
                            <i class="fas fa-trash-can"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer_admin.php'; ?>