<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]); // Todos los roles pueden ver y registrar compras

$can_delete = in_array((int) $_SESSION["role_id"], [1, 2], true);

$success_msg = "";
$error_msg = "";

if (isset($_SESSION["flash_success"])) {
    $success_msg = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}
if (isset($_SESSION["flash_error"])) {
    $error_msg = $_SESSION["flash_error"];
    unset($_SESSION["flash_error"]);
}

// ---------------------------------------------------------------
// ELIMINAR factura
// ---------------------------------------------------------------
if ($can_delete && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $invoice_id = (int) ($_POST["id"] ?? 0);
    if ($invoice_id > 0) {
        mysqli_begin_transaction($link);
        try {
            $delMov = mysqli_prepare($link, "DELETE FROM stock_movements WHERE reference_type = 'compra' AND reference_id = ?");
            mysqli_stmt_bind_param($delMov, "i", $invoice_id);
            mysqli_stmt_execute($delMov);

            $stmt = mysqli_prepare($link, "DELETE FROM purchase_invoices WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $invoice_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_error($link));
            }

            mysqli_commit($link);
            $success_msg = "Factura eliminada correctamente.";
        } catch (Exception $e) {
            mysqli_rollback($link);
            $error_msg = "No se pudo eliminar la factura: " . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------
// Filtros simples
// ---------------------------------------------------------------
$filter_provider = isset($_GET["provider_id"]) ? (int) $_GET["provider_id"] : 0;
$filter_article  = isset($_GET["article_id"]) ? (int) $_GET["article_id"] : 0;

$where = [];
$params = [];
$types = "";

if ($filter_provider > 0) {
    $where[] = "i.provider_id = ?";
    $params[] = $filter_provider;
    $types .= "i";
}

$joinArticle = "";
if ($filter_article > 0) {
    $joinArticle = "JOIN purchase_invoice_items fi ON fi.invoice_id = i.id AND fi.article_id = ?";
    // prepend article filter param (must come first because it appears first in SQL)
}

$sql = "SELECT DISTINCT i.id, i.invoice_number, i.purchase_date, i.currency, i.total_amount, i.notes, i.created_at,
                p.name AS provider_name, u.username AS registered_by_name
         FROM purchase_invoices i
         JOIN providers p ON p.id = i.provider_id
         LEFT JOIN users u ON u.id = i.registered_by
         $joinArticle";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY i.purchase_date DESC, i.id DESC";

if ($filter_article > 0) {
    $finalParams = array_merge([$filter_article], $params);
    $finalTypes = "i" . $types;
} else {
    $finalParams = $params;
    $finalTypes = $types;
}

if ($finalParams) {
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, $finalTypes, ...$finalParams);
    mysqli_stmt_execute($stmt);
    $invoices = mysqli_stmt_get_result($stmt);
} else {
    $invoices = mysqli_query($link, $sql);
}

$providers = mysqli_query($link, "SELECT id, name FROM providers ORDER BY name");
$articles_filter = mysqli_query($link, "SELECT id, name FROM articles ORDER BY name");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Compras | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>

<!-- Begin page -->
<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>

    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18">Compras a proveedores</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Compras</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end page title -->

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Facturas de compra</h5>
                                    <a href="admin-purchase-form.php" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-plus me-1"></i> Nueva factura
                                    </a>
                                </div>

                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-auto">
                                        <select name="provider_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todos los proveedores</option>
                                            <?php mysqli_data_seek($providers, 0); while ($p = mysqli_fetch_assoc($providers)): ?>
                                                <option value="<?php echo (int) $p["id"]; ?>" <?php echo $filter_provider == $p["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($p["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select name="article_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todos los articulos</option>
                                            <?php mysqli_data_seek($articles_filter, 0); while ($a = mysqli_fetch_assoc($articles_filter)): ?>
                                                <option value="<?php echo (int) $a["id"]; ?>" <?php echo $filter_article == $a["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($a["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <?php if ($filter_provider || $filter_article): ?>
                                        <div class="col-auto">
                                            <a href="admin-purchases-list.php" class="btn btn-light">Limpiar filtros</a>
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Factura</th>
                                                <th>Proveedor</th>
                                                <th>Fecha</th>
                                                <th>Total</th>
                                                <th>Registrado por</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($inv = mysqli_fetch_assoc($invoices)): ?>
                                                <tr>
                                                    <td><?php echo (int) $inv["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($inv["invoice_number"]); ?></td>
                                                    <td><?php echo htmlspecialchars($inv["provider_name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($inv["purchase_date"]); ?></td>
                                                    <td><?php echo htmlspecialchars($inv["currency"]); ?> <?php echo number_format((float) $inv["total_amount"], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($inv["registered_by_name"] ?? "-"); ?></td>
                                                    <td class="text-end">
                                                        <a href="admin-purchase-form.php?id=<?php echo (int) $inv["id"]; ?>" class="btn btn-sm btn-soft-primary">
                                                            <i class="mdi mdi-pencil"></i>
                                                        </a>
                                                        <?php if ($can_delete): ?>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta factura y sus detalles?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo (int) $inv["id"]; ?>">
                                                                <button type="submit" class="btn btn-sm btn-soft-danger">
                                                                    <i class="mdi mdi-delete"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
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

<!-- Right Sidebar -->
<?php include 'layouts/right-sidebar.php'; ?>

<!-- JAVASCRIPT -->
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

</body>

</html>
