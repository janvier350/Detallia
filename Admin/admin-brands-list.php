<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);
require_module_view('marcas');

$can_create = can('marcas', 'create');
$can_edit   = can('marcas', 'edit');
$can_delete = can('marcas', 'delete');

$success_msg = "";
$error_msg = "";

// ---------------------------------------------------------------
// CREAR / EDITAR marca
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "save") {
    $brand_id    = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    if (($brand_id > 0 && !$can_edit) || ($brand_id === 0 && !$can_create)) {
        header('location: pages-403.php');
        exit;
    }
    $name        = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $status      = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";

    if ($name === "") {
        $error_msg = "El nombre de la marca es obligatorio.";
    } else {
        if ($brand_id > 0) {
            $sql  = "UPDATE brands SET name=?, description=?, status=? WHERE id=?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $name, $description, $status, $brand_id);
        } else {
            $sql  = "INSERT INTO brands (name, description, status) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "sss", $name, $description, $status);
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            $success_msg = $brand_id > 0 ? "Marca actualizada correctamente." : "Marca creada correctamente.";
        } else {
            if (mysqli_errno($link) == 1062) {
                $error_msg = "Ya existe una marca con ese nombre.";
            } else {
                $error_msg = "No se pudo guardar la marca: " . mysqli_error($link);
            }
        }
    }
}

// ---------------------------------------------------------------
// ELIMINAR marca
// ---------------------------------------------------------------
if ($can_delete && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $brand_id = (int) ($_POST["id"] ?? 0);
    if ($brand_id > 0) {
        $sql  = "DELETE FROM brands WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $brand_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Marca eliminada correctamente.";
        } else {
            if (mysqli_errno($link) == 1451) {
                $error_msg = "No se puede eliminar: esta marca esta asociada a clientes o kits.";
            } else {
                $error_msg = "No se pudo eliminar la marca: " . mysqli_error($link);
            }
        }
    }
}

$brands = mysqli_query($link, "SELECT id, name, description, status, created_at FROM brands ORDER BY name ASC");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Marcas | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Marcas</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Marcas</li>
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
                                    <h5 class="card-title mb-0">Marcas / Empresas clientes</h5>
                                    <?php if ($can_create): ?>
                                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#brandModal" onclick="openCreateModal()">
                                            <i class="mdi mdi-plus me-1"></i> Nueva marca
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th>Descripcion</th>
                                                <th>Estado</th>
                                                <?php if ($can_edit || $can_delete): ?><th class="text-end">Acciones</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($b = mysqli_fetch_assoc($brands)): ?>
                                                <tr>
                                                    <td><?php echo (int) $b["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($b["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($b["description"] ?? ""); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $b["status"] === 'activo' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($b["status"]); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($can_edit || $can_delete): ?>
                                                    <td class="text-end">
                                                        <?php if ($can_edit): ?>
                                                        <button type="button" class="btn btn-sm btn-soft-primary"
                                                            onclick='openEditModal(<?php echo json_encode($b, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if ($can_delete): ?>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta marca?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo (int) $b["id"]; ?>">
                                                            <button type="submit" class="btn btn-sm btn-soft-danger">
                                                                <i class="mdi mdi-delete"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
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

<?php if ($can_create || $can_edit): ?>
<div class="modal fade" id="brandModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="brandModalLabel">Nueva marca</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="brand_id" value="">

                <div class="mb-3">
                    <label class="form-label">Nombre de la marca / empresa</label>
                    <input type="text" name="name" id="brand_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" id="brand_description" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="status" id="brand_status" class="form-select">
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

<?php if ($can_create || $can_edit): ?>
<script>
function openCreateModal() {
    document.getElementById('brandModalLabel').innerText = 'Nueva marca';
    document.getElementById('brand_id').value = '';
    document.getElementById('brand_name').value = '';
    document.getElementById('brand_description').value = '';
    document.getElementById('brand_status').value = 'activo';
}

function openEditModal(brand) {
    document.getElementById('brandModalLabel').innerText = 'Editar marca';
    document.getElementById('brand_id').value = brand.id;
    document.getElementById('brand_name').value = brand.name;
    document.getElementById('brand_description').value = brand.description || '';
    document.getElementById('brand_status').value = brand.status;

    var modal = new bootstrap.Modal(document.getElementById('brandModal'));
    modal.show();
}
</script>
<?php endif; ?>

</body>

</html>
