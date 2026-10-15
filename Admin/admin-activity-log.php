<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2]);

// ---------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------
$f_user   = (int) ($_GET["user_id"] ?? 0);
$f_module = trim($_GET["module"] ?? "");
$f_action = trim($_GET["action"] ?? "");
$f_from   = trim($_GET["from"] ?? "");
$f_to     = trim($_GET["to"] ?? "");
$f_search = trim($_GET["q"] ?? "");
$export   = ($_GET["export"] ?? "") === "1";

$conds = []; $params = []; $types = "";
if ($f_user > 0)      { $conds[] = "l.user_id = ?";      $params[] = $f_user;   $types .= "i"; }
if ($f_module !== "") { $conds[] = "l.module = ?";       $params[] = $f_module; $types .= "s"; }
if ($f_action !== "") { $conds[] = "l.action = ?";       $params[] = $f_action; $types .= "s"; }
if ($f_from !== "")   { $conds[] = "l.created_at >= ?";  $params[] = $f_from . " 00:00:00"; $types .= "s"; }
if ($f_to !== "")     { $conds[] = "l.created_at <= ?";  $params[] = $f_to . " 23:59:59";   $types .= "s"; }
if ($f_search !== "") {
    $conds[] = "(l.username LIKE ? OR l.reference LIKE ? OR l.detail LIKE ?)";
    $like = "%" . $f_search . "%";
    $params[] = $like; $params[] = $like; $params[] = $like; $types .= "sss";
}
$where = !empty($conds) ? (" WHERE " . implode(" AND ", $conds)) : "";

// Total que coincide
$countSql = "SELECT COUNT(*) c FROM activity_log l$where";
$cstmt = mysqli_prepare($link, $countSql);
if (!empty($params)) mysqli_stmt_bind_param($cstmt, $types, ...$params);
mysqli_stmt_execute($cstmt);
$totalRows = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($cstmt))["c"];

$limit = $export ? 100000 : 500;
$dataSql = "SELECT l.* FROM activity_log l$where ORDER BY l.id DESC LIMIT $limit";
$dstmt = mysqli_prepare($link, $dataSql);
if (!empty($params)) mysqli_stmt_bind_param($dstmt, $types, ...$params);
mysqli_stmt_execute($dstmt);
$rows = mysqli_stmt_get_result($dstmt);

// ---------------------------------------------------------------
// Exportar a Excel (CSV compatible)
// ---------------------------------------------------------------
if ($export) {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bitacora_' . date('Ymd_His') . '.xls"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo "Fecha/hora\tUsuario\tRol\tModulo\tReferencia\tAccion\tDetalle\tDispositivo\tSO\tNavegador\tIP\n";
    while ($r = mysqli_fetch_assoc($rows)) {
        $line = [
            $r["created_at"], $r["username"], $r["role_name"], $r["module"], $r["reference"],
            $r["action"], str_replace(["\t", "\n", "\r"], " ", (string) $r["detail"]),
            $r["device"], $r["os"], $r["browser"], $r["ip"],
        ];
        echo implode("\t", array_map(function ($v) { return str_replace(["\t", "\n", "\r"], " ", (string) $v); }, $line)) . "\n";
    }
    exit;
}

// Opciones para los selectores
$usersOpt  = mysqli_query($link, "SELECT DISTINCT user_id, username FROM activity_log WHERE user_id IS NOT NULL ORDER BY username");
$modsOpt   = mysqli_query($link, "SELECT DISTINCT module FROM activity_log ORDER BY module");
$actsOpt   = mysqli_query($link, "SELECT DISTINCT action FROM activity_log ORDER BY action");

