<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';
require_role([1, 2]);

$error_msg   = "";
$success_msg = "";
$step        = "upload"; // upload | review | done
$parsedRows  = [];
$generatedLink = null;

// ---------------------------------------------------------------
// Lector nativo de XLSX (sin dependencias externas)
// ---------------------------------------------------------------
function xlsx_col_to_index($col)
{
    $col = strtoupper($col);
    $result = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $result = $result * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $result - 1;
}

function parse_xlsx_sheet($filepath, $sheetName)
{
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new Exception("No se pudo abrir el archivo. Asegurate de que sea un .xlsx valido.");
    }

    // Cadenas compartidas
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = simplexml_load_string($ssXml);
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string) $si->t;
            } else {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // Ubicar la hoja por nombre
    $wbXml = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $wbXml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $sheetRid = null;
    foreach ($wbXml->sheets->sheet as $sheet) {
        if (trim((string) $sheet['name']) === $sheetName) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $sheetRid = (string) $attrs['id'];
            break;
        }
    }
    if (!$sheetRid) {
        $zip->close();
        throw new Exception("No se encontro la pestana \"$sheetName\" en el archivo.");
    }

    $relsXml = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $target = null;
    foreach ($relsXml->Relationship as $rel) {
        if ((string) $rel['Id'] === $sheetRid) {
            $target = (string) $rel['Target'];
            break;
        }
    }
    if (!$target) {
        $zip->close();
        throw new Exception("No se pudo ubicar el archivo interno de la hoja.");
    }
    $sheetPath = 'xl/' . ltrim($target, '/');

    $sheetXmlRaw = $zip->getFromName($sheetPath);
    $zip->close();

    if ($sheetXmlRaw === false) {
        throw new Exception("No se pudo leer el contenido de la hoja.");
    }

    $sheetXml = simplexml_load_string($sheetXmlRaw);
    $rows = [];
    foreach ($sheetXml->sheetData->row as $row) {
        $rowIndex = (int) $row['r'];
        $rowData = [];
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $colIndex = xlsx_col_to_index($m[1]);

            $type = (string) $c['t'];
            $value = '';
            if ($type === 's') {
                $idx = (int) $c->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) $c->is->t;
            } else {
                $value = (string) $c->v;
            }
            $rowData[$colIndex] = trim($value);
        }
        $rows[$rowIndex] = $rowData;
    }
    return $rows;
}

// Similitud simple para sugerir marca (0-100)
function best_match($value, $candidates)
{
    $value = mb_strtoupper(trim($value));
    if ($value === '') {
        return null;
    }
    $best = null;
    $bestScore = 0;
    foreach ($candidates as $id => $name) {
        $nameUpper = mb_strtoupper($name);
        similar_text($value, $nameUpper, $percent);
        if (strpos($nameUpper, $value) !== false || strpos($value, $nameUpper) !== false) {
            $percent = max($percent, 80);
        }
        if ($percent > $bestScore) {
            $bestScore = $percent;
            $best = $id;
        }
    }
    return $bestScore >= 45 ? $best : null;
}

$brandsRes = mysqli_query($link, "SELECT id, name FROM brands ORDER BY name");
$brandsMap = [];
while ($b = mysqli_fetch_assoc($brandsRes)) {
    $brandsMap[(int) $b["id"]] = $b["name"];
}

$classRes = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY name");
$classMap = [];
while ($cl = mysqli_fetch_assoc($classRes)) {
    $classMap[(int) $cl["id"]] = $cl["name"];
}

$existingRes = mysqli_query($link, "SELECT LOWER(name) AS name FROM clients");
$existingNames = [];
while ($e = mysqli_fetch_assoc($existingRes)) {
    $existingNames[$e["name"]] = true;
}

