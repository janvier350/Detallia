<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3]);

$delivery_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
if ($delivery_id <= 0) {
    header("location: admin-deliveries-list.php");
    exit;
}

$sql = "SELECT d.id, d.delivery_date, d.notes,
               k.name AS kit_name, k.image_path AS kit_image,
               c.name AS client_name, c.contact_name, c.address, c.ciudad, c.provincia,
               mt.name AS management_type_name,
               COALESCE(u.full_name, u.username) AS delivered_by_name
        FROM kit_deliveries d
        JOIN kits k ON k.id = d.kit_id
        JOIN clients c ON c.id = d.client_id
        LEFT JOIN management_types mt ON mt.id = d.management_type_id
        LEFT JOIN users u ON u.id = d.delivered_by
        WHERE d.id = ?";
$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, "i", $delivery_id);
mysqli_stmt_execute($stmt);
$res      = mysqli_stmt_get_result($stmt);
$delivery = mysqli_fetch_assoc($res);

if (!$delivery) {
    header("location: admin-deliveries-list.php");
    exit;
}

$stmt = mysqli_prepare($link, "SELECT a.name AS article_name, ki.quantity, a.unit
                                FROM kit_items ki
                                JOIN articles a ON a.id = ki.article_id
                                JOIN kit_deliveries d ON d.kit_id = ki.kit_id
                                WHERE d.id = ?
                                ORDER BY a.name");
mysqli_stmt_bind_param($stmt, "i", $delivery_id);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Documento de entrega #<?php echo (int) $delivery["id"]; ?> | Detallia</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #2d3748; margin: 40px; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #556ee6; padding-bottom: 16px; margin-bottom: 24px; }
        .header img { height: 34px; }
        h1 { font-size: 20px; margin: 0; }
        .doc-id { color: #74788d; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        .label { color: #74788d; font-size: 12px; text-transform: uppercase; }
        .section { margin-bottom: 20px; }
        .kit-image { max-width: 200px; max-height: 200px; border-radius: 6px; float: right; }
        .signatures { display: flex; justify-content: space-between; margin-top: 60px; }
        .signature-box { width: 45%; text-align: center; border-top: 1px solid #333; padding-top: 8px; font-size: 13px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>

<body>

    <div class="header">
        <img src="assets/images/logo-detallia.svg" alt="Detallia">
        <div class="doc-id">Documento de entrega N.° <?php echo str_pad((string) $delivery["id"], 5, "0", STR_PAD_LEFT); ?></div>
    </div>

    <?php if ($delivery["kit_image"]): ?>
        <img src="<?php echo htmlspecialchars($delivery["kit_image"]); ?>" class="kit-image" alt="Kit">
    <?php endif; ?>

    <div class="section">
        <div class="label">Cliente</div>
        <div><strong><?php echo htmlspecialchars($delivery["client_name"]); ?></strong></div>
        <?php if ($delivery["contact_name"]): ?><div>Contacto: <?php echo htmlspecialchars($delivery["contact_name"]); ?></div><?php endif; ?>
        <?php if ($delivery["address"]): ?><div><?php echo htmlspecialchars($delivery["address"]); ?></div><?php endif; ?>
        <div><?php echo htmlspecialchars(trim(($delivery["ciudad"] ?? "") . ", " . ($delivery["provincia"] ?? ""), ", ")); ?></div>
    </div>

    <div class="section">
        <div class="label">Kit entregado</div>
        <div><strong><?php echo htmlspecialchars($delivery["kit_name"]); ?></strong></div>
    </div>

    <div class="section">
        <div class="label">Fecha de entrega</div>
        <div><?php echo htmlspecialchars($delivery["delivery_date"]); ?></div>
    </div>

    <?php if ($delivery["management_type_name"]): ?>
        <div class="section">
            <div class="label">Tipo de gestion</div>
            <div><?php echo htmlspecialchars($delivery["management_type_name"]); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($delivery["notes"]): ?>
        <div class="section">
            <div class="label">Notas</div>
            <div><?php echo nl2br(htmlspecialchars($delivery["notes"])); ?></div>
        </div>
    <?php endif; ?>

    <div class="section">
        <div class="label">Contenido del kit</div>
        <table>
            <thead>
                <tr>
                    <th>Articulo</th>
                    <th>Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($it = mysqli_fetch_assoc($items)): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($it["article_name"]); ?></td>
                        <td><?php echo format_qty($it["quantity"]) . " " . htmlspecialchars($it["unit"]); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="label">Registrado por</div>
        <div><?php echo htmlspecialchars($delivery["delivered_by_name"] ?? "—"); ?></div>
    </div>

    <div class="signatures">
        <div class="signature-box">Entregado por</div>
        <div class="signature-box">Recibido por</div>
    </div>

    <div class="no-print" style="margin-top: 30px;">
        <button onclick="window.print()">Imprimir</button>
    </div>

</body>

</html>
