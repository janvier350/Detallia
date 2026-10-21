<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 6, 3, 5]);

$sim_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$error_msg = "";

// ---------------------------------------------------------------
// Guardar (crear / editar)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save") {
    $name     = trim($_POST["name"] ?? "");
    $sim_date = trim($_POST["sim_date"] ?? "");
    $classId  = (int) ($_POST["classification_id"] ?? 0);
    $classId  = $classId > 0 ? $classId : null;
    $kitQty   = (int) ($_POST["kit_quantity"] ?? 1);
    if ($kitQty < 1) $kitQty = 1;
    $notes    = trim($_POST["notes"] ?? "");

    // Imagen del kit (opcional)
    $new_image_path = null;
    if (!empty($_FILES["image"]["name"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $ftype = mime_content_type($_FILES["image"]["tmp_name"]);
        $ext   = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        if (!in_array($ftype, $allowed)) {
            $error_msg = "Solo se permiten imagenes JPG, PNG, GIF o WEBP.";
        } elseif ($_FILES["image"]["size"] > 3 * 1024 * 1024) {
            $error_msg = "La imagen no puede superar 3 MB.";
        } else {
            $dir = __DIR__ . '/assets/images/kitsims/';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $fn = 'kitsim_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $dir . $fn)) {
                $new_image_path = 'assets/images/kitsims/' . $fn;
            }
        }
    }

    $products = $_POST["product_name"] ?? [];
    $prices   = $_POST["unit_price"] ?? [];
    $ivas     = $_POST["iva_rate"] ?? [];
    $qtys     = $_POST["quantity"] ?? [];
    $provIds  = $_POST["provider_id"] ?? [];
    $provNames = $_POST["provider_name"] ?? [];

    if ($name === "") {
        $error_msg = "El nombre del simulacro es obligatorio.";
    } elseif ($sim_date === "") {
        $error_msg = "La fecha es obligatoria.";
    } elseif ($error_msg === "") {
        $editing = $sim_id > 0;
        if ($editing) {
            if ($new_image_path !== null) {
                $stmt = mysqli_prepare($link, "UPDATE kit_simulations SET name=?, sim_date=?, classification_id=?, kit_quantity=?, image_path=?, notes=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "ssiissi", $name, $sim_date, $classId, $kitQty, $new_image_path, $notes, $sim_id);
            } else {
                $stmt = mysqli_prepare($link, "UPDATE kit_simulations SET name=?, sim_date=?, classification_id=?, kit_quantity=?, notes=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, "ssiisi", $name, $sim_date, $classId, $kitQty, $notes, $sim_id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_query($link, "DELETE FROM kit_simulation_items WHERE simulation_id = " . (int) $sim_id);
        } else {
            $createdBy = (int) $_SESSION["id"];
            $stmt = mysqli_prepare($link, "INSERT INTO kit_simulations (name, sim_date, classification_id, kit_quantity, image_path, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssiissi", $name, $sim_date, $classId, $kitQty, $new_image_path, $notes, $createdBy);
            mysqli_stmt_execute($stmt);
            $sim_id = mysqli_insert_id($link);
        }

        $itemStmt = mysqli_prepare($link, "INSERT INTO kit_simulation_items (simulation_id, product_name, provider_id, provider_name, unit_price, iva_rate, quantity, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $order = 0;
        for ($i = 0; $i < count($products); $i++) {
            $pname = trim($products[$i] ?? "");
            if ($pname === "") continue;
            $price = (float) ($prices[$i] ?? 0);
            $iva   = (float) ($ivas[$i] ?? 15);
            $qty   = (float) ($qtys[$i] ?? 1);
            if ($qty <= 0) $qty = 1;
            $pid   = (int) ($provIds[$i] ?? 0);
            $pid   = $pid > 0 ? $pid : null;
            $pn    = trim($provNames[$i] ?? "");
            $pn    = $pn !== "" ? $pn : null;
            mysqli_stmt_bind_param($itemStmt, "isisdddi", $sim_id, $pname, $pid, $pn, $price, $iva, $qty, $order);
            mysqli_stmt_execute($itemStmt);
            $order++;
        }

        $_SESSION["flash_success"] = $editing ? "Simulacro actualizado." : "Simulacro creado.";
        log_activity($link, $editing ? "edito" : "creo", "Simulador de kits", ($editing ? "Edito" : "Creo") . " simulacro #" . $sim_id, $name);
        header("location: admin-kit-sim-list.php");
        exit;
    }
}

// ---------------------------------------------------------------
// Cargar datos si es edicion
// ---------------------------------------------------------------
$sim = null;
$items = [];
if ($sim_id > 0) {
    $stmt = mysqli_prepare($link, "SELECT * FROM kit_simulations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $sim_id);
    mysqli_stmt_execute($stmt);
    $sim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$sim) { header("location: admin-kit-sim-list.php"); exit; }

    $ri = mysqli_query($link, "SELECT * FROM kit_simulation_items WHERE simulation_id = " . (int) $sim_id . " ORDER BY sort_order, id");
    while ($r = mysqli_fetch_assoc($ri)) { $items[] = $r; }
}

$classRes = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY name");
$classes = [];
while ($c = mysqli_fetch_assoc($classRes)) { $classes[] = $c; }

// Cotizaciones disponibles para elegir en el simulador
$quotes = [];
$qr = mysqli_query($link, "SELECT q.article_id, a.name AS article_name, q.provider_id, p.name AS provider_name,
                                  q.unit_price, q.iva_rate
                           FROM quotations q
                           JOIN articles a ON a.id = q.article_id
                           JOIN providers p ON p.id = q.provider_id
                           ORDER BY a.name, q.unit_price ASC");
while ($q = mysqli_fetch_assoc($qr)) {
    $quotes[] = [
        "article_id" => (int) $q["article_id"],
        "article_name" => $q["article_name"],
        "provider_id" => (int) $q["provider_id"],
        "provider_name" => $q["provider_name"],
        "unit_price" => (float) $q["unit_price"],
        "iva_rate" => (float) $q["iva_rate"],
    ];
}

$isEdit = $sim_id > 0;
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title><?php echo $isEdit ? "Editar" : "Nuevo"; ?> simulacro de kit | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18"><?php echo $isEdit ? "Editar simulacro de kit" : "Nuevo simulacro de kit"; ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-kit-sim-list.php">Simulador de kits</a></li>
                                    <li class="breadcrumb-item active"><?php echo $isEdit ? "Editar" : "Nuevo"; ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">

                    <!-- Cabecera -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Datos del simulacro</h5>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label">Nombre del simulacro <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" required
                                           value="<?php echo htmlspecialchars($sim["name"] ?? ""); ?>" placeholder="Ej. Kit Navidad opcion 1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Fecha de creacion <span class="text-danger">*</span></label>
                                    <input type="date" name="sim_date" class="form-control" required
                                           value="<?php echo htmlspecialchars($sim["sim_date"] ?? date('Y-m-d')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tipo de cliente</label>
                                    <select name="classification_id" class="form-select">
                                        <option value="">Sin especificar</option>
                                        <?php foreach ($classes as $cl): ?>
                                            <option value="<?php echo (int) $cl['id']; ?>" <?php echo (($sim["classification_id"] ?? 0) == $cl['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cl['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Cantidad de kits</label>
                                    <input type="number" name="kit_quantity" id="kitQty" class="form-control" min="1" step="1"
                                           value="<?php echo (int) ($sim["kit_quantity"] ?? 1); ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Imagen del kit (como queda)</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <div class="form-text">JPG, PNG, GIF o WEBP (max 3 MB).</div>
                                </div>
                                <?php if (!empty($sim["image_path"])): ?>
                                <div class="col-md-4 text-center">
                                    <img src="<?php echo htmlspecialchars($sim["image_path"]); ?>" alt="Kit" style="max-height:90px;border-radius:8px;border:1px solid #eee;">
                                    <div class="small text-muted">Imagen actual</div>
                                </div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <label class="form-label">Notas</label>
                                    <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($sim["notes"] ?? ""); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Productos -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">Productos del kit</h5>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-soft-success" id="pickQuoteBtn" data-bs-toggle="modal" data-bs-target="#quotePickModal"><i class="mdi mdi-tag-text-outline me-1"></i> Elegir producto cotizado</button>
                                    <button type="button" class="btn btn-soft-primary" id="addRowBtn"><i class="mdi mdi-plus me-1"></i> Agregar producto</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0" id="itemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width:200px">Producto</th>
                                            <th style="min-width:150px">Proveedor</th>
                                            <th style="width:130px">Precio unit.</th>
                                            <th style="width:110px">IVA %</th>
                                            <th style="width:100px">Cantidad</th>
                                            <th style="width:130px" class="text-end">Subtotal</th>
                                            <th style="width:130px" class="text-end">Total c/IVA</th>
                                            <th style="width:50px"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsBody"><!-- filas por JS --></tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <th colspan="5" class="text-end">Total por kit</th>
                                            <th class="text-end"><span id="grandSubtotal">0.00</span></th>
                                            <th class="text-end"><span id="grandTotal">0.00</span></th>
                                            <th></th>
                                        </tr>
                                        <tr class="table-light">
                                            <th colspan="6" class="text-end">Total x <span id="kitQtyLbl">1</span> kit(s)</th>
                                            <th class="text-end text-primary">$<span id="grandTotalKits">0.00</span></th>
                                            <th></th>
                                        </tr>
                                        <tr>
                                            <td colspan="8" class="text-end small text-muted">
                                                IVA total (por kit): <span id="grandIva">0.00</span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save-outline me-1"></i> Guardar simulacro</button>
                        <a href="admin-kit-sim-list.php" class="btn btn-light">Cancelar</a>
                    </div>
                </form>

            </div>
        </div>
        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>

<!-- Modal: elegir producto cotizado -->
<div class="modal fade" id="quotePickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-tag-text-outline me-1"></i> Productos cotizados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="quoteSearch" class="form-control" placeholder="Buscar producto o proveedor..." autocomplete="off">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Producto</th><th>Proveedor</th><th class="text-end">Precio</th><th class="text-end">Total c/IVA</th><th></th></tr>
                        </thead>
                        <tbody id="quoteModalBody"></tbody>
                    </table>
                    <p class="text-muted text-center my-3 d-none" id="quoteNoResults">No hay cotizaciones. Registralas en el modulo Cotizaciones.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<script>
var EXISTING_ITEMS = <?php echo json_encode(array_map(function ($r) {
    return [
        "product_name" => $r["product_name"],
        "provider_id"  => $r["provider_id"] !== null ? (int) $r["provider_id"] : "",
        "provider_name" => $r["provider_name"],
        "unit_price"   => (float) $r["unit_price"],
        "iva_rate"     => (float) $r["iva_rate"],
        "quantity"     => (float) $r["quantity"],
    ];
}, $items)); ?>;

var QUOTES = <?php echo json_encode($quotes); ?>;
var DEFAULT_IVA = 15;

function money(n) { return (isNaN(n) ? 0 : n).toFixed(2); }
function escq(s) { return s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/"/g, '&quot;').replace(/</g,'&lt;'); }

function addRow(item) {
    item = item || {};
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><input type="text" name="product_name[]" class="form-control form-control-sm" value="' + escq(item.product_name) + '"></td>' +
        '<td>' +
            '<input type="hidden" name="provider_id[]" class="it-provid" value="' + (item.provider_id != null ? item.provider_id : '') + '">' +
            '<input type="text" name="provider_name[]" class="form-control form-control-sm it-provname" value="' + escq(item.provider_name) + '" placeholder="(opcional)">' +
        '</td>' +
        '<td><input type="number" step="0.01" min="0" name="unit_price[]" class="form-control form-control-sm text-end it-price" value="' + (item.unit_price != null ? item.unit_price : '') + '"></td>' +
        '<td><input type="number" step="0.01" min="0" name="iva_rate[]" class="form-control form-control-sm text-end it-iva" value="' + (item.iva_rate != null ? item.iva_rate : DEFAULT_IVA) + '"></td>' +
        '<td><input type="number" step="0.01" min="0" name="quantity[]" class="form-control form-control-sm text-end it-qty" value="' + (item.quantity != null ? item.quantity : 1) + '"></td>' +
        '<td class="text-end it-subtotal">0.00</td>' +
        '<td class="text-end it-total">0.00</td>' +
        '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger it-del"><i class="mdi mdi-close"></i></button></td>';
    document.getElementById('itemsBody').appendChild(tr);
    recalc();
}

function recalc() {
    var subtotalSum = 0, ivaSum = 0, totalSum = 0;
    document.querySelectorAll('#itemsBody tr').forEach(function (tr) {
        var price = parseFloat(tr.querySelector('.it-price').value) || 0;
        var iva   = parseFloat(tr.querySelector('.it-iva').value) || 0;
        var qty   = parseFloat(tr.querySelector('.it-qty').value) || 0;
        var sub   = price * qty;
        var ivaAmt = sub * (iva / 100);
        var tot   = sub + ivaAmt;
        tr.querySelector('.it-subtotal').innerText = money(sub);
        tr.querySelector('.it-total').innerText = money(tot);
        subtotalSum += sub; ivaSum += ivaAmt; totalSum += tot;
    });
    document.getElementById('grandSubtotal').innerText = money(subtotalSum);
    document.getElementById('grandIva').innerText = money(ivaSum);
    document.getElementById('grandTotal').innerText = money(totalSum);
    var kq = parseInt(document.getElementById('kitQty').value) || 1;
    if (kq < 1) kq = 1;
    document.getElementById('kitQtyLbl').innerText = kq;
    document.getElementById('grandTotalKits').innerText = money(totalSum * kq);
}

document.getElementById('kitQty').addEventListener('input', recalc);

document.getElementById('addRowBtn').addEventListener('click', function () { addRow(); });

document.getElementById('itemsBody').addEventListener('input', recalc);
document.getElementById('itemsBody').addEventListener('click', function (e) {
    var btn = e.target.closest('.it-del');
    if (btn) { btn.closest('tr').remove(); recalc(); }
});

// Inicializar
if (EXISTING_ITEMS.length > 0) {
    EXISTING_ITEMS.forEach(function (it) { addRow(it); });
} else {
    addRow(); addRow(); addRow();
}

// ---- Modal de cotizaciones ----
(function () {
    var body = document.getElementById('quoteModalBody');
    var search = document.getElementById('quoteSearch');
    var noRes = document.getElementById('quoteNoResults');
    if (!body) return;

    // Marcar la cotizacion mas barata por producto
    var cheapest = {};
    QUOTES.forEach(function (q) {
        if (cheapest[q.article_id] == null || q.unit_price < cheapest[q.article_id]) cheapest[q.article_id] = q.unit_price;
    });

    function render(filter) {
        filter = (filter || '').toLowerCase();
        var html = ''; var shown = 0;
        QUOTES.forEach(function (q, idx) {
            var hay = (q.article_name + ' ' + q.provider_name).toLowerCase();
            if (filter && hay.indexOf(filter) === -1) return;
            shown++;
            var tot = q.unit_price * (1 + q.iva_rate / 100);
            var best = (q.unit_price === cheapest[q.article_id]) ? ' <span class="badge bg-success">Mas barato</span>' : '';
            html += '<tr>' +
                '<td>' + escq(q.article_name) + best + '</td>' +
                '<td>' + escq(q.provider_name) + '</td>' +
                '<td class="text-end">$' + money(q.unit_price) + '</td>' +
                '<td class="text-end">$' + money(tot) + '</td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-soft-primary choose-quote" data-idx="' + idx + '">Elegir</button></td>' +
                '</tr>';
        });
        body.innerHTML = html;
        noRes.classList.toggle('d-none', shown > 0);
    }

    search.addEventListener('input', function () { render(this.value); });
    document.getElementById('quotePickModal').addEventListener('shown.bs.modal', function () {
        render(search.value);
        search.focus();
    });
    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.choose-quote');
        if (!btn) return;
        var q = QUOTES[parseInt(btn.getAttribute('data-idx'))];
        if (q) {
            addRow({
                product_name: q.article_name,
                provider_id: q.provider_id,
                provider_name: q.provider_name,
                unit_price: q.unit_price,
                iva_rate: q.iva_rate,
                quantity: 1
            });
        }
        bootstrap.Modal.getInstance(document.getElementById('quotePickModal')).hide();
    });
})();
</script>

</body>
</html>