// ---------------------------------------------------------------
// PASO 1: subir y parsear archivo
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "parse") {
    if (empty($_FILES["excel_file"]["name"]) || $_FILES["excel_file"]["error"] !== UPLOAD_ERR_OK) {
        $error_msg = "Selecciona un archivo .xlsx valido.";
    } else {
        try {
            $rawRows = parse_xlsx_sheet($_FILES["excel_file"]["tmp_name"], "BASE COMBINADA");

            // Localizar fila de encabezados buscando "RAZÓN SOCIAL"
            $headerRowNum = null;
            $colMap = [];
            foreach ($rawRows as $rowNum => $row) {
                foreach ($row as $colIdx => $val) {
                    if (mb_strtoupper(trim($val)) === "RAZÓN SOCIAL" || mb_strtoupper(trim($val)) === "RAZON SOCIAL") {
                        $headerRowNum = $rowNum;
                        break 2;
                    }
                }
            }
            if ($headerRowNum === null) {
                throw new Exception("No se encontro la fila de encabezados (RAZON SOCIAL) en la pestana.");
            }
            foreach ($rawRows[$headerRowNum] as $colIdx => $val) {
                $colMap[mb_strtoupper(trim($val))] = $colIdx;
            }

            $need = ["RAZÓN SOCIAL", "PERSONA / DESTINATARIO", "OFICINA", "OBSEQUIO DE", "CATEGORÍA", "CONTACTO", "CIUDAD", "ZONA", "DIRECCIÓN", "ESTATUS", "RUC/CI"];
            foreach ($need as $col) {
                if (!isset($colMap[$col])) {
                    throw new Exception("Falta la columna \"$col\" en la pestana BASE COMBINADA.");
                }
            }

            $seenInBatch = [];
            foreach ($rawRows as $rowNum => $row) {
                if ($rowNum <= $headerRowNum) {
                    continue;
                }
                $get = function ($col) use ($row, $colMap) {
                    $idx = $colMap[$col] ?? null;
                    return $idx !== null ? ($row[$idx] ?? "") : "";
                };

                $estatus = trim($get("ESTATUS"));
                if (!in_array($estatus, ["EN AMBOS", "SOLO REGALOS"], true)) {
                    continue;
                }

                $razonSocial = trim($get("RAZÓN SOCIAL"));
                $persona     = trim($get("PERSONA / DESTINATARIO"));
                if ($razonSocial === "" && $persona === "") {
                    continue;
                }

                $name = $persona !== "" ? $persona : $razonSocial;

                $oficina         = trim($get("OFICINA"));
                $zona            = trim($get("ZONA"));
                $contactoInterno = trim($get("CONTACTO"));
                $ruc             = trim($get("RUC/CI"));

                $categoria = mb_strtoupper(trim($get("CATEGORÍA")));
                $classification_guess = null;
                foreach ($classMap as $id => $cname) {
                    if (mb_strtoupper($cname) === $categoria) {
                        $classification_guess = $id;
                        break;
                    }
                }

                $obsequioDe = trim($get("OBSEQUIO DE"));
                $brand_guess = best_match($obsequioDe, $brandsMap);

                $mesesFact    = trim($get("MESES FACT."));
                $detalleMeses = trim($get("DETALLE MESES"));
                $alerta       = trim($get("ALERTA"));

                $dupKey = mb_strtolower($name);
                $duplicate_in_batch = isset($seenInBatch[$dupKey]);
                $seenInBatch[$dupKey] = true;
                $duplicate_in_db = isset($existingNames[$dupKey]);

                $parsedRows[] = [
                    "name" => $name,
                    "contact_name" => $razonSocial,
                    "oficina" => $oficina,
                    "zona" => $zona,
                    "contacto_interno" => $contactoInterno,
                    "ruc_ci" => $ruc,
                    "meses_fact" => $mesesFact,
                    "detalle_meses" => $detalleMeses,
                    "estatus_excel" => $estatus,
                    "alerta" => $alerta,
                    "ciudad" => trim($get("CIUDAD")),
                    "address" => trim($get("DIRECCIÓN")),
                    "notes" => "",
                    "classification_id" => $classification_guess,
                    "brand_id" => $brand_guess,
                    "include" => !$duplicate_in_batch && !$duplicate_in_db,
                    "duplicate_in_batch" => $duplicate_in_batch,
                    "duplicate_in_db" => $duplicate_in_db,
                ];
            }

            if (empty($parsedRows)) {
                $error_msg = "No se encontraron filas validas (EN AMBOS / SOLO REGALOS) para importar.";
            } else {
                $step = "review";
            }
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------
// PASO 2: enviar las filas seleccionadas a validacion (pendientes)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "stage") {
    $names               = $_POST["name"] ?? [];
    $contactNames        = $_POST["contact_name"] ?? [];
    $oficinas            = $_POST["oficina"] ?? [];
    $zonas               = $_POST["zona"] ?? [];
    $contactosInternos   = $_POST["contacto_interno"] ?? [];
    $rucs                = $_POST["ruc_ci"] ?? [];
    $ciudades            = $_POST["ciudad"] ?? [];
    $addresses           = $_POST["address"] ?? [];
    $notes               = $_POST["notes"] ?? [];
    $classificationIds   = $_POST["classification_id"] ?? [];
    $brandIds            = $_POST["brand_id"] ?? [];
    $includes            = $_POST["include"] ?? [];
    $mesesFacts          = $_POST["meses_fact"] ?? [];
    $detalleMesesArr     = $_POST["detalle_meses"] ?? [];
    $estatusExcelArr     = $_POST["estatus_excel"] ?? [];
    $alertas             = $_POST["alerta"] ?? [];
    $linkLabel           = trim($_POST["link_label"] ?? "") ?: ("Importacion " . date("d/m/Y H:i"));

    $staged = 0;
    $skippedEmpty = 0;

    $token = bin2hex(random_bytes(24));
    $createdBy = (int) $_SESSION["id"];
    $linkStmt = mysqli_prepare($link, "INSERT INTO validation_links (token, label, created_by) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($linkStmt, "ssi", $token, $linkLabel, $createdBy);
    mysqli_stmt_execute($linkStmt);
    $linkId = mysqli_insert_id($link);

    $stmt = mysqli_prepare($link, "INSERT INTO pending_clients (link_id, name, contact_name, oficina, zona, contacto_interno, ruc_ci, meses_fact, detalle_meses, estatus_excel, alerta, ciudad, address, notes, brand_id, classification_id)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($names as $idx => $name) {
        if (empty($includes[$idx])) {
            continue;
        }
        $name = trim($name);
        if ($name === "") {
            $skippedEmpty++;
            continue;
        }
        $contact       = trim($contactNames[$idx] ?? "");
        $oficina       = trim($oficinas[$idx] ?? "");
        $zona          = trim($zonas[$idx] ?? "");
        $contacto      = trim($contactosInternos[$idx] ?? "");
        $ruc           = trim($rucs[$idx] ?? "");
        $mesesFact     = trim($mesesFacts[$idx] ?? "");
        $detalleMeses  = trim($detalleMesesArr[$idx] ?? "");
        $estatusExcel  = trim($estatusExcelArr[$idx] ?? "");
        $alerta        = trim($alertas[$idx] ?? "");
        $ciudad        = trim($ciudades[$idx] ?? "");
        $address       = trim($addresses[$idx] ?? "");
        $note          = trim($notes[$idx] ?? "");
        $classId = (int) ($classificationIds[$idx] ?? 0);
        $classId = $classId > 0 ? $classId : null;
        $brandId = (int) ($brandIds[$idx] ?? 0);
        $brandId = $brandId > 0 ? $brandId : null;

        mysqli_stmt_bind_param($stmt, "isssssssssssssii", $linkId, $name, $contact, $oficina, $zona, $contacto, $ruc, $mesesFact, $detalleMeses, $estatusExcel, $alerta, $ciudad, $address, $note, $brandId, $classId);
        if (mysqli_stmt_execute($stmt)) {
            $staged++;
        }
    }

    // Post-Redirect-Get: guardamos el resultado en sesion y redirigimos, para que
    // refrescar la pagina (o volver atras) no vuelva a crear otro lote duplicado.
    $_SESSION["import_generated_link"] = [
        "token" => $token,
        "label" => $linkLabel,
        "staged" => $staged,
        "skipped_empty" => $skippedEmpty,
    ];
    header("location: admin-clients-import.php?done=1");
    exit;
}

// Mostrar el paso final tras el redirect (PRG)
if (($_GET["done"] ?? "") === "1" && isset($_SESSION["import_generated_link"])) {
    $generatedLink = $_SESSION["import_generated_link"];
    unset($_SESSION["import_generated_link"]);
    $step = "done";
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Importar clientes | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Importar clientes desde Excel</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="admin-clients-list.php">Clientes</a></li>
                                    <li class="breadcrumb-item active">Importar</li>
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
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Paso 1: sube el archivo</h5>
                                    <p class="text-muted">
                                        Se leera la pestana <strong>"BASE COMBINADA"</strong>. Solo se importaran las filas con estatus
                                        <strong>EN AMBOS</strong> o <strong>SOLO REGALOS</strong>. El nombre del cliente sera la persona
                                        destinataria (o la razon social/grupo si no hay persona).
                                    </p>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="parse">
                                        <div class="mb-3">
                                            <input type="file" name="excel_file" class="form-control" accept=".xlsx" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Leer archivo</button>
                                        <a href="admin-clients-list.php" class="btn btn-light">Cancelar</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($step === "review"): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                        <h5 class="card-title mb-0">Paso 2: revisa y envia a validacion (<?php echo count($parsedRows); ?> filas)</h5>
                                        <div>
                                            <span class="badge bg-secondary-subtle text-secondary me-1">Gris = ya existe / duplicado, sin marcar</span>
                                        </div>
                                    </div>

                                    <p class="text-muted">
                                        Esto no crea los clientes todavia. Se genera un enlace temporal para que un encargado
                                        confirme cada contacto (autenticandose con su correo institucional); recien despues de
                                        confirmados se importan a Clientes desde <a href="admin-validation-links.php">Enlaces de validacion</a>.
                                    </p>

                                    <form method="post" id="importForm">
                                        <input type="hidden" name="action" value="stage">
                                        <div class="mb-3" style="max-width:400px;">
                                            <label class="form-label">Nombre de este lote (para identificarlo despues)</label>
                                            <input type="text" name="link_label" class="form-control" placeholder="Ej. Base combinada julio 2026">
                                        </div>
                                        <div class="table-responsive" style="max-height:600px;">
                                            <table class="table table-bordered table-sm align-middle">
                                                <thead class="table-light" style="position:sticky;top:0;">
                                                    <tr>
                                                        <th style="width:40px">
                                                            <input type="checkbox" id="checkAll" checked>
                                                        </th>
                                                        <th style="min-width:180px">Nombre (cliente)</th>
                                                        <th style="min-width:150px">Empresa/Grupo (contacto)</th>
                                                        <th style="min-width:120px">Oficina</th>
                                                        <th style="min-width:100px">Zona</th>
                                                        <th style="min-width:150px">Contacto interno</th>
                                                        <th style="min-width:100px">RUC/CI</th>
                                                        <th style="min-width:90px">Meses fact.</th>
                                                        <th style="min-width:180px">Detalle meses</th>
                                                        <th style="min-width:110px">Estatus</th>
                                                        <th style="min-width:150px">Alerta</th>
                                                        <th style="min-width:130px">Ciudad</th>
                                                        <th style="min-width:150px">Marca</th>
                                                        <th style="min-width:130px">Clasificacion</th>
                                                        <th style="min-width:200px">Notas</th>
                                                        <th style="width:90px">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($parsedRows as $i => $r): ?>
                                                        <?php $rowClass = ($r["duplicate_in_batch"] || $r["duplicate_in_db"]) ? "table-secondary" : ""; ?>
                                                        <tr class="<?php echo $rowClass; ?>">
                                                            <td>
                                                                <input type="checkbox" name="include[<?php echo $i; ?>]" value="1" class="row-include" <?php echo $r["include"] ? "checked" : ""; ?>>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="name[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["name"]); ?>">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="contact_name[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["contact_name"]); ?>">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="oficina[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["oficina"]); ?>">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="zona[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["zona"]); ?>">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="contacto_interno[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["contacto_interno"]); ?>">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="ruc_ci[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["ruc_ci"]); ?>">
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($r["meses_fact"]); ?>
                                                                <input type="hidden" name="meses_fact[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($r["meses_fact"]); ?>">
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($r["detalle_meses"]); ?>
                                                                <input type="hidden" name="detalle_meses[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($r["detalle_meses"]); ?>">
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($r["estatus_excel"]); ?>
                                                                <input type="hidden" name="estatus_excel[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($r["estatus_excel"]); ?>">
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($r["alerta"]); ?>
                                                                <input type="hidden" name="alerta[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($r["alerta"]); ?>">
                                                            </td>
                                                            <td>
                                                                <input type="text" name="ciudad[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["ciudad"]); ?>">
                                                                <input type="hidden" name="address[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($r["address"]); ?>">
                                                            </td>
                                                            <td>
                                                                <select name="brand_id[<?php echo $i; ?>]" class="form-select form-select-sm">
                                                                    <option value="">Sin marca</option>
                                                                    <?php foreach ($brandsMap as $bid => $bname): ?>
                                                                        <option value="<?php echo $bid; ?>" <?php echo $r["brand_id"] == $bid ? "selected" : ""; ?>><?php echo htmlspecialchars($bname); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <select name="classification_id[<?php echo $i; ?>]" class="form-select form-select-sm">
                                                                    <option value="">Sin clasificacion</option>
                                                                    <?php foreach ($classMap as $cid => $cname): ?>
                                                                        <option value="<?php echo $cid; ?>" <?php echo $r["classification_id"] == $cid ? "selected" : ""; ?>><?php echo htmlspecialchars($cname); ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <input type="text" name="notes[<?php echo $i; ?>]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($r["notes"]); ?>">
                                                            </td>
                                                            <td>
                                                                <?php if ($r["duplicate_in_db"]): ?>
                                                                    <span class="badge bg-warning">Ya existe</span>
                                                                <?php elseif ($r["duplicate_in_batch"]): ?>
                                                                    <span class="badge bg-danger">Repetido</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success">Nuevo</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-primary" id="stageSubmitBtn">Generar enlace de validacion</button>
                                            <a href="admin-clients-list.php" class="btn btn-light">Cancelar</a>
                                        </div>
                                    </form>
                                    <script>
                                    document.getElementById('importForm').addEventListener('submit', function () {
                                        var btn = document.getElementById('stageSubmitBtn');
                                        btn.disabled = true;
                                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando...';
                                    });
                                    </script>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($step === "done" && $generatedLink): ?>
                    <?php
                        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                        $baseUrl = $scheme . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                        $validateUrl = $baseUrl . "/validate.php?token=" . $generatedLink["token"];
                    ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Enlace de validacion generado</h5>
                                    <p>
                                        <strong><?php echo (int) $generatedLink["staged"]; ?></strong> contactos quedaron pendientes de validacion
                                        en el lote "<?php echo htmlspecialchars($generatedLink["label"]); ?>".
                                        <?php if ($generatedLink["skipped_empty"] > 0): ?>
                                            (<?php echo (int) $generatedLink["skipped_empty"]; ?> omitidos por no tener nombre.)
                                        <?php endif; ?>
                                    </p>
                                    <div class="input-group mb-3" style="max-width:700px;">
                                        <input type="text" class="form-control" id="validateUrlInput" value="<?php echo htmlspecialchars($validateUrl); ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('validateUrlInput').value); this.innerText='Copiado!';">
                                            Copiar
                                        </button>
                                    </div>
                                    <p class="text-muted">
                                        Comparte este enlace con la persona encargada de validar. Debera confirmar su correo
                                        institucional con un codigo antes de poder revisar y confirmar los contactos.
                                    </p>
                                    <a href="admin-validation-links.php" class="btn btn-primary">Ver enlaces de validacion</a>
                                    <a href="admin-clients-list.php" class="btn btn-light">Ir a Clientes</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php include 'layouts/footer.php'; ?>
    </div>
</div>

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>
<script src="assets/js/app.js"></script>

<script>
var checkAll = document.getElementById('checkAll');
if (checkAll) {
    checkAll.addEventListener('change', function () {
        document.querySelectorAll('.row-include').forEach(function (chk) {
            chk.checked = checkAll.checked;
        });
    });
}
</script>

</body>

</html>
