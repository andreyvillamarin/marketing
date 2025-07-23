<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'miembro') {
    header("Location: " . BASE_URL . "/miembro/login.php");
    exit();
}

$id_miembro = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT t.* FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.estado = 'cerrada' ORDER BY t.fecha_vencimiento DESC");
    $stmt->execute([$id_miembro]);
    $tareas = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error al recuperar tus tareas completadas: " . $e->getMessage());
}

include '../includes/header_miembro.php';
?>

<h2>Mis Tareas Completadas</h2>
<p>Hola, <?php echo e($_SESSION['user_nombre']); ?>. Aquí puedes ver tus tareas que han sido completadas y cerradas.</p>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Nombre Tarea</th>
                    <th>Fecha Vencimiento</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($tareas)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;">No tienes ninguna tarea completada por el momento.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach($tareas as $tarea): ?>
                        <tr>
                            <td><?php echo e($tarea['nombre_tarea']); ?></td>
                            <td>
                                <span class="icon-text"><i class="fas fa-calendar-day"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_vencimiento'])); ?></span>
                            </td>
                            <td>
                                <?php
                                $prioridad_clase = e($tarea['prioridad']);
                                $prioridad_texto = ucfirst($prioridad_clase);
                                $prioridad_icono = 'fa-circle-info';
                                if ($prioridad_clase == 'alta') $prioridad_icono = 'fa-triangle-exclamation';
                                if ($prioridad_clase == 'media') $prioridad_icono = 'fa-circle-exclamation';
                                echo "<span class='icon-text icon-prioridad-{$prioridad_clase}'><i class='fas {$prioridad_icono}'></i> {$prioridad_texto}</span>";
                                ?>
                            </td>
                            <td>
                                <?php
                                $estado_clase = e($tarea['estado']);
                                $estado_texto = ucfirst(str_replace('_', ' ', $estado_clase));
                                $estado_icono = 'fa-check-double'; // Icono para cerradas
                                echo "<span class='icon-text icon-estado-{$estado_clase}'><i class='fas {$estado_icono}'></i> {$estado_texto}</span>";
                                ?>
                            </td>
                            <td>
                                <a href="tarea.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer_miembro.php'; ?>
