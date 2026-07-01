<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 4]);

$is_solicitante = (int) $_SESSION["role_id"] === 4;

$request_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
if ($request_id <= 0) {
    header("location: admin-requests-list.php");
    exit;
}

$stmt = mysqli_prepare($link, "SELECT r.id, r.request_date, r.notes, r.requested_by,
                                       COALESCE(u.full_name, u.username) AS requested_by_name
                                FROM requests r
                                LEFT JOIN users u ON u.id = r.requested_by
                                WHERE r.id = ?");
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$request) {
    header("location: admin-requests-list.php");
    exit;
}

// Un solicitante solo puede ver/imprimir sus propias solicitudes
if ($is_solicitante && (int) $request["requested_by"] !== (int) $_SESSION["id"]) {
    header("location: pages-403.php");
    exit;
}

$stmt = mysqli_prepare($link, "SELECT ri.item_type, ri.quantity,
                                       a.name AS article_name, a.unit AS article_unit,
                                       k.name AS kit_name
                                FROM request_items ri
                                LEFT JOIN articles a ON a.id = ri.article_id
                                LEFT JOIN kits k ON k.id = ri.kit_id
                                WHERE ri.request_id = ?");
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Solicitud #<?php echo (int) $request["id"]; ?> | Detallia</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #2d3748; margin: 40px; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #556ee6; padding-bottom: 16px; margin-bottom: 24px; }
        .header img { height: 34px; }
        .doc-id { color: #74788d; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        .label { color: #74788d; font-size: 12px; text-transform: uppercase; }
        .section { margin-bottom: 20px; }
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
        <div class="doc-id">Solicitud N.° <?php echo str_pad((string) $request["id"], 5, "0", STR_PAD_LEFT); ?></div>
    </div>

    <div class="section">
        <div class="label">Fecha de solicitud</div>
        <div><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($request["request_date"]))); ?></div>
    </div>

    <div class="section">
        <div class="label">Solicitado por</div>
        <div><?php echo htmlspecialchars($request["requested_by_name"] ?? "—"); ?></div>
    </div>

    <div class="section">
        <div class="label">Articulos / Kits solicitados</div>
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descripcion</th>
                    <th>Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($it = mysqli_fetch_assoc($items)): ?>
                    <tr>
                        <td><?php echo $it["item_type"] === "kit" ? "Kit" : "Articulo"; ?></td>
                        <td><?php echo htmlspecialchars($it["item_type"] === "kit" ? $it["kit_name"] : $it["article_name"]); ?></td>
                        <td><?php echo format_qty($it["quantity"]) . ($it["item_type"] === "articulo" ? " " . htmlspecialchars($it["article_unit"]) : ""); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($request["notes"]): ?>
        <div class="section">
            <div class="label">Notas</div>
            <div><?php echo nl2br(htmlspecialchars($request["notes"])); ?></div>
        </div>
    <?php endif; ?>

    <div class="signatures">
        <div class="signature-box">Solicitado por</div>
        <div class="signature-box">Autorizado por</div>
    </div>

    <div class="no-print" style="margin-top: 30px;">
        <button onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="admin-requests-list.php" style="margin-left: 10px; padding: 6px 12px; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #2d3748; display: inline-block;">Volver a <?php echo $is_solicitante ? "mis solicitudes" : "solicitudes"; ?></a>
    </div>

</body>

</html>
