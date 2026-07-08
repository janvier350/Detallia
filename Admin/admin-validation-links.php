<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2]);

$success_msg = "";

// ---------------------------------------------------------------
// Activar / desactivar enlace
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "toggle") {
    $link_id = (int) ($_POST["id"] ?? 0);
    $active  = (int) ($_POST["active"] ?? 0) ? 1 : 0;
    if ($link_id > 0) {
        $stmt = mysqli_prepare($link, "UPDATE validation_links SET active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $active, $link_id);
        mysqli_stmt_execute($stmt);
        $_SESSION["flash_success"] = $active ? "Enlace activado." : "Enlace desactivado.";
        header("location: admin-validation-links.php");
        exit;
    }
}

if (isset($_SESSION["flash_success"])) {
    $success_msg = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}

$links = mysqli_query($link, "SELECT vl.id, vl.token, vl.label, vl.active, vl.created_at,
                                      COALESCE(u.full_name, u.username) AS created_by_name,
                                      SUM(CASE WHEN pc.status = 'pendiente' THEN 1 ELSE 0 END) AS total_pendiente,
                                      SUM(CASE WHEN pc.status = 'confirmado' THEN 1 ELSE 0 END) AS total_confirmado,
                                      SUM(CASE WHEN pc.status = 'rechazado' THEN 1 ELSE 0 END) AS total_rechazado,
                                      COUNT(pc.id) AS total_rows
                               FROM validation_links vl
                               LEFT JOIN users u ON u.id = vl.created_by
                               LEFT JOIN pending_clients pc ON pc.link_id = vl.id
                               GROUP BY vl.id
                               ORDER BY vl.id DESC");

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$baseUrl = $scheme . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Enlaces de validacion | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Enlaces de validacion de contactos</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-clients-list.php">Clientes</a></li>
                                    <li class="breadcrumb-item active">Enlaces de validacion</li>
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
                                    <h5 class="card-title mb-0">Lotes generados</h5>
                                    <a href="admin-clients-import.php" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-plus me-1"></i> Nueva importacion
                                    </a>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Lote</th>
                                                <th>Creado por</th>
                                                <th>Fecha</th>
                                                <th>Pendientes</th>
                                                <th>Confirmados</th>
                                                <th>Rechazados</th>
                                                <th>Estado</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($l = mysqli_fetch_assoc($links)): ?>
                                                <?php $validateUrl = $baseUrl . "/validate.php?token=" . $l["token"]; ?>
                                                <tr>
                                                    <td><?php echo (int) $l["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($l["label"]); ?></td>
                                                    <td><?php echo htmlspecialchars($l["created_by_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($l["created_at"]))); ?></td>
                                                    <td><span class="badge bg-warning"><?php echo (int) $l["total_pendiente"]; ?></span></td>
                                                    <td><span class="badge bg-success"><?php echo (int) $l["total_confirmado"]; ?></span></td>
                                                    <td><span class="badge bg-danger"><?php echo (int) $l["total_rechazado"]; ?></span></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $l["active"] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $l["active"] ? "Activo" : "Inactivo"; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-soft-secondary" title="Copiar enlace"
                                                            onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($validateUrl, ENT_QUOTES); ?>'); this.innerHTML='<i class=\'mdi mdi-check\'></i>';">
                                                            <i class="mdi mdi-content-copy"></i>
                                                        </button>
                                                        <a href="admin-pending-review.php?link_id=<?php echo (int) $l['id']; ?>" class="btn btn-sm btn-soft-primary" title="Revisar">
                                                            <i class="mdi mdi-eye-outline"></i>
                                                        </a>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="id" value="<?php echo (int) $l['id']; ?>">
                                                            <input type="hidden" name="active" value="<?php echo $l["active"] ? 0 : 1; ?>">
                                                            <button type="submit" class="btn btn-sm btn-soft-<?php echo $l["active"] ? 'danger' : 'success'; ?>" title="<?php echo $l["active"] ? 'Desactivar' : 'Activar'; ?>">
                                                                <i class="mdi mdi-power"></i>
                                                            </button>
                                                        </form>
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
