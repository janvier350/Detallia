<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]);

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2], true);

$success_msg = "";
$error_msg = "";

// ---------------------------------------------------------------
// CREAR / EDITAR cliente
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "save") {
    $client_id         = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $name              = trim($_POST["name"] ?? "");
    $brand_id          = (int) ($_POST["brand_id"] ?? 0);
    $classification_id = (int) ($_POST["classification_id"] ?? 0);
    $contact_name      = trim($_POST["contact_name"] ?? "");
    $phone             = trim($_POST["phone"] ?? "");
    $email             = trim($_POST["email"] ?? "");
    $address           = trim($_POST["address"] ?? "");
    $notes             = trim($_POST["notes"] ?? "");
    $status            = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";

    $brand_id = $brand_id > 0 ? $brand_id : null;

    if ($name === "" || $classification_id <= 0) {
        $error_msg = "El nombre y la clasificacion son obligatorios.";
    } else {
        if ($client_id > 0) {
            $sql  = "UPDATE clients SET name=?, brand_id=?, classification_id=?, contact_name=?, phone=?, email=?, address=?, notes=?, status=? WHERE id=?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "siissssssi", $name, $brand_id, $classification_id, $contact_name, $phone, $email, $address, $notes, $status, $client_id);
        } else {
            $sql  = "INSERT INTO clients (name, brand_id, classification_id, contact_name, phone, email, address, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "siissssss", $name, $brand_id, $classification_id, $contact_name, $phone, $email, $address, $notes, $status);
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            $success_msg = $client_id > 0 ? "Cliente actualizado correctamente." : "Cliente creado correctamente.";
        } else {
            $error_msg = "No se pudo guardar el cliente: " . mysqli_error($link);
        }
    }
}

// ---------------------------------------------------------------
// ELIMINAR cliente
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $client_id = (int) ($_POST["id"] ?? 0);
    if ($client_id > 0) {
        $sql  = "DELETE FROM clients WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $client_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Cliente eliminado correctamente.";
        } else {
            if (mysqli_errno($link) == 1451) {
                $error_msg = "No se puede eliminar: este cliente tiene entregas registradas.";
            } else {
                $error_msg = "No se pudo eliminar el cliente: " . mysqli_error($link);
            }
        }
    }
}

// ---------------------------------------------------------------
// Filtro por clasificacion
// ---------------------------------------------------------------
$filter_classification = isset($_GET["classification_id"]) ? (int) $_GET["classification_id"] : 0;

$sql_clients = "SELECT c.id, c.name, c.contact_name, c.phone, c.email, c.status,
                       b.name AS brand_name, cc.name AS classification_name
                FROM clients c
                LEFT JOIN brands b ON b.id = c.brand_id
                JOIN client_classifications cc ON cc.id = c.classification_id";

if ($filter_classification > 0) {
    $stmt_c = mysqli_prepare($link, $sql_clients . " WHERE c.classification_id = ? ORDER BY c.name ASC");
    mysqli_stmt_bind_param($stmt_c, "i", $filter_classification);
    mysqli_stmt_execute($stmt_c);
    $clients = mysqli_stmt_get_result($stmt_c);
} else {
    $clients = mysqli_query($link, $sql_clients . " ORDER BY c.name ASC");
}

