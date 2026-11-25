<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 6]); // Administrador, Jefe, Asistente, Editor

$success_msg = "";
$error_msg   = "";
if (isset($_SESSION["flash_success"])) { $success_msg = $_SESSION["flash_success"]; unset($_SESSION["flash_success"]); }
if (isset($_SESSION["flash_error"]))   { $error_msg   = $_SESSION["flash_error"];   unset($_SESSION["flash_error"]); }

// ---------------------------------------------------------------
// Subir nueva plantilla (imagen de fondo)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "upload") {
    $tpl_name = trim($_POST["tpl_name"] ?? "");
    if ($tpl_name === "") { $tpl_name = "Tarjeta " . date("d/m/Y H:i"); }
    if (!empty($_FILES["image"]["name"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $ftype = mime_content_type($_FILES["image"]["tmp_name"]);
        $ext   = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        if (!in_array($ftype, $allowed)) {
            $_SESSION["flash_error"] = "Solo se permiten imágenes JPG, PNG, GIF o WEBP.";
        } elseif ($_FILES["image"]["size"] > 6 * 1024 * 1024) {
            $_SESSION["flash_error"] = "La imagen no puede superar 6 MB.";
        } else {
            $dir = __DIR__ . '/assets/images/cards/';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $fn = 'card_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $dir . $fn)) {
                $path = 'assets/images/cards/' . $fn;
                $by = (int) $_SESSION["id"];
                $stmt = mysqli_prepare($link, "INSERT INTO card_templates (name, image_path, created_by) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssi", $tpl_name, $path, $by);
                mysqli_stmt_execute($stmt);
                $newId = mysqli_insert_id($link);
                log_activity($link, "creo", "Tarjetas", "Subió plantilla de tarjeta: " . $tpl_name);
                $_SESSION["flash_success"] = "Plantilla subida. Ahora coloca el nombre con arrastrar y soltar.";
                header("location: admin-cards.php?id=" . $newId);
                exit;
            } else {
                $_SESSION["flash_error"] = "No se pudo guardar la imagen.";
            }
        }
    } else {
        $_SESSION["flash_error"] = "Debes seleccionar una imagen.";
    }
    header("location: admin-cards.php");
    exit;
}

// ---------------------------------------------------------------
// Guardar posición / estilo del nombre
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_layout") {
    $id    = (int) ($_POST["id"] ?? 0);
    $px    = max(0, min(100, (float) ($_POST["pos_x"] ?? 50)));
    $py    = max(0, min(100, (float) ($_POST["pos_y"] ?? 50)));
    $fp    = max(1, min(30, (float) ($_POST["font_pct"] ?? 6)));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST["font_color"] ?? "") ? $_POST["font_color"] : "#1b5e20";
    $align = in_array($_POST["text_align"] ?? "", ["left", "center", "right"], true) ? $_POST["text_align"] : "center";
    $cols  = max(1, min(6, (int) ($_POST["grid_cols"] ?? 2)));
    if ($id > 0) {
        $stmt = mysqli_prepare($link, "UPDATE card_templates SET pos_x=?, pos_y=?, font_pct=?, font_color=?, text_align=?, grid_cols=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "dddssii", $px, $py, $fp, $color, $align, $cols, $id);
        mysqli_stmt_execute($stmt);
        $_SESSION["flash_success"] = "Posición guardada.";
    }
    header("location: admin-cards.php?id=" . $id);
    exit;
}

// ---------------------------------------------------------------
// Eliminar plantilla
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete") {
    $id = (int) ($_POST["id"] ?? 0);
    if ($id > 0) {
        $q = mysqli_query($link, "SELECT image_path FROM card_templates WHERE id=" . $id);
        if ($q && ($r = mysqli_fetch_assoc($q)) && $r["image_path"]) {
            $f = __DIR__ . '/' . $r["image_path"];
            if (is_file($f)) @unlink($f);
        }
        $stmt = mysqli_prepare($link, "DELETE FROM card_templates WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        log_activity($link, "elimino", "Tarjetas", "Eliminó plantilla de tarjeta #" . $id);
        $_SESSION["flash_success"] = "Plantilla eliminada.";
    }
    header("location: admin-cards.php");
    exit;
}

