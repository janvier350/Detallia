<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 6]);

$template_id = (int) ($_GET["template_id"] ?? 0);
$s = mysqli_prepare($link, "SELECT * FROM card_templates WHERE id=?");
mysqli_stmt_bind_param($s, "i", $template_id);
mysqli_stmt_execute($s);
$tpl = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
if (!$tpl) { header("location: admin-cards.php"); exit; }

// Clientes segun filtros
$f_class  = (int) ($_GET["classification_id"] ?? 0);
$f_zona   = trim($_GET["zona"] ?? "");
$f_ciudad = trim($_GET["ciudad"] ?? "");
$f_brand  = (int) ($_GET["brand_id"] ?? 0);

$sql = "SELECT c.name FROM clients c WHERE 1=1";
$p = []; $t = "";
if ($f_class > 0)   { $sql .= " AND c.classification_id=?"; $p[] = $f_class;  $t .= "i"; }
if ($f_zona !== "") { $sql .= " AND c.zona=?";              $p[] = $f_zona;   $t .= "s"; }
if ($f_ciudad !== ""){ $sql .= " AND c.ciudad=?";           $p[] = $f_ciudad; $t .= "s"; }
if ($f_brand > 0)   { $sql .= " AND c.brand_id=?";          $p[] = $f_brand;  $t .= "i"; }
$sql .= " ORDER BY c.name ASC";
$st = mysqli_prepare($link, $sql);
if ($p) { mysqli_stmt_bind_param($st, $t, ...$p); }
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$names = [];
while ($row = mysqli_fetch_assoc($res)) { $names[] = $row["name"]; }

// Dimensiones de la imagen para conservar su proporcion
$imgW = 1754; $imgH = 1240;
$imgFile = __DIR__ . '/' . $tpl["image_path"];
if (is_file($imgFile)) {
    $sz = @getimagesize($imgFile);
    if ($sz && $sz[0] > 0 && $sz[1] > 0) { $imgW = $sz[0]; $imgH = $sz[1]; }
}
$aspect = $imgH / $imgW; // alto/ancho

// Geometria A4 (vertical) en mm
$cols = max(1, min(6, (int) $tpl["grid_cols"]));
$pageW = 210; $margin = 8; $gap = 4;
$content = $pageW - 2 * $margin;
$cardW = ($content - ($cols - 1) * $gap) / $cols;
$cardH = $cardW * $aspect;
$fontMm = (float) $tpl["font_pct"] / 100 * $cardW;

$imgUrl = htmlspecialchars($tpl["image_path"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tarjetas — <?php echo htmlspecialchars($tpl["name"]); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e9ecef; }
        .toolbar { padding: 10px; text-align: center; background: #fff; border-bottom: 1px solid #ddd; }
        .sheet {
            width: 210mm; margin: 10px auto; background: #fff; padding: <?php echo $margin; ?>mm;
            display: grid; grid-template-columns: repeat(<?php echo $cols; ?>, <?php echo $cardW; ?>mm);
            gap: <?php echo $gap; ?>mm; justify-content: center;
        }
        .card {
            width: <?php echo $cardW; ?>mm; height: <?php echo $cardH; ?>mm; position: relative;
            background-image: url('<?php echo $imgUrl; ?>'); background-size: cover; background-position: center;
            page-break-inside: avoid; break-inside: avoid; overflow: hidden;
        }
        .card .nm {
            position: absolute; left: <?php echo (float) $tpl["pos_x"]; ?>%; top: <?php echo (float) $tpl["pos_y"]; ?>%;
            transform: translate(-50%, -50%); white-space: nowrap; font-weight: 700;
            font-size: <?php echo $fontMm; ?>mm; color: <?php echo htmlspecialchars($tpl["font_color"]); ?>;
            text-align: <?php echo htmlspecialchars($tpl["text_align"]); ?>;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; }
            @page { size: A4 portrait; margin: <?php echo $margin; ?>mm; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong><?php echo count($names); ?></strong> tarjeta(s) &nbsp;
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
        <a href="admin-cards.php?id=<?php echo (int) $tpl["id"]; ?>" style="margin-left:10px;">Volver</a>
    </div>

    <div class="sheet">
        <?php if (empty($names)): ?>
            <p style="padding:20px;">No hay clientes para los filtros seleccionados.</p>
        <?php else: ?>
            <?php foreach ($names as $nm): ?>
                <div class="card">
                    <div class="nm"><?php echo htmlspecialchars($nm); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
