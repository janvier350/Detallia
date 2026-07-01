<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);
require_module_view('dashboard');

// ---------------------------------------------------------------
// Indicadores generales
// ---------------------------------------------------------------
$total_clients = (int) mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) AS c FROM clients WHERE status = 'activo'"))["c"];
$total_kits    = (int) mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) AS c FROM kits WHERE status = 'activo'"))["c"];

$deliveries_month = (int) mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) AS c FROM kit_deliveries WHERE MONTH(delivery_date) = MONTH(CURDATE()) AND YEAR(delivery_date) = YEAR(CURDATE())"
))["c"];

$low_stock_count = (int) mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) AS c FROM (
        SELECT a.id, COALESCE(SUM(sm.quantity), 0) AS stock
        FROM articles a
        LEFT JOIN stock_movements sm ON sm.article_id = a.id
        WHERE a.status = 'activo'
        GROUP BY a.id
        HAVING stock <= 10
     ) t"
))["c"];

// ---------------------------------------------------------------
// Entregas por mes (ultimos 6 meses) para el grafico
// ---------------------------------------------------------------
$months_labels = [];
$months_counts = [];
$monthNamesEs = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months");
    $y = (int) date("Y", $ts);
    $m = (int) date("n", $ts);
    $months_labels[] = $monthNamesEs[$m] . " " . $y;

    $stmt = mysqli_prepare($link, "SELECT COUNT(*) AS c FROM kit_deliveries WHERE MONTH(delivery_date) = ? AND YEAR(delivery_date) = ?");
    mysqli_stmt_bind_param($stmt, "ii", $m, $y);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $months_counts[] = (int) mysqli_fetch_assoc($res)["c"];
}

