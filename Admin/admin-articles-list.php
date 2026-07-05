<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2], true);

$success_msg = "";
$error_msg = "";

$upload_dir = __DIR__ . '/assets/images/articles/';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 2 * 1024 * 1024; // 2 MB

// ---------------------------------------------------------------
// CREAR categoria (AJAX, desde el modal de articulo)
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "add_category") {
    header("Content-Type: application/json");
    $cat_name = trim($_POST["name"] ?? "");

    if ($cat_name === "") {
        echo json_encode(["ok" => false, "error" => "El nombre de la categoria es obligatorio."]);
        exit;
    }

    $stmt = mysqli_prepare($link, "INSERT INTO article_categories (name) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $cat_name);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["ok" => true, "id" => mysqli_insert_id($link), "name" => $cat_name]);
    } else {
        $msg = mysqli_errno($link) == 1062 ? "Ya existe una categoria con ese nombre." : "No se pudo crear la categoria.";
        echo json_encode(["ok" => false, "error" => $msg]);
    }
    exit;
}

// ---------------------------------------------------------------
// CREAR / EDITAR articulo
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "save") {
    $article_id  = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $name        = trim($_POST["name"] ?? "");
    $category_id = (int) ($_POST["category_id"] ?? 0);
    $brand_id    = (int) ($_POST["brand_id"] ?? 0);
    $unit        = trim($_POST["unit"] ?? "unidad");
    $description = trim($_POST["description"] ?? "");
    $status      = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";

    $category_id = $category_id > 0 ? $category_id : null;
    $brand_id    = $brand_id > 0 ? $brand_id : null;

    // Manejo de imagen
    $new_image_path = null;
    $image_error = "";

    if (!empty($_FILES["image"]["name"])) {
        $file      = $_FILES["image"];
        $ftype     = mime_content_type($file["tmp_name"]);
        $fsize     = $file["size"];
        $ext       = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

        if (!in_array($ftype, $allowed_types)) {
            $image_error = "Solo se permiten imagenes JPG, PNG, GIF o WEBP.";
        } elseif ($fsize > $max_size) {
            $image_error = "La imagen no puede superar 2 MB.";
        } else {
            $filename = 'art_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($file["tmp_name"], $upload_dir . $filename)) {
                $new_image_path = 'assets/images/articles/' . $filename;
            } else {
                $image_error = "No se pudo guardar la imagen. Verifica permisos del directorio.";
            }
        }
    }

    if ($image_error !== "") {
        $error_msg = $image_error;
    } elseif ($name === "") {
        $error_msg = "El nombre del articulo es obligatorio.";
    } else {
        if ($article_id > 0) {
            if ($new_image_path !== null) {
                // Borrar imagen anterior si existe
                $old = mysqli_query($link, "SELECT image_path FROM articles WHERE id = " . $article_id);
                if ($old && ($oldrow = mysqli_fetch_assoc($old)) && $oldrow["image_path"]) {
                    $oldfile = __DIR__ . '/' . $oldrow["image_path"];
                    if (file_exists($oldfile)) @unlink($oldfile);
                }
                $sql  = "UPDATE articles SET name=?, category_id=?, brand_id=?, unit=?, description=?, status=?, image_path=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "siissssi", $name, $category_id, $brand_id, $unit, $description, $status, $new_image_path, $article_id);
            } else {
                $sql  = "UPDATE articles SET name=?, category_id=?, brand_id=?, unit=?, description=?, status=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "siisssi", $name, $category_id, $brand_id, $unit, $description, $status, $article_id);
            }
        } else {
            $sql  = "INSERT INTO articles (name, category_id, brand_id, unit, description, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($link, $sql);
            mysqli_stmt_bind_param($stmt, "siissss", $name, $category_id, $brand_id, $unit, $description, $status, $new_image_path);
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            $success_msg = $article_id > 0 ? "Articulo actualizado correctamente." : "Articulo creado correctamente.";
        } else {
            $error_msg = "No se pudo guardar el articulo: " . mysqli_error($link);
        }
    }
}

