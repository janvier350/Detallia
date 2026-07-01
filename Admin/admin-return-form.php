<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3]);

$error_msg = "";

// ---------------------------------------------------------------
// GUARDAR devolucion
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $delivery_id   = (int) ($_POST["delivery_id"] ?? 0);
    $return_date   = trim($_POST["return_date"] ?? "");
    $reason        = trim($_POST["reason"] ?? "");
    $items_article = $_POST["item_article_id"] ?? [];
    $items_qty     = $_POST["item_quantity"] ?? [];

    $valid_items = [];
    foreach ($items_article as $idx => $aid) {
        $aid = (int) $aid;
        $qty = (float) ($items_qty[$idx] ?? 0);
        if ($aid > 0 && $qty > 0) {
            $valid_items[] = ["article_id" => $aid, "quantity" => $qty];
        }
    }

    if ($delivery_id <= 0 || $return_date === "" || $reason === "") {
        $error_msg = "Entrega, fecha y motivo de la devolucion son obligatorios.";
    } elseif (empty($valid_items)) {
        $error_msg = "Selecciona al menos un articulo con cantidad a devolver.";
    } else {
        // Datos de la entrega original (no confiar en lo que venga del formulario)
        $stmt = mysqli_prepare($link, "SELECT client_id, kit_id FROM kit_deliveries WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $delivery_id);
        mysqli_stmt_execute($stmt);
        $deliveryRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$deliveryRow) {
            $error_msg = "La entrega seleccionada no existe.";
        } else {
            $client_id = (int) $deliveryRow["client_id"];
            $kit_id    = (int) $deliveryRow["kit_id"];

            // Cantidades entregadas por articulo (segun el kit)
            $delivered = [];
            $res = mysqli_prepare($link, "SELECT article_id, quantity FROM kit_items WHERE kit_id = ?");
            mysqli_stmt_bind_param($res, "i", $kit_id);
            mysqli_stmt_execute($res);
            $resResult = mysqli_stmt_get_result($res);
            while ($row = mysqli_fetch_assoc($resResult)) {
                $delivered[(int) $row["article_id"]] = (float) $row["quantity"];
            }

            // Cantidades ya devueltas por articulo para esta entrega
            $alreadyReturned = [];
            $res2 = mysqli_prepare($link, "SELECT ri.article_id, SUM(ri.quantity) AS qty
                                            FROM return_items ri
                                            JOIN returns r ON r.id = ri.return_id
                                            WHERE r.delivery_id = ?
                                            GROUP BY ri.article_id");
            mysqli_stmt_bind_param($res2, "i", $delivery_id);
            mysqli_stmt_execute($res2);
            $res2Result = mysqli_stmt_get_result($res2);
            while ($row = mysqli_fetch_assoc($res2Result)) {
                $alreadyReturned[(int) $row["article_id"]] = (float) $row["qty"];
            }

            foreach ($valid_items as $it) {
                $max = ($delivered[$it["article_id"]] ?? 0) - ($alreadyReturned[$it["article_id"]] ?? 0);
                if ($it["quantity"] > $max) {
                    $error_msg = "La cantidad a devolver supera lo disponible para uno o mas articulos.";
                    break;
                }
            }

            if ($error_msg === "") {
                mysqli_begin_transaction($link);
                try {
                    $registered_by = (int) $_SESSION["id"];
                    $sql = "INSERT INTO returns (delivery_id, client_id, return_date, reason, registered_by) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($link, $sql);
                    mysqli_stmt_bind_param($stmt, "iissi", $delivery_id, $client_id, $return_date, $reason, $registered_by);
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_error($link));
                    }
                    $return_id = mysqli_insert_id($link);

                    $itemStmt = mysqli_prepare($link, "INSERT INTO return_items (return_id, article_id, quantity) VALUES (?, ?, ?)");
                    $movStmt  = mysqli_prepare($link, "INSERT INTO stock_movements (article_id, movement_type, quantity, reference_type, reference_id, created_by) VALUES (?, 'devolucion', ?, 'devolucion', ?, ?)");
                    foreach ($valid_items as $it) {
                        mysqli_stmt_bind_param($itemStmt, "iid", $return_id, $it["article_id"], $it["quantity"]);
                        if (!mysqli_stmt_execute($itemStmt)) {
                            throw new Exception(mysqli_error($link));
                        }
                        mysqli_stmt_bind_param($movStmt, "idii", $it["article_id"], $it["quantity"], $return_id, $registered_by);
                        if (!mysqli_stmt_execute($movStmt)) {
                            throw new Exception(mysqli_error($link));
                        }
                    }

                    mysqli_commit($link);
                    $_SESSION["flash_success"] = "Devolucion registrada correctamente.";
                    header("location: admin-returns-list.php");
                    exit;
                } catch (Exception $e) {
                    mysqli_rollback($link);
                    $error_msg = "No se pudo guardar la devolucion: " . $e->getMessage();
                }
            }
        }
    }
}

