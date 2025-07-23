<?php $page_title = 'Analíticas del Equipo'; ?>
<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') { header("Location: index.php"); exit(); }

$tipo_informe = $_GET['tipo_informe'] ?? 'individual';
$id_miembro_filtro = $_GET['id_miembro'] ?? null;
$fecha_inicio = $_GET['fecha_inicio'] ?? null;
$fecha_fin = $_GET['fecha_fin'] ?? null;

$miembros = $pdo->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE rol != 'admin' ORDER BY nombre_completo ASC")->fetchAll();
$datos_graficos = [];
$listados = [];
$error = '';
$datos_encontrados = false;

if ($fecha_inicio && $fecha_fin) {
    try {
        $fecha_fin_sql = $fecha_fin . ' 23:59:59';

        if ($tipo_informe === 'individual' && $id_miembro_filtro) {
            // LÓGICA PARA INFORME INDIVIDUAL (SIN CAMBIOS)
            $stmt_pie = $pdo->prepare("SELECT estado, COUNT(*) as total FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.fecha_creacion BETWEEN ? AND ? GROUP BY estado");
            $stmt_pie->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
            $data_pie = $stmt_pie->fetchAll();
            $datos_graficos['individual_pie'] = ['labels' => array_column($data_pie, 'estado'), 'data' => array_column($data_pie, 'total')];

            $stmt_bar = $pdo->prepare("SELECT DATE_FORMAT(fecha_vencimiento, '%Y-%m') as mes, COUNT(*) as total FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.fecha_vencimiento BETWEEN ? AND ? GROUP BY mes ORDER BY mes ASC");
            $stmt_bar->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
            $data_bar = $stmt_bar->fetchAll();
            $datos_graficos['individual_bar'] = ['labels' => array_column($data_bar, 'mes'), 'data' => array_column($data_bar, 'total')];
            
            $stmt_completadas = $pdo->prepare("SELECT nombre_tarea FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.estado = 'completada' AND t.fecha_vencimiento BETWEEN ? AND ?");
            $stmt_completadas->execute([$id_miembro_filtro, $fecha_inicio, $fecha_fin_sql]);
            $listados['completadas'] = $stmt_completadas->fetchAll();
            
            $stmt_vencidas = $pdo->prepare("SELECT nombre_tarea, fecha_vencimiento FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea WHERE ta.id_usuario = ? AND t.estado != 'completada' AND t.fecha_vencimiento < NOW()");
            $stmt_vencidas->execute([$id_miembro_filtro]);
            $listados['vencidas'] = $stmt_vencidas->fetchAll();
            
            if(!empty($data_pie) || !empty($data_bar)) $datos_encontrados = true;

        } elseif ($tipo_informe === 'equipo') {
            // --- INICIO DE LA MODIFICACIÓN: CONSULTAS CORREGIDAS PARA INCLUIR ANALISTAS ---
            $roles_incluidos = ['miembro', 'analista'];

            $placeholders = implode(',', array_fill(0, count($roles_incluidos), '?'));

            $stmt_bar = $pdo->prepare("SELECT u.nombre_completo, COUNT(t.id_tarea) as total FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea JOIN usuarios u ON ta.id_usuario = u.id_usuario WHERE t.estado = 'completada' AND t.fecha_creacion BETWEEN ? AND ? AND u.rol IN ($placeholders) GROUP BY u.id_usuario ORDER BY total DESC");
            $stmt_bar->execute(array_merge([$fecha_inicio, $fecha_fin_sql], $roles_incluidos));
            $data_bar = $stmt_bar->fetchAll();
            $datos_graficos['equipo_bar'] = ['labels' => array_column($data_bar, 'nombre_completo'), 'data' => array_column($data_bar, 'total')];

            $stmt_pie = $pdo->prepare("SELECT u.nombre_completo, COUNT(t.id_tarea) as total FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea JOIN usuarios u ON ta.id_usuario = u.id_usuario WHERE t.fecha_creacion BETWEEN ? AND ? AND u.rol IN ($placeholders) GROUP BY u.id_usuario ORDER BY total DESC");
            $stmt_pie->execute(array_merge([$fecha_inicio, $fecha_fin_sql], $roles_incluidos));
            $data_pie = $stmt_pie->fetchAll();
            $datos_graficos['equipo_pie'] = ['labels' => array_column($data_pie, 'nombre_completo'), 'data' => array_column($data_pie, 'total')];

            $stmt_vencidas = $pdo->prepare("SELECT u.nombre_completo, COUNT(t.id_tarea) as total FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea JOIN usuarios u ON ta.id_usuario = u.id_usuario WHERE t.estado != 'completada' AND t.fecha_vencimiento < NOW() AND u.rol IN ($placeholders) GROUP BY u.id_usuario ORDER BY total DESC");
            $stmt_vencidas->execute($roles_incluidos);
            $listados['vencidas'] = $stmt_vencidas->fetchAll();
            // --- FIN DE LA MODIFICACIÓN ---
            
            if(!empty($data_bar) || !empty($data_pie)) $datos_encontrados = true;
        }
    } catch(PDOException $e) { $error = "Error al generar las analíticas: " . $e->getMessage(); }
}