// ---------------------------------------------------------------
// ELIMINAR articulo
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] === "delete") {
    $article_id = (int) ($_POST["id"] ?? 0);
    if ($article_id > 0) {
        // Borrar imagen si tiene
        $old = mysqli_query($link, "SELECT image_path FROM articles WHERE id = " . $article_id);
        if ($old && ($oldrow = mysqli_fetch_assoc($old)) && $oldrow["image_path"]) {
            $oldfile = __DIR__ . '/' . $oldrow["image_path"];
            if (file_exists($oldfile)) @unlink($oldfile);
        }

        $sql  = "DELETE FROM articles WHERE id = ?";
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
$brands     = mysqli_query($link, "SELECT id, name FROM brands ORDER BY name");

// ---------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------
$filter_category = isset($_GET["category_id"]) ? (int) $_GET["category_id"] : 0;
$filter_brand    = isset($_GET["brand_id"]) ? (int) $_GET["brand_id"] : 0;
$filter_search   = isset($_GET["q"]) ? trim($_GET["q"]) : "";

$where  = [];
$params = [];
$types  = "";

if ($filter_category > 0) {
    $where[]  = "a.category_id = ?";
    $params[] = $filter_category;
    $types   .= "i";
}
if ($filter_brand > 0) {
    $where[]  = "a.brand_id = ?";
    $params[] = $filter_brand;
    $types   .= "i";
}
if ($filter_search !== "") {
    $where[]  = "a.name LIKE ?";
    $params[] = "%" . $filter_search . "%";
    $types   .= "s";
}

$articlesSql = "SELECT a.id, a.name, a.unit, a.description, a.image_path, a.status, a.created_at,
                       c.id AS category_id, c.name AS category_name,
                       b.id AS brand_id, b.name AS brand_name,
                       COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.article_id = a.id), 0) AS stock
                FROM articles a
                LEFT JOIN article_categories c ON c.id = a.category_id
                LEFT JOIN brands b ON b.id = a.brand_id";

if ($where) {
    $articlesSql .= " WHERE " . implode(" AND ", $where);
}
$articlesSql .= " ORDER BY a.name ASC";