$brands          = mysqli_query($link, "SELECT id, name FROM brands WHERE status = 'activo' ORDER BY name");
$classifications = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY id");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Clientes | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Clientes</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Clientes</li>
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
                                    <h5 class="card-title mb-0">Listado de clientes</h5>
                                    <?php if ($can_edit): ?>
                                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#clientModal" onclick="openCreateModal()">
                                            <i class="mdi mdi-plus me-1"></i> Nuevo cliente
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-auto">
                                        <select name="classification_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todas las clasificaciones</option>
                                            <?php mysqli_data_seek($classifications, 0); while ($cl = mysqli_fetch_assoc($classifications)): ?>
                                                <option value="<?php echo (int) $cl["id"]; ?>" <?php echo $filter_classification == $cl["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($cl["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <?php if ($filter_classification): ?>
                                        <div class="col-auto">
                                            <a href="admin-clients-list.php" class="btn btn-light">Limpiar filtro</a>
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th>Marca / Empresa</th>
                                                <th>Clasificacion</th>
                                                <th>Contacto</th>
                                                <th>Telefono</th>
                                                <th>Estado</th>
                                                <?php if ($can_edit): ?><th class="text-end">Acciones</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($c = mysqli_fetch_assoc($clients)): ?>
                                                <tr>
                                                    <td><?php echo (int) $c["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($c["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($c["brand_name"] ?? "—"); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge = match($c["classification_name"]) {
                                                            "VIP"      => "danger",
                                                            "Frecuente" => "warning",
                                                            default    => "secondary",
                                                        };
                                                        ?>
                                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars($c["classification_name"]); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($c["contact_name"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($c["phone"] ?? ""); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $c["status"] === 'activo' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($c["status"]); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($can_edit): ?>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-soft-primary"
                                                            onclick='openEditModal(<?php echo json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este cliente?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo (int) $c["id"]; ?>">
                                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                                <i class="mdi mdi-delete"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <?php endif; ?>
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

<?php if ($can_edit): ?>
<div class="modal fade" id="clientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clientModalLabel">Nuevo cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="client_id" value="">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del cliente</label>
                        <input type="text" name="name" id="client_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Marca / Empresa</label>
                        <select name="brand_id" id="client_brand_id" class="form-select">
                            <option value="">Sin marca</option>
                            <?php mysqli_data_seek($brands, 0); while ($br = mysqli_fetch_assoc($brands)): ?>
                                <option value="<?php echo (int) $br["id"]; ?>"><?php echo htmlspecialchars($br["name"]); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Clasificacion</label>
                        <select name="classification_id" id="client_classification_id" class="form-select" required>
                            <option value="">Selecciona...</option>
                            <?php mysqli_data_seek($classifications, 0); while ($cl = mysqli_fetch_assoc($classifications)): ?>
                                <option value="<?php echo (int) $cl["id"]; ?>"><?php echo htmlspecialchars($cl["name"]); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre de contacto</label>
                        <input type="text" name="contact_name" id="client_contact_name" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Telefono</label>
                        <input type="text" name="phone" id="client_phone" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="client_email" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Direccion</label>
                    <input type="text" name="address" id="client_address" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" id="client_notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="status" id="client_status" class="form-select">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<?php if ($can_edit): ?>
<script>
function openCreateModal() {
    document.getElementById('clientModalLabel').innerText = 'Nuevo cliente';
    document.getElementById('client_id').value = '';
    document.getElementById('client_name').value = '';
    document.getElementById('client_brand_id').value = '';
    document.getElementById('client_classification_id').value = '';
    document.getElementById('client_contact_name').value = '';
    document.getElementById('client_phone').value = '';
    document.getElementById('client_email').value = '';
    document.getElementById('client_address').value = '';
    document.getElementById('client_notes').value = '';
    document.getElementById('client_status').value = 'activo';
}

function openEditModal(c) {
    document.getElementById('clientModalLabel').innerText = 'Editar cliente';
    document.getElementById('client_id').value = c.id;
    document.getElementById('client_name').value = c.name;
    document.getElementById('client_brand_id').value = c.brand_id || '';
    document.getElementById('client_classification_id').value = c.classification_id || '';
    document.getElementById('client_contact_name').value = c.contact_name || '';
    document.getElementById('client_phone').value = c.phone || '';
    document.getElementById('client_email').value = c.email || '';
    document.getElementById('client_address').value = c.address || '';
    document.getElementById('client_notes').value = c.notes || '';
    document.getElementById('client_status').value = c.status;

    var modal = new bootstrap.Modal(document.getElementById('clientModal'));
    modal.show();
}
</script>
<?php endif; ?>

</body>

</html>
