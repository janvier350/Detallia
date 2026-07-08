<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2]);

$link_id = isset($_GET["link_id"]) ? (int) $_GET["link_id"] : 0;
if ($link_id <= 0) {
    header("location: admin-validation-links.php");
    exit;
}

$success_msg = "";
$importSummary = null;

$stmt = mysqli_prepare($link, "SELECT id, label, token, active FROM validation_links WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $link_id);
mysqli_stmt_execute($stmt);
$batch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$batch) {
    header("location: admin-validation-links.php");
    exit;
}

// ---------------------------------------------------------------
// Importar confirmados a Clientes
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "import_confirmed") {
    $rows = mysqli_query($link, "SELECT * FROM pending_clients WHERE link_id = " . (int) $link_id . " AND status = 'confirmado' AND imported = 0");
    $imported = 0;
    $skipped = 0;

    $insertStmt = mysqli_prepare($link, "INSERT INTO clients (name, contact_name, address, ciudad, provincia, phone, email, notes, status, brand_id, classification_id)
                                          VALUES (?, ?, ?, ?, NULL, NULL, NULL, ?, 'activo', ?, ?)");
    $markStmt = mysqli_prepare($link, "UPDATE pending_clients SET imported = 1 WHERE id = ?");

    while ($row = mysqli_fetch_assoc($rows)) {
        mysqli_stmt_bind_param(
            $insertStmt,
            "sssssii",
            $row["name"],
            $row["contact_name"],
            $row["address"],
            $row["ciudad"],
            $row["notes"],
            $row["brand_id"],
            $row["classification_id"]
        );
        if (mysqli_stmt_execute($insertStmt)) {
            $imported++;
            $pid = (int) $row["id"];
            mysqli_stmt_bind_param($markStmt, "i", $pid);
            mysqli_stmt_execute($markStmt);
        } else {
            $skipped++;
        }
    }
    $importSummary = ["imported" => $imported, "skipped" => $skipped];
}

$pending = mysqli_query($link, "SELECT pc.*, b.name AS brand_name, cl.name AS classification_name
                                 FROM pending_clients pc
                                 LEFT JOIN brands b ON b.id = pc.brand_id
                                 LEFT JOIN client_classifications cl ON cl.id = pc.classification_id
                                 WHERE pc.link_id = " . (int) $link_id . "
                                 ORDER BY pc.status = 'pendiente' DESC, pc.name ASC");

$confirmedPendingImport = (int) mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) AS c FROM pending_clients WHERE link_id = " . (int) $link_id . " AND status = 'confirmado' AND imported = 0"
))["c"];
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Revision de validacion | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Revision: <?php echo htmlspecialchars($batch["label"]); ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-validation-links.php">Enlaces de validacion</a></li>
                                    <li class="breadcrumb-item active">Revision</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($importSummary): ?>
                    <div class="alert alert-success">
                        <strong><?php echo $importSummary["imported"]; ?></strong> contactos confirmados fueron importados a Clientes.
                        <?php if ($importSummary["skipped"] > 0): ?>
                            <?php echo $importSummary["skipped"]; ?> se omitieron por nombre duplicado.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Contactos del lote</h5>
                                    <?php if ($confirmedPendingImport > 0): ?>
                                        <form method="post" onsubmit="return confirm('¿Importar <?php echo $confirmedPendingImport; ?> contactos confirmados a Clientes?');">
                                            <input type="hidden" name="action" value="import_confirmed">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="mdi mdi-database-import-outline me-1"></i>
                                                Importar <?php echo $confirmedPendingImport; ?> confirmados a Clientes
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Ciudad</th>
                                                <th>Marca</th>
                                                <th>Clasificacion</th>
                                                <th>Estado</th>
                                                <th>Validado por</th>
                                                <th>Fecha validacion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($p = mysqli_fetch_assoc($pending)): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($p["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($p["ciudad"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["brand_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($p["classification_name"] ?? "—"); ?></td>
                                                    <td>
                                                        <?php
                                                            $badgeClass = ["pendiente" => "warning", "confirmado" => "success", "rechazado" => "danger"][$p["status"]];
                                                        ?>
                                                        <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($p["status"]); ?></span>
                                                        <?php if ($p["imported"]): ?>
                                                            <span class="badge bg-primary-subtle text-primary">Ya en Clientes</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p["validated_by_email"] ?? "—"); ?></td>
                                                    <td><?php echo $p["validated_at"] ? htmlspecialchars(date("d/m/Y H:i", strtotime($p["validated_at"]))) : "—"; ?></td>
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