include '../includes/header_admin.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if (isset($error) && !empty($error)): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
<div class="card">
    <form action="analiticas.php" method="GET">
        <h3><i class="fas fa-filter"></i> Filtrar Datos</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
            <div class="form-group" style="flex: 1 1 200px;"><label for="tipo_informe">Tipo de Informe:</label><select name="tipo_informe" id="tipo_informe"><option value="individual" <?php if($tipo_informe == 'individual') echo 'selected'; ?>>Rendimiento Individual</option><option value="equipo" <?php if($tipo_informe == 'equipo') echo 'selected'; ?>>Comparativa de Equipo</option></select></div>
            <div class="form-group" style="flex: 1 1 200px;" id="miembro_selector_group"><label for="id_miembro">Seleccionar Usuario:</label><select name="id_miembro" id="id_miembro"><option value="">-- Todos --</option><?php foreach($miembros as $miembro): ?><option value="<?php echo $miembro['id_usuario']; ?>" <?php echo ($id_miembro_filtro == $miembro['id_usuario']) ? 'selected' : ''; ?>><?php echo e($miembro['nombre_completo']); ?> (<?php echo e(ucfirst($miembro['rol'])); ?>)</option><?php endforeach; ?></select></div>
            <div class="form-group" style="flex: 1 1 150px;"><label for="fecha_inicio">Desde:</label><input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo e($fecha_inicio); ?>" required></div>
            <div class="form-group" style="flex: 1 1 150px;"><label for="fecha_fin">Hasta:</label><input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo e($fecha_fin); ?>" required></div>
            <button type="submit" class="btn"><i class="fas fa-search"></i> Generar</button>
            <a href="analiticas.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
        </div>
    </form>
    <?php if ($datos_encontrados && $tipo_informe === 'individual'): ?>
        <div style="border-top: 1px solid #eee; margin-top: 20px; padding-top: 20px; text-align: right;">
            <a href="generar_informe_pdf.php?id_miembro=<?php echo e($id_miembro_filtro); ?>&fecha_inicio=<?php echo e($fecha_inicio); ?>&fecha_fin=<?php echo e($fecha_fin); ?>" class="btn btn-success" target="_blank"><i class="fas fa-file-pdf"></i> Descargar Informe en PDF</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($datos_encontrados): ?>
    <?php if ($tipo_informe === 'individual'): ?>
        <div class="analytics-grid" style="margin-top:20px;">
            <div class="chart-container"><h4><i class="fas fa-pie-chart"></i> Distribución por Estado</h4><canvas id="pieChart"></canvas></div>
            <div class="chart-container"><h4><i class="fas fa-chart-bar"></i> Tareas Gestionadas por Mes</h4><canvas id="barChart"></canvas></div>
            <div class="chart-container"><h4><i class="fas fa-check-double" style="color:var(--success-color)"></i> Tareas Completadas</h4><ul class="analytics-list"><?php if(empty($listados['completadas'])) echo "<li>No hay tareas completadas.</li>"; foreach($listados['completadas'] as $t) echo '<li><i class="fas fa-check"></i> '.e($t['nombre_tarea']).'</li>'; ?></ul></div>
            <div class="chart-container"><h4><i class="fas fa-exclamation-triangle" style="color:var(--danger-color)"></i> Tareas Vencidas</h4><ul class="analytics-list"><?php if(empty($listados['vencidas'])): ?><li><i class="fas fa-thumbs-up"></i> ¡Excelente! No hay tareas vencidas.</li><?php else: foreach($listados['vencidas'] as $t): ?><li class="task-overdue"><i class="fas fa-exclamation-circle"></i> <div><?php echo e($t['nombre_tarea']); ?><small style="display: block; color: #777;">Venció el: <?php echo date('d/m/Y', strtotime($t['fecha_vencimiento'])); ?></small></div></li><?php endforeach; endif; ?></ul></div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pieData = { labels: <?php echo json_encode(array_map('ucfirst', $datos_graficos['individual_pie']['labels'])); ?>, datasets: [{ data: <?php echo json_encode($datos_graficos['individual_pie']['data']); ?>, backgroundColor: [ 'rgba(108, 117, 125, 0.7)', 'rgba(255, 193, 7, 0.7)', 'rgba(40, 167, 69, 0.7)' ] }]};
            new Chart(document.getElementById('pieChart'), { type: 'pie', data: pieData });
            const barData = { labels: <?php echo json_encode($datos_graficos['individual_bar']['labels']); ?>, datasets: [{ label: 'Tareas Gestionadas', data: <?php echo json_encode($datos_graficos['individual_bar']['data']); ?>, backgroundColor: 'rgba(54, 162, 235, 0.7)' }]};
            new Chart(document.getElementById('barChart'), { type: 'bar', data: barData, options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
        });
        </script>
    <?php elseif ($tipo_informe === 'equipo'): ?>
        <div class="analytics-grid" style="margin-top:20px;">
            <div class="chart-container"><h4><i class="fas fa-chart-bar"></i> Tareas Completadas por Usuario</h4><canvas id="teamBarChart"></canvas></div>
            <div class="chart-container"><h4><i class="fas fa-pie-chart"></i> Distribución de Carga de Trabajo</h4><canvas id="teamPieChart"></canvas></div>
            <div class="chart-container"><h4><i class="fas fa-exclamation-triangle" style="color:var(--danger-color)"></i> Ranking de Tareas Vencidas</h4>
                <table class="ranking-table"><?php if(empty($listados['vencidas'])): ?><tr><td>¡Excelente! No hay tareas vencidas.</td></tr><?php endif; ?><?php foreach($listados['vencidas'] as $item): ?><tr><td><?php echo e($item['nombre_completo']); ?></td><td class="rank-value"><?php echo e($item['total']); ?></td></tr><?php endforeach; ?></table>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const teamBarData = { labels: <?php echo json_encode($datos_graficos['equipo_bar']['labels']); ?>, datasets: [{ label: 'Tareas Completadas', data: <?php echo json_encode($datos_graficos['equipo_bar']['data']); ?>, backgroundColor: 'rgba(75, 192, 192, 0.7)' }]};
            new Chart(document.getElementById('teamBarChart'), { type: 'bar', data: teamBarData, options: { indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } } });
            const teamPieData = { labels: <?php echo json_encode($datos_graficos['equipo_pie']['labels']); ?>, datasets: [{ data: <?php echo json_encode($datos_graficos['equipo_pie']['data']); ?> }]};
            new Chart(document.getElementById('teamPieChart'), { type: 'pie', data: teamPieData, options: { plugins: { legend: { position: 'right' } } } });
        });
        </script>
    <?php endif; ?>
<?php elseif ($fecha_inicio && $fecha_fin): ?>
    <div class="alert alert-info" style="margin-top:20px;">No se encontraron datos para el tipo de informe y el rango de fechas seleccionados.</div>
    <?php endif; ?>

<?php include '../includes/footer_admin.php'; ?>