$actionBadges = [
    "ingreso" => ["Ingreso", "primary"],
    "salio"   => ["Salio", "secondary"],
    "creo"    => ["Creo", "success"],
    "edito"   => ["Edito", "warning"],
    "elimino" => ["Elimino", "danger"],
    "estado"  => ["Estado", "info"],
    "importo" => ["Importo", "success"],
];
function badge_for($action, $map) {
    $a = strtolower($action);
    if (isset($map[$a])) return $map[$a];
    return [ucfirst($a), "dark"];
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Bitacora de cambios | Detallia</title>
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
                            <div>
                                <h4 class="mb-1 font-size-18">Bitacora de cambios (auditoria)</h4>
                                <p class="text-muted mb-0">Quien crea, edita o elimina informacion y en que modulo.</p>
                            </div>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Bitacora</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card">
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end">
                            <div class="col-6 col-md-3">
                                <label class="form-label small text-muted mb-1">Usuario</label>
                                <select name="user_id" class="form-select">
                                    <option value="">Todos</option>
                                    <?php while ($u = mysqli_fetch_assoc($usersOpt)): ?>
                                        <option value="<?php echo (int) $u["user_id"]; ?>" <?php echo $f_user === (int) $u["user_id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($u["username"]); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted mb-1">Modulo</label>
                                <select name="module" class="form-select">
                                    <option value="">Todos</option>
                                    <?php while ($m = mysqli_fetch_assoc($modsOpt)): ?>
                                        <option value="<?php echo htmlspecialchars($m["module"]); ?>" <?php echo $f_module === $m["module"] ? "selected" : ""; ?>><?php echo htmlspecialchars($m["module"]); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted mb-1">Accion</label>
                                <select name="action" class="form-select">
                                    <option value="">Todas</option>
                                    <?php while ($a = mysqli_fetch_assoc($actsOpt)): ?>
                                        <option value="<?php echo htmlspecialchars($a["action"]); ?>" <?php echo $f_action === $a["action"] ? "selected" : ""; ?>><?php echo htmlspecialchars(ucfirst($a["action"])); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted mb-1">Desde</label>
                                <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($f_from); ?>">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted mb-1">Hasta</label>
                                <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($f_to); ?>">
                            </div>
                            <div class="col-6 col-md-1">
                                <button type="submit" class="btn btn-primary w-100"><i class="mdi mdi-magnify"></i></button>
                            </div>
                            <div class="col-12 col-md-9">
                                <input type="text" name="q" class="form-control" placeholder="Buscar en usuario / referencia / detalle..." value="<?php echo htmlspecialchars($f_search); ?>">
                            </div>
                            <div class="col-6 col-md-3 d-flex gap-2">
                                <a href="admin-activity-log.php" class="btn btn-light w-100"><i class="mdi mdi-close me-1"></i>Limpiar</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <p class="text-muted mb-0">Mostrando <?php echo min($limit, $totalRows); ?> de <?php echo number_format($totalRows); ?> registros<?php echo $totalRows > $limit ? " (ultimos $limit)" : ""; ?>.</p>
                            <a href="?<?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'] . '&export=1' : 'export=1'); ?>" class="btn btn-success btn-sm"><i class="mdi mdi-file-excel me-1"></i> Exportar a Excel</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha/hora</th>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Modulo</th>
                                        <th>Referencia</th>
                                        <th>Accion</th>
                                        <th style="white-space:normal;min-width:260px">Detalle</th>
                                        <th>Dispositivo</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($totalRows === 0): ?>
                                        <tr><td colspan="9" class="text-center text-muted py-4">Sin registros para los filtros aplicados.</td></tr>
                                    <?php else: while ($r = mysqli_fetch_assoc($rows)): $b = badge_for($r["action"], $actionBadges); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($r["created_at"]))); ?></td>
                                            <td><?php echo htmlspecialchars($r["username"] ?? "—"); ?></td>
                                            <td><span class="text-muted small"><?php echo htmlspecialchars($r["role_name"] ?? ""); ?></span></td>
                                            <td><?php echo htmlspecialchars($r["module"]); ?></td>
                                            <td><?php echo htmlspecialchars($r["reference"] ?? "—"); ?></td>
                                            <td><span class="badge bg-<?php echo $b[1]; ?>"><?php echo htmlspecialchars($b[0]); ?></span></td>
                                            <td style="white-space:normal"><?php echo htmlspecialchars($r["detail"] ?? ""); ?></td>
                                            <td>
                                                <i class="mdi mdi-<?php echo $r["device"] === 'Telefono' ? 'cellphone' : ($r["device"] === 'Tablet' ? 'tablet' : 'monitor'); ?> me-1"></i><?php echo htmlspecialchars($r["device"] ?? ""); ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars(trim(($r["os"] ?? "") . " · " . ($r["browser"] ?? ""), " ·")); ?></div>
                                            </td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($r["ip"] ?? ""); ?></td>
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

<?php include 'layouts/right-sidebar.php'; ?>
<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

</body>
</html>
