<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 6, 3, 5]);

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2, 3, 5], true);
$success_msg = "";

// ---------------------------------------------------------------
// Eliminar
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $id = (int) ($_POST["id"] ?? 0);
    if ($id > 0) {
        // Guardamos copia en la papelera antes de borrar (items se borran en cascada)
        $snap = snapshot_record($link, "kit_simulations", $id, [['table' => 'kit_simulation_items', 'fk' => 'simulation_id']]);
        $lbl  = $snap ? ($snap['parent']['name'] ?? ('#' . $id)) : ('#' . $id);
        $stmt = mysqli_prepare($link, "DELETE FROM kit_simulations WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            archive_snapshot($link, "Simulador de kits", "kit_simulations", $id, $lbl, $snap);
        }
        $_SESSION["flash_success"] = "Simulacro eliminado. Puedes recuperarlo desde la Papelera.";
        log_activity($link, "elimino", "Simulador de kits", "Elimino simulacro #" . $id);
        header("location: admin-kit-sim-list.php");
        exit;
    }
}

// ---------------------------------------------------------------
// Duplicar (copia cabecera + productos)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "duplicate") {
    $id = (int) ($_POST["id"] ?? 0);
    $src = null;
    if ($id > 0) {
        $stmt = mysqli_prepare($link, "SELECT * FROM kit_simulations WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $src = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
    if ($src) {
        $newName   = mb_substr($src["name"] . " (copia)", 0, 150);
        $today     = date("Y-m-d");
        $classId   = $src["classification_id"] !== null ? (int) $src["classification_id"] : null;
        $kitQty    = max(1, (int) ($src["kit_quantity"] ?? 1));
        $imagePath = $src["image_path"];
        $notes     = $src["notes"];
        $createdBy = (int) $_SESSION["id"];
        $ins = mysqli_prepare($link, "INSERT INTO kit_simulations (name, sim_date, classification_id, kit_quantity, image_path, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "ssiissi", $newName, $today, $classId, $kitQty, $imagePath, $notes, $createdBy);
        mysqli_stmt_execute($ins);
        $newId = mysqli_insert_id($link);

        // Copiar items
        mysqli_query($link, "INSERT INTO kit_simulation_items (simulation_id, product_name, provider_id, provider_name, unit_price, iva_rate, quantity, sort_order)
                             SELECT " . (int) $newId . ", product_name, provider_id, provider_name, unit_price, iva_rate, quantity, sort_order
                             FROM kit_simulation_items WHERE simulation_id = " . (int) $id);

        $_SESSION["flash_success"] = "Simulacro duplicado. Ajusta el tipo de cliente o los precios en la copia.";
        header("location: admin-kit-sim-form.php?id=" . (int) $newId);
        exit;
    }
}

if (isset($_SESSION["flash_success"])) {
    $success_msg = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}

// Lista con totales calculados
$rows = mysqli_query($link, "SELECT s.id, s.name, s.sim_date, s.created_at, s.kit_quantity, s.image_path,
                                    cl.name AS classification_name,
                                    COALESCE(u.full_name, u.username) AS created_by_name,
                                    COUNT(i.id) AS num_items,
                                    COALESCE(SUM(i.unit_price * i.quantity), 0) AS subtotal,
                                    COALESCE(SUM(i.unit_price * i.quantity * (i.iva_rate/100)), 0) AS iva,
                                    COALESCE(SUM(i.unit_price * i.quantity * (1 + i.iva_rate/100)), 0) AS total
                             FROM kit_simulations s
                             LEFT JOIN client_classifications cl ON cl.id = s.classification_id
                             LEFT JOIN users u ON u.id = s.created_by
                             LEFT JOIN kit_simulation_items i ON i.simulation_id = s.id
                             GROUP BY s.id
                             ORDER BY s.id DESC");
$sims = [];
$simIds = [];
while ($r = mysqli_fetch_assoc($rows)) { $sims[] = $r; $simIds[] = (int) $r["id"]; }

// Detalle de productos por simulacro (para el modal de las tarjetas)
$itemsBySim = [];
if (!empty($simIds)) {
    $idList = implode(",", $simIds);
    $ir = mysqli_query($link, "SELECT simulation_id, product_name, provider_name, unit_price, iva_rate, quantity
                               FROM kit_simulation_items WHERE simulation_id IN ($idList) ORDER BY sort_order, id");
    while ($it = mysqli_fetch_assoc($ir)) {
        $sid = (int) $it["simulation_id"];
        $itemsBySim[$sid][] = [
            "product" => $it["product_name"],
            "provider" => $it["provider_name"],
            "qty" => (float) $it["quantity"],
            "total" => (float) $it["unit_price"] * (float) $it["quantity"] * (1 + (float) $it["iva_rate"] / 100),
        ];
    }
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Simulador de kits | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Simulador de kits</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-kits-list.php">Kits</a></li>
                                    <li class="breadcrumb-item active">Simulador</li>
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

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <div>
                                        <h5 class="card-title mb-1">Simulacros guardados</h5>
                                        <p class="text-muted mb-0 small">Compara el costo de un kit para distintos tipos de cliente.</p>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-soft-secondary active" id="viewTableBtn" title="Vista tabla"><i class="mdi mdi-table"></i></button>
                                            <button type="button" class="btn btn-soft-secondary" id="viewCardsBtn" title="Vista tarjetas"><i class="mdi mdi-view-grid-outline"></i></button>
                                        </div>
                                        <button type="button" id="compareBtn" class="btn btn-soft-info" disabled><i class="mdi mdi-compare-horizontal me-1"></i> Comparar seleccionados (<span id="compareCount">0</span>)</button>
                                        <a href="admin-kit-sim-form.php" class="btn btn-primary"><i class="mdi mdi-plus me-1"></i> Nuevo simulacro</a>
                                    </div>
                                </div>

                                <?php if (empty($sims)): ?>
                                    <div class="alert alert-info mb-0">Aun no hay simulacros. Crea el primero con "Nuevo simulacro".</div>
                                <?php else: ?>

                                <!-- Vista tarjetas -->
                                <div id="cardsView" class="row g-3 mb-2" style="display:none;">
                                    <?php foreach ($sims as $s): $totalKits = $s["total"] * max(1, (int) $s["kit_quantity"]); ?>
                                    <div class="col-sm-6 col-xl-3">
                                        <div class="card h-100 border shadow-none mb-0">
                                            <div style="height:150px;background:#f4f5f8;border-top-left-radius:.5rem;border-top-right-radius:.5rem;overflow:hidden;display:flex;align-items:center;justify-content:center;">
                                                <?php if (!empty($s["image_path"])): ?>
                                                    <img src="<?php echo htmlspecialchars($s["image_path"]); ?>" alt="Kit" style="width:100%;height:100%;object-fit:cover;">
                                                <?php else: ?>
                                                    <i class="mdi mdi-gift-outline" style="font-size:3rem;color:#ced4da;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($s["name"]); ?></h6>
                                                    <?php if ($s["classification_name"]): ?><span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($s["classification_name"]); ?></span><?php endif; ?>
                                                </div>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(date("d/m/Y", strtotime($s["sim_date"]))); ?> · <?php echo (int) $s["kit_quantity"]; ?> kit(s)</p>
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span class="text-muted">Valor por kit</span>
                                                    <span class="fw-medium">$<?php echo number_format($s["total"], 2); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="text-muted small">Valor total</span>
                                                    <span class="fw-bold text-primary">$<?php echo number_format($totalKits, 2); ?></span>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-soft-secondary w-100 mb-2 view-detail" data-id="<?php echo (int) $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>" data-image="<?php echo htmlspecialchars($s['image_path'] ?? '', ENT_QUOTES); ?>">
                                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Ver <?php echo (int) $s["num_items"]; ?> productos
                                                </button>
                                                <div class="d-flex gap-1">
                                                    <a href="admin-kit-sim-print.php?id=<?php echo (int) $s['id']; ?>" target="_blank" class="btn btn-sm btn-soft-secondary flex-fill"><i class="mdi mdi-printer"></i></a>
                                                    <a href="admin-kit-sim-form.php?id=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-soft-primary flex-fill"><i class="mdi mdi-pencil"></i></a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Vista tabla -->
                                <div class="table-responsive" id="tableView">
                                    <table class="table table-centered table-nowrap mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width:34px"></th>
                                                <th>#</th>
                                                <th>Simulacro</th>
                                                <th>Fecha</th>
                                                <th>Tipo de cliente</th>
                                                <th class="text-center">Productos</th>
                                                <th class="text-end">Subtotal</th>
                                                <th class="text-end">IVA</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sims as $s): ?>
                                                <tr>
                                                    <td><input type="checkbox" class="form-check-input sim-check" value="<?php echo (int) $s['id']; ?>"></td>
                                                    <td><?php echo (int) $s["id"]; ?></td>
                                                    <td class="fw-medium"><?php echo htmlspecialchars($s["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($s["sim_date"]))); ?></td>
                                                    <td>
                                                        <?php if ($s["classification_name"]): ?>
                                                            <span class="badge bg-primary-subtle text-primary"><?php echo htmlspecialchars($s["classification_name"]); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center"><?php echo (int) $s["num_items"]; ?></td>
                                                    <td class="text-end">$<?php echo number_format($s["subtotal"], 2); ?></td>
                                                    <td class="text-end">$<?php echo number_format($s["iva"], 2); ?></td>
                                                    <td class="text-end fw-bold">$<?php echo number_format($s["total"], 2); ?></td>
                                                    <td class="text-end text-nowrap">
                                                        <a href="admin-kit-sim-print.php?id=<?php echo (int) $s['id']; ?>" target="_blank" class="btn btn-sm btn-soft-secondary" title="Imprimir / PDF"><i class="mdi mdi-printer"></i></a>
                                                        <a href="admin-kit-sim-form.php?id=<?php echo (int) $s['id']; ?>" class="btn btn-sm btn-soft-primary" title="Editar"><i class="mdi mdi-pencil"></i></a>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="duplicate">
                                                            <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-soft-success" title="Duplicar"><i class="mdi mdi-content-copy"></i></button>
                                                        </form>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este simulacro?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-soft-danger" title="Eliminar"><i class="mdi mdi-delete"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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

<!-- Modal detalle de productos -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailTitle">Detalle del kit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detailImageWrap" class="text-center mb-3" style="display:none;">
                    <img id="detailImage" src="" alt="Kit" style="max-height:220px;max-width:100%;border-radius:8px;border:1px solid #eee;">
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Producto</th><th>Proveedor</th><th class="text-end">Cant.</th><th class="text-end">Total</th></tr></thead>
                        <tbody id="detailBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<script>
var SIM_DETAIL = <?php echo json_encode($itemsBySim, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

(function () {
    var checks = document.querySelectorAll('.sim-check');
    var btn = document.getElementById('compareBtn');
    var count = document.getElementById('compareCount');

    function selectedIds() {
        return Array.prototype.filter.call(checks, function (c) { return c.checked; })
                    .map(function (c) { return c.value; });
    }
    function update() {
        if (!btn) return;
        var ids = selectedIds();
        count.innerText = ids.length;
        btn.disabled = ids.length < 2;
    }
    checks.forEach(function (c) { c.addEventListener('change', update); });
    if (btn) btn.addEventListener('click', function () {
        var ids = selectedIds();
        if (ids.length >= 2) window.location.href = 'admin-kit-sim-compare.php?ids=' + ids.join(',');
    });
    update();

    // Toggle tabla / tarjetas
    var tableView = document.getElementById('tableView');
    var cardsView = document.getElementById('cardsView');
    var bT = document.getElementById('viewTableBtn');
    var bC = document.getElementById('viewCardsBtn');
    function setView(v) {
        if (!tableView || !cardsView) return;
        var cards = v === 'cards';
        cardsView.style.display = cards ? '' : 'none';
        tableView.style.display = cards ? 'none' : '';
        bC.classList.toggle('active', cards);
        bT.classList.toggle('active', !cards);
        try { localStorage.setItem('kitsim_view', v); } catch (e) {}
    }
    if (bT) bT.addEventListener('click', function () { setView('table'); });
    if (bC) bC.addEventListener('click', function () { setView('cards'); });
    try { if (localStorage.getItem('kitsim_view') === 'cards') setView('cards'); } catch (e) {}

    // Modal de detalle
    function money(n) { return (isNaN(n) ? 0 : n).toFixed(2); }
    function esc(s) { return s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }
    document.querySelectorAll('.view-detail').forEach(function (b) {
        b.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            document.getElementById('detailTitle').innerText = this.getAttribute('data-name') || 'Detalle del kit';
            var img = this.getAttribute('data-image');
            var wrap = document.getElementById('detailImageWrap');
            if (img) { document.getElementById('detailImage').src = img; wrap.style.display = ''; }
            else { wrap.style.display = 'none'; }
            var items = SIM_DETAIL[id] || [];
            var html = '';
            if (!items.length) html = '<tr><td colspan="4" class="text-center text-muted">Sin productos</td></tr>';
            items.forEach(function (it) {
                html += '<tr><td>' + esc(it.product) + '</td><td class="text-muted">' + esc(it.provider || '—') + '</td>' +
                        '<td class="text-end">' + (it.qty % 1 === 0 ? it.qty : it.qty.toFixed(2)) + '</td>' +
                        '<td class="text-end">$' + money(it.total) + '</td></tr>';
            });
            document.getElementById('detailBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        });
    });
})();
</script>

</body>
</html>
