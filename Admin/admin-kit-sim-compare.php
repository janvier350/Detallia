<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);

// ids=1,2,3
$idsRaw = trim($_GET["ids"] ?? "");
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsRaw)), function ($v) { return $v > 0; })));
$ids = array_slice($ids, 0, 6); // maximo 6 columnas

if (count($ids) < 2) {
    $_SESSION["flash_success"] = "Selecciona al menos 2 simulacros para comparar.";
    header("location: admin-kit-sim-list.php");
    exit;
}

$idList = implode(",", $ids);

// Cabeceras + totales
$sims = [];
$res = mysqli_query($link, "SELECT s.id, s.name, s.sim_date, cl.name AS classification_name,
                                   COUNT(i.id) AS num_items,
                                   COALESCE(SUM(i.unit_price * i.quantity), 0) AS subtotal,
                                   COALESCE(SUM(i.unit_price * i.quantity * (i.iva_rate/100)), 0) AS iva,
                                   COALESCE(SUM(i.unit_price * i.quantity * (1 + i.iva_rate/100)), 0) AS total
                            FROM kit_simulations s
                            LEFT JOIN client_classifications cl ON cl.id = s.classification_id
                            LEFT JOIN kit_simulation_items i ON i.simulation_id = s.id
                            WHERE s.id IN ($idList)
                            GROUP BY s.id");
while ($r = mysqli_fetch_assoc($res)) { $sims[(int) $r["id"]] = $r; }

// Reordenar segun el orden solicitado
$ordered = [];
foreach ($ids as $id) { if (isset($sims[$id])) $ordered[] = $sims[$id]; }

// Items por simulacro
$itemsBySim = [];
$ri = mysqli_query($link, "SELECT * FROM kit_simulation_items WHERE simulation_id IN ($idList) ORDER BY sort_order, id");
while ($it = mysqli_fetch_assoc($ri)) {
    $itemsBySim[(int) $it["simulation_id"]][] = $it;
}

// Datos para el grafico
$chartLabels = array_map(function ($s) { return $s["name"]; }, $ordered);
$chartTotals = array_map(function ($s) { return round((float) $s["total"], 2); }, $ordered);

// Referencia: total mas bajo / mas alto
$totalsOnly = array_map(function ($s) { return (float) $s["total"]; }, $ordered);
$minTotal = !empty($totalsOnly) ? min($totalsOnly) : 0;
$maxTotal = !empty($totalsOnly) ? max($totalsOnly) : 0;
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Comparar simulacros | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Comparativa de simulacros</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-kit-sim-list.php">Simulador de kits</a></li>
                                    <li class="breadcrumb-item active">Comparar</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Resumen</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Concepto</th>
                                                <?php foreach ($ordered as $s): ?>
                                                    <th><?php echo htmlspecialchars($s["name"]); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="text-muted">Fecha</td>
                                                <?php foreach ($ordered as $s): ?><td><?php echo htmlspecialchars(date("d/m/Y", strtotime($s["sim_date"]))); ?></td><?php endforeach; ?>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Tipo de cliente</td>
                                                <?php foreach ($ordered as $s): ?><td><?php echo $s["classification_name"] ? '<span class="badge bg-primary-subtle text-primary">' . htmlspecialchars($s["classification_name"]) . '</span>' : '<span class="text-muted">—</span>'; ?></td><?php endforeach; ?>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Productos</td>
                                                <?php foreach ($ordered as $s): ?><td><?php echo (int) $s["num_items"]; ?></td><?php endforeach; ?>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Subtotal</td>
                                                <?php foreach ($ordered as $s): ?><td>$<?php echo number_format($s["subtotal"], 2); ?></td><?php endforeach; ?>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">IVA</td>
                                                <?php foreach ($ordered as $s): ?><td>$<?php echo number_format($s["iva"], 2); ?></td><?php endforeach; ?>
                                            </tr>
                                            <tr class="table-light">
                                                <td class="fw-bold">Total</td>
                                                <?php foreach ($ordered as $s): ?>
                                                    <td class="fw-bold">
                                                        $<?php echo number_format($s["total"], 2); ?>
                                                        <?php if ((float) $s["total"] == $minTotal && $minTotal != $maxTotal): ?>
                                                            <span class="badge bg-success ms-1">Mas economico</span>
                                                        <?php elseif ((float) $s["total"] == $maxTotal && $minTotal != $maxTotal): ?>
                                                            <span class="badge bg-danger ms-1">Mas caro</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Diferencia vs. mas economico</td>
                                                <?php foreach ($ordered as $s): $diff = (float) $s["total"] - $minTotal; ?>
                                                    <td><?php echo $diff > 0 ? '<span class="text-danger">+$' . number_format($diff, 2) . '</span>' : '<span class="text-success">—</span>'; ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grafico -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Total por simulacro</h5>
                                <div id="compareChart"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle por columna -->
                <div class="row">
                    <?php
                        $colClass = count($ordered) >= 3 ? "col-xl-4 col-md-6" : "col-md-6";
                        foreach ($ordered as $s):
                            $its = $itemsBySim[(int) $s["id"]] ?? [];
                    ?>
                    <div class="<?php echo $colClass; ?>">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($s["name"]); ?></h5>
                                    <a href="admin-kit-sim-print.php?id=<?php echo (int) $s['id']; ?>" target="_blank" class="btn btn-sm btn-soft-secondary"><i class="mdi mdi-printer"></i></a>
                                </div>
                                <p class="text-muted small mb-3">
                                    <?php echo htmlspecialchars(date("d/m/Y", strtotime($s["sim_date"]))); ?>
                                    <?php if ($s["classification_name"]): ?> · <?php echo htmlspecialchars($s["classification_name"]); ?><?php endif; ?>
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">Total</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($its)): ?>
                                                <tr><td colspan="3" class="text-muted text-center">Sin productos</td></tr>
                                            <?php else: foreach ($its as $it): $tot = $it["unit_price"] * $it["quantity"] * (1 + $it["iva_rate"] / 100); ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($it["product_name"]); ?></td>
                                                    <td class="text-end"><?php echo rtrim(rtrim(number_format($it["quantity"], 2), '0'), '.'); ?></td>
                                                    <td class="text-end">$<?php echo number_format($tot, 2); ?></td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-light"><th colspan="2">Total</th><th class="text-end">$<?php echo number_format($s["total"], 2); ?></th></tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-4">
                    <a href="admin-kit-sim-list.php" class="btn btn-light"><i class="mdi mdi-arrow-left me-1"></i> Volver al listado</a>
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
new ApexCharts(document.querySelector("#compareChart"), {
    series: [{ name: 'Total', data: <?php echo json_encode($chartTotals); ?> }],
    chart: { type: 'bar', height: 320, toolbar: { show: false } },
    colors: ['#556ee6'],
    plotOptions: { bar: { borderRadius: 4, columnWidth: '45%', distributed: true, dataLabels: { position: 'top' } } },
    dataLabels: { enabled: true, formatter: function (v) { return '$' + v.toFixed(2); }, offsetY: -20, style: { colors: ['#495057'] } },
    legend: { show: false },
    xaxis: { categories: <?php echo json_encode($chartLabels); ?> },
    yaxis: { labels: { formatter: function (v) { return '$' + v.toFixed(0); } } },
    grid: { borderColor: 'rgba(0,0,0,.08)' }
}).render();
</script>

</body>
</html>
