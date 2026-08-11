<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);

// ---------------------------------------------------------------
// Paleta de colores (consistente con el tema)
// ---------------------------------------------------------------
$palette = ['#556ee6', '#34c38f', '#f1b44c', '#f46a6a', '#50a5f1', '#6f42c1', '#e83e8c', '#2ab57d', '#fd7e14', '#00b8d4'];

// ---------------------------------------------------------------
// KPIs
// ---------------------------------------------------------------
$totalClients   = (int) mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) c FROM clients"))["c"];
$activeClients  = (int) mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) c FROM clients WHERE status='activo'"))["c"];
$totalCiudades  = (int) mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(DISTINCT NULLIF(TRIM(ciudad),'')) c FROM clients"))["c"];
$totalMarcas    = (int) mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(DISTINCT brand_id) c FROM clients WHERE brand_id IS NOT NULL"))["c"];

// ---------------------------------------------------------------
// Por clasificacion
// ---------------------------------------------------------------
$classLabels = [];
$classData   = [];
$res = mysqli_query($link, "SELECT COALESCE(cl.name,'(Sin clasificacion)') AS g, COUNT(c.id) AS total
                            FROM clients c
                            LEFT JOIN client_classifications cl ON cl.id = c.classification_id
                            GROUP BY g ORDER BY total DESC");
while ($r = mysqli_fetch_assoc($res)) { $classLabels[] = $r["g"]; $classData[] = (int) $r["total"]; }

// ---------------------------------------------------------------
// Por ciudad (top 10)
// ---------------------------------------------------------------
$cityLabels = [];
$cityData   = [];
$res = mysqli_query($link, "SELECT COALESCE(NULLIF(TRIM(ciudad),''),'(Sin ciudad)') AS g, COUNT(*) AS total
                            FROM clients GROUP BY g ORDER BY total DESC LIMIT 10");
while ($r = mysqli_fetch_assoc($res)) { $cityLabels[] = $r["g"]; $cityData[] = (int) $r["total"]; }

// ---------------------------------------------------------------
// Por marca
// ---------------------------------------------------------------
$brandLabels = [];
$brandData   = [];
$res = mysqli_query($link, "SELECT COALESCE(b.name,'(Sin marca)') AS g, COUNT(c.id) AS total
                            FROM clients c
                            LEFT JOIN brands b ON b.id = c.brand_id
                            GROUP BY g ORDER BY total DESC");
while ($r = mysqli_fetch_assoc($res)) { $brandLabels[] = $r["g"]; $brandData[] = (int) $r["total"]; }

// ---------------------------------------------------------------
// Cruce Clasificacion x Marca (tabla dinamica + barras apiladas)
// ---------------------------------------------------------------
$brandOrder = $brandLabels; // columnas
$pivot = [];                 // [clasificacion][marca] = count
$res = mysqli_query($link, "SELECT COALESCE(cl.name,'(Sin clasificacion)') AS cls,
                                   COALESCE(b.name,'(Sin marca)') AS brd,
                                   COUNT(*) AS total
                            FROM clients c
                            LEFT JOIN client_classifications cl ON cl.id = c.classification_id
                            LEFT JOIN brands b ON b.id = c.brand_id
                            GROUP BY cls, brd");
while ($r = mysqli_fetch_assoc($res)) {
    $pivot[$r["cls"]][$r["brd"]] = (int) $r["total"];
}
$pivotRows = $classLabels; // filas en el mismo orden que la clasificacion
// series apiladas: una serie por marca
$stackSeries = [];
foreach ($brandOrder as $brd) {
    $data = [];
    foreach ($pivotRows as $cls) {
        $data[] = $pivot[$cls][$brd] ?? 0;
    }
    $stackSeries[] = ["name" => $brd, "data" => $data];
}
// totales por fila y columna para la tabla
$rowTotals = [];
foreach ($pivotRows as $cls) {
    $t = 0; foreach ($brandOrder as $brd) { $t += $pivot[$cls][$brd] ?? 0; }
    $rowTotals[$cls] = $t;
}
$colTotals = [];
foreach ($brandOrder as $brd) {
    $t = 0; foreach ($pivotRows as $cls) { $t += $pivot[$cls][$brd] ?? 0; }
    $colTotals[$brd] = $t;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Dashboard de clientes | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>
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
                            <h4 class="mb-sm-0 font-size-18">Dashboard de clientes</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-clients-list.php">Clientes</a></li>
                                    <li class="breadcrumb-item active">Dashboard</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPIs -->
                <div class="row">
                    <?php
                        $kpis = [
                            ["Total de clientes", $totalClients, "bx-group", "primary"],
                            ["Activos", $activeClients, "bx-user-check", "success"],
                            ["Ciudades", $totalCiudades, "bx-map", "info"],
                            ["Marcas", $totalMarcas, "bx-purchase-tag", "warning"],
                        ];
                        foreach ($kpis as $k):
                    ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <span class="avatar-title rounded-circle bg-<?php echo $k[3]; ?>-subtle text-<?php echo $k[3]; ?> font-size-22">
                                            <i class="bx <?php echo $k[2]; ?>"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-muted mb-1"><?php echo $k[0]; ?></p>
                                        <h4 class="mb-0"><?php echo number_format($k[1]); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Fila 1: clasificacion (donut) + ciudad (barras horizontales) -->
                <div class="row">
                    <div class="col-xl-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Clientes por clasificacion</h5>
                                <div id="chart-classification"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Top ciudades</h5>
                                <div id="chart-city"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fila 2: marca (barras) + cruce clasificacion x marca (apiladas) -->
                <div class="row">
                    <div class="col-xl-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Clientes por marca</h5>
                                <div id="chart-brand"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Clasificacion por marca</h5>
                                <p class="text-muted small mb-3">Cruce de cada clasificacion segun la marca asignada.</p>
                                <div id="chart-cross"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla dinamica -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Tabla resumen: Clasificacion x Marca</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm text-center align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-start">Clasificacion</th>
                                                <?php foreach ($brandOrder as $brd): ?>
                                                    <th><?php echo htmlspecialchars($brd); ?></th>
                                                <?php endforeach; ?>
                                                <th class="table-light">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pivotRows as $cls): ?>
                                                <tr>
                                                    <td class="text-start fw-medium"><?php echo htmlspecialchars($cls); ?></td>
                                                    <?php foreach ($brandOrder as $brd): $v = $pivot[$cls][$brd] ?? 0; ?>
                                                        <td><?php echo $v > 0 ? $v : '<span class="text-muted">·</span>'; ?></td>
                                                    <?php endforeach; ?>
                                                    <td class="fw-bold"><?php echo $rowTotals[$cls]; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light fw-bold">
                                                <td class="text-start">Total general</td>
                                                <?php foreach ($brandOrder as $brd): ?>
                                                    <td><?php echo $colTotals[$brd]; ?></td>
                                                <?php endforeach; ?>
                                                <td><?php echo $totalClients; ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
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
<script src="assets/js/app.js"></script>

<script>
var PALETTE = <?php echo json_encode($palette); ?>;

// ---- Donut: clasificacion ----
new ApexCharts(document.querySelector("#chart-classification"), {
    series: <?php echo json_encode($classData); ?>,
    labels: <?php echo json_encode($classLabels); ?>,
    chart: { type: 'donut', height: 320 },
    colors: PALETTE,
    legend: { position: 'bottom' },
    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Total', formatter: function(){ return <?php echo $totalClients; ?>; } } } } } },
    dataLabels: { enabled: true, formatter: function(val){ return Math.round(val) + '%'; } },
    responsive: [{ breakpoint: 480, options: { legend: { position: 'bottom' } } }]
}).render();

// ---- Barras horizontales: ciudades ----
new ApexCharts(document.querySelector("#chart-city"), {
    series: [{ name: 'Clientes', data: <?php echo json_encode($cityData); ?> }],
    chart: { type: 'bar', height: 320, toolbar: { show: false } },
    colors: ['#50a5f1'],
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '65%', distributed: false } },
    dataLabels: { enabled: true },
    xaxis: { categories: <?php echo json_encode($cityLabels); ?> },
    grid: { borderColor: 'rgba(0,0,0,.08)' }
}).render();

// ---- Barras verticales: marca ----
new ApexCharts(document.querySelector("#chart-brand"), {
    series: [{ name: 'Clientes', data: <?php echo json_encode($brandData); ?> }],
    chart: { type: 'bar', height: 320, toolbar: { show: false } },
    colors: PALETTE,
    plotOptions: { bar: { borderRadius: 4, columnWidth: '50%', distributed: true } },
    dataLabels: { enabled: true },
    legend: { show: false },
    xaxis: { categories: <?php echo json_encode($brandLabels); ?> },
    grid: { borderColor: 'rgba(0,0,0,.08)' }
}).render();

// ---- Barras apiladas: clasificacion x marca ----
new ApexCharts(document.querySelector("#chart-cross"), {
    series: <?php echo json_encode($stackSeries); ?>,
    chart: { type: 'bar', height: 340, stacked: true, toolbar: { show: false } },
    colors: PALETTE,
    plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
    dataLabels: { enabled: false },
    xaxis: { categories: <?php echo json_encode($pivotRows); ?> },
    legend: { position: 'bottom' },
    grid: { borderColor: 'rgba(0,0,0,.08)' }
}).render();
</script>

</body>
</html>
