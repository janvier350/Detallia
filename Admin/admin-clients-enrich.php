<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_once 'layouts/xlsx-reader.php';
require_role([1, 2]);

$error_msg = "";
$step = "upload"; // upload | preview | done
$matched = [];     // [ ['id','persona','empresa','genero','cargo','actual_genero','actual_cargo'] ]
$unmatched = [];   // [ ['persona','empresa','genero','cargo'] ]
$ambiguous = [];
$applied = 0;

function enr_norm($s)
{
    $s = mb_strtoupper(trim((string) $s), 'UTF-8');
    $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','Ü'=>'U','À'=>'A','È'=>'E']);
    $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

// ---------------------------------------------------------------
// Construir indices de clientes existentes
// ---------------------------------------------------------------
function build_client_index($link)
{
    $byKey = [];   // norm(name)|norm(empresa) => id
    $byName = [];  // norm(name) => [ids]
    $info = [];    // id => ['name','empresa','genero','cargo']
    $res = mysqli_query($link, "SELECT id, name, contact_name, genero, cargo FROM clients");
    while ($r = mysqli_fetch_assoc($res)) {
        $id = (int) $r["id"];
        $nName = enr_norm($r["name"]);
        $nEmp  = enr_norm($r["contact_name"] ?? "");
        $byKey[$nName . '|' . $nEmp] = $id;
        $byName[$nName][] = $id;
        $info[$id] = ["name" => $r["name"], "empresa" => $r["contact_name"], "genero" => $r["genero"], "cargo" => $r["cargo"]];
    }
    return [$byKey, $byName, $info];
}

// ---------------------------------------------------------------
// PASO 1: subir y previsualizar
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "preview") {
    $sheet = trim($_POST["sheet"] ?? "LISTADO (2025)");
    if (empty($_FILES["excel_file"]["name"]) || $_FILES["excel_file"]["error"] !== UPLOAD_ERR_OK) {
        $error_msg = "Selecciona un archivo .xlsx valido.";
    } else {
        try {
            $rows = parse_xlsx_sheet($_FILES["excel_file"]["tmp_name"], $sheet);

            // Localizar fila de encabezado (la que contiene RAZON SOCIAL y REGALO)
            $headerRow = null; $cols = [];
            foreach ($rows as $rowNum => $cells) {
                $joined = enr_norm(implode(' ', $cells));
                if (strpos($joined, 'RAZON SOCIAL') !== false && strpos($joined, 'REGALO') !== false) {
                    $headerRow = $rowNum;
                    foreach ($cells as $ci => $val) {
                        $cols[enr_norm($val)] = $ci;
                    }
                    break;
                }
            }
            if ($headerRow === null) {
                throw new Exception("No se encontro la fila de encabezados (RAZON SOCIAL / REGALO) en la pestana \"$sheet\".");
            }

            $cRazon  = $cols["RAZON SOCIAL"] ?? null;
            $cRegalo = $cols["REGALO"] ?? null;
            $cCargo  = $cols["CARGO"] ?? null;
            $cGenero = $cols["GENERO"] ?? null;
            if ($cRegalo === null || ($cCargo === null && $cGenero === null)) {
                throw new Exception("La hoja no tiene las columnas esperadas (REGALO, CARGO, GENERO).");
            }

            list($byKey, $byName, $info) = build_client_index($link);

            foreach ($rows as $rowNum => $cells) {
                if ($rowNum <= $headerRow) continue;
                $persona = trim($cells[$cRegalo] ?? "");
                if ($persona === "") continue; // fila solo-empresa
                $empresa = $cRazon !== null ? trim($cells[$cRazon] ?? "") : "";
                $genero  = $cGenero !== null ? trim($cells[$cGenero] ?? "") : "";
                $cargo   = $cCargo !== null ? trim($cells[$cCargo] ?? "") : "";
                if ($genero === "" && $cargo === "") continue; // nada que completar

                $nPer = enr_norm($persona);
                $nEmp = enr_norm($empresa);
                $id = null;

                if (isset($byKey[$nPer . '|' . $nEmp])) {
                    $id = $byKey[$nPer . '|' . $nEmp];
                } elseif (isset($byName[$nPer])) {
                    if (count($byName[$nPer]) === 1) {
                        $id = $byName[$nPer][0];
                    } else {
                        $ambiguous[] = ["persona" => $persona, "empresa" => $empresa, "genero" => $genero, "cargo" => $cargo];
                        continue;
                    }
                }

                if ($id === null) {
                    $unmatched[] = ["persona" => $persona, "empresa" => $empresa, "genero" => $genero, "cargo" => $cargo];
                } else {
                    $matched[] = [
                        "id" => $id,
                        "persona" => $info[$id]["name"],
                        "empresa" => $info[$id]["empresa"],
                        "genero" => $genero,
                        "cargo" => $cargo,
                        "actual_genero" => $info[$id]["genero"],
                        "actual_cargo" => $info[$id]["cargo"],
                    ];
                }
            }
            $step = "preview";
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------
// PASO 2: aplicar actualizacion (solo campos genero/cargo)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "apply") {
    $payload = json_decode($_POST["updates"] ?? "[]", true);
    if (is_array($payload)) {
        $stmt = mysqli_prepare($link, "UPDATE clients SET genero = COALESCE(NULLIF(?,''), genero), cargo = COALESCE(NULLIF(?,''), cargo) WHERE id = ?");
        foreach ($payload as $u) {
            $id = (int) ($u["id"] ?? 0);
            if ($id <= 0) continue;
            $g = (string) ($u["genero"] ?? "");
            $c = (string) ($u["cargo"] ?? "");
            mysqli_stmt_bind_param($stmt, "ssi", $g, $c, $id);
            if (mysqli_stmt_execute($stmt)) $applied++;
        }
    }
    $step = "done";
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>
    <title>Completar genero y cargo | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Completar genero y cargo desde Excel</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-clients-list.php">Clientes</a></li>
                                    <li class="breadcrumb-item active">Completar genero/cargo</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <?php if ($step === "upload"): ?>
                <div class="row">
                    <div class="col-xl-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-2">Subir archivo</h5>
                                <p class="text-muted">Sube el Excel de regalos corporativos. El sistema <strong>solo completara genero y cargo</strong> de los contactos que <strong>ya existen</strong> en la aplicacion (empareja por nombre de la persona y empresa). <strong>No crea contactos nuevos.</strong></p>
                                <form method="post" enctype="multipart/form-data" class="row g-3">
                                    <input type="hidden" name="action" value="preview">
                                    <div class="col-md-7">
                                        <label class="form-label">Archivo .xlsx</label>
                                        <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Pestana (hoja)</label>
                                        <input type="text" name="sheet" class="form-control" value="LISTADO (2025)">
                                        <div class="form-text">Nombre exacto de la pestana con los datos.</div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary"><i class="mdi mdi-magnify me-1"></i> Analizar y previsualizar</button>
                                        <a href="admin-clients-list.php" class="btn btn-light">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($step === "preview"): ?>
                <div class="row">
                    <div class="col-md-4"><div class="card"><div class="card-body text-center">
                        <h3 class="text-success mb-0"><?php echo count($matched); ?></h3><p class="text-muted mb-0">Se actualizaran (emparejados)</p>
                    </div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-body text-center">
                        <h3 class="text-warning mb-0"><?php echo count($unmatched); ?></h3><p class="text-muted mb-0">No estan en la app (se ignoran)</p>
                    </div></div></div>
                    <div class="col-md-4"><div class="card"><div class="card-body text-center">
                        <h3 class="text-danger mb-0"><?php echo count($ambiguous); ?></h3><p class="text-muted mb-0">Nombre duplicado (se omiten)</p>
                    </div></div></div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="card-title mb-0">Contactos a actualizar (<?php echo count($matched); ?>)</h5>
                            <?php if (!empty($matched)): ?>
                            <form method="post" onsubmit="return confirm('¿Aplicar genero y cargo a <?php echo count($matched); ?> contactos existentes?');">
                                <input type="hidden" name="action" value="apply">
                                <input type="hidden" name="updates" value='<?php echo htmlspecialchars(json_encode(array_map(function ($m) {
                                    return ["id" => $m["id"], "genero" => $m["genero"], "cargo" => $m["cargo"]];
                                }, $matched)), ENT_QUOTES); ?>'>
                                <button type="submit" class="btn btn-success"><i class="mdi mdi-check-bold me-1"></i> Aplicar actualizacion</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($matched)): ?>
                            <div class="alert alert-warning mb-0">Ningun contacto del Excel coincidio con los existentes.</div>
                        <?php else: ?>
                        <div class="table-responsive" style="max-height:420px;overflow:auto;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light" style="position:sticky;top:0;">
                                    <tr><th>Contacto</th><th>Empresa</th><th>Genero (nuevo)</th><th>Cargo (nuevo)</th><th>Valor actual</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matched as $m): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($m["persona"]); ?></td>
                                            <td><?php echo htmlspecialchars($m["empresa"] ?? ""); ?></td>
                                            <td><?php echo htmlspecialchars($m["genero"] ?: "—"); ?></td>
                                            <td><?php echo htmlspecialchars($m["cargo"] ?: "—"); ?></td>
                                            <td class="text-muted small"><?php echo htmlspecialchars(trim(($m["actual_genero"] ?? "") . " / " . ($m["actual_cargo"] ?? ""), " /")) ?: "(vacio)"; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($unmatched) || !empty($ambiguous)): ?>
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">No actualizados (referencia)</h6>
                        <div class="table-responsive" style="max-height:300px;overflow:auto;">
                            <table class="table table-sm mb-0">
                                <thead class="table-light" style="position:sticky;top:0;"><tr><th>Contacto</th><th>Empresa</th><th>Motivo</th></tr></thead>
                                <tbody>
                                    <?php foreach ($ambiguous as $u): ?>
                                        <tr><td><?php echo htmlspecialchars($u["persona"]); ?></td><td><?php echo htmlspecialchars($u["empresa"]); ?></td><td><span class="badge bg-danger">Nombre duplicado</span></td></tr>
                                    <?php endforeach; ?>
                                    <?php foreach ($unmatched as $u): ?>
                                        <tr><td><?php echo htmlspecialchars($u["persona"]); ?></td><td><?php echo htmlspecialchars($u["empresa"]); ?></td><td><span class="badge bg-warning">No esta en la app</span></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <a href="admin-clients-enrich.php" class="btn btn-light mb-4">Volver a subir otro archivo</a>
                <?php endif; ?>

                <?php if ($step === "done"): ?>
                <div class="alert alert-success"><strong><?php echo (int) $applied; ?></strong> contactos fueron actualizados con su genero y cargo.</div>
                <a href="admin-clients-list.php" class="btn btn-primary">Ir a Clientes</a>
                <a href="admin-clients-enrich.php" class="btn btn-light">Subir otro archivo</a>
                <?php endif; ?>

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
