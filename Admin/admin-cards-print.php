<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2, 3, 6]);

$CARD_FONTS = [
    'Arial'           => 'Arial, Helvetica, sans-serif',
    'Georgia'         => 'Georgia, serif',
    'Times New Roman' => "'Times New Roman', Times, serif",
    'Verdana'         => 'Verdana, sans-serif',
    'Courier New'     => "'Courier New', monospace",
    'Alex Brush'      => "'Alex Brush', cursive",
    'Great Vibes'     => "'Great Vibes', cursive",
    'Dancing Script'  => "'Dancing Script', cursive",
    'Pacifico'        => "'Pacifico', cursive",
    'Satisfy'         => "'Satisfy', cursive",
    'Sacramento'      => "'Sacramento', cursive",
    'Parisienne'      => "'Parisienne', cursive",
];
$GFONTS_LINK = 'https://fonts.googleapis.com/css2?family=Alex+Brush&family=Great+Vibes&family=Dancing+Script:wght@400;700&family=Pacifico&family=Satisfy&family=Sacramento&family=Parisienne&display=swap';

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

// Geometria A4 en mm (segun orientacion)
$orient = ($tpl["orientation"] ?? "portrait") === "landscape" ? "landscape" : "portrait";
$margin = 8;
$pageW = $orient === "landscape" ? 297 : 210;
$content = $pageW - 2 * $margin;

// "Tarjetas por fila" siempre define las columnas.
$cols = max(1, min(6, (int) $tpl["grid_cols"]));
if ((float) $tpl["card_w_mm"] > 0 && (float) $tpl["card_h_mm"] > 0) {
    // Tamaño fisico exacto (cm).
    $cardW = (float) $tpl["card_w_mm"];
    $cardH = (float) $tpl["card_h_mm"];
} else {
    // Automatico: ancho = contenido / columnas, alto por la proporcion de la imagen.
    $cardW = $content / $cols;
    $cardH = $cardW * $aspect;
}
$fontPt = (float) ($tpl["font_pt"] ?? 24);
$fontStack = $CARD_FONTS[$tpl["font_family"] ?? 'Arial'] ?? $CARD_FONTS['Arial'];

$imgUrl = htmlspecialchars($tpl["image_path"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tarjetas — <?php echo htmlspecialchars($tpl["name"]); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="<?php echo $GFONTS_LINK; ?>" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e9ecef;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { padding: 10px; text-align: center; background: #fff; border-bottom: 1px solid #ddd; }
        .sheet {
            width: <?php echo $pageW; ?>mm; margin: 10px auto; background: #fff; padding: <?php echo $margin; ?>mm;
            display: grid; grid-template-columns: repeat(<?php echo $cols; ?>, <?php echo $cardW; ?>mm);
            gap: 0; justify-content: center;
        }
        /* Tarjetas unidas con una fina linea de corte entre ellas */
        .card {
            width: <?php echo $cardW; ?>mm; height: <?php echo $cardH; ?>mm; position: relative;
            page-break-inside: avoid; break-inside: avoid; overflow: hidden;
            outline: 0.2mm dashed #9aa0a6; outline-offset: -0.1mm;
        }
        /* Imagen real (se imprime siempre, no depende de "graficos de fondo") */
        .card .bg {
            position: absolute; inset: 0; width: 100%; height: 100%;
            object-fit: cover; z-index: 1;
        }
        .card .nm {
            position: absolute; left: <?php echo (float) $tpl["pos_x"]; ?>%; top: <?php echo (float) $tpl["pos_y"]; ?>%;
            transform: translate(-50%, -50%); white-space: nowrap; font-weight: 700; z-index: 2;
            font-size: <?php echo $fontPt; ?>pt; color: <?php echo htmlspecialchars($tpl["font_color"]); ?>;
            text-align: <?php echo htmlspecialchars($tpl["text_align"]); ?>;
            font-family: <?php echo $fontStack; ?>;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; }
            @page { size: A4 <?php echo $orient; ?>; margin: <?php echo $margin; ?>mm; }
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
            <?php foreach ($names as $nm):
                // Pasar de MAYUSCULAS a tipo titulo (solo iniciales en mayuscula)
                $display = function_exists('mb_convert_case')
                    ? mb_convert_case(mb_strtolower($nm, 'UTF-8'), MB_CASE_TITLE, 'UTF-8')
                    : ucwords(strtolower($nm)); ?>
                <div class="card">
                    <img class="bg" src="<?php echo $imgUrl; ?>" alt="" decoding="async">
                    <div class="nm"><?php echo htmlspecialchars($display); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
