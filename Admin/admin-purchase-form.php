<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_role([1, 2, 3]); // Todos los roles pueden registrar compras

$invoice_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$error_msg = "";

// ---------------------------------------------------------------
// GUARDAR factura (crear o editar)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_id        = isset($_POST["id"]) ? (int) $_POST["id"] : 0;
    $provider_id    = (int) ($_POST["provider_id"] ?? 0);
    $invoice_number = trim($_POST["invoice_number"] ?? "");
    $purchase_date  = trim($_POST["purchase_date"] ?? "");
    $currency       = trim($_POST["currency"] ?? "USD");
    $notes          = trim($_POST["notes"] ?? "");
    $items_article  = $_POST["item_article_id"] ?? [];
    $items_qty      = $_POST["item_quantity"] ?? [];
    $items_price    = $_POST["item_unit_price"] ?? [];
    $items_iva      = $_POST["item_iva_rate"] ?? [];

    $valid_items = [];
    foreach ($items_article as $idx => $aid) {
        $aid = (int) $aid;
        $qty = (float) ($items_qty[$idx] ?? 0);
        $price = (float) ($items_price[$idx] ?? 0);
        $iva = isset($items_iva[$idx]) ? (float) $items_iva[$idx] : 15;
        if ($iva < 0) $iva = 0;
        if ($aid > 0 && $qty > 0 && $price >= 0) {
            $valid_items[] = ["article_id" => $aid, "quantity" => $qty, "unit_price" => $price, "iva_rate" => $iva];
        }
    }

    if ($provider_id <= 0 || $invoice_number === "" || $purchase_date === "") {
        $error_msg = "Proveedor, numero de factura y fecha son obligatorios.";
    } elseif (empty($valid_items)) {
        $error_msg = "Agrega al menos un articulo con cantidad y precio validos.";
    } else {
        $total = 0;
        foreach ($valid_items as $it) {
            $total += $it["quantity"] * $it["unit_price"] * (1 + $it["iva_rate"] / 100);
        }

        mysqli_begin_transaction($link);
        try {
            if ($form_id > 0) {
                $sql = "UPDATE purchase_invoices SET provider_id=?, invoice_number=?, purchase_date=?, currency=?, total_amount=?, notes=? WHERE id=?";
                $stmt = mysqli_prepare($link, $sql);
                mysqli_stmt_bind_param($stmt, "isssdsi", $provider_id, $invoice_number, $purchase_date, $currency, $total, $notes, $form_id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }

                $del = mysqli_prepare($link, "DELETE FROM purchase_invoice_items WHERE invoice_id = ?");
                mysqli_stmt_bind_param($del, "i", $form_id);
                mysqli_stmt_execute($del);

                $target_id = $form_id;
            } else {
                $sql = "INSERT INTO purchase_invoices (provider_id, invoice_number, purchase_date, currency, total_amount, notes, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($link, $sql);
                $registered_by = (int) $_SESSION["id"];
                mysqli_stmt_bind_param($stmt, "isssdsi", $provider_id, $invoice_number, $purchase_date, $currency, $total, $notes, $registered_by);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($link));
                }
                $target_id = mysqli_insert_id($link);
            }

            $itemStmt = mysqli_prepare($link, "INSERT INTO purchase_invoice_items (invoice_id, article_id, quantity, unit_price, iva_rate) VALUES (?, ?, ?, ?, ?)");
            foreach ($valid_items as $it) {
                mysqli_stmt_bind_param($itemStmt, "iiddd", $target_id, $it["article_id"], $it["quantity"], $it["unit_price"], $it["iva_rate"]);
                if (!mysqli_stmt_execute($itemStmt)) {
                    throw new Exception(mysqli_error($link));
                }
            }

            // Reconstruir movimientos de stock (entrada) para esta factura
            $delMov = mysqli_prepare($link, "DELETE FROM stock_movements WHERE reference_type = 'compra' AND reference_id = ?");
            mysqli_stmt_bind_param($delMov, "i", $target_id);
            mysqli_stmt_execute($delMov);

            $registered_by_mov = (int) $_SESSION["id"];
            $movStmt = mysqli_prepare($link, "INSERT INTO stock_movements (article_id, movement_type, quantity, reference_type, reference_id, created_by) VALUES (?, 'compra', ?, 'compra', ?, ?)");
            foreach ($valid_items as $it) {
                mysqli_stmt_bind_param($movStmt, "idii", $it["article_id"], $it["quantity"], $target_id, $registered_by_mov);
                if (!mysqli_stmt_execute($movStmt)) {
                    throw new Exception(mysqli_error($link));
                }
            }

            mysqli_commit($link);
            $_SESSION["flash_success"] = $form_id > 0 ? "Factura actualizada correctamente." : "Factura registrada correctamente.";
            header("location: admin-purchases-list.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($link);
            if (strpos($e->getMessage(), "uq_provider_invoice") !== false) {
                $error_msg = "Ya existe una factura con ese numero para este proveedor.";
            } else {
                $error_msg = "No se pudo guardar la factura: " . $e->getMessage();
            }
        }
    }

    // Si hubo error, recargar variables para re-renderizar el formulario con lo que el usuario escribio
    $invoice = [
        "id" => $form_id,
        "provider_id" => $provider_id,
        "invoice_number" => $invoice_number,
        "purchase_date" => $purchase_date,
        "currency" => $currency,
        "notes" => $notes,
    ];
    $invoice_items = $valid_items;
} else {
    // ---------------------------------------------------------------
    // Cargar factura existente (modo edicion)
    // ---------------------------------------------------------------
    $invoice = ["id" => 0, "provider_id" => 0, "invoice_number" => "", "purchase_date" => date("Y-m-d"), "currency" => "USD", "notes" => ""];
    $invoice_items = [];

    if ($invoice_id > 0) {
        $stmt = mysqli_prepare($link, "SELECT id, provider_id, invoice_number, purchase_date, currency, notes FROM purchase_invoices WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $invoice_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $invoice = $row;

            $itemsRes = mysqli_query($link, "SELECT pii.article_id, pii.quantity, pii.unit_price, pii.iva_rate, a.name AS article_name
                                              FROM purchase_invoice_items pii
                                              JOIN articles a ON a.id = pii.article_id
                                              WHERE pii.invoice_id = " . (int) $invoice_id);
            while ($it = mysqli_fetch_assoc($itemsRes)) {
                $invoice_items[] = $it;
            }
        }
    }
}

$providers = mysqli_query($link, "SELECT id, name FROM providers WHERE status = 'activo' ORDER BY name");
$articles  = mysqli_query($link, "SELECT a.id, a.name, a.unit, c.name AS category_name, b.name AS brand_name
                                   FROM articles a
                                   LEFT JOIN article_categories c ON c.id = a.category_id
                                   LEFT JOIN brands b ON b.id = a.brand_id
                                   WHERE a.status = 'activo'
                                   ORDER BY a.name");

// Historico de precios por articulo (ultimas 5 compras) para comparativo
$priceHistory = [];
$histRes = mysqli_query($link, "SELECT pii.article_id, pii.unit_price, pi.purchase_date, p.name AS provider_name
                                 FROM purchase_invoice_items pii
                                 JOIN purchase_invoices pi ON pi.id = pii.invoice_id
                                 JOIN providers p ON p.id = pi.provider_id
                                 ORDER BY pi.purchase_date DESC");
while ($h = mysqli_fetch_assoc($histRes)) {
    $aid = (int) $h["article_id"];
    if (!isset($priceHistory[$aid])) {
        $priceHistory[$aid] = [];
    }
    if (count($priceHistory[$aid]) < 5) {
        $priceHistory[$aid][] = [
            "price" => (float) $h["unit_price"],
            "date" => $h["purchase_date"],
            "provider" => $h["provider_name"],
        ];
    }
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title><?php echo $invoice["id"] ? "Editar factura" : "Nueva factura"; ?> | Detallia</title>
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

                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18"><?php echo $invoice["id"] ? "Editar factura" : "Nueva factura de compra"; ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-purchases-list.php">Compras</a></li>
                                    <li class="breadcrumb-item active">Factura</li>
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
                                <form method="post" id="invoiceForm">
                                    <input type="hidden" name="id" value="<?php echo (int) $invoice["id"]; ?>">

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Proveedor</label>
                                            <select name="provider_id" class="form-select" required>
                                                <option value="">Selecciona...</option>
                                                <?php mysqli_data_seek($providers, 0); while ($p = mysqli_fetch_assoc($providers)): ?>
                                                    <option value="<?php echo (int) $p["id"]; ?>" <?php echo (int) $invoice["provider_id"] === (int) $p["id"] ? "selected" : ""; ?>>
                                                        <?php echo htmlspecialchars($p["name"]); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Numero de factura</label>
                                            <input type="text" name="invoice_number" class="form-control" required value="<?php echo htmlspecialchars($invoice["invoice_number"]); ?>">
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label class="form-label">Fecha</label>
                                            <input type="date" name="purchase_date" class="form-control" required value="<?php echo htmlspecialchars($invoice["purchase_date"]); ?>">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Moneda</label>
                                            <input type="text" name="currency" class="form-control" value="<?php echo htmlspecialchars($invoice["currency"]); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Notas</label>
                                        <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($invoice["notes"]); ?></textarea>
                                    </div>

                                    <hr>
                                    <h5 class="mb-3">Articulos comprados</h5>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle" id="itemsTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width:260px">Articulo</th>
                                                    <th style="width:110px">Cantidad</th>
                                                    <th style="width:130px">Precio unit.</th>
                                                    <th style="width:100px">IVA %</th>
                                                    <th style="width:120px" class="text-end">Subtotal</th>
                                                    <th style="width:130px" class="text-end">Total c/IVA</th>
                                                    <th style="width:180px">Historico de precio</th>
                                                    <th style="width:50px"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="itemsBody">
                                            </tbody>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <td colspan="4" class="text-end fw-bold">Totales</td>
                                                    <td class="fw-bold text-end" id="grandSubtotal">0.00</td>
                                                    <td class="fw-bold text-end" id="grandTotal">0.00</td>
                                                    <td colspan="2" class="small text-muted">IVA total: <span id="grandIva">0.00</span></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <button type="button" class="btn btn-soft-primary mb-3" id="addItemBtn">
                                        <i class="mdi mdi-plus me-1"></i> Agregar articulo
                                    </button>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">Guardar factura</button>
                                        <a href="admin-purchases-list.php" class="btn btn-light">Cancelar</a>
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

<!-- Right Sidebar -->
<?php include 'layouts/right-sidebar.php'; ?>

<!-- Modal: seleccionar articulo -->
<div class="modal fade" id="articleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="mdi mdi-package-variant-closed me-1"></i> Seleccionar articulo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="articleSearch" class="form-control" placeholder="Buscar por nombre, categoria o marca..." autocomplete="off">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Articulo</th><th>Categoria</th><th>Marca</th><th class="text-end">Elegir</th></tr>
                        </thead>
                        <tbody id="articleModalBody"><!-- filas por JS --></tbody>
                    </table>
                    <p class="text-muted text-center my-3 d-none" id="articleNoResults">Sin resultados.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JAVASCRIPT -->
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<script>
var ARTICLES = <?php
    $articleList = [];
    mysqli_data_seek($articles, 0);
    while ($a = mysqli_fetch_assoc($articles)) {
        $articleList[] = [
            "id" => (int) $a["id"],
            "name" => $a["name"],
            "unit" => $a["unit"],
            "category_name" => $a["category_name"] ?: "Sin categoria",
            "brand_name" => $a["brand_name"] ?: "—",
        ];
    }
    echo json_encode($articleList, JSON_HEX_APOS | JSON_HEX_QUOT);
?>;

var PRICE_HISTORY = <?php echo json_encode($priceHistory, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var EXISTING_ITEMS = <?php echo json_encode($invoice_items, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var DEFAULT_IVA = 15;

var itemsBody = document.getElementById('itemsBody');
var ARTICLE_MAP = {};
ARTICLES.forEach(function (a) { ARTICLE_MAP[a.id] = a; });

function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

function buildHistoryHtml(articleId) {
    var hist = PRICE_HISTORY[articleId];
    if (!hist || hist.length === 0) return '<small class="text-muted">Sin compras previas</small>';
    var html = '<small class="text-muted">';
    hist.forEach(function (h) { html += h.date + ': $' + h.price.toFixed(2) + ' (' + esc(h.provider) + ')<br>'; });
    return html + '</small>';
}

function addRow(item) {
    item = item || {};
    var tr = document.createElement('tr');
    var ivaVal = (item.iva_rate != null && item.iva_rate !== '') ? item.iva_rate : DEFAULT_IVA;
    tr.innerHTML =
        '<td>' +
            '<input type="hidden" name="item_article_id[]" class="it-article" value="' + (item.article_id || '') + '" required>' +
            '<button type="button" class="btn btn-outline-secondary w-100 text-start pick-article">' +
                '<span class="pick-label text-muted">Seleccionar articulo...</span>' +
            '</button>' +
        '</td>' +
        '<td><input type="number" step="0.01" min="0.01" name="item_quantity[]" class="form-control it-qty" value="' + (item.quantity || 1) + '" required></td>' +
        '<td><input type="number" step="0.01" min="0" name="item_unit_price[]" class="form-control it-price" value="' + (item.unit_price || '') + '" required></td>' +
        '<td><input type="number" step="0.01" min="0" name="item_iva_rate[]" class="form-control it-iva" value="' + ivaVal + '"></td>' +
        '<td class="text-end it-subtotal">0.00</td>' +
        '<td class="text-end it-total">0.00</td>' +
        '<td class="it-history">' + buildHistoryHtml(item.article_id || '') + '</td>' +
        '<td><button type="button" class="btn btn-sm btn-soft-danger remove-row"><i class="mdi mdi-delete"></i></button></td>';
    itemsBody.appendChild(tr);

    if (item.article_id && ARTICLE_MAP[item.article_id]) {
        setRowArticle(tr, ARTICLE_MAP[item.article_id]);
    }
    recalcTotals();
}

function setRowArticle(tr, article) {
    tr.querySelector('.it-article').value = article.id;
    var label = tr.querySelector('.pick-label');
    label.classList.remove('text-muted');
    label.innerHTML = esc(article.name) + ' <small class="text-muted">(' + esc(article.category_name) + ' / ' + esc(article.brand_name) + ')</small>';
    tr.querySelector('.it-history').innerHTML = buildHistoryHtml(article.id);
}

function recalcRow(tr) {
    var qty = parseFloat(tr.querySelector('.it-qty').value) || 0;
    var price = parseFloat(tr.querySelector('.it-price').value) || 0;
    var iva = parseFloat(tr.querySelector('.it-iva').value) || 0;
    var sub = qty * price;
    var tot = sub * (1 + iva / 100);
    tr.querySelector('.it-subtotal').innerText = sub.toFixed(2);
    tr.querySelector('.it-total').innerText = tot.toFixed(2);
}

function recalcTotals() {
    var subSum = 0, totSum = 0;
    document.querySelectorAll('#itemsBody tr').forEach(function (tr) {
        recalcRow(tr);
        subSum += parseFloat(tr.querySelector('.it-subtotal').innerText) || 0;
        totSum += parseFloat(tr.querySelector('.it-total').innerText) || 0;
    });
    document.getElementById('grandSubtotal').innerText = subSum.toFixed(2);
    document.getElementById('grandTotal').innerText = totSum.toFixed(2);
    document.getElementById('grandIva').innerText = (totSum - subSum).toFixed(2);
}

// Delegacion de eventos en el cuerpo de la tabla
itemsBody.addEventListener('input', recalcTotals);
itemsBody.addEventListener('click', function (e) {
    if (e.target.closest('.remove-row')) {
        e.target.closest('tr').remove();
        recalcTotals();
    } else if (e.target.closest('.pick-article')) {
        openArticleModal(e.target.closest('tr'));
    }
});

document.getElementById('addItemBtn').addEventListener('click', function () { addRow(); });

// ---- Modal de seleccion de articulo ----
var activeRow = null;
var articleModal = new bootstrap.Modal(document.getElementById('articleModal'));
var modalBody = document.getElementById('articleModalBody');
var searchInput = document.getElementById('articleSearch');
var noResults = document.getElementById('articleNoResults');

function renderArticleList(filter) {
    filter = (filter || '').toLowerCase();
    var html = '';
    var shown = 0;
    ARTICLES.forEach(function (a) {
        var hay = (a.name + ' ' + a.category_name + ' ' + a.brand_name).toLowerCase();
        if (filter && hay.indexOf(filter) === -1) return;
        shown++;
        html += '<tr>' +
            '<td>' + esc(a.name) + '</td>' +
            '<td class="text-muted">' + esc(a.category_name) + '</td>' +
            '<td class="text-muted">' + esc(a.brand_name) + '</td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-soft-primary choose-article" data-id="' + a.id + '">Elegir</button></td>' +
            '</tr>';
    });
    modalBody.innerHTML = html;
    noResults.classList.toggle('d-none', shown > 0);
}

function openArticleModal(tr) {
    activeRow = tr;
    searchInput.value = '';
    renderArticleList('');
    articleModal.show();
    setTimeout(function () { searchInput.focus(); }, 300);
}

searchInput.addEventListener('input', function () { renderArticleList(this.value); });
modalBody.addEventListener('click', function (e) {
    var btn = e.target.closest('.choose-article');
    if (!btn || !activeRow) return;
    var a = ARTICLE_MAP[parseInt(btn.getAttribute('data-id'))];
    if (a) { setRowArticle(activeRow, a); recalcTotals(); }
    articleModal.hide();
});

// Evitar enviar el formulario si alguna fila no tiene articulo elegido
document.getElementById('invoiceForm').addEventListener('submit', function (e) {
    var missing = false;
    document.querySelectorAll('#itemsBody .it-article').forEach(function (inp) {
        if (!inp.value) missing = true;
    });
    if (missing) {
        e.preventDefault();
        alert('Cada fila debe tener un articulo seleccionado.');
    }
});

// Inicializar
if (EXISTING_ITEMS.length > 0) {
    EXISTING_ITEMS.forEach(function (it) { addRow(it); });
} else {
    addRow();
}
</script>

</body>

</html>
