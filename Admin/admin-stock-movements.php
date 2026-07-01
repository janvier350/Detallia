<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3]);

// ---------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------
$filter_article = isset($_GET["article_id"]) ? (int) $_GET["article_id"] : 0;
$filter_type    = isset($_GET["movement_type"]) ? trim($_GET["movement_type"]) : "";

$where  = [];
$params = [];
$types  = "";

if ($filter_article > 0) {
    $where[]  = "sm.article_id = ?";
    $params[] = $filter_article;
    $types   .= "i";
}
if (in_array($filter_type, ["compra", "entrega", "ajuste"], true)) {
    $where[]  = "sm.movement_type = ?";
    $params[] = $filter_type;
    $types   .= "s";
}

$sql = "SELECT sm.id, sm.movement_type, sm.quantity, sm.reference_type, sm.reference_id, sm.notes, sm.created_at,
               a.name AS article_name, a.unit,
               COALESCE(u.full_name, u.username) AS created_by_name
        FROM stock_movements sm
        JOIN articles a ON a.id = sm.article_id
        LEFT JOIN users u ON u.id = sm.created_by";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY sm.created_at DESC, sm.id DESC";

if ($params) {
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $movements = mysqli_stmt_get_result($stmt);
} else {
    $movements = mysqli_query($link, $sql);
}

$articles_filter = mysqli_query($link, "SELECT id, name FROM articles ORDER BY name");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Movimientos de inventario | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Movimientos de inventario</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Inventario</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Historial de entradas y salidas</h5>
                                    <a href="admin-articles-list.php" class="btn btn-soft-primary waves-effect waves-light">
                                        <i class="mdi mdi-package-variant me-1"></i> Ver catalogo de articulos
                                    </a>
                                </div>

                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-auto">
                                        <select name="article_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todos los articulos</option>
                                            <?php while ($af = mysqli_fetch_assoc($articles_filter)): ?>
                                                <option value="<?php echo (int) $af["id"]; ?>" <?php echo $filter_article == $af["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($af["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select name="movement_type" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todos los tipos</option>
                                            <option value="compra" <?php echo $filter_type === "compra" ? "selected" : ""; ?>>Compra (entrada)</option>
                                            <option value="entrega" <?php echo $filter_type === "entrega" ? "selected" : ""; ?>>Entrega (salida)</option>
                                            <option value="ajuste" <?php echo $filter_type === "ajuste" ? "selected" : ""; ?>>Ajuste</option>
                                        </select>
                                    </div>
                                    <?php if ($filter_article || $filter_type): ?>
                                        <div class="col-auto">
                                            <a href="admin-stock-movements.php" class="btn btn-light">Limpiar filtros</a>
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Articulo</th>
                                                <th>Tipo</th>
                                                <th>Cantidad</th>
                                                <th>Origen</th>
                                                <th>Registrado por</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($m = mysqli_fetch_assoc($movements)): ?>
                                                <?php
                                                    $qty = (float) $m["quantity"];
                                                    $badgeClass = $qty >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                                    $icon = $qty >= 0 ? 'mdi-arrow-down-bold' : 'mdi-arrow-up-bold';
                                                    $originLabel = [
                                                        'compra' => 'Compra #' . (int) $m["reference_id"],
                                                        'entrega' => 'Entrega #' . (int) $m["reference_id"],
                                                        'ajuste' => 'Ajuste manual',
                                                    ][$m["movement_type"]] ?? '—';
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($m["created_at"]))); ?></td>
                                                    <td><?php echo htmlspecialchars($m["article_name"]); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $badgeClass; ?>">
                                                            <i class="mdi <?php echo $icon; ?> me-1"></i>
                                                            <?php echo ucfirst(htmlspecialchars($m["movement_type"])); ?>
                                                        </span>
                                                    </td>
                                                    <td class="<?php echo $qty >= 0 ? 'text-success' : 'text-danger'; ?> fw-medium">
                                                        <?php echo ($qty >= 0 ? '+' : '') . format_qty($qty) . ' ' . htmlspecialchars($m["unit"]); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($originLabel); ?></td>
                                                    <td><?php echo htmlspecialchars($m["created_by_name"] ?? "—"); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
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
<script src="assets/js/app.js"></script>

</body>

</html>
