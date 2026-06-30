<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]); // Todos los roles pueden ver; solo Admin/Jefe pueden editar

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2], true);

$success_msg = "";
$error_msg = "";

// ---------------------------------------------------------------
// CREAR / EDITAR proveedor
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "save") {
    $provider_id  = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $name         = trim($_POST["name"] ?? "");
    $address      = trim($_POST["address"] ?? "");
    $phone        = trim($_POST["phone"] ?? "");
    $email        = trim($_POST["email"] ?? "");
    $contact_name = trim($_POST["contact_name"] ?? "");
    $notes        = trim($_POST["notes"] ?? "");
    $status       = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";

    if ($name === "") {
        $error_msg = "El nombre del proveedor es obligatorio.";
    } else {
        if ($provider_id > 0) {
            $sql = "UPDATE providers SET name=?, address=?, phone=?, email=?, contact_name=?, notes=?, status=? WHERE id=?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssi", $name, $address, $phone, $email, $contact_name, $notes, $status, $provider_id);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Proveedor actualizado correctamente.";
            } else {
                $error_msg = "No se pudo actualizar el proveedor: " . mysqli_error($link);
            }
        } else {
            $sql = "INSERT INTO providers (name, address, phone, email, contact_name, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $sql);
            $created_by = (int) $_SESSION["id"];
            mysqli_stmt_bind_param($stmt, "sssssssi", $name, $address, $phone, $email, $contact_name, $notes, $status, $created_by);
            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "Proveedor creado correctamente.";
            } else {
                $error_msg = "No se pudo crear el proveedor: " . mysqli_error($link);
            }
        }
    }
}

// ---------------------------------------------------------------
// ELIMINAR proveedor
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $provider_id = (int) ($_POST["id"] ?? 0);
    if ($provider_id > 0) {
        $sql = "DELETE FROM providers WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $provider_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Proveedor eliminado correctamente.";
        } else {
            if (mysqli_errno($link) == 1451) {
                $error_msg = "No se puede eliminar: este proveedor tiene facturas de compra registradas.";
            } else {
                $error_msg = "No se pudo eliminar el proveedor: " . mysqli_error($link);
            }
        }
    }
}

// ---------------------------------------------------------------
// Cargar proveedores
// ---------------------------------------------------------------
$providers = mysqli_query($link, "SELECT id, name, address, phone, email, contact_name, notes, status, created_at
                                   FROM providers ORDER BY name ASC");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Proveedores | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Proveedores</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Proveedores</li>
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
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="card-title mb-0">Listado de proveedores</h5>
                                    <?php if ($can_edit): ?>
                                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#providerModal" onclick="openCreateModal()">
                                            <i class="mdi mdi-plus me-1"></i> Nuevo proveedor
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th>Contacto</th>
                                                <th>Telefono</th>
                                                <th>Correo</th>
                                                <th>Direccion</th>
                                                <th>Estado</th>
                                                <?php if ($can_edit): ?><th class="text-end">Acciones</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($p = mysqli_fetch_assoc($providers)): ?>
                                                <tr>
                                                    <td><?php echo (int) $p["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($p["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($p["contact_name"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["phone"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["email"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($p["address"] ?? ""); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $p["status"] === 'activo' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($p["status"]); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($can_edit): ?>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-soft-primary"
                                                            onclick='openEditModal(<?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este proveedor?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo (int) $p["id"]; ?>">
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
<!-- Modal: Crear / Editar proveedor -->
<div class="modal fade" id="providerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="providerModalLabel">Nuevo proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="provider_id" value="">

                <div class="mb-3">
                    <label class="form-label">Nombre del proveedor</label>
                    <input type="text" name="name" id="provider_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Persona de contacto</label>
                    <input type="text" name="contact_name" id="provider_contact_name" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Telefono</label>
                    <input type="text" name="phone" id="provider_phone" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" id="provider_email" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Direccion</label>
                    <input type="text" name="address" id="provider_address" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" id="provider_notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="status" id="provider_status" class="form-select">
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

<!-- Right Sidebar -->
<?php include 'layouts/right-sidebar.php'; ?>

<!-- JAVASCRIPT -->
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<?php if ($can_edit): ?>
<script>
function openCreateModal() {
    document.getElementById('providerModalLabel').innerText = 'Nuevo proveedor';
    document.getElementById('provider_id').value = '';
    document.getElementById('provider_name').value = '';
    document.getElementById('provider_contact_name').value = '';
    document.getElementById('provider_phone').value = '';
    document.getElementById('provider_email').value = '';
    document.getElementById('provider_address').value = '';
    document.getElementById('provider_notes').value = '';
    document.getElementById('provider_status').value = 'activo';
}

function openEditModal(provider) {
    document.getElementById('providerModalLabel').innerText = 'Editar proveedor';
    document.getElementById('provider_id').value = provider.id;
    document.getElementById('provider_name').value = provider.name;
    document.getElementById('provider_contact_name').value = provider.contact_name || '';
    document.getElementById('provider_phone').value = provider.phone || '';
    document.getElementById('provider_email').value = provider.email || '';
    document.getElementById('provider_address').value = provider.address || '';
    document.getElementById('provider_notes').value = provider.notes || '';
    document.getElementById('provider_status').value = provider.status;

    var modal = new bootstrap.Modal(document.getElementById('providerModal'));
    modal.show();
}
</script>
<?php endif; ?>

</body>

</html>