// ---------------------------------------------------------------
// Entregas disponibles + su contenido y lo ya devuelto (para JS)
// ---------------------------------------------------------------
$deliveries = mysqli_query($link, "SELECT d.id, d.delivery_date, k.name AS kit_name, c.name AS client_name
                                    FROM kit_deliveries d
                                    JOIN kits k ON k.id = d.kit_id
                                    JOIN clients c ON c.id = d.client_id
                                    ORDER BY d.id DESC");

$deliveryItems = [];
$res = mysqli_query($link, "SELECT d.id AS delivery_id, a.id AS article_id, a.name AS article_name, a.unit, ki.quantity
                             FROM kit_deliveries d
                             JOIN kit_items ki ON ki.kit_id = d.kit_id
                             JOIN articles a ON a.id = ki.article_id
                             ORDER BY d.id, a.name");
while ($row = mysqli_fetch_assoc($res)) {
    $did = (int) $row["delivery_id"];
    if (!isset($deliveryItems[$did])) {
        $deliveryItems[$did] = [];
    }
    $deliveryItems[$did][] = [
        "article_id" => (int) $row["article_id"],
        "article_name" => $row["article_name"],
        "unit" => $row["unit"],
        "quantity" => (float) $row["quantity"],
    ];
}

$returnedByDelivery = [];
$res = mysqli_query($link, "SELECT r.delivery_id, ri.article_id, SUM(ri.quantity) AS qty
                             FROM return_items ri
                             JOIN returns r ON r.id = ri.return_id
                             GROUP BY r.delivery_id, ri.article_id");
while ($row = mysqli_fetch_assoc($res)) {
    $returnedByDelivery[(int) $row["delivery_id"]][(int) $row["article_id"]] = (float) $row["qty"];
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Registrar devolucion | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Registrar devolucion</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-returns-list.php">Devoluciones</a></li>
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
                                <form method="post" id="returnForm">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Entrega original</label>
                                            <select name="delivery_id" id="deliverySelect" class="form-select" required>
                                                <option value="">Selecciona una entrega...</option>
                                                <?php while ($d = mysqli_fetch_assoc($deliveries)): ?>
                                                    <option value="<?php echo (int) $d["id"]; ?>">
                                                        #<?php echo (int) $d["id"]; ?> — <?php echo htmlspecialchars($d["delivery_date"]); ?> — <?php echo htmlspecialchars($d["client_name"]); ?> — <?php echo htmlspecialchars($d["kit_name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Fecha de devolucion</label>
                                            <input type="date" name="return_date" class="form-control" required value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Motivo de la devolucion</label>
                                        <textarea name="reason" class="form-control" rows="2" required placeholder="Explica por que se devuelven estos articulos..."></textarea>
                                    </div>

                                    <hr>
                                    <h5 class="mb-3">Articulos a devolver</h5>
                                    <div id="itemsWrap">
                                        <p class="text-muted">Selecciona una entrega para ver sus articulos.</p>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">Guardar devolucion</button>
                                        <a href="admin-returns-list.php" class="btn btn-light">Cancelar</a>
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
var DELIVERY_ITEMS = <?php echo json_encode($deliveryItems, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var RETURNED_BY_DELIVERY = <?php echo json_encode($returnedByDelivery, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

var itemsWrap = document.getElementById('itemsWrap');

document.getElementById('deliverySelect').addEventListener('change', function () {
    var deliveryId = this.value;
    var items = DELIVERY_ITEMS[deliveryId];

    if (!items || items.length === 0) {
        itemsWrap.innerHTML = '<p class="text-muted">Esta entrega no tiene articulos registrados.</p>';
        return;
    }

    var returned = RETURNED_BY_DELIVERY[deliveryId] || {};
    var html = '<div class="table-responsive"><table class="table table-bordered align-middle">' +
        '<thead class="table-light"><tr><th style="width:40px"></th><th>Articulo</th><th>Entregado</th><th>Ya devuelto</th><th style="width:150px">Cantidad a devolver</th></tr></thead><tbody>';

    items.forEach(function (it) {
        var alreadyReturned = returned[it.article_id] || 0;
        var available = it.quantity - alreadyReturned;
        html += '<tr>' +
            '<td><input type="checkbox" class="form-check-input item-check" data-max="' + available + '" ' + (available <= 0 ? 'disabled' : '') + '></td>' +
            '<td>' + it.article_name + ' <input type="hidden" class="item-article-id" value="' + it.article_id + '"></td>' +
            '<td>' + it.quantity + ' ' + it.unit + '</td>' +
            '<td>' + alreadyReturned + ' ' + it.unit + '</td>' +
            '<td><input type="number" step="0.01" min="0" max="' + available + '" class="form-control item-qty" value="' + available + '" disabled></td>' +
            '</tr>';
    });

    html += '</tbody></table></div>';
    itemsWrap.innerHTML = html;

    itemsWrap.querySelectorAll('.item-check').forEach(function (chk) {
        chk.addEventListener('change', function () {
            var qtyInput = this.closest('tr').querySelector('.item-qty');
            qtyInput.disabled = !this.checked;
        });
    });
});

document.getElementById('returnForm').addEventListener('submit', function (e) {
    var rows = itemsWrap.querySelectorAll('tbody tr');
    var hasSelection = false;
    rows.forEach(function (tr) {
        var chk = tr.querySelector('.item-check');
        if (chk && chk.checked) {
            hasSelection = true;
            var articleId = tr.querySelector('.item-article-id').value;
            var qty = tr.querySelector('.item-qty').value;

            var hiddenArticle = document.createElement('input');
            hiddenArticle.type = 'hidden';
            hiddenArticle.name = 'item_article_id[]';
            hiddenArticle.value = articleId;
            e.target.appendChild(hiddenArticle);

            var hiddenQty = document.createElement('input');
            hiddenQty.type = 'hidden';
            hiddenQty.name = 'item_quantity[]';
            hiddenQty.value = qty;
            e.target.appendChild(hiddenQty);
        }
    });
    if (!hasSelection) {
        e.preventDefault();
        alert('Selecciona al menos un articulo a devolver.');
    }
});
</script>

</body>

</html>
