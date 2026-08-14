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

$stmt = mysqli_prepare($link, "SELECT id, label, token, mode, active, finished_at, finished_by FROM validation_links WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $link_id);
mysqli_stmt_execute($stmt);
$batch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$batch) {
    header("location: admin-validation-links.php");
    exit;
}

// ---------------------------------------------------------------
// Revision interna: aprobar / rechazar un contacto pendiente
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($_POST["action"] ?? "", ["approve_one", "reject_one"], true)) {
    $pendingId = (int) ($_POST["pending_id"] ?? 0);
    $newStatus = $_POST["action"] === "approve_one" ? "confirmado" : "rechazado";
    if ($pendingId > 0) {
        $upd = mysqli_prepare($link, "UPDATE pending_clients SET status = ? WHERE id = ? AND link_id = ? AND imported = 0");
        mysqli_stmt_bind_param($upd, "sii", $newStatus, $pendingId, $link_id);
        mysqli_stmt_execute($upd);
        $success_msg = $newStatus === "confirmado" ? "Contacto aprobado." : "Contacto rechazado.";
    }
}

// ---------------------------------------------------------------
// Revision interna: aprobar todos los pendientes de una vez
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "approve_all") {
    $upd = mysqli_prepare($link, "UPDATE pending_clients SET status = 'confirmado' WHERE link_id = ? AND status = 'pendiente' AND imported = 0");
    mysqli_stmt_bind_param($upd, "i", $link_id);
    mysqli_stmt_execute($upd);
    $affected = mysqli_stmt_affected_rows($upd);
    $success_msg = $affected . " contacto(s) aprobado(s).";
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

    // Convencion de Clientes: name = persona (unica), contact_name = empresa.
    // Lotes de Excel (validacion): pending.name = persona, pending.contact_name = empresa -> ya coincide.
    // Lotes de recoleccion: pending.name = razon social (empresa), pending.contact_name = quien recibe (persona) -> se intercambia.
    $isRecoleccion = (($batch["mode"] ?? "validacion") === "recoleccion");

    while ($row = mysqli_fetch_assoc($rows)) {
        if ($isRecoleccion) {
            $clientName    = $row["contact_name"]; // persona (quien recibe) -> name
            $clientContact = $row["name"];         // empresa / razon social  -> contact_name
        } else {
            $clientName    = $row["name"];         // persona
            $clientContact = $row["contact_name"]; // empresa
        }
        // Preservar la persona de contacto (contacto_interno) dentro de las notas del cliente.
        $notes = $row["notes"] ?? "";
        if (!empty($row["contacto_interno"])) {
            $notes = trim(($notes !== "" ? $notes . " | " : "") . "Contacto: " . $row["contacto_interno"]);
        }
        mysqli_stmt_bind_param(
            $insertStmt,
            "sssssii",
            $clientName,
            $clientContact,
            $row["address"],
            $row["ciudad"],
            $notes,
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

$sends = mysqli_query($link, "SELECT s.email, s.success, s.sent_at, COALESCE(u.full_name, u.username) AS sent_by_name
                               FROM validation_link_sends s
                               LEFT JOIN users u ON u.id = s.sent_by
                               WHERE s.link_id = " . (int) $link_id . "
                               ORDER BY s.sent_at DESC");

$pending = mysqli_query($link, "SELECT pc.*, b.name AS brand_name, cl.name AS classification_name
                                 FROM pending_clients pc
                                 LEFT JOIN brands b ON b.id = pc.brand_id
                                 LEFT JOIN client_classifications cl ON cl.id = pc.classification_id
                                 WHERE pc.link_id = " . (int) $link_id . "
                                 ORDER BY pc.status = 'pendiente' DESC, pc.name ASC");

$confirmedPendingImport = (int) mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) AS c FROM pending_clients WHERE link_id = " . (int) $link_id . " AND status = 'confirmado' AND imported = 0"
))["c"];

$stillPending = (int) mysqli_fetch_assoc(mysqli_query($link,
    "SELECT COUNT(*) AS c FROM pending_clients WHERE link_id = " . (int) $link_id . " AND status = 'pendiente'"
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

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

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
                                <h5 class="card-title mb-3">Envios de este enlace</h5>
                                <?php if (mysqli_num_rows($sends) === 0): ?>
                                    <p class="text-muted">Este enlace aun no se ha enviado por correo a ningun encargado.</p>
                                <?php else: ?>
                                    <div class="table-responsive mb-2">
                                        <table class="table table-sm table-nowrap mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Correo del encargado</th>
                                                    <th>Enviado por</th>
                                                    <th>Fecha de envio</th>
                                                    <th>Resultado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($s = mysqli_fetch_assoc($sends)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($s["email"]); ?></td>
                                                        <td><?php echo htmlspecialchars($s["sent_by_name"] ?? "—"); ?></td>
                                                        <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($s["sent_at"]))); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $s["success"] ? 'success' : 'danger'; ?>">
                                                                <?php echo $s["success"] ? "Enviado" : "Fallo"; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Contactos del lote</h5>
                                    <div class="d-flex gap-2 flex-wrap">
                                    <?php if ($stillPending > 0): ?>
                                        <form method="post" onsubmit="return confirm('¿Aprobar los <?php echo $stillPending; ?> contactos pendientes?');">
                                            <input type="hidden" name="action" value="approve_all">
                                            <button type="submit" class="btn btn-success">
                                                <i class="mdi mdi-check-all me-1"></i>
                                                Aprobar <?php echo $stillPending; ?> pendientes
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Acciones</th>
                                                <th>Nombre</th>
                                                <th>Empresa/Grupo</th>
                                                <th>Oficina</th>
                                                <th>Zona</th>
                                                <th>Contacto interno</th>
                                                <th>RUC/CI</th>
                                                <th>Meses fact.</th>
                                                <th>Estatus</th>
                                                <th>Alerta</th>
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
                                                    <td class="text-nowrap">
                                                        <?php if (!$p["imported"] && $p["status"] !== "confirmado"): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="action" value="approve_one">
                                                                <input type="hidden" name="pending_id" value="<?php echo (int) $p['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-soft-success" title="Aprobar"><i class="mdi mdi-check-bold"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if (!$p["imported"] && $p["status"] !== "rechazado"): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="action" value="reject_one">
                                                                <input type="hidden" name="pending_id" value="<?php echo (int) $p['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-soft-danger" title="Rechazar"><i class="mdi mdi-close"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($p["imported"]): ?>
                                                            <span class="text-muted small">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($p["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($p["contact_name"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["oficina"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["zona"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["contacto_interno"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["ruc_ci"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["meses_fact"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["estatus_excel"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["alerta"] ?? ""); ?></td>
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
