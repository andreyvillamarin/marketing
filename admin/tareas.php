<?php $page_title = 'Todas las Tareas'; ?>
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

// Manejo de eliminación múltiple
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_seleccionados'])) {
    $ids_tareas = isset($_POST['ids_tareas']) ? $_POST['ids_tareas'] : [];
    if (!empty($ids_tareas)) {
        try {
            $placeholders = implode(',', array_fill(0, count($ids_tareas), '?'));
            $stmt = $pdo->prepare("DELETE FROM tareas WHERE id_tarea IN ($placeholders)");
            $stmt->execute($ids_tareas); $mensaje = "Tareas seleccionadas eliminadas correctamente.";
        } catch (PDOException $e) { $error = "Error al eliminar las tareas: " . $e->getMessage(); }
    } else { $error = "No se seleccionó ninguna tarea para eliminar."; }
}

// Construcción de la consulta con filtros
$sql = "SELECT t.*, u.nombre_completo as creador FROM tareas t JOIN usuarios u ON t.id_admin_creador = u.id_usuario";
$params = []; $where_clauses = [];
if (!empty($_GET['q'])) { $where_clauses[] = "t.nombre_tarea LIKE ?"; $params[] = '%' . $_GET['q'] . '%'; }
if (!empty($_GET['estado'])) { $where_clauses[] = "t.estado = ?"; $params[] = $_GET['estado']; }
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(' AND ', $where_clauses); }
$sql .= " ORDER BY t.fecha_vencimiento ASC";
try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $tareas = $stmt->fetchAll(); } 
catch(PDOException $e) { die("Error al recuperar las tareas: " . $e->getMessage()); }

include '../includes/header_admin.php';
?>
<h2><i class="fas fa-tasks"></i> Todas las Tareas</h2>

<?php if ($mensaje): ?><div class="alert alert-success"><?php echo e($mensaje); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div class="card">
    <form action="tareas.php" method="GET">
        <h3><i class="fas fa-filter"></i> Filtrar Tareas</h3>
        <div class="form-group" style="display:inline-block; width: 40%;"><label for="q">Buscar por nombre:</label><input type="text" name="q" id="q" value="<?php echo e($_GET['q'] ?? ''); ?>"></div>
        <div class="form-group" style="display:inline-block; width: 30%;"><label for="estado">Filtrar por estado:</label><select name="estado" id="estado"><option value="">Todos</option><option value="pendiente" <?php echo (($_GET['estado'] ?? '') == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option><option value="finalizada_usuario" <?php echo (($_GET['estado'] ?? '') == 'finalizada_usuario') ? 'selected' : ''; ?>>Finalizada por Usuario</option><option value="cerrada" <?php echo (($_GET['estado'] ?? '') == 'cerrada') ? 'selected' : ''; ?>>Cerrada</option></select></div>
        <button type="submit" class="btn"><i class="fas fa-search"></i> Filtrar</button>
        <a href="tareas.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
    </form>
</div>

<form action="tareas.php" method="POST">
    <button type="submit" name="eliminar_seleccionados" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar las tareas seleccionadas?');"><i class="fas fa-trash-can"></i> Eliminar Seleccionados</button>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll"></th>
                    <th>Nombre Tarea</th><th>Creador</th><th>Fecha Vencimiento</th><th>Estado</th><th>Prioridad</th><th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tareas)): ?>
                    <tr><td colspan="7">No se encontraron tareas con los filtros aplicados.</td></tr>
                <?php else: ?>
                    <?php foreach ($tareas as $tarea): ?>
                        <tr>
                            <td><input type="checkbox" name="ids_tareas[]" value="<?php echo $tarea['id_tarea']; ?>"></td>
                            <td><?php echo e($tarea['nombre_tarea']); ?></td>
                            <td><?php echo e($tarea['creador']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($tarea['fecha_vencimiento'])); ?></td>
                            <td>
                                <?php
                                $estado_clase = e($tarea['estado']);
                                $estado_texto = ucfirst(str_replace('_', ' ', $estado_clase));
                                $estado_icono = 'fa-clock'; // Default para 'pendiente'
                                if ($estado_clase == 'finalizada_usuario') $estado_icono = 'fa-check';
                                if ($estado_clase == 'cerrada') $estado_icono = 'fa-check-double';
                                echo "<span class='icon-text icon-estado-{$estado_clase}'><i class='fas {$estado_icono}'></i> {$estado_texto}</span>";
                                ?>
                            </td>
                            <td>
                                <?php
                                $prioridad_clase = e($tarea['prioridad']);
                                $prioridad_texto = ucfirst($prioridad_clase);
                                $prioridad_icono = 'fa-circle-info'; // Default para 'baja'
                                if ($prioridad_clase == 'alta') $prioridad_icono = 'fa-triangle-exclamation';
                                if ($prioridad_clase == 'media') $prioridad_icono = 'fa-circle-exclamation';
                                echo "<span class='icon-text icon-prioridad-{$prioridad_clase}'><i class='fas {$prioridad_icono}'></i> {$prioridad_texto}</span>";
                                ?>
                            </td>
                            <td class="actions">
                                <a href="editar_tarea.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-warning"><i class="fas fa-pencil-alt"></i> Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<?php include '../includes/footer_admin.php'; ?>