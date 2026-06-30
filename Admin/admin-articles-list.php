<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]); // Todos los roles pueden ver; solo Admin/Jefe pueden editar

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2], true);

$success_msg = "";
$error_msg = "";

// ---------------------------------------------------------------
// CREAR / EDITAR articulo
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "save") {
    $article_id  = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $name        = trim($_POST["name"] ?? "");
    $sku         = trim($_POST["sku"] ?? "");
    $category_id = (int) ($_POST["category_id"] ?? 0);
    $unit        = trim($_POST["unit"] ?? "unidad");
    $description = trim($_POST["description"] ?? "");
    $status      = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";

    $category_id = $category_id > 0 ? $category_id : null;
    $sku = $sku !== "" ? $sku : null;

    if ($name === "") {
        $error_msg = "El nombre del articulo es obligatorio.";
    } else {
        if ($article_id > 0) {
            $sql = "UPDATE articles SET name=?, sku=?, category_id=?, unit=?, description=?, status=? WHERE id=?";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "ssisssi", $name, $sku, $category_id, $unit, $description, $status, $article_id);
        } else {
            $sql = "INSERT INTO articles (name, sku, category_id, unit, description, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "ssisss", $name, $sku, $category_id, $unit, $description, $status);
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            $success_msg = $article_id > 0 ? "Articulo actualizado correctamente." : "Articulo creado correctamente.";
        } else {
            if (mysqli_errno($link) == 1062) {
                $error_msg = "Ya existe un articulo con ese SKU.";
            } else {
                $error_msg = "No se pudo guardar el articulo: " . mysqli_error($link);
            }
        }
    }
}

// ---------------------------------------------------------------
// ELIMINAR articulo
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $article_id = (int) ($_POST["id"] ?? 0);
    if ($article_id > 0) {
        $sql = "DELETE FROM articles WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $article_id);
        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Articulo eliminado correctamente.";
        } else {
            if (mysqli_errno($link) == 1451) {
                $error_msg = "No se puede eliminar: este articulo esta usado en compras o kits.";
            } else {
                $error_msg = "No se pudo eliminar el articulo: " . mysqli_error($link);
            }
        }
    }
}

// ---------------------------------------------------------------
// Cargar categorias y articulos
// ---------------------------------------------------------------
$categories = mysqli_query($link, "SELECT id, name FROM article_categories ORDER BY name");

$articles = mysqli_query($link, "SELECT a.id, a.name, a.sku, a.unit, a.description, a.status, a.created_at,
                                         c.id AS category_id, c.name AS category_name
                                  FROM articles a
                                  LEFT JOIN article_categories c ON c.id = a.category_id
                                  ORDER BY a.name ASC");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Articulos | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Articulos</h4>

                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Articulos</li>
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
                                    <h5 class="card-title mb-0">Catalogo de articulos</h5>
                                    <?php if ($can_edit): ?>
                                        <button type="button" class="btn btn-primary waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#articleModal" onclick="openCreateModal()">
                                            <i class="mdi mdi-plus me-1"></i> Nuevo articulo
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th>SKU</th>
                                                <th>Categoria</th>
                                                <th>Unidad</th>
                                                <th>Estado</th>
                                                <?php if ($can_edit): ?><th class="text-end">Acciones</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($a = mysqli_fetch_assoc($articles)): ?>
                                                <tr>
                                                    <td><?php echo (int) $a["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($a["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($a["sku"] ?? ""); ?></td>
                                                    <td><?php echo htmlspecialchars($a["category_name"] ?? "Sin categoria"); ?></td>
                                                    <td><?php echo htmlspecialchars($a["unit"]); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $a["status"] === 'activo' ? 'success' : 'secondary'; ?>">
                                                            <?php echo htmlspecialchars($a["status"]); ?>
                                                        </span>
                                                    </td>
                                                    <?php if ($can_edit): ?>
                                                    <td class="text-end">
                                                        <button type="button" class="btn btn-sm btn-soft-primary"
                                                            onclick='openEditModal(<?php echo json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este articulo?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo (int) $a["id"]; ?>">
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
<!-- Modal: Crear / Editar articulo -->
<div class="modal fade" id="articleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="articleModalLabel">Nuevo articulo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="article_id" value="">

                <div class="mb-3">
                    <label class="form-label">Nombre del articulo</label>
                    <input type="text" name="name" id="article_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">SKU / Codigo</label>
                    <input type="text" name="sku" id="article_sku" class="form-control">
                </div>

                <div class="mb-3">
                    <label class="form-label">Categoria</label>
                    <select name="category_id" id="article_category_id" class="form-select">
                        <option value="">Sin categoria</option>
                        <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?php echo (int) $c["id"]; ?>"><?php echo htmlspecialchars($c["name"]); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Unidad</label>
                    <input type="text" name="unit" id="article_unit" class="form-control" value="unidad">
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripcion</label>
                    <textarea name="description" id="article_description" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="status" id="article_status" class="form-select">
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
    document.getElementById('articleModalLabel').innerText = 'Nuevo articulo';
    document.getElementById('article_id').value = '';
    document.getElementById('article_name').value = '';
    document.getElementById('article_sku').value = '';
    document.getElementById('article_category_id').value = '';
    document.getElementById('article_unit').value = 'unidad';
    document.getElementById('article_description').value = '';
    document.getElementById('article_status').value = 'activo';
}

function openEditModal(article) {
    document.getElementById('articleModalLabel').innerText = 'Editar articulo';
    document.getElementById('article_id').value = article.id;
    document.getElementById('article_name').value = article.name;
    document.getElementById('article_sku').value = article.sku || '';
    document.getElementById('article_category_id').value = article.category_id || '';
    document.getElementById('article_unit').value = article.unit;
    document.getElementById('article_description').value = article.description || '';
    document.getElementById('article_status').value = article.status;

    var modal = new bootstrap.Modal(document.getElementById('articleModal'));
    modal.show();
}
</script>
<?php endif; ?>

</body>

</html>
