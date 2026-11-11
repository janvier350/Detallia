<?php
// =====================================================================
// INFORME (solo lectura) - Inventario pendiente de descontar por
// solicitudes ya despachadas antes de que el despacho moviera stock.
//
// Esta pantalla NO modifica nada: solo calcula y muestra el impacto.
// El ajuste real se hace, si se aprueba, en una accion aparte.
// =====================================================================
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2]); // Administrador y Jefe

// Filtro opcional por fecha de solicitud (desde)
$desde = trim($_GET["desde"] ?? "");
$fechaCond = "";
if ($desde !== "" && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $fechaCond = " AND r.request_date >= '" . mysqli_real_escape_string($link, $desde) . " 00:00:00'";
}

// Solicitudes despachadas que AUN no tienen su movimiento de inventario
// (no existe stock_movements con reference_type='solicitud' y ese request id).
$sql = "SELECT r.id, r.request_date,
               COALESCE(u.full_name, u.username) AS solicitante
        FROM requests r
        LEFT JOIN users u ON u.id = r.requested_by
        WHERE r.status = 'despachado' $fechaCond
          AND NOT EXISTS (
              SELECT 1 FROM stock_movements sm
              WHERE sm.reference_type = 'solicitud' AND sm.reference_id = r.id
          )
        ORDER BY r.id";
$reqs = mysqli_query($link, $sql);

$pendingRequests = [];
$reqIds = [];
while ($row = mysqli_fetch_assoc($reqs)) {
    $pendingRequests[(int) $row["id"]] = $row;
    $reqIds[] = (int) $row["id"];
}

// Acumular deducciones por articulo y el detalle por solicitud.
$deductByArticle = [];   // article_id => total a descontar
$deductDetail    = [];   // request_id => [ [article_id, article_name, qty], ... ]