if ($params) {
    $stmt = mysqli_prepare($link, $articlesSql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $articles = mysqli_stmt_get_result($stmt);
} else {
    $articles = mysqli_query($link, $articlesSql);
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Articulos | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <!-- glightbox css -->
    <link rel="stylesheet" href="assets/libs/glightbox/css/glightbox.min.css">
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

                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-auto">
                                        <input type="text" name="q" class="form-control" placeholder="Buscar por nombre..." value="<?php echo htmlspecialchars($filter_search); ?>">
                                    </div>
                                    <div class="col-auto">
                                        <select name="category_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todas las categorias</option>
                                            <?php mysqli_data_seek($categories, 0); while ($cf = mysqli_fetch_assoc($categories)): ?>
                                                <option value="<?php echo (int) $cf["id"]; ?>" <?php echo $filter_category == $cf["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($cf["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <select name="brand_id" class="form-select" onchange="this.form.submit()">
                                            <option value="0">Todas las marcas</option>
                                            <?php mysqli_data_seek($brands, 0); while ($bf = mysqli_fetch_assoc($brands)): ?>
                                                <option value="<?php echo (int) $bf["id"]; ?>" <?php echo $filter_brand == $bf["id"] ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($bf["name"]); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="mdi mdi-magnify"></i> Buscar
                                        </button>
                                    </div>
                                    <?php if ($filter_category || $filter_brand || $filter_search !== ""): ?>
                                        <div class="col-auto">
                                            <a href="admin-articles-list.php" class="btn btn-light">Limpiar filtros</a>
                                        </div>
                                    <?php endif; ?>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:60px">Imagen</th>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th>Categoria</th>
                                                <th>Marca</th>
                                                <th>Unidad</th>
                                                <th>Stock</th>
                                                <th>Estado</th>
                                                <?php if ($can_edit): ?><th class="text-end">Acciones</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($a = mysqli_fetch_assoc($articles)): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($a["image_path"])): ?>
                                                            <a href="<?php echo htmlspecialchars($a["image_path"]); ?>"
                                                               class="image-popup"
                                                               data-glightbox="title: <?php echo htmlspecialchars(addslashes($a['name'])); ?>">
                                                                <img src="<?php echo htmlspecialchars($a["image_path"]); ?>"
                                                                     alt="<?php echo htmlspecialchars($a['name']); ?>"
                                                                     style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:zoom-in;">
                                                            </a>
                                                        <?php else: ?>
                                                            <div style="width:40px;height:40px;background:#f0f0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                                                                <i class="mdi mdi-image-outline text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo (int) $a["id"]; ?></td>
                                                    <td><?php echo htmlspecialchars($a["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($a["category_name"] ?? "Sin categoria"); ?></td>
                                                    <td><?php echo htmlspecialchars($a["brand_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($a["unit"]); ?></td>
                                                    <td>
                                                        <?php $stockVal = (float) $a["stock"]; ?>
                                                        <span class="badge bg-<?php echo $stockVal <= 0 ? 'danger' : ($stockVal <= 10 ? 'warning' : 'success'); ?>">
                                                            <?php echo format_qty($stockVal); ?>
                                                        </span>
                                                    </td>
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
        <form method="post" enctype="multipart/form-data" class="modal-content">
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
                    <label class="form-label">Categoria</label>
                    <div class="d-flex gap-2">
                        <select name="category_id" id="article_category_id" class="form-select">
                            <option value="">Sin categoria</option>
                            <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?php echo (int) $c["id"]; ?>"><?php echo htmlspecialchars($c["name"]); ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button type="button" class="btn btn-soft-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#categoryModal" title="Nueva categoria">
                            <i class="mdi mdi-plus"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Marca</label>
                    <select name="brand_id" id="article_brand_id" class="form-select">
                        <option value="">Sin marca</option>
                        <?php mysqli_data_seek($brands, 0); while ($b = mysqli_fetch_assoc($brands)): ?>
                            <option value="<?php echo (int) $b["id"]; ?>"><?php echo htmlspecialchars($b["name"]); ?></option>
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
                    <label class="form-label">Imagen del producto</label>
                    <div id="currentImageWrapper" class="mb-2" style="display:none;">
                        <img id="currentImage" src="" alt="" style="height:80px;border-radius:6px;object-fit:cover;">
                        <p class="text-muted mt-1 mb-0" style="font-size:0.8rem;">Imagen actual — sube una nueva para reemplazarla</p>
                    </div>
                    <input type="file" name="image" id="article_image" class="form-control" accept="image/*">
                    <div class="form-text">JPG, PNG, GIF o WEBP · max 2 MB</div>
                    <div id="imagePreviewWrapper" class="mt-2" style="display:none;">
                        <img id="imagePreview" src="" alt="" style="height:80px;border-radius:6px;object-fit:cover;">
                    </div>
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

<!-- Modal: Nueva categoria -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="categoryModalError" class="alert alert-danger" style="display:none;"></div>
                <div class="mb-3">
                    <label class="form-label">Nombre de la categoria</label>
                    <input type="text" id="new_category_name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveCategoryBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>
<!-- glightbox js -->
<script src="assets/libs/glightbox/js/glightbox.min.js"></script>
<script>
GLightbox({ selector: '.image-popup', title: false });
</script>

<?php if ($can_edit): ?>
<script>
var imageInput   = document.getElementById('article_image');
var previewImg   = document.getElementById('imagePreview');
var previewWrap  = document.getElementById('imagePreviewWrapper');
var currentWrap  = document.getElementById('currentImageWrapper');
var currentImg   = document.getElementById('currentImage');

imageInput.addEventListener('change', function () {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewWrap.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        previewWrap.style.display = 'none';
    }
});

function openCreateModal() {
    document.getElementById('articleModalLabel').innerText = 'Nuevo articulo';
    document.getElementById('article_id').value = '';
    document.getElementById('article_name').value = '';
    document.getElementById('article_category_id').value = '';
    document.getElementById('article_brand_id').value = '';
    document.getElementById('article_unit').value = 'unidad';
    document.getElementById('article_description').value = '';
    document.getElementById('article_status').value = 'activo';
    imageInput.value = '';
    previewWrap.style.display = 'none';
    currentWrap.style.display = 'none';
}

function openEditModal(article) {
    document.getElementById('articleModalLabel').innerText = 'Editar articulo';
    document.getElementById('article_id').value = article.id;
    document.getElementById('article_name').value = article.name;
    document.getElementById('article_category_id').value = article.category_id || '';
    document.getElementById('article_brand_id').value = article.brand_id || '';
    document.getElementById('article_unit').value = article.unit;
    document.getElementById('article_description').value = article.description || '';
    document.getElementById('article_status').value = article.status;
    imageInput.value = '';
    previewWrap.style.display = 'none';

    if (article.image_path) {
        currentImg.src = article.image_path;
        currentWrap.style.display = 'block';
    } else {
        currentWrap.style.display = 'none';
    }

    var modal = new bootstrap.Modal(document.getElementById('articleModal'));
    modal.show();
}

document.getElementById('saveCategoryBtn').addEventListener('click', function () {
    var nameInput = document.getElementById('new_category_name');
    var errorBox = document.getElementById('categoryModalError');
    var name = nameInput.value.trim();

    errorBox.style.display = 'none';
    if (name === '') {
        errorBox.innerText = 'El nombre de la categoria es obligatorio.';
        errorBox.style.display = 'block';
        return;
    }

    var formData = new FormData();
    formData.append('action', 'add_category');
    formData.append('name', name);

    fetch('admin-articles-list.php', { method: 'POST', body: formData })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.ok) {
                errorBox.innerText = data.error || 'No se pudo crear la categoria.';
                errorBox.style.display = 'block';
                return;
            }

            var select = document.getElementById('article_category_id');
            var option = document.createElement('option');
            option.value = data.id;
            option.text = data.name;
            select.appendChild(option);
            select.value = data.id;

            nameInput.value = '';
            bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
        })
        .catch(function () {
            errorBox.innerText = 'Error de conexion al crear la categoria.';
            errorBox.style.display = 'block';
        });
});
</script>
<?php endif; ?>

</body>

</html>
