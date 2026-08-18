<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);

$sim_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
if ($sim_id <= 0) { header("location: admin-kit-sim-list.php"); exit; }

$stmt = mysqli_prepare($link, "SELECT s.*, cl.name AS classification_name, COALESCE(u.full_name, u.username) AS created_by_name
                               FROM kit_simulations s
                               LEFT JOIN client_classifications cl ON cl.id = s.classification_id
                               LEFT JOIN users u ON u.id = s.created_by
                               WHERE s.id = ?");
mysqli_stmt_bind_param($stmt, "i", $sim_id);
mysqli_stmt_execute($stmt);
$sim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$sim) { header("location: admin-kit-sim-list.php"); exit; }

$ri = mysqli_query($link, "SELECT * FROM kit_simulation_items WHERE simulation_id = " . (int) $sim_id . " ORDER BY sort_order, id");
$items = [];
$subtotalSum = 0; $ivaSum = 0; $totalSum = 0;
while ($r = mysqli_fetch_assoc($ri)) {
    $sub = $r["unit_price"] * $r["quantity"];
    $ivaAmt = $sub * ($r["iva_rate"] / 100);
    $r["_sub"] = $sub; $r["_iva"] = $ivaAmt; $r["_tot"] = $sub + $ivaAmt;
    $subtotalSum += $sub; $ivaSum += $ivaAmt; $totalSum += $sub + $ivaAmt;
    $items[] = $r;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Simulacro de kit #<?php echo (int) $sim["id"]; ?> | Detallia</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #2d3748; margin: 40px; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #556ee6; padding-bottom: 16px; margin-bottom: 24px; }
        .header img { height: 34px; }
        h1 { font-size: 20px; margin: 0; }
        .doc-id { color: #74788d; font-size: 13px; }
        .meta { display: flex; flex-wrap: wrap; gap: 24px 48px; margin-bottom: 24px; }
        .meta .label { color: #74788d; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .meta .val { font-size: 15px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { background: #f5f6fa; text-align: left; font-size: 12px; text-transform: uppercase; color: #74788d; padding: 10px 8px; border-bottom: 2px solid #e2e5ec; }
        table.items td { padding: 9px 8px; border-bottom: 1px solid #eef0f4; font-size: 14px; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 2px solid #e2e5ec; }
        .totbox { margin-top: 20px; float: right; width: 300px; }
        .totbox .line { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; border-bottom: 1px solid #eef0f4; }
        .totbox .grand { font-size: 18px; font-weight: bold; color: #556ee6; border-bottom: none; }
        .notes { margin-top: 90px; font-size: 13px; color: #495057; clear: both; }
        .print-actions { margin-bottom: 20px; }
        .print-actions button, .print-actions a { padding: 8px 16px; font-size: 14px; border-radius: 6px; border: 1px solid #556ee6; cursor: pointer; text-decoration: none; }
        .print-actions button { background: #556ee6; color: #fff; }
        .print-actions a { background: #fff; color: #556ee6; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>

<body>

    <div class="print-actions no-print">
        <button onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
        <a href="admin-kit-sim-list.php">Volver</a>
    </div>

    <div class="header">
        <img src="assets/images/logo-detallia.svg" alt="Detallia">
        <div class="doc-id">Simulacro de kit N.° <?php echo str_pad((string) $sim["id"], 5, "0", STR_PAD_LEFT); ?></div>
    </div>

    <h1><?php echo htmlspecialchars($sim["name"]); ?></h1>

    <div class="meta" style="margin-top:16px;">
        <div>
            <div class="label">Fecha de creacion</div>
            <div class="val"><?php echo htmlspecialchars(date("d/m/Y", strtotime($sim["sim_date"]))); ?></div>
        </div>
        <div>
            <div class="label">Tipo de cliente</div>
            <div class="val"><?php echo htmlspecialchars($sim["classification_name"] ?? "Sin especificar"); ?></div>
        </div>
        <div>
            <div class="label">Elaborado por</div>
            <div class="val"><?php echo htmlspecialchars($sim["created_by_name"] ?? "—"); ?></div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Precio unit.</th>
                <th class="num">IVA %</th>
                <th class="num">Cant.</th>
                <th class="num">Subtotal</th>
                <th class="num">IVA</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;">Sin productos.</td></tr>
            <?php else: foreach ($items as $it): ?>
                <tr>
                    <td><?php echo htmlspecialchars($it["product_name"]); ?></td>
                    <td class="num">$<?php echo number_format($it["unit_price"], 2); ?></td>
                    <td class="num"><?php echo rtrim(rtrim(number_format($it["iva_rate"], 2), '0'), '.'); ?>%</td>
                    <td class="num"><?php echo rtrim(rtrim(number_format($it["quantity"], 2), '0'), '.'); ?></td>
                    <td class="num">$<?php echo number_format($it["_sub"], 2); ?></td>
                    <td class="num">$<?php echo number_format($it["_iva"], 2); ?></td>
                    <td class="num">$<?php echo number_format($it["_tot"], 2); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="totbox">
        <div class="line"><span>Subtotal</span><span>$<?php echo number_format($subtotalSum, 2); ?></span></div>
        <div class="line"><span>IVA</span><span>$<?php echo number_format($ivaSum, 2); ?></span></div>
        <div class="line grand"><span>Total</span><span>$<?php echo number_format($totalSum, 2); ?></span></div>
    </div>

    <?php if (!empty($sim["notes"])): ?>
        <div class="notes"><strong>Notas:</strong><br><?php echo nl2br(htmlspecialchars($sim["notes"])); ?></div>
    <?php endif; ?>

</body>
</html>
