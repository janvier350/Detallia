<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 6, 3]);

$can_edit = in_array((int) $_SESSION["role_id"], [1, 2, 3], true);
$success_msg = "";
$error_msg = "";

// ---------------------------------------------------------------
// Guardar (crear / editar)
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save") {
    $id          = (int) ($_POST["id"] ?? 0);
    $article_id  = (int) ($_POST["article_id"] ?? 0);
    $provider_id = (int) ($_POST["provider_id"] ?? 0);
    $unit_price  = (float) ($_POST["unit_price"] ?? 0);
    $iva_rate    = isset($_POST["iva_rate"]) ? (float) $_POST["iva_rate"] : 15;
    if ($iva_rate < 0) $iva_rate = 0;
    $quote_date  = trim($_POST["quote_date"] ?? "");
    $notes       = trim($_POST["notes"] ?? "");

    if ($article_id <= 0 || $provider_id <= 0 || $quote_date === "") {
        $error_msg = "Producto, proveedor y fecha son obligatorios.";
    } else {
        if ($id > 0) {
            $stmt = mysqli_prepare($link, "UPDATE quotations SET article_id=?, provider_id=?, unit_price=?, iva_rate=?, quote_date=?, notes=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "iiddssi", $article_id, $provider_id, $unit_price, $iva_rate, $quote_date, $notes, $id);
            $ok = mysqli_stmt_execute($stmt);
            if ($ok) { $success_msg = "Cotizacion actualizada."; log_activity($link, "edito", "Cotizaciones", "Edito cotizacion #" . $id); }
        } else {
            $created_by = (int) $_SESSION["id"];
            $stmt = mysqli_prepare($link, "INSERT INTO quotations (article_id, provider_id, unit_price, iva_rate, quote_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiddssi", $article_id, $provider_id, $unit_price, $iva_rate, $quote_date, $notes, $created_by);
            $ok = mysqli_stmt_execute($stmt);
            if ($ok) { $success_msg = "Cotizacion registrada."; log_activity($link, "creo", "Cotizaciones", "Creo cotizacion #" . mysqli_insert_id($link)); }
        }
        if (!$ok) $error_msg = "No se pudo guardar la cotizacion: " . mysqli_error($link);
    }
}

