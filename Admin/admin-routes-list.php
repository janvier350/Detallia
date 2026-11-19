<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 6]); // Administrador, Jefe, Asistente, Editor

$STATUS_LABELS = [
    'pendiente' => 'Pendiente',
    'entregado' => 'Entregado',
    'devuelto'  => 'Devuelto',
    'cancelado' => 'Entrega cancelada',
];

// Conserva los filtros al redirigir (PRG) o en enlaces
function routes_query_string($source = null)
{
    $src = $source ?? array_merge($_GET, $_POST);
    $keep = [];
    foreach (["provincia", "ciudad", "brand_id", "classification_id", "estado", "carrier"] as $k) {
        if (isset($src[$k]) && $src[$k] !== "") { $keep[$k] = $src[$k]; }
    }
    return $keep ? ("?" . http_build_query($keep)) : "";
}

// =====================================================================
// AJAX: guardado rapido de la confirmacion del transportador
// (visto + fecha de entrega + comentario). Devuelve JSON.
// =====================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "carrier_update") {
    header('Content-Type: application/json');
    $client_id = (int) ($_POST["client_id"] ?? 0);
    $checked   = !empty($_POST["carrier_checked"]) ? 1 : 0;
    $date      = trim($_POST["delivery_date"] ?? "");
    $comment   = trim($_POST["carrier_comment"] ?? "");
    if ($date !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = ""; }
    if ($client_id <= 0) { echo json_encode(["ok" => false, "msg" => "Cliente no válido"]); exit; }

    $date_val = $date !== "" ? $date : null;
    $by = (int) $_SESSION["id"];
    $stmt = mysqli_prepare($link, "INSERT INTO route_deliveries
        (client_id, carrier_checked, delivery_date, carrier_comment, updated_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        carrier_checked = VALUES(carrier_checked),
        delivery_date   = VALUES(delivery_date),
        carrier_comment = VALUES(carrier_comment),
        updated_by      = VALUES(updated_by)");
    mysqli_stmt_bind_param($stmt, "iissi", $client_id, $checked, $date_val, $comment, $by);
    $ok = mysqli_stmt_execute($stmt);
    if ($ok) { log_activity($link, "actualizo", "Rutas", "Transportador actualizo entrega del cliente #" . $client_id); }
    echo json_encode(["ok" => (bool) $ok]);
    exit;
}

$success_msg = "";
$error_msg   = "";
if (isset($_SESSION["flash_success"])) { $success_msg = $_SESSION["flash_success"]; unset($_SESSION["flash_success"]); }
if (isset($_SESSION["flash_error"]))   { $error_msg   = $_SESSION["flash_error"];   unset($_SESSION["flash_error"]); }

// ---------------------------------------------------------------
// Guardar estado de entrega + ejecutivo de cuenta (modal)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_status") {
    $client_id = (int) ($_POST["client_id"] ?? 0);
    $status    = $_POST["status"] ?? "pendiente";
    $date      = trim($_POST["delivery_date"] ?? "");
    $carrier   = (int) ($_POST["carrier_id"] ?? 0);
    $comment   = trim($_POST["cancel_comment"] ?? "");
    $ejec      = trim($_POST["account_executive"] ?? "");

    if (!array_key_exists($status, $STATUS_LABELS)) { $status = "pendiente"; }
    if ($date !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $date = ""; }

    if ($client_id <= 0) {
        $_SESSION["flash_error"] = "Cliente no válido.";
    } elseif ($status === "cancelado" && $comment === "") {
        $_SESSION["flash_error"] = "Para cancelar la entrega debes escribir el motivo de la cancelación.";
    } else {
        $date_val    = $date !== "" ? $date : null;
        $carrier_val = $carrier > 0 ? $carrier : null;
        $comment_val = $status === "cancelado" ? $comment : null;
        $by = (int) $_SESSION["id"];

        // Estado anterior de la entrega (para saber si hay transicion a 'entregado')
        $ps = mysqli_prepare($link, "SELECT status FROM route_deliveries WHERE client_id = ?");
        mysqli_stmt_bind_param($ps, "i", $client_id);
        mysqli_stmt_execute($ps);
        $prevRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ps));
        $prev = $prevRow["status"] ?? "pendiente";

        $to_entregado  = ($status === "entregado" && $prev !== "entregado");
        $from_entregado = ($status !== "entregado" && $prev === "entregado");
        $block_error    = "";
        $kit_deductions = []; // article_id => cantidad
        $kit_name_msg   = "";

        // Al pasar a ENTREGADO: localizar el kit por clasificacion + marca y
        // validar stock. Si falta stock o no hay kit, se BLOQUEA (no cambia estado).
        if ($to_entregado) {
            $cs = mysqli_prepare($link, "SELECT c.brand_id, c.classification_id, b.name AS brand_name, cl.name AS class_name
                FROM clients c
                LEFT JOIN brands b ON b.id = c.brand_id
                LEFT JOIN client_classifications cl ON cl.id = c.classification_id
                WHERE c.id = ?");
            mysqli_stmt_bind_param($cs, "i", $client_id);
            mysqli_stmt_execute($cs);
            $cli = mysqli_fetch_assoc(mysqli_stmt_get_result($cs));

            if (!$cli || !$cli["brand_id"] || !$cli["classification_id"]) {
                $block_error = "El cliente no tiene marca y/o clasificación asignada; no se puede determinar el kit a descontar.";
            } else {
                $ks = mysqli_prepare($link, "SELECT id, name FROM kits WHERE classification_id = ? AND brand_id = ? AND status = 'activo' ORDER BY id DESC LIMIT 1");
                mysqli_stmt_bind_param($ks, "ii", $cli["classification_id"], $cli["brand_id"]);
                mysqli_stmt_execute($ks);
                $kit = mysqli_fetch_assoc(mysqli_stmt_get_result($ks));
                if (!$kit) {
                    $block_error = "No hay un kit definido para la clasificación \"" . ($cli["class_name"] ?? "?") . "\" y la marca \"" . ($cli["brand_name"] ?? "?") . "\". Crea ese kit o revisa la clasificación/marca del cliente.";
                } else {
                    $kit_name_msg = $kit["name"];
                    $its = mysqli_prepare($link, "SELECT ki.article_id, a.name, ki.quantity,
                        COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.article_id = a.id), 0) AS stock
                        FROM kit_items ki JOIN articles a ON a.id = ki.article_id WHERE ki.kit_id = ?");
                    mysqli_stmt_bind_param($its, "i", $kit["id"]);
                    mysqli_stmt_execute($its);
                    $ires = mysqli_stmt_get_result($its);
                    $shortages = [];
                    $nItems = 0;
                    while ($it = mysqli_fetch_assoc($ires)) {
                        $nItems++;
                        $need = (float) $it["quantity"];
                        $have = (float) $it["stock"];
                        if ($have < $need) {
                            $shortages[] = $it["name"] . " (necesita " . format_qty($need) . ", hay " . format_qty($have) . ")";
                        }
                        $aid = (int) $it["article_id"];
                        $kit_deductions[$aid] = ($kit_deductions[$aid] ?? 0) + $need;
                    }
                    if ($nItems === 0) {
                        $block_error = "El kit \"" . $kit["name"] . "\" no tiene artículos; no hay nada que descontar.";
                    } elseif ($shortages) {
                        $block_error = "No se puede marcar Entregado: falta stock de " . implode("; ", $shortages) . ".";
                    }
                }
            }
        }

        if ($block_error !== "") {
            $_SESSION["flash_error"] = $block_error;
        } else {
            $stmt = mysqli_prepare($link, "INSERT INTO route_deliveries
                (client_id, status, delivery_date, carrier_id, cancel_comment, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                status = VALUES(status), delivery_date = VALUES(delivery_date),
                carrier_id = VALUES(carrier_id), cancel_comment = VALUES(cancel_comment),
                updated_by = VALUES(updated_by)");
            mysqli_stmt_bind_param($stmt, "issisi", $client_id, $status, $date_val, $carrier_val, $comment_val, $by);
            $ok = mysqli_stmt_execute($stmt);

            // Ejecutivo de cuenta se guarda en el cliente.
            $eu = mysqli_prepare($link, "UPDATE clients SET account_executive = ? WHERE id = ?");
            $ejec_val = $ejec !== "" ? $ejec : null;
            mysqli_stmt_bind_param($eu, "si", $ejec_val, $client_id);
            mysqli_stmt_execute($eu);

            // Movimientos de inventario por el cambio de estado
            if ($to_entregado && $kit_deductions) {
                $chk = mysqli_query($link, "SELECT 1 FROM stock_movements WHERE reference_type='ruta' AND reference_id=" . $client_id . " LIMIT 1");
                if (!($chk && mysqli_num_rows($chk) > 0)) {
                    $mov = mysqli_prepare($link, "INSERT INTO stock_movements (article_id, movement_type, quantity, reference_type, reference_id, created_by) VALUES (?, 'ruta', ?, 'ruta', ?, ?)");
                    foreach ($kit_deductions as $aid => $total) {
                        $neg = -1 * (float) $total;
                        mysqli_stmt_bind_param($mov, "idii", $aid, $neg, $client_id, $by);
                        mysqli_stmt_execute($mov);
                    }
                }
            } elseif ($from_entregado) {
                // Sale de 'entregado': revertir la salida de inventario del kit.
                $dm = mysqli_prepare($link, "DELETE FROM stock_movements WHERE reference_type='ruta' AND reference_id=?");
                mysqli_stmt_bind_param($dm, "i", $client_id);
                mysqli_stmt_execute($dm);
            }

            if ($ok) {
                log_activity($link, "actualizo", "Rutas", "Entrega del cliente #" . $client_id . " -> " . $STATUS_LABELS[$status]);
                if ($to_entregado && $kit_deductions) {
                    $_SESSION["flash_success"] = "Entregado. Se descontó el kit \"" . $kit_name_msg . "\" del inventario.";
                } elseif ($from_entregado) {
                    $_SESSION["flash_success"] = "Estado actualizado. Se revirtió el descuento de inventario del kit.";
                } else {
                    $_SESSION["flash_success"] = "Estado de entrega actualizado.";
                }
            } else {
                $_SESSION["flash_error"] = "No se pudo actualizar: " . mysqli_error($link);
            }
        }
    }
    header("location: admin-routes-list.php" . routes_query_string());
    exit;
}

