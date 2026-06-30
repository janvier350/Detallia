<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]);

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2], true);

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
// ELIMINAR kit
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $kit_id = (int) ($_POST["id"] ?? 0);
    if ($kit_id > 0) {
        $sql  = "DELETE FROM kits WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $kit_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Kit eliminado correctamente.";
        } else {
            if (mysqli_errno($link) == 1451) {
                $error_msg = "No se puede eliminar: este kit tiene entregas registradas.";
            } else {
                $error_msg = "No se pudo eliminar el kit: " . mysqli_error($link);
            }
        }
    }
}

$kits = mysqli_query($link, "SELECT k.id, k.name, k.description, k.status,
                                      b.name AS brand_name, mt.name AS management_type_name, cc.name AS classification_name,
                                      (SELECT COUNT(*) FROM kit_items ki WHERE ki.kit_id = k.id) AS item_count
                               FROM kits k
                               LEFT JOIN brands b ON b.id = k.brand_id
                               LEFT JOIN management_types mt ON mt.id = k.management_type_id
                               LEFT JOIN client_classifications cc ON cc.id = k.classification_id
                               ORDER BY k.name ASC");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Kits | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Kits</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Kits</li>
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
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="card-title mb-0">Plantillas de kit</h5>
                                    <?php if ($can_edit): ?>
                                        <a href="admin-kit-form.php" class="btn btn-primary waves-effect waves-light">
                                            <i class="mdi mdi-plus me-1"></i> Nuevo kit
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th>Marca</th>
                                                <th>Tipo de gestion</th>
                                                <th>Clasificacion</th>
                                                <th>Articulos</th>
                                                <th>Estado</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($k = mysqli_fetch_assoc($kits)): ?>
                                                <tr>
                                                    <td><?php echo (int) $k["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($k["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($k["brand_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($k["management_type_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($k["classification_name"] ?? "—"); ?></td>
                                                    <td><span class="badge bg-info"><?php echo (int) $k["item_count"]; ?></span></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $k["status"] === 'activo' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($k["status"]); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="admin-kit-form.php?id=<?php echo (int) $k["id"]; ?>" class="btn btn-sm btn-soft-primary">
                                                            <i class="mdi mdi-pencil"></i>
                                                        </a>
                                                        <?php if ($can_edit): ?>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este kit y sus articulos?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo (int) $k["id"]; ?>">
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

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

</body>

</html>
