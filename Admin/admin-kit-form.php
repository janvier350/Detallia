<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2]);

$kit_id    = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$error_msg = "";

// ---------------------------------------------------------------
// GUARDAR kit (crear o editar)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_id            = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $name               = trim($_POST["name"] ?? "");
    $brand_id           = (int) ($_POST["brand_id"] ?? 0);
    $management_type_id = (int) ($_POST["management_type_id"] ?? 0);
    $classification_id  = (int) ($_POST["classification_id"] ?? 0);
    $description        = trim($_POST["description"] ?? "");
    $status             = ($_POST["status"] ?? "activo") === "inactivo" ? "inactivo" : "activo";

    $items_article = $_POST["item_article_id"] ?? [];
    $items_qty     = $_POST["item_quantity"] ?? [];

    $brand_id           = $brand_id > 0 ? $brand_id : null;
    $management_type_id = $management_type_id > 0 ? $management_type_id : null;
    $classification_id  = $classification_id > 0 ? $classification_id : null;

    $valid_items = [];
    foreach ($items_article as $idx => $aid) {
        $aid = (int) $aid;
        $qty = (float) ($items_qty[$idx] ?? 0);
        if ($aid > 0 && $qty > 0) {
            $valid_items[$aid] = $qty; // key by article_id prevents duplicates
        }
    }

    if ($name === "") {
        $error_msg = "El nombre del kit es obligatorio.";
    } elseif (empty($valid_items)) {
        $error_msg = "Agrega al menos un articulo al kit.";
    } else {
        mysqli_begin_transaction($link);
        try {
            if ($form_id > 0) {
                $sql  = "UPDATE kits SET name=?, brand_id=?, management_type_id=?, classification_id=?, description=?, status=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "siiissi", $name, $brand_id, $management_type_id, $classification_id, $description, $status, $form_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }

                $del = mysqli_prepare($link, "DELETE FROM kit_items WHERE kit_id = ?");
                mysqli_stmt_bind_param($del, "i", $form_id);
                mysqli_stmt_execute($del);

                $target_id = $form_id;
            } else {
                $registered_by = (int) $_SESSION["id"];
                $sql  = "INSERT INTO kits (name, brand_id, management_type_id, classification_id, description, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "siiissi", $name, $brand_id, $management_type_id, $classification_id, $description, $status, $registered_by);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }
                $target_id = mysqli_insert_id($link);
            }

            $itemStmt = mysqli_prepare($link, "INSERT INTO kit_items (kit_id, article_id, quantity) VALUES (?, ?, ?)");
            foreach ($valid_items as $aid => $qty) {
                mysqli_stmt_bind_param($itemStmt, "iid", $target_id, $aid, $qty);
                if (!mysqli_stmt_execute($itemStmt)) {
                    throw new Exception(mysqli_error($link));
                }
            }

            mysqli_commit($link);
            $_SESSION["flash_success"] = $form_id > 0 ? "Kit actualizado correctamente." : "Kit creado correctamente.";
            header("location: admin-kits-list.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($link);
            $error_msg = "No se pudo guardar el kit: " . $e->getMessage();
        }
    }

    $kit = [
        "id" => $form_id, "name" => $name,
        "brand_id" => $brand_id, "management_type_id" => $management_type_id,
        "classification_id" => $classification_id, "description" => $description, "status" => $status,
    ];
    $kit_items_data = [];
    foreach ($valid_items as $aid => $qty) {
        $kit_items_data[] = ["article_id" => $aid, "quantity" => $qty];
    }
} else {
    $kit = ["id" => 0, "name" => "", "brand_id" => null, "management_type_id" => null, "classification_id" => null, "description" => "", "status" => "activo"];
    $kit_items_data = [];

    if ($kit_id > 0) {
        $stmt = mysqli_prepare($link, "SELECT id, name, brand_id, management_type_id, classification_id, description, status FROM kits WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $kit_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $kit = $row;
            $itemsRes = mysqli_query($link, "SELECT ki.article_id, ki.quantity, a.name AS article_name
                                             FROM kit_items ki
                                             JOIN articles a ON a.id = ki.article_id
                                             WHERE ki.kit_id = " . (int) $kit_id);
            while ($it = mysqli_fetch_assoc($itemsRes)) {
                $kit_items_data[] = $it;
            }
        }
    }
}

$brands          = mysqli_query($link, "SELECT id, name FROM brands WHERE status = 'activo' ORDER BY name");
$management_types = mysqli_query($link, "SELECT id, name FROM management_types ORDER BY id");
$classifications  = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY id");
$articles         = mysqli_query($link, "SELECT id, name, unit FROM articles WHERE status = 'activo' ORDER BY name");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title><?php echo $kit["id"] ? "Editar kit" : "Nuevo kit"; ?> | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18"><?php echo $kit["id"] ? "Editar kit" : "Nuevo kit"; ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-kits-list.php">Kits</a></li>
                                    <li class="breadcrumb-item active">Formulario</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="post" id="kitForm">
                                    <input type="hidden" name="id" value="<?php echo (int) $kit["id"]; ?>">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nombre del kit</label>
                                            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($kit["name"]); ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Estado</label>
                                            <select name="status" class="form-select">
                                                <option value="activo" <?php echo $kit["status"] === "activo" ? "selected" : ""; ?>>Activo</option>
                                                <option value="inactivo" <?php echo $kit["status"] === "inactivo" ? "selected" : ""; ?>>Inactivo</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Marca / Empresa</label>
                                            <select name="brand_id" class="form-select">
                                                <option value="">Todas las marcas</option>
                                                <?php while ($br = mysqli_fetch_assoc($brands)): ?>
                                                    <option value="<?php echo (int) $br["id"]; ?>" <?php echo (int) $kit["brand_id"] === (int) $br["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($br["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Tipo de gestion</label>
                                            <select name="management_type_id" class="form-select">
                                                <option value="">Cualquier tipo</option>
                                                <?php while ($mt = mysqli_fetch_assoc($management_types)): ?>
                                                    <option value="<?php echo (int) $mt["id"]; ?>" <?php echo (int) $kit["management_type_id"] === (int) $mt["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($mt["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Clasificacion de cliente</label>
                                            <select name="classification_id" class="form-select">
                                                <option value="">Cualquier clasificacion</option>
                                                <?php while ($cl = mysqli_fetch_assoc($classifications)): ?>
                                                    <option value="<?php echo (int) $cl["id"]; ?>" <?php echo (int) $kit["classification_id"] === (int) $cl["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($cl["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Descripcion</label>
                                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($kit["description"]); ?></textarea>
                                    </div>

                                    <hr>
                                    <h5 class="mb-3">Articulos del kit</h5>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle" id="itemsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width:250px">Articulo</th>
                                                    <th style="width:140px">Cantidad</th>
                                                    <th style="width:50px"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemsBody">
                                            </tbody>
                                        </table>
                                    </div>

                                    <button type="button" class="btn btn-soft-primary mb-3" id="addItemBtn">
                                        <i class="mdi mdi-plus me-1"></i> Agregar articulo
                                    </button>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">Guardar kit</button>
                                        <a href="admin-kits-list.php" class="btn btn-light">Cancelar</a>
                                    </div>
                                </form>
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

<script>
var ARTICLES = <?php
    $articleList = [];
    mysqli_data_seek($articles, 0);
    while ($a = mysqli_fetch_assoc($articles)) {
        $articleList[] = ["id" => (int) $a["id"], "name" => $a["name"], "unit" => $a["unit"]];
    }
    echo json_encode($articleList, JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

var EXISTING_ITEMS = <?php echo json_encode($kit_items_data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

var itemsBody = document.getElementById('itemsBody');

function buildArticleOptions(selectedId) {
    var html = '<option value="">Selecciona...</option>';
    ARTICLES.forEach(function (a) {
        var sel = (selectedId && parseInt(selectedId) === a.id) ? 'selected' : '';
        html += '<option value="' + a.id + '" ' + sel + '>' + a.name + ' (' + a.unit + ')</option>';
    });
    return html;
}

function addRow(item) {
    item = item || {};
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="item_article_id[]" class="form-select" required>' + buildArticleOptions(item.article_id) + '</select></td>' +
        '<td><input type="number" step="0.01" min="0.01" name="item_quantity[]" class="form-control" value="' + (item.quantity || 1) + '" required></td>' +
        '<td><button type="button" class="btn btn-sm btn-soft-danger remove-row"><i class="mdi mdi-delete"></i></button></td>';
    itemsBody.appendChild(tr);
    tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); });
}

document.getElementById('addItemBtn').addEventListener('click', function () { addRow(); });

if (EXISTING_ITEMS.length > 0) {
    EXISTING_ITEMS.forEach(function (it) { addRow(it); });
} else {
    addRow();
}
</script>

</body>

</html>
