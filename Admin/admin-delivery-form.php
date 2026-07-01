<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]);

$delivery_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$error_msg   = "";

// ---------------------------------------------------------------
// GUARDAR entrega (crear o editar)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_id            = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $kit_id             = (int) ($_POST["kit_id"] ?? 0);
    $client_id          = (int) ($_POST["client_id"] ?? 0);
    $management_type_id = (int) ($_POST["management_type_id"] ?? 0);
    $delivery_date      = trim($_POST["delivery_date"] ?? "");
    $notes              = trim($_POST["notes"] ?? "");

    $management_type_id = $management_type_id > 0 ? $management_type_id : null;
    $delivered_by       = (int) $_SESSION["id"];

    if ($kit_id <= 0 || $client_id <= 0 || $delivery_date === "") {
        $error_msg = "Kit, cliente y fecha son obligatorios.";
    } else {
        mysqli_begin_transaction($link);
        try {
            if ($form_id > 0) {
                $sql  = "UPDATE kit_deliveries SET kit_id=?, client_id=?, management_type_id=?, delivery_date=?, notes=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "iiissi", $kit_id, $client_id, $management_type_id, $delivery_date, $notes, $form_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }
                $target_id = $form_id;
            } else {
                $sql  = "INSERT INTO kit_deliveries (kit_id, client_id, management_type_id, delivery_date, delivered_by, notes) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "iiisis", $kit_id, $client_id, $management_type_id, $delivery_date, $delivered_by, $notes);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }
                $target_id = mysqli_insert_id($link);
            }

            // Reconstruir movimientos de stock (salida) segun el contenido del kit
            $delMov = mysqli_prepare($link, "DELETE FROM stock_movements WHERE reference_type = 'entrega' AND reference_id = ?");
            mysqli_stmt_bind_param($delMov, "i", $target_id);
            mysqli_stmt_execute($delMov);

            $kitItemsRes = mysqli_prepare($link, "SELECT article_id, quantity FROM kit_items WHERE kit_id = ?");
            mysqli_stmt_bind_param($kitItemsRes, "i", $kit_id);
            mysqli_stmt_execute($kitItemsRes);
            $kitItemsResult = mysqli_stmt_get_result($kitItemsRes);

            $movStmt = mysqli_prepare($link, "INSERT INTO stock_movements (article_id, movement_type, quantity, reference_type, reference_id, created_by) VALUES (?, 'entrega', ?, 'entrega', ?, ?)");
            while ($ki = mysqli_fetch_assoc($kitItemsResult)) {
                $neg_qty = -1 * (float) $ki["quantity"];
                mysqli_stmt_bind_param($movStmt, "idii", $ki["article_id"], $neg_qty, $target_id, $delivered_by);
                if (!mysqli_stmt_execute($movStmt)) {
                    throw new Exception(mysqli_error($link));
                }
            }

            mysqli_commit($link);
            $_SESSION["flash_success"] = $form_id > 0 ? "Entrega actualizada correctamente." : "Entrega registrada correctamente.";
            header("location: admin-deliveries-list.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($link);
            $error_msg = "No se pudo guardar la entrega: " . $e->getMessage();
        }
    }

    $delivery = [
        "id" => $form_id, "kit_id" => $kit_id, "client_id" => $client_id,
        "management_type_id" => $management_type_id, "delivery_date" => $delivery_date, "notes" => $notes,
    ];
} else {
    $delivery = ["id" => 0, "kit_id" => 0, "client_id" => 0, "management_type_id" => 0, "delivery_date" => date("Y-m-d"), "notes" => ""];

    if ($delivery_id > 0) {
        $stmt = mysqli_prepare($link, "SELECT id, kit_id, client_id, management_type_id, delivery_date, notes FROM kit_deliveries WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $delivery_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $delivery = $row;
        }
    }
}

$kits             = mysqli_query($link, "SELECT id, name FROM kits WHERE status = 'activo' ORDER BY name") ?: false;
$clients          = mysqli_query($link, "SELECT id, name FROM clients WHERE status = 'activo' ORDER BY name") ?: false;
$management_types = mysqli_query($link, "SELECT id, name FROM management_types ORDER BY id") ?: false;

// Imagenes de los kits para el panel de vista previa
$kitsImages = [];
$resImg = mysqli_query($link, "SELECT id, image_path FROM kits");
if ($resImg) {
    while ($row = mysqli_fetch_assoc($resImg)) {
        $kitsImages[(int) $row["id"]] = $row["image_path"];
    }
}