// Plantillas existentes
$templates = [];
$r = mysqli_query($link, "SELECT id, name, image_path FROM card_templates ORDER BY id DESC");
while ($x = mysqli_fetch_assoc($r)) { $templates[] = $x; }

// Plantilla seleccionada
$sel_id = (int) ($_GET["id"] ?? 0);
$tpl = null;
if ($sel_id > 0) {
    $s = mysqli_prepare($link, "SELECT * FROM card_templates WHERE id=?");
    mysqli_stmt_bind_param($s, "i", $sel_id);
    mysqli_stmt_execute($s);
    $tpl = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
}

// Filtros de clientes (para contar / imprimir)
$f_class = (int) ($_GET["classification_id"] ?? 0);
$f_zona  = trim($_GET["zona"] ?? "");
$f_ciudad = trim($_GET["ciudad"] ?? "");
$f_brand = (int) ($_GET["brand_id"] ?? 0);

$clientCount = 0;
if ($tpl) {
    $cc = "SELECT COUNT(*) n FROM clients c WHERE 1=1";
    $cp = []; $ct = "";
    if ($f_class > 0)  { $cc .= " AND c.classification_id=?"; $cp[] = $f_class; $ct .= "i"; }
    if ($f_zona !== "") { $cc .= " AND c.zona=?"; $cp[] = $f_zona; $ct .= "s"; }
    if ($f_ciudad !== "") { $cc .= " AND c.ciudad=?"; $cp[] = $f_ciudad; $ct .= "s"; }
    if ($f_brand > 0)  { $cc .= " AND c.brand_id=?"; $cp[] = $f_brand; $ct .= "i"; }
    $st = mysqli_prepare($link, $cc);
    if ($cp) { mysqli_stmt_bind_param($st, $ct, ...$cp); }
    mysqli_stmt_execute($st);
    $clientCount = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($st))["n"] ?? 0);
}

// Opciones de filtro
$classes = [];
$r = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY name");
while ($x = mysqli_fetch_assoc($r)) { $classes[] = $x; }
$zonas = [];
$r = mysqli_query($link, "SELECT DISTINCT zona FROM clients WHERE zona IS NOT NULL AND zona <> '' ORDER BY zona");
while ($x = mysqli_fetch_row($r)) { $zonas[] = $x[0]; }
$ciudades = [];
$r = mysqli_query($link, "SELECT DISTINCT ciudad FROM clients WHERE ciudad IS NOT NULL AND ciudad <> '' ORDER BY ciudad");
while ($x = mysqli_fetch_row($r)) { $ciudades[] = $x[0]; }
$brands = [];
$r = mysqli_query($link, "SELECT id, name FROM brands ORDER BY name");
while ($x = mysqli_fetch_assoc($r)) { $brands[] = $x; }

