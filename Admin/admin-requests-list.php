<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 4]);

$is_solicitante = (int) $_SESSION["role_id"] === 4;
$can_dispatch   = !$is_solicitante;

$success_msg = "";
if (isset($_SESSION["flash_success"])) {
    $success_msg = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}

// Las solicitudes son registros de auditoria: una vez guardadas no se
// pueden editar ni eliminar. Solo se puede marcar como despachada.
if ($can_dispatch && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "dispatch") {
    $request_id = (int) ($_POST["id"] ?? 0);
    if ($request_id > 0) {
        $stmt = mysqli_prepare($link, "UPDATE requests SET status = 'despachado' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $_SESSION["flash_success"] = "Solicitud marcada como despachada.";
        header("location: admin-requests-list.php");
        exit;
    }
}

$sql = "SELECT r.id, r.request_date, r.notes, r.status, COALESCE(u.full_name, u.username) AS requested_by_name
        FROM requests r
        LEFT JOIN users u ON u.id = r.requested_by";
if ($is_solicitante) {
    $sql .= " WHERE r.requested_by = " . (int) $_SESSION["id"];
}
$sql .= " ORDER BY r.id DESC";
$requests = mysqli_query($link, $sql);

$itemsByRequest = [];
$res = mysqli_query($link, "SELECT ri.request_id, ri.item_type, ri.quantity,
                                    a.name AS article_name, a.unit AS article_unit,
                                    k.name AS kit_name
                             FROM request_items ri
                             LEFT JOIN articles a ON a.id = ri.article_id
                             LEFT JOIN kits k ON k.id = ri.kit_id
                             ORDER BY ri.request_id");
while ($row = mysqli_fetch_assoc($res)) {
    $itemsByRequest[(int) $row["request_id"]][] = $row;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Solicitudes | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18"><?php echo $is_solicitante ? "Mis solicitudes" : "Solicitudes"; ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="<?php echo $is_solicitante ? 'admin-requests-list.php' : 'index.php'; ?>">Detallia</a></li>
                                    <li class="breadcrumb-item active">Solicitudes</li>
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

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Historial de solicitudes</h5>
                                    <a href="admin-request-form.php" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-plus me-1"></i> Nueva solicitud
                                    </a>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Fecha</th>
                                                <?php if (!$is_solicitante): ?><th>Solicitado por</th><?php endif; ?>
                                                <th>Articulos / Kits</th>
                                                <th>Notas</th>
                                                <th>Estado</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($r = mysqli_fetch_assoc($requests)): ?>
                                                <tr>
                                                    <td><?php echo (int) $r["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($r["request_date"]))); ?></td>
                                                    <?php if (!$is_solicitante): ?><td><?php echo htmlspecialchars($r["requested_by_name"] ?? "—"); ?></td><?php endif; ?>
                                                    <td>
                                                        <?php foreach (($itemsByRequest[(int) $r["id"]] ?? []) as $it): ?>
                                                            <div>
                                                                <?php if ($it["item_type"] === "kit"): ?>
                                                                    <span class="badge bg-success-subtle text-success me-1">Kit</span> <?php echo htmlspecialchars($it["kit_name"]); ?> (<?php echo format_qty($it["quantity"]); ?>)
                                                                <?php else: ?>
                                                                    <span class="badge bg-info-subtle text-info me-1">Articulo</span> <?php echo htmlspecialchars($it["article_name"]); ?> (<?php echo format_qty($it["quantity"]) . " " . htmlspecialchars($it["article_unit"]); ?>)
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </td>
                                                    <td><?php echo nl2br(htmlspecialchars($r["notes"])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $r["status"] === "despachado" ? "success" : "warning"; ?>">
                                                            <?php echo $r["status"] === "despachado" ? "Despachado" : "Pendiente"; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="admin-request-print.php?id=<?php echo (int) $r["id"]; ?>" target="_blank" class="btn btn-sm btn-soft-secondary">
                                                            <i class="mdi mdi-printer"></i>
                                                        </a>
                                                        <?php if ($can_dispatch && $r["status"] !== "despachado"): ?>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Marcar esta solicitud como despachada?');">
                                                                <input type="hidden" name="action" value="dispatch">
                                                                <input type="hidden" name="id" value="<?php echo (int) $r["id"]; ?>">
                                                                <button type="submit" class="btn btn-sm btn-soft-success">
                                                                    <i class="mdi mdi-check-bold"></i>
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

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

</body>

</html>