// Kit items preview for JS panel
$kitsWithItems = [];
$res = mysqli_query($link, "SELECT ki.kit_id, ki.article_id, a.name AS article_name, ki.quantity, a.unit,
                                    COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.article_id = a.id), 0) AS stock
                             FROM kit_items ki
                             JOIN articles a ON a.id = ki.article_id
                             ORDER BY ki.kit_id, a.name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $kitsWithItems[(int) $row["kit_id"]][] = $row;
    }
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title><?php echo $delivery["id"] ? "Editar entrega" : "Registrar entrega"; ?> | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18"><?php echo $delivery["id"] ? "Editar entrega" : "Registrar entrega de kit"; ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-deliveries-list.php">Entregas</a></li>
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
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="id" value="<?php echo (int) $delivery["id"]; ?>">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Kit a entregar</label>
                                            <select name="kit_id" id="kitSelect" class="form-select" required>
                                                <option value="">Selecciona un kit...</option>
                                                <?php while ($kits && ($k = mysqli_fetch_assoc($kits))): ?>
                                                    <option value="<?php echo (int) $k["id"]; ?>" <?php echo (int) $delivery["kit_id"] === (int) $k["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($k["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Cliente</label>
                                            <select name="client_id" class="form-select" required>
                                                <option value="">Selecciona un cliente...</option>
                                                <?php while ($clients && ($c = mysqli_fetch_assoc($clients))): ?>
                                                    <option value="<?php echo (int) $c["id"]; ?>" <?php echo (int) $delivery["client_id"] === (int) $c["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($c["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tipo de gestion</label>
                                            <select name="management_type_id" class="form-select">
                                                <option value="">Sin especificar</option>
                                                <?php while ($management_types && ($mt = mysqli_fetch_assoc($management_types))): ?>
                                                    <option value="<?php echo (int) $mt["id"]; ?>" <?php echo (int) $delivery["management_type_id"] === (int) $mt["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($mt["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Fecha de entrega</label>
                                            <input type="date" name="delivery_date" class="form-control" required value="<?php echo htmlspecialchars($delivery["delivery_date"]); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Notas</label>
                                        <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($delivery["notes"]); ?></textarea>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">Guardar entrega</button>
                                        <a href="admin-deliveries-list.php" class="btn btn-light">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Kit seleccionado</h5>
                                <div id="kitImageWrap" class="text-center mb-3" style="display:none;">
                                    <img id="kitImage" src="" alt="Kit" style="max-width:100%;max-height:220px;border-radius:6px;object-fit:cover;">
                                </div>
                                <h6 class="text-muted">Contenido del kit</h6>
                                <div id="kitPreview">
                                    <p class="text-muted">Selecciona un kit para ver su contenido.</p>
                                </div>
                                <?php if ((int) $delivery["id"] > 0): ?>
                                    <div class="mt-3">
                                        <a href="admin-delivery-print.php?id=<?php echo (int) $delivery["id"]; ?>" target="_blank" class="btn btn-outline-secondary w-100">
                                            <i class="mdi mdi-printer me-1"></i> Imprimir documento de entrega
                                        </a>
                                    </div>
                                <?php endif; ?>
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
var KITS_ITEMS  = <?php echo json_encode($kitsWithItems, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var KITS_IMAGES = <?php echo json_encode($kitsImages, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function renderKitPreview(kitId) {
    var preview = document.getElementById('kitPreview');
    var items   = KITS_ITEMS[kitId];
    if (!items || items.length === 0) {
        preview.innerHTML = '<p class="text-muted">Este kit no tiene articulos registrados.</p>';
    } else {
        var html = '<ul class="list-group list-group-flush">';
        items.forEach(function (it) {
            var insufficient = parseFloat(it.stock) < parseFloat(it.quantity);
            var badgeClass = insufficient ? 'bg-danger' : 'bg-secondary';
            html += '<li class="list-group-item px-0">' + it.article_name + ' <span class="badge ' + badgeClass + ' float-end">' + it.quantity + ' ' + it.unit + '</span>';
            if (insufficient) {
                html += '<br><small class="text-danger">Stock insuficiente (disponible: ' + it.stock + ')</small>';
            }
            html += '</li>';
        });
        html += '</ul>';
        preview.innerHTML = html;
    }

    var imageWrap = document.getElementById('kitImageWrap');
    var image     = document.getElementById('kitImage');
    var imagePath = KITS_IMAGES[kitId];
    if (imagePath) {
        image.src = imagePath;
        imageWrap.style.display = '';
    } else {
        imageWrap.style.display = 'none';
    }
}

var kitSelect = document.getElementById('kitSelect');
kitSelect.addEventListener('change', function () {
    renderKitPreview(this.value);
});

if (kitSelect.value) {
    renderKitPreview(kitSelect.value);
}
</script>

</body>

</html>
