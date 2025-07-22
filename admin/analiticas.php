<?php $page_title = 'Analíticas del Equipo'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

// Proteger página
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Obtener parámetros del filtro desde la URL
$id_miembro_filtro = $_GET['id_miembro'] ?? null;
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;

// Obtener lista completa de miembros para el menú desplegable del filtro
$miembros = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol = 'miembro' ORDER BY nombre_completo ASC")->fetchAll();

$datos_graficos = [];
$listados = [];
$error = '';

// Solo ejecutar las consultas si el formulario de filtro ha sido enviado con todos los datos
if ($id_miembro_filtro && $fecha_inicio && $fecha_fin) {
    try {
        $fecha_fin_sql = $fecha_fin . ' 23:59:59';

        // --- DATOS PARA GRÁFICO DE TORTA (Distribución por Estado) ---
        $stmt_pie = $pdo->prepare("
            SELECT estado, COUNT(*) as total FROM tareas t
            JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea
            WHERE ta.id_usuario = ? AND t.fecha_creacion BETWEEN ? AND ?
            GROUP BY estado
        ");
        $stmt_pie->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
        $data_pie = $stmt_pie->fetchAll();
        
        $datos_graficos['pie'] = [
            'labels' => array_column($data_pie, 'estado'),
            'data' => array_column($data_pie, 'total')
        ];

        // --- DATOS PARA GRÁFICO DE BARRAS (Tareas Gestionadas por Mes) ---
        $stmt_bar = $pdo->prepare("
            SELECT DATE_FORMAT(fecha_vencimiento, '%Y-%m') as mes, COUNT(*) as total FROM tareas t
            JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea
            WHERE ta.id_usuario = ? AND t.fecha_vencimiento BETWEEN ? AND ?
            GROUP BY mes ORDER BY mes ASC
        ");
        $stmt_bar->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
        $data_bar = $stmt_bar->fetchAll();

        $datos_graficos['bar'] = [
            'labels' => array_column($data_bar, 'mes'),
            'data' => array_column($data_bar, 'total')
        ];
        
        // --- DATOS PARA LAS LISTAS MEJORADAS ---
        $stmt_completadas = $pdo->prepare("SELECT nombre_tarea FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.estado = 'cerrada' AND t.fecha_vencimiento BETWEEN ? AND ? ORDER BY fecha_vencimiento DESC");
        $stmt_completadas->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
        $listados['completadas'] = $stmt_completadas->fetchAll();
        
        $stmt_pendientes = $pdo->prepare("SELECT nombre_tarea FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.estado = 'pendiente' AND t.fecha_creacion BETWEEN ? AND ? ORDER BY fecha_vencimiento ASC");
        $stmt_pendientes->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
        $listados['pendientes'] = $stmt_pendientes->fetchAll();

        $stmt_vencidas = $pdo->prepare("
            SELECT nombre_tarea, fecha_vencimiento FROM tareas t 
            JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea 
            WHERE ta.id_usuario = ? 
            AND t.estado != 'cerrada' 
            AND t.fecha_vencimiento < NOW()
        ");
        $stmt_vencidas->execute([$id_miembro_filtro]);
        $listados['vencidas'] = $stmt_vencidas->fetchAll();

    } catch(PDOException $e) {
        $error = "Error al generar las analíticas: " . $e->getMessage();
    }
}

include '../includes/header_admin.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<h2><i class="fas fa-chart-line"></i> Analíticas por Miembro</h2>

<?php if (isset($error) && !empty($error)): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

<div class="card">
    <form action="analiticas.php" method="GET" id="form-analiticas">
        <h3><i class="fas fa-filter"></i> Filtrar Datos</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            <div class="form-group" style="flex: 1 1 200px;">
                <label for="id_miembro">Seleccionar Miembro:</label>
                <select name="id_miembro" id="id_miembro" required>
                    <option value="">-- Miembro --</option>
                    <?php foreach($miembros as $miembro): ?>
                        <option value="<?php echo $miembro['id_usuario']; ?>" <?php echo ($id_miembro_filtro == $miembro['id_usuario']) ? 'selected' : ''; ?>>
                            <?php echo e($miembro['nombre_completo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 1 1 150px;">
                <label for="fecha_inicio">Desde:</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo e($fecha_inicio); ?>" required>
            </div>
            <div class="form-group" style="flex: 1 1 150px;">
                <label for="fecha_fin">Hasta:</label>
                <input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo e($fecha_fin); ?>" required>
            </div>
            <button type="submit" class="btn"><i class="fas fa-search"></i> Generar</button>
            <a href="analiticas.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
        </div>
    </form>
    
    <?php if($id_miembro_filtro && $fecha_inicio && $fecha_fin): ?>
        <div style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; text-align: right;">
            <a href="generar_informe_pdf.php?id_miembro=<?php echo e($id_miembro_filtro); ?>&fecha_inicio=<?php echo e($fecha_inicio); ?>&fecha_fin=<?php echo e($fecha_fin); ?>" 
               class="btn btn-success" 
               target="_blank">
               <i class="fas fa-file-pdf"></i> Descargar Informe en PDF
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if(!empty($datos_graficos['pie']['data'])): ?>
<div class="analytics-grid" style="margin-top:20px;">
    <div class="chart-container">
        <h4><i class="fas fa-pie-chart"></i> Distribución por Estado</h4>
        <canvas id="pieChart"></canvas>
    </div>
    <div class="chart-container">
        <h4><i class="fas fa-chart-bar"></i> Tareas Gestionadas por Mes</h4>
        <canvas id="barChart"></canvas>
    </div>
    <div class="chart-container">
        <h4><i class="fas fa-check-double" style="color:var(--success-color)"></i> Tareas Completadas en Periodo</h4>
        <ul class="analytics-list">
            <?php if(empty($listados['completadas'])): echo "<li>No hay tareas completadas.</li>"; endif; ?>
            <?php foreach($listados['completadas'] as $t): ?>
                <li><i class="fas fa-check" style="color:var(--success-color)"></i> <?php echo e($t['nombre_tarea']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="chart-container">
        <h4><i class="fas fa-hourglass-half" style="color:var(--secondary-color)"></i> Tareas Pendientes en Periodo</h4>
        <ul class="analytics-list">
            <?php if(empty($listados['pendientes'])): echo "<li>No hay tareas pendientes.</li>"; endif; ?>
            <?php foreach($listados['pendientes'] as $t): ?>
                <li><i class="far fa-clock" style="color:var(--secondary-color)"></i> <?php echo e($t['nombre_tarea']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="chart-container">
        <h4><i class="fas fa-exclamation-triangle" style="color:var(--danger-color)"></i> Tareas Vencidas</h4>
        <ul class="analytics-list">
            <?php if(empty($listados['vencidas'])): ?>
                <li><i class="fas fa-thumbs-up"></i> ¡Excelente! No hay tareas vencidas.</li>
            <?php else: ?>
                <?php foreach($listados['vencidas'] as $t): ?>
                    <li class="task-overdue">
                        <i class="fas fa-exclamation-circle"></i> 
                        <div>
                            <?php echo e($t['nombre_tarea']); ?>
                            <small style="display: block; color: #777;">Venció el: <?php echo date('d/m/Y', strtotime($t['fecha_vencimiento'])); ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- GRÁFICO DE TORTA ---
    const pieData = {
        labels: <?php echo json_encode(array_map('ucfirst', $datos_graficos['pie']['labels'])); ?>,
        datasets: [{
            label: 'Total Tareas',
            data: <?php echo json_encode($datos_graficos['pie']['data']); ?>,
            backgroundColor: [ 'rgba(108, 117, 125, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(40, 167, 69, 0.7)'],
            borderColor: [ 'rgba(108, 117, 125, 1)', 'rgba(255, 193, 7, 1)', 'rgba(40, 167, 69, 1)' ],
            borderWidth: 1
        }]
    };
    new Chart(document.getElementById('pieChart'), { type: 'pie', data: pieData, options: { responsive: true, maintainAspectRatio: true }});

    // --- GRÁFICO DE BARRAS ---
    const barData = {
        labels: <?php echo json_encode($datos_graficos['bar']['labels']); ?>,
        datasets: [{
            label: 'Tareas Gestionadas',
            data: <?php echo json_encode($datos_graficos['bar']['data']); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    };
    new Chart(document.getElementById('barChart'), { type: 'bar', data: barData, options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, responsive: true, maintainAspectRatio: true }});
});
</script>

<?php elseif(isset($_GET['id_miembro'])): ?>
<div class="alert alert-info" style="margin-top:20px;">No se encontraron datos para los filtros seleccionados o el miembro no tuvo actividad en este periodo.</div>
<?php endif; ?>

<?php include '../includes/footer_admin.php'; ?>