if ($reqIds) {
    $idList = implode(",", $reqIds);

    // 1) Items de tipo articulo (descuento directo)
    $res = mysqli_query($link, "SELECT ri.request_id, ri.article_id, a.name AS article_name, ri.quantity
                                FROM request_items ri
                                JOIN articles a ON a.id = ri.article_id
                                WHERE ri.item_type = 'articulo' AND ri.request_id IN ($idList)");
    while ($r = mysqli_fetch_assoc($res)) {
        $aid = (int) $r["article_id"];
        $qty = (float) $r["quantity"];
        $deductByArticle[$aid] = ($deductByArticle[$aid] ?? 0) + $qty;
        $deductDetail[(int) $r["request_id"]][] = [$aid, $r["article_name"], $qty];
    }

    // 2) Items de tipo kit (expandir articulos del kit * cantidad solicitada)
    $res = mysqli_query($link, "SELECT ri.request_id, ki.article_id, a.name AS article_name,
                                       (ki.quantity * ri.quantity) AS qty
                                FROM request_items ri
                                JOIN kit_items ki ON ki.kit_id = ri.kit_id
                                JOIN articles a ON a.id = ki.article_id
                                WHERE ri.item_type = 'kit' AND ri.request_id IN ($idList)");
    while ($r = mysqli_fetch_assoc($res)) {
        $aid = (int) $r["article_id"];
        $qty = (float) $r["qty"];
        $deductByArticle[$aid] = ($deductByArticle[$aid] ?? 0) + $qty;
        $deductDetail[(int) $r["request_id"]][] = [$aid, $r["article_name"], $qty];
    }
}

// Stock actual por articulo afectado
$stockNow = [];
$articleNames = [];
if ($deductByArticle) {
    $idList = implode(",", array_map('intval', array_keys($deductByArticle)));
    $res = mysqli_query($link, "SELECT a.id, a.name,
                                       COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.article_id = a.id), 0) AS stock
                                FROM articles a WHERE a.id IN ($idList)");
    while ($r = mysqli_fetch_assoc($res)) {
        $stockNow[(int) $r["id"]] = (float) $r["stock"];
        $articleNames[(int) $r["id"]] = $r["name"];
    }
}

// Ordenar el resumen por nombre de articulo
uksort($deductByArticle, function ($a, $b) use ($articleNames) {
    return strcasecmp($articleNames[$a] ?? '', $articleNames[$b] ?? '');
});

$totalUnidades = array_sum($deductByArticle);
$negativos = 0;
foreach ($deductByArticle as $aid => $tot) {
    if (($stockNow[$aid] ?? 0) - $tot < 0) $negativos++;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Informe de inventario por solicitudes | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Informe: inventario pendiente por solicitudes</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Informe de ajuste</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning" role="alert">
                    <i class="mdi mdi-alert-outline me-1"></i>
                    <strong>Solo informe.</strong> Esta pantalla no modifica el inventario. Muestra cuánto se
                    descontaría por las solicitudes <strong>ya despachadas</strong> que nunca movieron stock
                    (antes del cambio). Revisa los números; el ajuste real se aplica en un paso aparte.
                </div>

                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-sm-3">
                        <label class="form-label mb-1">Considerar desde (fecha de solicitud)</label>
                        <input type="date" name="desde" value="<?php echo htmlspecialchars($desde); ?>" class="form-control">
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-filter-variant me-1"></i>Aplicar</button>
                        <a href="admin-requests-stock-audit.php" class="btn btn-soft-secondary">Todas</a>
                    </div>
                </form>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card"><div class="card-body text-center">
                            <h3 class="mb-0"><?php echo count($pendingRequests); ?></h3>
                            <span class="text-muted">Solicitudes por ajustar</span>
                        </div></div>
                    </div>
                    <div class="col-md-4">
                        <div class="card"><div class="card-body text-center">
                            <h3 class="mb-0"><?php echo format_qty($totalUnidades); ?></h3>
                            <span class="text-muted">Unidades totales a descontar</span>
                        </div></div>
                    </div>
                    <div class="col-md-4">
                        <div class="card"><div class="card-body text-center">
                            <h3 class="mb-0 <?php echo $negativos ? 'text-danger' : ''; ?>"><?php echo $negativos; ?></h3>
                            <span class="text-muted">Artículos que quedarían en negativo</span>
                        </div></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Resumen por artículo</h5>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Artículo</th>
                                        <th class="text-end">Stock actual</th>
                                        <th class="text-end">A descontar</th>
                                        <th class="text-end">Stock resultante</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($deductByArticle): ?>
                                        <?php foreach ($deductByArticle as $aid => $tot):
                                            $now = $stockNow[$aid] ?? 0;
                                            $after = $now - $tot; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($articleNames[$aid] ?? ("#" . $aid)); ?></td>
                                                <td class="text-end"><?php echo format_qty($now); ?></td>
                                                <td class="text-end text-danger">-<?php echo format_qty($tot); ?></td>
                                                <td class="text-end <?php echo $after < 0 ? 'text-danger fw-bold' : 'fw-bold'; ?>">
                                                    <?php echo format_qty($after); ?>
                                                    <?php if ($after < 0): ?><i class="mdi mdi-alert" title="Quedaria en negativo"></i><?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">
                                            No hay solicitudes despachadas pendientes de descontar. Todo está al día.
                                        </td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php if ($pendingRequests): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Detalle por solicitud</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr><th>Solicitud</th><th>Fecha</th><th>Solicitante</th><th>Artículos a descontar</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $rid => $rq): ?>
                                        <tr>
                                            <td>#<?php echo $rid; ?></td>
                                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($rq["request_date"]))); ?></td>
                                            <td><?php echo htmlspecialchars($rq["solicitante"] ?? "—"); ?></td>
                                            <td>
                                                <?php foreach (($deductDetail[$rid] ?? []) as $d): ?>
                                                    <div><?php echo htmlspecialchars($d[1]); ?>
                                                        <span class="badge bg-danger-subtle text-danger">-<?php echo format_qty($d[2]); ?></span></div>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