// ---------------------------------------------------------------
// Catalogo de transportadores
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_carrier") {
    $name  = trim($_POST["carrier_name"] ?? "");
    $phone = trim($_POST["carrier_phone"] ?? "");
    if ($name !== "") {
        $stmt = mysqli_prepare($link, "INSERT INTO carriers (name, phone) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $name, $phone);
        mysqli_stmt_execute($stmt);
        log_activity($link, "creo", "Rutas", "Agrego transportador: " . $name);
        $_SESSION["flash_success"] = "Transportador agregado.";
    } else {
        $_SESSION["flash_error"] = "El nombre del transportador es obligatorio.";
    }
    header("location: admin-routes-list.php" . routes_query_string());
    exit;
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_carrier") {
    $cid = (int) ($_POST["carrier_id"] ?? 0);
    if ($cid > 0) {
        $stmt = mysqli_prepare($link, "DELETE FROM carriers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $cid);
        mysqli_stmt_execute($stmt);
        log_activity($link, "elimino", "Rutas", "Elimino transportador #" . $cid);
        $_SESSION["flash_success"] = "Transportador eliminado.";
    }
    header("location: admin-routes-list.php" . routes_query_string());
    exit;
}

// ---------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------
$filter_provincia = trim($_GET["provincia"] ?? "");
$filter_ciudad    = trim($_GET["ciudad"] ?? "");
$filter_brand     = (int) ($_GET["brand_id"] ?? 0);
$filter_class     = (int) ($_GET["classification_id"] ?? 0);
$filter_estado    = trim($_GET["estado"] ?? "");
$filter_carrier   = (int) ($_GET["carrier"] ?? 0);

$sql = "SELECT c.id, c.name, c.contact_name, c.account_executive, c.ciudad, c.zona, c.provincia,
               cl.name AS classification_name,
               COALESCE(rd.status, 'pendiente') AS rstatus,
               rd.delivery_date, rd.carrier_id, rd.cancel_comment,
               rd.carrier_checked, rd.carrier_comment,
               ca.name AS carrier_name
        FROM clients c
        LEFT JOIN client_classifications cl ON cl.id = c.classification_id
        LEFT JOIN route_deliveries rd ON rd.client_id = c.id
        LEFT JOIN carriers ca ON ca.id = rd.carrier_id";

$conds = [];
$params = [];
$types = "";
if ($filter_provincia !== "") { $conds[] = "c.provincia = ?";         $params[] = $filter_provincia; $types .= "s"; }
if ($filter_ciudad !== "")    { $conds[] = "c.ciudad = ?";            $params[] = $filter_ciudad;    $types .= "s"; }
if ($filter_brand > 0)        { $conds[] = "c.brand_id = ?";          $params[] = $filter_brand;     $types .= "i"; }
if ($filter_class > 0)        { $conds[] = "c.classification_id = ?"; $params[] = $filter_class;     $types .= "i"; }
if ($filter_estado !== "" && isset($STATUS_LABELS[$filter_estado])) {
    $conds[] = "COALESCE(rd.status, 'pendiente') = ?"; $params[] = $filter_estado; $types .= "s";
}
if ($filter_carrier > 0)      { $conds[] = "rd.carrier_id = ?";       $params[] = $filter_carrier;   $types .= "i"; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY c.contact_name ASC, c.name ASC";

$stmt = mysqli_prepare($link, $sql);
if ($params) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

// =====================================================================
// Exportar a Excel (respeta filtros) - antes de cualquier HTML
// =====================================================================
if (($_GET["export"] ?? "") === "excel") {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="rutas_detallia.xls"');
    echo "\xEF\xBB\xBF"; // BOM utf-8 para acentos en Excel
    echo "<table border='1'><tr>"
        . "<th>Contacto</th><th>Empresa</th><th>Ejecutivo de cuenta</th><th>Clasificacion</th>"
        . "<th>Ciudad</th><th>Zona</th><th>Provincia</th><th>Estado</th><th>Transportador</th>"
        . "<th>Visto</th><th>Fecha entrega</th><th>Comentario transportador</th><th>Motivo cancelacion</th></tr>";
    while ($r = mysqli_fetch_assoc($rows)) {
        $vals = [
            $r["name"], $r["contact_name"], $r["account_executive"], $r["classification_name"],
            $r["ciudad"], $r["zona"], $r["provincia"], $STATUS_LABELS[$r["rstatus"]] ?? $r["rstatus"],
            $r["carrier_name"], (!empty($r["carrier_checked"]) ? "SI" : ""),
            $r["delivery_date"] ? date("d/m/Y", strtotime($r["delivery_date"])) : "",
            $r["carrier_comment"], $r["cancel_comment"],
        ];
        echo "<tr>";
        foreach ($vals as $v) { echo "<td>" . htmlspecialchars((string) $v) . "</td>"; }
        echo "</tr>";
    }
    echo "</table>";
    exit;
}

// Totales por estado
$counts = ['pendiente' => 0, 'entregado' => 0, 'devuelto' => 0, 'cancelado' => 0];
$cres = mysqli_query($link, "SELECT COALESCE(rd.status,'pendiente') s, COUNT(*) n
    FROM clients c LEFT JOIN route_deliveries rd ON rd.client_id=c.id GROUP BY s");
while ($cr = mysqli_fetch_assoc($cres)) { $counts[$cr["s"]] = (int) $cr["n"]; }

// Opciones de filtro
$ciudades = [];
$r = mysqli_query($link, "SELECT DISTINCT ciudad FROM clients WHERE ciudad IS NOT NULL AND ciudad <> '' ORDER BY ciudad");
while ($x = mysqli_fetch_row($r)) { $ciudades[] = $x[0]; }
$provincias = [];
$r = mysqli_query($link, "SELECT DISTINCT provincia FROM clients WHERE provincia IS NOT NULL AND provincia <> '' ORDER BY provincia");
while ($x = mysqli_fetch_row($r)) { $provincias[] = $x[0]; }
$brands_filter = [];
$r = mysqli_query($link, "SELECT id, name FROM brands ORDER BY name");
while ($x = mysqli_fetch_assoc($r)) { $brands_filter[] = $x; }
$class_filter = [];
$r = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY name");
while ($x = mysqli_fetch_assoc($r)) { $class_filter[] = $x; }
$carriers = [];
$r = mysqli_query($link, "SELECT id, name, phone FROM carriers ORDER BY name");
while ($x = mysqli_fetch_assoc($r)) { $carriers[] = $x; }

function status_badge($status, $labels)
{
    $map = ['pendiente' => 'bg-warning', 'entregado' => 'bg-success', 'devuelto' => 'bg-info', 'cancelado' => 'bg-danger'];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($labels[$status] ?? $status) . '</span>';
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Rutas | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Rutas de entrega</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Rutas</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_msg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <?php
                    $cards = [['pendiente', 'Pendientes', 'text-warning'], ['entregado', 'Entregados', 'text-success'], ['devuelto', 'Devueltos', 'text-info'], ['cancelado', 'Cancelados', 'text-danger']];
                    foreach ($cards as $cd): ?>
                        <div class="col-6 col-md-3">
                            <div class="card"><div class="card-body text-center py-3">
                                <h3 class="mb-0 <?php echo $cd[2]; ?>"><?php echo $counts[$cd[0]]; ?></h3>
                                <span class="text-muted"><?php echo $cd[1]; ?></span>
                            </div></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                    <h5 class="card-title mb-0">Listado de entregas por cliente</h5>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="admin-routes-print.php<?php echo routes_query_string($_GET); ?>" target="_blank" class="btn btn-soft-secondary">
                                            <i class="mdi mdi-printer me-1"></i> Hoja de ruta (PDF)
                                        </a>
                                        <a href="admin-routes-list.php<?php echo (routes_query_string($_GET) ? routes_query_string($_GET) . '&' : '?'); ?>export=excel" class="btn btn-soft-success">
                                            <i class="mdi mdi-file-excel me-1"></i> Excel
                                        </a>
                                        <button type="button" class="btn btn-soft-primary" data-bs-toggle="modal" data-bs-target="#carriersModal">
                                            <i class="mdi mdi-truck-outline me-1"></i> Transportadores
                                        </button>
                                    </div>
                                </div>

                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Marca</label>
                                        <select name="brand_id" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($brands_filter as $b): ?>
                                                <option value="<?php echo $b["id"]; ?>" <?php echo $filter_brand == $b["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($b["name"]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Clasificación</label>
                                        <select name="classification_id" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($class_filter as $c): ?>
                                                <option value="<?php echo $c["id"]; ?>" <?php echo $filter_class == $c["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($c["name"]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Ciudad</label>
                                        <select name="ciudad" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($ciudades as $ci): ?>
                                                <option value="<?php echo htmlspecialchars($ci); ?>" <?php echo $filter_ciudad === $ci ? "selected" : ""; ?>><?php echo htmlspecialchars($ci); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Provincia</label>
                                        <select name="provincia" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todas</option>
                                            <?php foreach ($provincias as $pr): ?>
                                                <option value="<?php echo htmlspecialchars($pr); ?>" <?php echo $filter_provincia === $pr ? "selected" : ""; ?>><?php echo htmlspecialchars($pr); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Estado</label>
                                        <select name="estado" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todos</option>
                                            <?php foreach ($STATUS_LABELS as $k => $lbl): ?>
                                                <option value="<?php echo $k; ?>" <?php echo $filter_estado === $k ? "selected" : ""; ?>><?php echo $lbl; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label mb-1">Transportador</label>
                                        <select name="carrier" class="form-select" onchange="this.form.submit()">
                                            <option value="">Todos</option>
                                            <?php foreach ($carriers as $ca): ?>
                                                <option value="<?php echo $ca["id"]; ?>" <?php echo $filter_carrier == $ca["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($ca["name"]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <a href="admin-routes-list.php" class="btn btn-soft-secondary btn-sm"><i class="mdi mdi-filter-remove-outline me-1"></i>Limpiar filtros</a>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-centered table-nowrap mb-0" id="routesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Contacto</th>
                                                <th>Empresa</th>
                                                <th>Ejecutivo de cuenta</th>
                                                <th>Clasificación</th>
                                                <th>Ciudad</th>
                                                <th>Zona</th>
                                                <th>Provincia</th>
                                                <th>Estado</th>
                                                <th>Transportador</th>
                                                <th>Visto</th>
                                                <th>Fecha entrega</th>
                                                <th>Comentario transportador</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                            <tr>
                                                <th></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="1" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="2" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="3" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="4" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="5" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="6" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="7" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="8" placeholder="Buscar..."></th>
                                                <th><input type="text" class="form-control form-control-sm colf" data-col="9" placeholder="Buscar..."></th>
                                                <th></th><th></th><th></th><th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($row = mysqli_fetch_assoc($rows)):
                                                $st  = $row["rstatus"];
                                                $cid = (int) $row["id"]; ?>
                                                <tr>
                                                    <td><?php echo $cid; ?></td>
                                                    <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                                    <td><?php echo htmlspecialchars($row["contact_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($row["account_executive"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($row["classification_name"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($row["ciudad"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($row["zona"] ?? "—"); ?></td>
                                                    <td><?php echo htmlspecialchars($row["provincia"] ?? "—"); ?></td>
                                                    <td><?php echo status_badge($st, $STATUS_LABELS); ?></td>
                                                    <td><?php echo htmlspecialchars($row["carrier_name"] ?? "—"); ?></td>
                                                    <!-- Confirmacion del transportador (edicion rapida) -->
                                                    <td class="text-center">
                                                        <input type="checkbox" class="form-check-input cr-chk" data-client="<?php echo $cid; ?>" <?php echo !empty($row["carrier_checked"]) ? "checked" : ""; ?>>
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control form-control-sm cr-date" data-client="<?php echo $cid; ?>" value="<?php echo htmlspecialchars($row["delivery_date"] ?? ""); ?>" style="min-width:140px;">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm cr-com" data-client="<?php echo $cid; ?>" value="<?php echo htmlspecialchars($row["carrier_comment"] ?? "", ENT_QUOTES); ?>" placeholder="Incidencia / nota" style="min-width:180px;">
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="cr-saved text-success me-1" data-client="<?php echo $cid; ?>" style="display:none;"><i class="mdi mdi-check-circle"></i></span>
                                                        <button type="button" class="btn btn-sm btn-soft-primary btn-edit"
                                                            data-id="<?php echo $cid; ?>"
                                                            data-contacto="<?php echo htmlspecialchars($row["name"], ENT_QUOTES); ?>"
                                                            data-empresa="<?php echo htmlspecialchars($row["contact_name"] ?? "", ENT_QUOTES); ?>"
                                                            data-ejecutivo="<?php echo htmlspecialchars($row["account_executive"] ?? "", ENT_QUOTES); ?>"
                                                            data-status="<?php echo $st; ?>"
                                                            data-date="<?php echo htmlspecialchars($row["delivery_date"] ?? ""); ?>"
                                                            data-carrier="<?php echo (int) $row["carrier_id"]; ?>"
                                                            data-comment="<?php echo htmlspecialchars($row["cancel_comment"] ?? "", ENT_QUOTES); ?>">
                                                            <i class="mdi mdi-pencil"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-muted small mt-2 mb-0"><i class="mdi mdi-information-outline me-1"></i>El visto, la fecha y el comentario se guardan solos al cambiarlos. Usa el lápiz para el estado, transportador y ejecutivo de cuenta.</p>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<!-- Modal: estado de entrega -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Entrega / estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="client_id" id="m_client_id">
                    <?php foreach (["provincia" => $filter_provincia, "ciudad" => $filter_ciudad, "brand_id" => $filter_brand, "classification_id" => $filter_class, "estado" => $filter_estado, "carrier" => $filter_carrier] as $k => $v): ?>
                        <input type="hidden" name="<?php echo $k; ?>" value="<?php echo htmlspecialchars((string) $v); ?>">
                    <?php endforeach; ?>

                    <p class="mb-3"><strong id="m_contacto"></strong><br><span class="text-muted" id="m_empresa"></span></p>

                    <div class="mb-3">
                        <label class="form-label">Ejecutivo de cuenta</label>
                        <input type="text" name="account_executive" id="m_ejecutivo" class="form-control" placeholder="Nombre del ejecutivo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select name="status" id="m_status" class="form-select" required>
                            <?php foreach ($STATUS_LABELS as $k => $lbl): ?>
                                <option value="<?php echo $k; ?>"><?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de entrega</label>
                        <input type="date" name="delivery_date" id="m_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Transportador</label>
                        <select name="carrier_id" id="m_carrier" class="form-select">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($carriers as $ca): ?>
                                <option value="<?php echo $ca["id"]; ?>"><?php echo htmlspecialchars($ca["name"]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2" id="m_comment_wrap" style="display:none;">
                        <label class="form-label">Motivo de la cancelación <span class="text-danger">*</span></label>
                        <textarea name="cancel_comment" id="m_comment" class="form-control" rows="3" placeholder="Explica por qué se canceló la entrega"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: transportadores -->
<div class="modal fade" id="carriersModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transportadores</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="row g-2 mb-3">
                    <input type="hidden" name="action" value="add_carrier">
                    <div class="col-7"><input type="text" name="carrier_name" class="form-control" placeholder="Nombre" required></div>
                    <div class="col-3"><input type="text" name="carrier_phone" class="form-control" placeholder="Teléfono"></div>
                    <div class="col-2"><button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-plus"></i></button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Nombre</th><th>Teléfono</th><th></th></tr></thead>
                        <tbody>
                            <?php if ($carriers): foreach ($carriers as $ca): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ca["name"]); ?></td>
                                    <td><?php echo htmlspecialchars($ca["phone"] ?? "—"); ?></td>
                                    <td class="text-end">
                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este transportador?');">
                                            <input type="hidden" name="action" value="delete_carrier">
                                            <input type="hidden" name="carrier_id" value="<?php echo $ca["id"]; ?>">
                                            <button class="btn btn-sm btn-soft-danger"><i class="mdi mdi-delete"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="text-center text-muted">Aún no hay transportadores.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>
<script>
// Abrir modal de estado con los datos de la fila
document.querySelectorAll('.btn-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('m_client_id').value = btn.dataset.id;
        document.getElementById('m_contacto').textContent = btn.dataset.contacto;
        document.getElementById('m_empresa').textContent = btn.dataset.empresa;
        document.getElementById('m_ejecutivo').value = btn.dataset.ejecutivo || '';
        document.getElementById('m_status').value = btn.dataset.status;
        document.getElementById('m_date').value = btn.dataset.date;
        document.getElementById('m_carrier').value = btn.dataset.carrier && btn.dataset.carrier !== '0' ? btn.dataset.carrier : '';
        document.getElementById('m_comment').value = btn.dataset.comment || '';
        toggleComment();
        new bootstrap.Modal(document.getElementById('statusModal')).show();
    });
});
var statusSel = document.getElementById('m_status');
function toggleComment() {
    var isCancel = statusSel.value === 'cancelado';
    document.getElementById('m_comment_wrap').style.display = isCancel ? '' : 'none';
    document.getElementById('m_comment').required = isCancel;
}
statusSel.addEventListener('change', toggleComment);

// Guardado rapido de la confirmacion del transportador (visto/fecha/comentario)
function saveCarrier(clientId) {
    var chk  = document.querySelector('.cr-chk[data-client="' + clientId + '"]');
    var date = document.querySelector('.cr-date[data-client="' + clientId + '"]');
    var com  = document.querySelector('.cr-com[data-client="' + clientId + '"]');
    var saved = document.querySelector('.cr-saved[data-client="' + clientId + '"]');
    var body = new URLSearchParams();
    body.set('action', 'carrier_update');
    body.set('client_id', clientId);
    if (chk.checked) body.set('carrier_checked', '1');
    body.set('delivery_date', date.value || '');
    body.set('carrier_comment', com.value || '');
    fetch('admin-routes-list.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.ok && saved) { saved.style.display = ''; setTimeout(function () { saved.style.display = 'none'; }, 1500); }
        })
        .catch(function () {});
}
document.querySelectorAll('.cr-chk, .cr-date, .cr-com').forEach(function (el) {
    var ev = el.classList.contains('cr-com') ? 'change' : 'change';
    el.addEventListener(ev, function () { saveCarrier(el.dataset.client); });
});

// Busqueda por columna (estilo Tango)
(function () {
    var table = document.getElementById('routesTable');
    if (!table) return;
    var inputs = table.querySelectorAll('.colf');
    var rows = table.querySelectorAll('tbody tr');
    function apply() {
        var filters = [];
        inputs.forEach(function (inp) {
            var v = inp.value.trim().toLowerCase();
            if (v) filters.push({ col: parseInt(inp.getAttribute('data-col')), val: v });
        });
        rows.forEach(function (row) {
            var cells = row.children;
            var show = filters.every(function (f) {
                var cell = cells[f.col];
                return cell && (cell.innerText || cell.textContent || '').toLowerCase().indexOf(f.val) !== -1;
            });
            row.style.display = show ? '' : 'none';
        });
    }
    inputs.forEach(function (inp) { inp.addEventListener('input', apply); });
})();
</script>
</body>
</html>
