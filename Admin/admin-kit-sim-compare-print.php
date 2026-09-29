<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 5]);

$idsRaw = trim($_GET["ids"] ?? "");
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $idsRaw)), function ($v) { return $v > 0; })));
$ids = array_slice($ids, 0, 6);
if (count($ids) < 2) { header("location: admin-kit-sim-list.php"); exit; }
$idList = implode(",", $ids);

$sims = [];
$res = mysqli_query($link, "SELECT s.id, s.name, s.sim_date, cl.name AS classification_name,
                                   COUNT(i.id) AS num_items,
                                   COALESCE(SUM(i.unit_price * i.quantity), 0) AS subtotal,
                                   COALESCE(SUM(i.unit_price * i.quantity * (i.iva_rate/100)), 0) AS iva,
                                   COALESCE(SUM(i.unit_price * i.quantity * (1 + i.iva_rate/100)), 0) AS total
                            FROM kit_simulations s
                            LEFT JOIN client_classifications cl ON cl.id = s.classification_id
                            LEFT JOIN kit_simulation_items i ON i.simulation_id = s.id
                            WHERE s.id IN ($idList) GROUP BY s.id");
while ($r = mysqli_fetch_assoc($res)) { $sims[(int) $r["id"]] = $r; }

$ordered = [];
foreach ($ids as $id) { if (isset($sims[$id])) $ordered[] = $sims[$id]; }

$itemsBySim = [];
$ri = mysqli_query($link, "SELECT * FROM kit_simulation_items WHERE simulation_id IN ($idList) ORDER BY sort_order, id");
while ($it = mysqli_fetch_assoc($ri)) { $itemsBySim[(int) $it["simulation_id"]][] = $it; }

$totalsOnly = array_map(function ($s) { return (float) $s["total"]; }, $ordered);
$minTotal = !empty($totalsOnly) ? min($totalsOnly) : 0;
$maxTotal = !empty($totalsOnly) ? max($totalsOnly) : 0;

function fmt_num($n) { return rtrim(rtrim(number_format($n, 2), '0'), '.'); }
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Comparativa de simulacros | Detallia</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #2d3748; margin: 34px; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #556ee6; padding-bottom: 14px; margin-bottom: 22px; }
        .header img { height: 32px; }
        .doc-id { color: #74788d; font-size: 13px; }
        h1 { font-size: 19px; margin: 0 0 18px; }
        table { width: 100%; border-collapse: collapse; }
        .summary th, .summary td { padding: 8px 10px; border: 1px solid #e2e5ec; font-size: 13px; text-align: right; }
        .summary th:first-child, .summary td:first-child { text-align: left; color: #74788d; }
        .summary thead th { background: #556ee6; color: #fff; text-align: center; }
        .summary tr.total td { font-weight: bold; font-size: 15px; }
        .tag { display: inline-block; font-size: 10px; padding: 1px 6px; border-radius: 10px; color: #fff; margin-left: 4px; }
        .tag.min { background: #34c38f; } .tag.max { background: #f46a6a; }
        .cols { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 28px; }
        .col { flex: 1 1 260px; border: 1px solid #e2e5ec; border-radius: 6px; padding: 12px 14px; page-break-inside: avoid; }
        .col h3 { font-size: 14px; margin: 0 0 2px; }
        .col .sub { color: #74788d; font-size: 11px; margin-bottom: 8px; }
        .col table td { padding: 4px 0; font-size: 12px; border-bottom: 1px solid #f0f1f5; }
        .col table td.n { text-align: right; white-space: nowrap; }
        .col .tot { font-weight: bold; border-top: 2px solid #e2e5ec; }
        .print-actions { margin-bottom: 18px; }
        .print-actions button { padding: 8px 16px; font-size: 14px; border-radius: 6px; border: none; background: #556ee6; color: #fff; cursor: pointer; }
        .print-actions a { padding: 8px 16px; font-size: 14px; border-radius: 6px; border: 1px solid #556ee6; background: #fff; color: #556ee6; text-decoration: none; margin-left: 6px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>

<body>

    <div class="print-actions no-print">
        <button onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
        <a href="admin-kit-sim-compare.php?ids=<?php echo htmlspecialchars(implode(',', $ids)); ?>">Volver</a>
    </div>

    <div class="header">
        <img src="assets/images/logo-detallia.svg" alt="Detallia">
        <div class="doc-id">Comparativa de simulacros · <?php echo date("d/m/Y"); ?></div>
    </div>

    <h1>Comparativa de costos de kit (<?php echo count($ordered); ?> opciones)</h1>

    <table class="summary">
        <thead>
            <tr>
                <th>Concepto</th>
                <?php foreach ($ordered as $s): ?><th><?php echo htmlspecialchars($s["name"]); ?></th><?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Fecha</td>
                <?php foreach ($ordered as $s): ?><td><?php echo htmlspecialchars(date("d/m/Y", strtotime($s["sim_date"]))); ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td>Tipo de cliente</td>
                <?php foreach ($ordered as $s): ?><td><?php echo htmlspecialchars($s["classification_name"] ?? "—"); ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td>Productos</td>
                <?php foreach ($ordered as $s): ?><td><?php echo (int) $s["num_items"]; ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td>Subtotal</td>
                <?php foreach ($ordered as $s): ?><td>$<?php echo number_format($s["subtotal"], 2); ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td>IVA</td>
                <?php foreach ($ordered as $s): ?><td>$<?php echo number_format($s["iva"], 2); ?></td><?php endforeach; ?>
            </tr>
            <tr class="total">
                <td>Total</td>
                <?php foreach ($ordered as $s): ?>
                    <td>$<?php echo number_format($s["total"], 2); ?><?php
                        if ((float) $s["total"] == $minTotal && $minTotal != $maxTotal) echo '<span class="tag min">Menor</span>';
                        elseif ((float) $s["total"] == $maxTotal && $minTotal != $maxTotal) echo '<span class="tag max">Mayor</span>';
                    ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Diferencia vs. menor</td>
                <?php foreach ($ordered as $s): $diff = (float) $s["total"] - $minTotal; ?>
                    <td><?php echo $diff > 0 ? '+$' . number_format($diff, 2) : '—'; ?></td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>

    <div class="cols">
        <?php foreach ($ordered as $s): $its = $itemsBySim[(int) $s["id"]] ?? []; ?>
            <div class="col">
                <h3><?php echo htmlspecialchars($s["name"]); ?></h3>
                <div class="sub"><?php echo htmlspecialchars(date("d/m/Y", strtotime($s["sim_date"]))); ?><?php echo $s["classification_name"] ? " · " . htmlspecialchars($s["classification_name"]) : ""; ?></div>
                <table>
                    <?php if (empty($its)): ?>
                        <tr><td style="color:#999;">Sin productos</td></tr>
                    <?php else: foreach ($its as $it): $tot = $it["unit_price"] * $it["quantity"] * (1 + $it["iva_rate"] / 100); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it["product_name"]); ?> <span style="color:#999;">×<?php echo fmt_num($it["quantity"]); ?></span></td>
                            <td class="n">$<?php echo number_format($tot, 2); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    <tr class="tot"><td>Total</td><td class="n">$<?php echo number_format($s["total"], 2); ?></td></tr>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