// Query string de filtros para el enlace de impresión
function cards_filter_qs()
{
    $keep = [];
    foreach (["classification_id", "zona", "ciudad", "brand_id"] as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== "") { $keep[$k] = $_GET[$k]; }
    }
    return $keep;
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Tarjetas | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Tarjetas para imprimir</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Tarjetas</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?php echo htmlspecialchars($success_msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?php echo htmlspecialchars($error_msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Columna izquierda: plantilla + subir -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Plantilla</h5>
                                <form method="get" class="mb-3">
                                    <select name="id" class="form-select" onchange="this.form.submit()">
                                        <option value="">— Elegir plantilla —</option>
                                        <?php foreach ($templates as $t): ?>
                                            <option value="<?php echo $t["id"]; ?>" <?php echo $sel_id == $t["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($t["name"]); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>

                                <hr>
                                <h6 class="mb-2">Subir nueva plantilla</h6>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload">
                                    <div class="mb-2">
                                        <input type="text" name="tpl_name" class="form-control" placeholder="Nombre (ej. Navidad 2026)">
                                    </div>
                                    <div class="mb-2">
                                        <input type="file" name="image" accept="image/*" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-upload me-1"></i>Subir imagen</button>
                                </form>

                                <?php if ($tpl): ?>
                                    <hr>
                                    <form method="post" onsubmit="return confirm('¿Eliminar esta plantilla?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $tpl["id"]; ?>">
                                        <button type="submit" class="btn btn-soft-danger btn-sm w-100"><i class="mdi mdi-delete me-1"></i>Eliminar plantilla</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($tpl): ?>
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Clientes a imprimir</h5>
                                <form method="get">
                                    <input type="hidden" name="id" value="<?php echo (int) $tpl["id"]; ?>">
                                    <div class="mb-2">
                                        <label class="form-label mb-1">Clasificación</label>
                                        <select name="classification_id" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($classes as $c): ?>
                                                <option value="<?php echo $c["id"]; ?>" <?php echo $f_class == $c["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($c["name"]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-1">Zona</label>
                                        <select name="zona" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($zonas as $z): ?>
                                                <option value="<?php echo htmlspecialchars($z); ?>" <?php echo $f_zona === $z ? "selected" : ""; ?>><?php echo htmlspecialchars($z); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-1">Ciudad</label>
                                        <select name="ciudad" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($ciudades as $ci): ?>
                                                <option value="<?php echo htmlspecialchars($ci); ?>" <?php echo $f_ciudad === $ci ? "selected" : ""; ?>><?php echo htmlspecialchars($ci); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label mb-1">Marca</label>
                                        <select name="brand_id" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($brands as $b): ?>
                                                <option value="<?php echo $b["id"]; ?>" <?php echo $f_brand == $b["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($b["name"]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </form>
                                <p class="mb-2"><span class="badge bg-primary-subtle text-primary">Coinciden <?php echo $clientCount; ?> cliente(s)</span></p>
                                <a href="admin-cards-print.php?<?php echo http_build_query(array_merge(["template_id" => $tpl["id"]], cards_filter_qs())); ?>" target="_blank" class="btn btn-success w-100 <?php echo $clientCount === 0 ? 'disabled' : ''; ?>">
                                    <i class="mdi mdi-printer me-1"></i> Generar e imprimir (A4)
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Columna derecha: editor drag & drop -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <?php if (!$tpl): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="mdi mdi-image-plus" style="font-size:48px;"></i>
                                        <p class="mt-2">Sube o elige una plantilla para empezar.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                        <h5 class="card-title mb-0">Coloca el nombre (arrastra sobre la tarjeta)</h5>
                                    </div>

                                    <form method="post" id="layoutForm">
                                        <input type="hidden" name="action" value="save_layout">
                                        <input type="hidden" name="id" value="<?php echo (int) $tpl["id"]; ?>">
                                        <input type="hidden" name="pos_x" id="pos_x" value="<?php echo htmlspecialchars($tpl["pos_x"]); ?>">
                                        <input type="hidden" name="pos_y" id="pos_y" value="<?php echo htmlspecialchars($tpl["pos_y"]); ?>">

                                        <div class="row g-2 mb-3">
                                            <div class="col-sm-3">
                                                <label class="form-label mb-1">Tamaño de letra</label>
                                                <input type="range" min="1" max="20" step="0.5" value="<?php echo htmlspecialchars($tpl["font_pct"]); ?>" id="font_pct_range" class="form-range">
                                                <input type="hidden" name="font_pct" id="font_pct" value="<?php echo htmlspecialchars($tpl["font_pct"]); ?>">
                                            </div>
                                            <div class="col-sm-3">
                                                <label class="form-label mb-1">Color</label>
                                                <input type="color" name="font_color" id="font_color" value="<?php echo htmlspecialchars($tpl["font_color"]); ?>" class="form-control form-control-color">
                                            </div>
                                            <div class="col-sm-3">
                                                <label class="form-label mb-1">Alineación</label>
                                                <select name="text_align" id="text_align" class="form-select">
                                                    <option value="center" <?php echo $tpl["text_align"] === "center" ? "selected" : ""; ?>>Centro</option>
                                                    <option value="left" <?php echo $tpl["text_align"] === "left" ? "selected" : ""; ?>>Izquierda</option>
                                                    <option value="right" <?php echo $tpl["text_align"] === "right" ? "selected" : ""; ?>>Derecha</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-3">
                                                <label class="form-label mb-1">Tarjetas por fila</label>
                                                <input type="number" min="1" max="6" name="grid_cols" id="grid_cols" value="<?php echo (int) $tpl["grid_cols"]; ?>" class="form-control">
                                            </div>
                                        </div>

                                        <div id="cardStage" style="position:relative; display:inline-block; max-width:100%; border:1px solid #e5e7eb; user-select:none;">
                                            <img id="cardImg" src="<?php echo htmlspecialchars($tpl["image_path"]); ?>" style="display:block; max-width:100%; height:auto;" draggable="false">
                                            <div id="nameBox" style="position:absolute; left:<?php echo htmlspecialchars($tpl["pos_x"]); ?>%; top:<?php echo htmlspecialchars($tpl["pos_y"]); ?>%; transform:translate(-50%,-50%); cursor:move; white-space:nowrap; font-weight:700; color:<?php echo htmlspecialchars($tpl["font_color"]); ?>; text-align:<?php echo htmlspecialchars($tpl["text_align"]); ?>;">Juan Pérez</div>
                                        </div>

                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save me-1"></i>Guardar posición</button>
                                            <span class="text-muted small ms-2">Arrastra el nombre a su lugar y guarda. Luego usa "Generar e imprimir".</span>
                                        </div>
                                    </form>
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
<?php if ($tpl): ?>
<script>
(function () {
    var stage = document.getElementById('cardStage');
    var img = document.getElementById('cardImg');
    var box = document.getElementById('nameBox');
    var posX = document.getElementById('pos_x');
    var posY = document.getElementById('pos_y');
    var fontRange = document.getElementById('font_pct_range');
    var fontHidden = document.getElementById('font_pct');
    var colorInp = document.getElementById('font_color');
    var alignInp = document.getElementById('text_align');

    function applyFont() {
        var pct = parseFloat(fontRange.value) || 6;
        fontHidden.value = pct;
        // tamaño en px proporcional al ancho mostrado de la imagen
        box.style.fontSize = (pct / 100 * img.clientWidth) + 'px';
    }
    function applyStyle() {
        box.style.color = colorInp.value;
        box.style.textAlign = alignInp.value;
    }
    fontRange.addEventListener('input', applyFont);
    colorInp.addEventListener('input', applyStyle);
    alignInp.addEventListener('change', applyStyle);
    window.addEventListener('resize', applyFont);
    img.addEventListener('load', applyFont);
    applyFont(); applyStyle();

    // Arrastrar el nombre dentro de la imagen
    var dragging = false;
    function moveTo(clientX, clientY) {
        var rect = stage.getBoundingClientRect();
        var x = (clientX - rect.left) / rect.width * 100;
        var y = (clientY - rect.top) / rect.height * 100;
        x = Math.max(0, Math.min(100, x));
        y = Math.max(0, Math.min(100, y));
        box.style.left = x + '%';
        box.style.top = y + '%';
        posX.value = x.toFixed(2);
        posY.value = y.toFixed(2);
    }
    box.addEventListener('mousedown', function (e) { dragging = true; e.preventDefault(); });
    document.addEventListener('mousemove', function (e) { if (dragging) moveTo(e.clientX, e.clientY); });
    document.addEventListener('mouseup', function () { dragging = false; });
    // Soporte táctil
    box.addEventListener('touchstart', function (e) { dragging = true; }, { passive: true });
    document.addEventListener('touchmove', function (e) { if (dragging && e.touches[0]) { moveTo(e.touches[0].clientX, e.touches[0].clientY); } }, { passive: true });
    document.addEventListener('touchend', function () { dragging = false; });
})();
</script>
<?php endif; ?>
</body>
</html>
