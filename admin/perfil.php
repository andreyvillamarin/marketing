<?php $page_title = 'Mi Perfil y Seguridad'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página: solo para administradores logueados
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: " . BASE_URL . "/admin/login.php");
    exit();
}

$mensaje = '';
$error = '';
$id_admin = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (La lógica PHP se mantiene exactamente igual)
    $pass_actual = $_POST['password_actual'] ?? '';
    $pass_nueva = $_POST['password_nuevo'] ?? '';
    $pass_confirmar = $_POST['password_confirmar'] ?? '';

    if (empty($pass_actual) || empty($pass_nueva) || empty($pass_confirmar)) {
        $error = "Todos los campos son obligatorios.";
    } elseif ($pass_nueva !== $pass_confirmar) {
        $error = "La nueva contraseña y su confirmación no coinciden.";
    } elseif (strlen($pass_nueva) < 6) {
        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_admin]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($pass_actual, $admin['password'])) {
                $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
                $stmt_update->execute([$nuevo_hash, $id_admin]);
                
                $mensaje = "¡Tu contraseña ha sido actualizada exitosamente!";
            } else {
                $error = "La contraseña actual que ingresaste es incorrecta.";
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos. Por favor, intenta de nuevo.";
            error_log($e->getMessage());
        }
    }
}

include '../includes/header_admin.php';
?>

<h2>Mi Perfil y Seguridad</h2>
<p>Aquí puedes cambiar tu contraseña de acceso al panel de administración.</p>

<div class="card form-card">
<h3>Cambiar mi Contraseña</h3>

    <?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

    <form action="perfil.php" method="POST" class="form-container" style="padding:0; border:none; margin:0;">
        <div class="form-group">
            <label for="password_actual">Contraseña Actual</label>
            <input type="password" name="password_actual" id="password_actual" required>
        </div>
        <div class="form-group">
            <label for="password_nuevo">Nueva Contraseña</label>
            <input type="password" name="password_nuevo" id="password_nuevo" required>
        </div>
        <div class="form-group">
            <label for="password_confirmar">Confirmar Nueva Contraseña</label>
            <input type="password" name="password_confirmar" id="password_confirmar" required>
        </div>
        <button type="submit" class="btn btn-success">Actualizar Contraseña</button>
    </form>
</div>

<?php include '../includes/footer_admin.php'; ?>