// ---------------------------------------------------------------
// Ultimas entregas
// ---------------------------------------------------------------
$recentDeliveries = mysqli_query($link, "SELECT d.id, d.delivery_date, k.name AS kit_name, c.name AS client_name
                                          FROM kit_deliveries d
                                          JOIN kits k ON k.id = d.kit_id
                                          JOIN clients c ON c.id = d.client_id
                                          ORDER BY d.id DESC LIMIT 6");

// ---------------------------------------------------------------
// Top kits mas entregados
// ---------------------------------------------------------------
$topKits = mysqli_query($link, "SELECT k.name, COUNT(*) AS total
                                 FROM kit_deliveries d
                                 JOIN kits k ON k.id = d.kit_id
                                 GROUP BY k.id, k.name
                                 ORDER BY total DESC LIMIT 5");

// ---------------------------------------------------------------
// Articulos con stock bajo
// ---------------------------------------------------------------
$lowStockArticles = mysqli_query($link, "SELECT a.name, a.unit, COALESCE(SUM(sm.quantity), 0) AS stock
                                          FROM articles a
                                          LEFT JOIN stock_movements sm ON sm.article_id = a.id
                                          WHERE a.status = 'activo'
                                          GROUP BY a.id, a.name, a.unit
                                          HAVING stock <= 10
                                          ORDER BY stock ASC LIMIT 6");

// ---------------------------------------------------------------
// Movimientos de inventario para el calendario
// ---------------------------------------------------------------
$stockEvents = [];
$res = mysqli_query($link, "SELECT sm.movement_type, sm.quantity, sm.created_at, a.name AS article_name, a.unit
                             FROM stock_movements sm
                             JOIN articles a ON a.id = sm.article_id
                             ORDER BY sm.created_at DESC LIMIT 300");
while ($row = mysqli_fetch_assoc($res)) {
    $stockEvents[] = $row;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Dashboard | Detallia</title>

    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>
    <style>
        .fc-event.evt-compra { background-color: #34c38f !important; border-color: #34c38f !important; color: #fff !important; }
        .fc-event.evt-entrega { background-color: #f46a6a !important; border-color: #f46a6a !important; color: #fff !important; }
        .fc-event.evt-devolucion { background-color: #50a5f1 !important; border-color: #50a5f1 !important; color: #fff !important; }
        .fc-event.evt-ajuste { background-color: #74788d !important; border-color: #74788d !important; color: #fff !important; }
        .fc-event-title, .fc-list-event-title { color: #fff !important; }
    </style>
</head>

<?php include 'layouts/body.php'; ?>

<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>

    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Dashboard</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Dashboard</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-3 col-md-6">
                        <div class="card card-h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <span class="avatar-title rounded-circle bg-primary-subtle text-primary font-size-20">
                                            <i class="mdi mdi-account-group"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-muted mb-1">Clientes activos</p>
                                        <h4 class="mb-0"><?php echo $total_clients; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <span class="avatar-title rounded-circle bg-success-subtle text-success font-size-20">
                                            <i class="mdi mdi-gift-outline"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-muted mb-1">Kits activos</p>
                                        <h4 class="mb-0"><?php echo $total_kits; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <span class="avatar-title rounded-circle bg-info-subtle text-info font-size-20">
                                            <i class="mdi mdi-truck-delivery-outline"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-muted mb-1">Entregas este mes</p>
                                        <h4 class="mb-0"><?php echo $deliveries_month; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="card card-h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <span class="avatar-title rounded-circle bg-warning-subtle text-warning font-size-20">
                                            <i class="mdi mdi-alert-outline"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-muted mb-1">Articulos con stock bajo</p>
                                        <h4 class="mb-0"><?php echo $low_stock_count; ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-8">
                        <div class="card card-h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Entregas por mes</h5>
                                <div id="deliveries-chart" data-colors='["#556ee6"]'></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card card-h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Kits mas entregados</h5>
                                <?php if (mysqli_num_rows($topKits) === 0): ?>
                                    <p class="text-muted">Aun no hay entregas registradas.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while ($tk = mysqli_fetch_assoc($topKits)): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                <?php echo htmlspecialchars($tk["name"]); ?>
                                                <span class="badge bg-primary rounded-pill"><?php echo (int) $tk["total"]; ?></span>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-xl-7">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Ultimas entregas</h4>
                                <a href="admin-deliveries-list.php" class="btn btn-sm btn-soft-primary">Ver todas</a>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($recentDeliveries) === 0): ?>
                                    <p class="text-muted mb-0">Aun no hay entregas registradas.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-centered table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Cliente</th>
                                                    <th>Kit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($rd = mysqli_fetch_assoc($recentDeliveries)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($rd["delivery_date"]); ?></td>
                                                        <td><?php echo htmlspecialchars($rd["client_name"]); ?></td>
                                                        <td><?php echo htmlspecialchars($rd["kit_name"]); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Articulos con stock bajo</h4>
                                <a href="admin-articles-list.php" class="btn btn-sm btn-soft-primary">Ver articulos</a>
                            </div>
                            <div class="card-body">
                                <?php if (mysqli_num_rows($lowStockArticles) === 0): ?>
                                    <p class="text-muted mb-0">Todo el inventario esta en buen nivel.</p>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while ($ls = mysqli_fetch_assoc($lowStockArticles)): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                <?php echo htmlspecialchars($ls["name"]); ?>
                                                <span class="badge bg-<?php echo ((float) $ls["stock"]) <= 0 ? 'danger' : 'warning'; ?>">
                                                    <?php echo format_qty($ls["stock"]) . ' ' . htmlspecialchars($ls["unit"]); ?>
                                                </span>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header align-items-center d-flex">
                                <h4 class="card-title mb-0 flex-grow-1">Calendario de movimientos de inventario</h4>
                                <a href="admin-stock-movements.php" class="btn btn-sm btn-soft-primary">Ver historial completo</a>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3 mb-3">
                                    <span class="badge bg-success-subtle text-success"><i class="mdi mdi-circle-medium"></i> Compra (entrada)</span>
                                    <span class="badge bg-danger-subtle text-danger"><i class="mdi mdi-circle-medium"></i> Entrega (salida)</span>
                                    <span class="badge bg-info-subtle text-info"><i class="mdi mdi-circle-medium"></i> Devolucion (entrada)</span>
                                    <span class="badge bg-secondary-subtle text-secondary"><i class="mdi mdi-circle-medium"></i> Ajuste</span>
                                </div>
                                <div id="inventory-calendar"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>

<script src="assets/libs/apexcharts/apexcharts.min.js"></script>
<script src="assets/libs/fullcalendar/index.global.min.js"></script>
<script src="assets/js/app.js"></script>

<script>
var options = {
    series: [{
        name: 'Entregas',
        data: <?php echo json_encode($months_counts); ?>
    }],
    chart: {
        type: 'bar',
        height: 320,
        toolbar: { show: false }
    },
    plotOptions: {
        bar: { borderRadius: 4, columnWidth: '45%' }
    },
    dataLabels: { enabled: false },
    colors: ['#556ee6'],
    xaxis: {
        categories: <?php echo json_encode($months_labels); ?>
    }
};
var chart = new ApexCharts(document.querySelector("#deliveries-chart"), options);
chart.render();

var STOCK_EVENTS = <?php echo json_encode($stockEvents, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var MOVEMENT_LABELS = { compra: 'Compra', entrega: 'Entrega', devolucion: 'Devolucion', ajuste: 'Ajuste' };

var calendarEvents = STOCK_EVENTS.map(function (ev) {
    var qty = parseFloat(ev.quantity);
    var sign = qty >= 0 ? '+' : '';
    return {
        title: MOVEMENT_LABELS[ev.movement_type] + ': ' + ev.article_name + ' (' + sign + qty + ' ' + ev.unit + ')',
        start: ev.created_at.replace(' ', 'T'),
        classNames: ['evt-' + ev.movement_type]
    };
});

var calendarEl = document.getElementById('inventory-calendar');
var inventoryCalendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 600,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,dayGridWeek,listMonth'
    },
    buttonText: {
        today: 'Hoy',
        month: 'Mes',
        week: 'Semana',
        list: 'Lista'
    },
    events: calendarEvents,
    dayMaxEvents: 3
});
inventoryCalendar.render();
</script>

</body>

</html>
