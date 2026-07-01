<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 4]);

$error_msg = "";

// ---------------------------------------------------------------
// GUARDAR solicitud
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $notes       = trim($_POST["notes"] ?? "");
    $items_type  = $_POST["item_type"] ?? [];
    $items_ref   = $_POST["item_ref"] ?? [];
    $items_qty   = $_POST["item_quantity"] ?? [];

    $valid_items = [];
    foreach ($items_type as $idx => $type) {
        $ref = (int) ($items_ref[$idx] ?? 0);
        $qty = (float) ($items_qty[$idx] ?? 0);
        if (in_array($type, ["articulo", "kit"], true) && $ref > 0 && $qty > 0) {
            $valid_items[] = ["type" => $type, "ref" => $ref, "quantity" => $qty];
        }
    }

    if (empty($valid_items)) {
        $error_msg = "Agrega al menos un articulo o kit con cantidad valida.";
    } else {
        mysqli_begin_transaction($link);
        try {
            $requested_by = (int) $_SESSION["id"];
            $stmt = mysqli_prepare($link, "INSERT INTO requests (requested_by, notes) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "is", $requested_by, $notes);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_error($link));
            }
            $request_id = mysqli_insert_id($link);

            $itemStmt = mysqli_prepare($link, "INSERT INTO request_items (request_id, item_type, article_id, kit_id, quantity) VALUES (?, ?, ?, ?, ?)");
            foreach ($valid_items as $it) {
                $article_id = $it["type"] === "articulo" ? $it["ref"] : null;
                $kit_id     = $it["type"] === "kit" ? $it["ref"] : null;
                mysqli_stmt_bind_param($itemStmt, "isiid", $request_id, $it["type"], $article_id, $kit_id, $it["quantity"]);
                if (!mysqli_stmt_execute($itemStmt)) {
                    throw new Exception(mysqli_error($link));
                }
            }

            mysqli_commit($link);
            $_SESSION["flash_success"] = "Solicitud registrada correctamente.";
            header("location: admin-request-print.php?id=" . $request_id);
            exit;
        } catch (Exception $e) {
            mysqli_rollback($link);
            $error_msg = "No se pudo guardar la solicitud: " . $e->getMessage();
        }
    }
}

$articles = mysqli_query($link, "SELECT id, name, unit FROM articles WHERE status = 'activo' ORDER BY name");
$kits     = mysqli_query($link, "SELECT id, name FROM kits WHERE status = 'activo' ORDER BY name");
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Nueva solicitud | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Nueva solicitud</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-requests-list.php">Solicitudes</a></li>
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
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Fecha de solicitud</label>
                                        <input type="text" class="form-control" value="<?php echo date("d/m/Y H:i"); ?>" disabled>
                                        <div class="form-text">Se genera automaticamente al guardar.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Solicitado por</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION["username"] ?? ""); ?>" disabled>
                                    </div>
                                </div>

                                <form method="post" id="requestForm">
                                    <hr>
                                    <h5 class="mb-3">Articulos o kits solicitados</h5>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle" id="itemsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:140px">Tipo</th>
                                                    <th style="min-width:220px">Articulo / Kit</th>
                                                    <th style="width:120px">Cantidad</th>
                                                    <th style="width:50px"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemsBody"></tbody>
                                        </table>
                                    </div>

                                    <button type="button" class="btn btn-soft-primary mb-3" id="addItemBtn">
                                        <i class="mdi mdi-plus me-1"></i> Agregar linea
                                    </button>

                                    <div class="mb-3">
                                        <label class="form-label">Notas (opcional)</label>
                                        <textarea name="notes" class="form-control" rows="2" placeholder="Detalles adicionales sobre la solicitud..."></textarea>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">Generar solicitud</button>
                                        <a href="admin-requests-list.php" class="btn btn-light">Cancelar</a>
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
    while ($a = mysqli_fetch_assoc($articles)) {
        $articleList[] = ["id" => (int) $a["id"], "name" => $a["name"], "unit" => $a["unit"]];
    }
    echo json_encode($articleList, JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

var KITS = <?php
    $kitList = [];
    while ($k = mysqli_fetch_assoc($kits)) {
        $kitList[] = ["id" => (int) $k["id"], "name" => $k["name"]];
    }
    echo json_encode($kitList, JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

var itemsBody = document.getElementById('itemsBody');

function buildRefOptions(type) {
    var list = type === 'kit' ? KITS : ARTICLES;
    var html = '<option value="">Selecciona...</option>';
    list.forEach(function (item) {
        html += '<option value="' + item.id + '">' + item.name + '</option>';
    });
    return html;
}

function addRow() {
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="item_type[]" class="form-select item-type" required>' +
            '<option value="articulo">Articulo</option>' +
            '<option value="kit">Kit</option>' +
        '</select></td>' +
        '<td><select name="item_ref[]" class="form-select item-ref" required>' + buildRefOptions('articulo') + '</select></td>' +
        '<td><input type="number" step="0.01" min="0.01" name="item_quantity[]" class="form-control" value="1" required></td>' +
        '<td><button type="button" class="btn btn-sm btn-soft-danger remove-row"><i class="mdi mdi-delete"></i></button></td>';
    itemsBody.appendChild(tr);

    var typeSelect = tr.querySelector('.item-type');
    var refSelect = tr.querySelector('.item-ref');
    typeSelect.addEventListener('change', function () {
        refSelect.innerHTML = buildRefOptions(this.value);
    });
    tr.querySelector('.remove-row').addEventListener('click', function () {
        tr.remove();
    });
}

document.getElementById('addItemBtn').addEventListener('click', addRow);
addRow();
</script>

</body>

</html>
