<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3]);

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

// Las devoluciones son registros de auditoria: una vez guardadas no se
// pueden editar ni eliminar, para evitar adulteraciones del historial.

$returns = mysqli_query($link, "SELECT r.id, r.delivery_id, r.return_date, r.reason,
                                        c.name AS client_name, k.name AS kit_name,
                                        COALESCE(u.full_name, u.username) AS registered_by_name
                                 FROM returns r
                                 JOIN clients c ON c.id = r.client_id
                                 JOIN kit_deliveries d ON d.id = r.delivery_id
                                 JOIN kits k ON k.id = d.kit_id
                                 LEFT JOIN users u ON u.id = r.registered_by
                                 ORDER BY r.id DESC");

$itemsByReturn = [];
$res = mysqli_query($link, "SELECT ri.return_id, a.name AS article_name, ri.quantity, a.unit
                             FROM return_items ri
                             JOIN articles a ON a.id = ri.article_id
                             ORDER BY ri.return_id, a.name");
while ($row = mysqli_fetch_assoc($res)) {
    $itemsByReturn[(int) $row["return_id"]][] = $row;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Devoluciones | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Devoluciones</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Devoluciones</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

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
                                    <h5 class="card-title mb-0">Historial de devoluciones</h5>
                                    <a href="admin-return-form.php" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-plus me-1"></i> Registrar devolucion
                                    </a>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Fecha</th>
                                                <th>Cliente</th>
                                                <th>Entrega origen</th>
                                                <th>Articulos devueltos</th>
                                                <th>Motivo</th>
                                                <th>Registrado por</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($r = mysqli_fetch_assoc($returns)): ?>
                                                <tr>
                                                    <td><?php echo (int) $r["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($r["return_date"]); ?></td>
                                                    <td><?php echo htmlspecialchars($r["client_name"]); ?></td>
                                                    <td>
                                                        <a href="admin-delivery-print.php?id=<?php echo (int) $r["delivery_id"]; ?>" target="_blank">
                                                            #<?php echo (int) $r["delivery_id"]; ?> — <?php echo htmlspecialchars($r["kit_name"]); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php foreach (($itemsByReturn[(int) $r["id"]] ?? []) as $it): ?>
                                                            <div><?php echo htmlspecialchars($it["article_name"]) . ": " . format_qty($it["quantity"]) . " " . htmlspecialchars($it["unit"]); ?></div>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td><?php echo nl2br(htmlspecialchars($r["reason"])); ?></td>
                                                    <td><?php echo htmlspecialchars($r["registered_by_name"] ?? "—"); ?></td>
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
