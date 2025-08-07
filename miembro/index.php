<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/funciones.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'miembro') {
    header("Location: " . BASE_URL . "/miembro/login.php");
    exit();
}

$id_miembro = $_SESSION['user_id'];
// --- INICIO DE LA MODIFICACIÓN: CAMBIO DE VISTA PREDETERMINADA ---
$view_mode = $_GET['view'] ?? 'list'; // Antes decía 'calendar', ahora 'list'
// --- FIN DE LA MODIFICACIÓN ---

try {
    $stmt = $pdo->prepare("SELECT t.*, u.nombre_completo as nombre_creador FROM tareas t JOIN tareas_asignadas ta ON t.id_tarea = ta.id_tarea JOIN usuarios u ON t.id_admin_creador = u.id_usuario WHERE ta.id_usuario = ? AND t.estado != 'cerrada' ORDER BY t.fecha_vencimiento ASC");
    $stmt->execute([$id_miembro]);
    $tareas = $stmt->fetchAll();
} catch(PDOException $e) { die("Error al recuperar tus tareas: " . $e->getMessage()); }

$eventos_json = [];
foreach ($tareas as $tarea) {
    $color = '#007bff';
    if ($tarea['prioridad'] === 'media') $color = '#ffc107';
    if ($tarea['prioridad'] === 'alta') $color = '#dc3545';
    $eventos_json[] = [ 'title' => $tarea['nombre_tarea'], 'start' => $tarea['fecha_vencimiento'], 'url'   => BASE_URL . '/miembro/tarea.php?id=' . $tarea['id_tarea'], 'color' => $color, 'borderColor' => $color, 'extendedProps' => [ 'status' => $tarea['estado'] ] ];
}
$eventos_json = json_encode($eventos_json);

include '../includes/header_miembro.php';
?>

<h2>Mis Tareas Asignadas</h2>
<p>Hola, <?php echo e($_SESSION['user_nombre']); ?>. Aquí puedes ver tus tareas pendientes.</p>

<div class="view-switcher" style="margin-bottom: 20px;">
    <a href="?view=calendar" class="btn <?php echo $view_mode === 'calendar' ? 'btn-primary' : 'btn-secondary'; ?>"><i class="fas fa-calendar-alt"></i> Vista Calendario</a>
    <a href="?view=list" class="btn <?php echo $view_mode === 'list' ? 'btn-primary' : 'btn-secondary'; ?>"><i class="fas fa-list"></i> Vista Lista</a>
</div>

<?php if ($view_mode === 'calendar'): ?>
    <div class="card">
        <div id='calendario'></div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Nombre Tarea</th><th>Creado por</th><th>Nº Piezas</th><th>Negocio</th><th>Fecha Vencimiento</th><th>Prioridad</th><th>Estado</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php if(empty($tareas)): ?>
                        <tr><td colspan="8" style="text-align:center;">¡Felicidades! No tienes tareas pendientes.</td></tr>
                    <?php else: ?>
                        <?php foreach($tareas as $tarea): ?>
                            <tr>
                                <td><?php echo e($tarea['nombre_tarea']); ?></td>
                                <td><?php echo e($tarea['nombre_creador']); ?></td>
                                <td><?php echo e($tarea['numero_piezas']); ?></td>
                                <td><?php echo e($tarea['negocio']); ?></td>
                                <td><span class="icon-text"><i class="fas fa-calendar-day"></i> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_vencimiento'])); ?></span></td>
                                <td>
                                    <?php
                                    $prioridad_clase = e($tarea['prioridad']); $prioridad_texto = ucfirst($prioridad_clase); $prioridad_icono = 'fa-circle-info';
                                    if ($prioridad_clase == 'alta') $prioridad_icono = 'fa-triangle-exclamation';
                                    if ($prioridad_clase == 'media') $prioridad_icono = 'fa-circle-exclamation';
                                    echo "<span class='icon-text icon-prioridad-{$prioridad_clase}'><i class='fas {$prioridad_icono}'></i> {$prioridad_texto}</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php echo mostrar_estado_tarea($tarea); ?>
                                </td>
                                <td><a href="tarea.php?id=<?php echo $tarea['id_tarea']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
if (document.getElementById('calendario')) {
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendario');

        let calendarOptions = {
            locale: 'es', 
            buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
            events: <?php echo $eventos_json; ?>,
            eventDidMount: function(info) { if (info.event.extendedProps.status === 'finalizada_usuario') { info.el.style.opacity = '0.7'; info.el.style.borderStyle = 'dashed'; }}
        };

        if (window.innerWidth < 768) {
            calendarOptions.initialView = 'listWeek';
            calendarOptions.headerToolbar = { left: 'prev,next', center: 'title', right: 'today' };
            calendarOptions.titleFormat = { year: 'numeric', month: 'short', day: 'numeric' }; 
        }

        var calendar = new FullCalendar.Calendar(calendarEl, calendarOptions);
        calendar.render();
    });
}
</script>

<?php include '../includes/footer_miembro.php'; ?>