// ---------------------------------------------------------------
// Eliminar
// ---------------------------------------------------------------
if ($can_edit && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $id = (int) ($_POST["id"] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($link, "DELETE FROM quotations WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) { $success_msg = "Cotizacion eliminada."; log_activity($link, "elimino", "Cotizaciones", "Elimino cotizacion #" . $id); }
    }
}

// Filtros
$f_article  = (int) ($_GET["article_id"] ?? 0);
$f_provider = (int) ($_GET["provider_id"] ?? 0);

$conds = []; $params = []; $types = "";
if ($f_article > 0)  { $conds[] = "q.article_id = ?";  $params[] = $f_article;  $types .= "i"; }
if ($f_provider > 0) { $conds[] = "q.provider_id = ?"; $params[] = $f_provider; $types .= "i"; }
$where = !empty($conds) ? (" WHERE " . implode(" AND ", $conds)) : "";

$sql = "SELECT q.*, a.name AS article_name, p.name AS provider_name
        FROM quotations q
        JOIN articles a ON a.id = q.article_id
        JOIN providers p ON p.id = q.provider_id
        $where
        ORDER BY a.name, q.unit_price ASC";
$stmt = mysqli_prepare($link, $sql);
if (!empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$quotes = mysqli_stmt_get_result($stmt);

$articlesOpt  = mysqli_query($link, "SELECT id, name FROM articles WHERE status='activo' ORDER BY name");
$providersOpt = mysqli_query($link, "SELECT id, name FROM providers WHERE status='activo' ORDER BY name");
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Cotizaciones | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Cotizaciones</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-purchases-list.php">Compras</a></li>
                                    <li class="breadcrumb-item active">Cotizaciones</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($success_msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
                <?php if ($error_msg): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error_msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <div>
                                <h5 class="card-title mb-1">Precios cotizados por proveedor</h5>
                                <p class="text-muted mb-0 small">Se usan en el Simulador de kits para estimar el costo y comparar proveedores.</p>
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quoteModal" onclick="openCreate()"><i class="mdi mdi-plus me-1"></i> Nueva cotizacion</button>
                        </div>

                        <form method="get" class="row g-2 mb-3 align-items-end">
                            <div class="col-6 col-md-4">
                                <label class="form-label small text-muted mb-1">Producto</label>
                                <select name="article_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <?php mysqli_data_seek($articlesOpt, 0); while ($a = mysqli_fetch_assoc($articlesOpt)): ?>
                                        <option value="<?php echo (int) $a['id']; ?>" <?php echo $f_article === (int) $a['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label small text-muted mb-1">Proveedor</label>
                                <select name="provider_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <?php mysqli_data_seek($providersOpt, 0); while ($p = mysqli_fetch_assoc($providersOpt)): ?>
                                        <option value="<?php echo (int) $p['id']; ?>" <?php echo $f_provider === (int) $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <?php if ($f_article || $f_provider): ?><div class="col-auto"><a href="admin-quotations-list.php" class="btn btn-light">Limpiar</a></div><?php endif; ?>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Proveedor</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">IVA %</th>
                                        <th class="text-end">Total c/IVA</th>
                                        <th>Fecha</th>
                                        <th>Notas</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($quotes) === 0): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-4">Sin cotizaciones. Registra la primera.</td></tr>
                                    <?php else: while ($q = mysqli_fetch_assoc($quotes)): $tot = $q["unit_price"] * (1 + $q["iva_rate"] / 100); ?>
                                        <tr>
                                            <td class="fw-medium"><?php echo htmlspecialchars($q["article_name"]); ?></td>
                                            <td><?php echo htmlspecialchars($q["provider_name"]); ?></td>
                                            <td class="text-end">$<?php echo number_format($q["unit_price"], 2); ?></td>
                                            <td class="text-end"><?php echo rtrim(rtrim(number_format($q["iva_rate"], 2), '0'), '.'); ?></td>
                                            <td class="text-end fw-bold">$<?php echo number_format($tot, 2); ?></td>
                                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($q["quote_date"]))); ?></td>
                                            <td class="text-muted small" style="white-space:normal;max-width:240px"><?php echo htmlspecialchars($q["notes"] ?? ""); ?></td>
                                            <td class="text-end text-nowrap">
                                                <button type="button" class="btn btn-sm btn-soft-primary" onclick='openEdit(<?php echo json_encode($q, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><i class="mdi mdi-pencil"></i></button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta cotizacion?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $q['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-soft-danger"><i class="mdi mdi-delete"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="quoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quoteModalLabel">Nueva cotizacion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="q_id" value="">
                <div class="mb-3">
                    <label class="form-label">Producto <span class="text-danger">*</span></label>
                    <select name="article_id" id="q_article" class="form-select" required>
                        <option value="">Selecciona...</option>
                        <?php mysqli_data_seek($articlesOpt, 0); while ($a = mysqli_fetch_assoc($articlesOpt)): ?>
                            <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-text">¿No esta el producto? Agregalo primero en <a href="admin-articles-list.php">Articulos</a>.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                    <select name="provider_id" id="q_provider" class="form-select" required>
                        <option value="">Selecciona...</option>
                        <?php mysqli_data_seek($providersOpt, 0); while ($p = mysqli_fetch_assoc($providersOpt)): ?>
                            <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Precio unitario</label>
                        <input type="number" step="0.01" min="0" name="unit_price" id="q_price" class="form-control" value="0">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">IVA %</label>
                        <input type="number" step="0.01" min="0" name="iva_rate" id="q_iva" class="form-control" value="15">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="quote_date" id="q_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <input type="text" name="notes" id="q_notes" class="form-control" placeholder="Ej. incluye grabado, minimo 50 unidades...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>
<script>
function openCreate() {
    document.getElementById('quoteModalLabel').innerText = 'Nueva cotizacion';
    document.getElementById('q_id').value = '';
    document.getElementById('q_article').value = '';
    document.getElementById('q_provider').value = '';
    document.getElementById('q_price').value = '0';
    document.getElementById('q_iva').value = '15';
    document.getElementById('q_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('q_notes').value = '';
}
function openEdit(q) {
    document.getElementById('quoteModalLabel').innerText = 'Editar cotizacion';
    document.getElementById('q_id').value = q.id;
    document.getElementById('q_article').value = q.article_id;
    document.getElementById('q_provider').value = q.provider_id;
    document.getElementById('q_price').value = q.unit_price;
    document.getElementById('q_iva').value = q.iva_rate;
    document.getElementById('q_date').value = q.quote_date;
    document.getElementById('q_notes').value = q.notes || '';
    new bootstrap.Modal(document.getElementById('quoteModal')).show();
}
</script>

</body>
</html>
