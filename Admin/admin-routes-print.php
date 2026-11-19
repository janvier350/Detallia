<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 6]);

$STATUS_LABELS = [
    'pendiente' => 'Pendiente', 'entregado' => 'Entregado',
    'devuelto' => 'Devuelto', 'cancelado' => 'Entrega cancelada',
];

$filter_provincia = trim($_GET["provincia"] ?? "");
$filter_ciudad    = trim($_GET["ciudad"] ?? "");
$filter_brand     = (int) ($_GET["brand_id"] ?? 0);
$filter_class     = (int) ($_GET["classification_id"] ?? 0);
$filter_estado    = trim($_GET["estado"] ?? "");
$filter_carrier   = (int) ($_GET["carrier"] ?? 0);

$sql = "SELECT c.id, c.name, c.contact_name, c.account_executive, c.ciudad, c.zona, c.provincia, c.address,
               cl.name AS classification_name,
               COALESCE(rd.status,'pendiente') AS rstatus, rd.delivery_date,
               ca.name AS carrier_name
        FROM clients c
        LEFT JOIN client_classifications cl ON cl.id = c.classification_id
        LEFT JOIN route_deliveries rd ON rd.client_id = c.id
        LEFT JOIN carriers ca ON ca.id = rd.carrier_id";
$conds = []; $params = []; $types = "";
if ($filter_provincia !== "") { $conds[] = "c.provincia = ?";         $params[] = $filter_provincia; $types .= "s"; }
if ($filter_ciudad !== "")    { $conds[] = "c.ciudad = ?";            $params[] = $filter_ciudad;    $types .= "s"; }
if ($filter_brand > 0)        { $conds[] = "c.brand_id = ?";          $params[] = $filter_brand;     $types .= "i"; }
if ($filter_class > 0)        { $conds[] = "c.classification_id = ?"; $params[] = $filter_class;     $types .= "i"; }
if ($filter_estado !== "" && isset($STATUS_LABELS[$filter_estado])) { $conds[] = "COALESCE(rd.status,'pendiente') = ?"; $params[] = $filter_estado; $types .= "s"; }
if ($filter_carrier > 0)      { $conds[] = "rd.carrier_id = ?";       $params[] = $filter_carrier;   $types .= "i"; }
if ($conds) { $sql .= " WHERE " . implode(" AND ", $conds); }
$sql .= " ORDER BY c.contact_name ASC, c.name ASC";
$stmt = mysqli_prepare($link, $sql);
if ($params) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt);

$carrier_name = "";
if ($filter_carrier > 0) {
    $cs = mysqli_prepare($link, "SELECT name FROM carriers WHERE id = ?");
    mysqli_stmt_bind_param($cs, "i", $filter_carrier);
    mysqli_stmt_execute($cs);
    $carrier_name = mysqli_fetch_assoc(mysqli_stmt_get_result($cs))["name"] ?? "";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de ruta | Detallia</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #2d3748; margin: 24px; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #556ee6; padding-bottom: 12px; margin-bottom: 14px; }
        h1 { font-size: 18px; margin: 0; }
        .meta { color: #74788d; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #c9ccd6; padding: 5px 6px; font-size: 11px; text-align: left; vertical-align: top; }
        th { background: #f1f3f9; }
        .box { width: 16px; height: 16px; border: 1.5px solid #555; display: inline-block; }
        .firma { margin-top: 36px; font-size: 12px; }
        @media print { .no-print { display: none; } @page { size: landscape; margin: 10mm; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Hoja de ruta — Detallia</h1>
        <div class="meta">
            <?php echo $carrier_name ? ("Transportador: <strong>" . htmlspecialchars($carrier_name) . "</strong><br>") : ""; ?>
            Fecha de impresión: <?php echo date("d/m/Y H:i"); ?>
            <?php
            $fl = [];
            if ($filter_estado !== "" && isset($STATUS_LABELS[$filter_estado])) $fl[] = "Estado: " . $STATUS_LABELS[$filter_estado];
            if ($filter_ciudad !== "") $fl[] = "Ciudad: " . $filter_ciudad;
            if ($filter_provincia !== "") $fl[] = "Provincia: " . $filter_provincia;
            echo $fl ? ("<br>" . htmlspecialchars(implode(" · ", $fl))) : "";
            ?>
        </div>
    </div>

    <div class="no-print" style="margin-bottom:12px;">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Empresa</th>
                <th>Contacto</th>
                <th>Ejecutivo de cuenta</th>
                <th>Clasif.</th>
                <th>Ciudad</th>
                <th>Zona</th>
                <th>Dirección</th>
                <th>Estado</th>
                <th>Entregado<br>(visto)</th>
                <th>Fecha<br>entrega</th>
                <th>Comentario / incidencia</th>
            </tr>
        </thead>
        <tbody>
            <?php $n = 0; while ($r = mysqli_fetch_assoc($rows)): $n++; ?>
                <tr>
                    <td><?php echo $n; ?></td>
                    <td><?php echo htmlspecialchars($r["contact_name"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($r["name"]); ?></td>
                    <td><?php echo htmlspecialchars($r["account_executive"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($r["classification_name"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($r["ciudad"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($r["zona"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($r["address"] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($STATUS_LABELS[$r["rstatus"]] ?? $r["rstatus"]); ?></td>
                    <td style="text-align:center;"><span class="box"></span></td>
                    <td style="min-width:70px;">&nbsp;</td>
                    <td style="min-width:150px;">&nbsp;</td>
                </tr>
            <?php endwhile; ?>
            <?php if ($n === 0): ?>
                <tr><td colspan="12" style="text-align:center;">No hay registros para los filtros seleccionados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="firma">
        Transportador: ______________________________ &nbsp;&nbsp;&nbsp;
        Firma: ______________________________ &nbsp;&nbsp;&nbsp;
        Fecha: ____________________
    </div>
</body>
</html>
