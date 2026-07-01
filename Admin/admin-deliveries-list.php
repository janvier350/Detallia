<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
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

// Las entregas son registros de auditoria: una vez guardadas no se pueden
// editar ni eliminar, para evitar adulteraciones del historial de entregas.

// ---------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------
$filter_client = isset($_GET["client_id"]) ? (int) $_GET["client_id"] : 0;
$filter_kit    = isset($_GET["kit_id"]) ? (int) $_GET["kit_id"] : 0;

$where  = [];
$params = [];
$types  = "";

if ($filter_client > 0) {
    $where[]  = "d.client_id = ?";
    $params[] = $filter_client;
    $types   .= "i";
}
if ($filter_kit > 0) {
    $where[]  = "d.kit_id = ?";
    $params[] = $filter_kit;
    $types   .= "i";
}

$sql = "SELECT d.id, d.delivery_date, d.notes,
               k.name AS kit_name, c.name AS client_name,
               b.name AS brand_name, cc.name AS classification_name,
               mt.name AS management_type_name,
               COALESCE(u.full_name, u.username) AS delivered_by_name
        FROM kit_deliveries d
        JOIN kits k ON k.id = d.kit_id
        JOIN clients c ON c.id = d.client_id
        LEFT JOIN brands b ON b.id = k.brand_id
        LEFT JOIN client_classifications cc ON cc.id = k.classification_id
        LEFT JOIN management_types mt ON mt.id = d.management_type_id
        LEFT JOIN users u ON u.id = d.delivered_by";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY d.delivery_date DESC, d.id DESC";

if ($params) {
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $deliveries = mysqli_stmt_get_result($stmt);
} else {
    $deliveries = mysqli_query($link, $sql);
}

$clients_filter = mysqli_query($link, "SELECT id, name FROM clients ORDER BY name");
$kits_filter    = mysqli_query($link, "SELECT id, name FROM kits ORDER BY name");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Entregas | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Entregas de kits</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Entregas</li>
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
                                    <h5 class="card-title mb-0">Historial de entregas</h5>
                                    <a href="admin-delivery-form.php" class="btn btn-primary waves-effect waves-light">
                                        <i class="mdi mdi-plus me-1"></i> Registrar entrega
                                    </a>
                                </div>

                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-auto">
                                        <select name="client_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todos los clientes</option>
                                            <?php while ($cl = mysqli_fetch_assoc($clients_filter)): ?>
                                                <option value="<?php echo (int) $cl["id"]; ?>" <?php echo $filter_client == $cl["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($cl["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select name="kit_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todos los kits</option>
                                            <?php while ($ki = mysqli_fetch_assoc($kits_filter)): ?>
                                                <option value="<?php echo (int) $ki["id"]; ?>" <?php echo $filter_kit == $ki["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($ki["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <?php if ($filter_client || $filter_kit): ?>
                                        <div class="col-auto">
                                            <a href="admin-deliveries-list.php" class="btn btn-light">Limpiar filtros</a>
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Fecha</th>
                                                <th>Cliente</th>
                                                <th>Marca</th>
                                                <th>Clasificacion</th>
                                                <th>Kit entregado</th>
                                                <th>Tipo de gestion</th>
                                                <th>Entregado por</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($d = mysqli_fetch_assoc($deliveries)): ?>
                                                <tr>
                                                    <td><?php echo (int) $d["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($d["delivery_date"]); ?></td>
                                                    <td><?php echo htmlspecialchars($d["client_name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($d["brand_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($d["classification_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($d["kit_name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($d["management_type_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($d["delivered_by_name"] ?? "—"); ?></td>
                                                    <td class="text-end">
                                                        <a href="admin-delivery-print.php?id=<?php echo (int) $d["id"]; ?>" target="_blank" class="btn btn-sm btn-soft-secondary">
                                                            <i class="mdi mdi-printer"></i>
                                                        </a>
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
