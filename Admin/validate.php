<?php
require_once 'layouts/config.php';
require_once 'layouts/mailer.php';

$token = trim($_GET["token"] ?? $_POST["token"] ?? "");
$error_msg = "";
$info_msg = "";
$otp_sent_to = "";

if ($token === "") {
    http_response_code(404);
    die("Enlace invalido.");
}

$stmt = mysqli_prepare($link, "SELECT id, label, mode, active, finished_at, finished_by FROM validation_links WHERE token = ?");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$batch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$batch) {
    http_response_code(404);
    die("Este enlace no existe.");
}

$cookieName = "dtv_" . substr(md5($token), 0, 16);
$verifiedEmail = null;

function get_verified_email($link, $linkId, $sessionToken)
{
    if (!$sessionToken) {
        return null;
    }
    $stmt = mysqli_prepare($link, "SELECT email FROM validation_otp_codes WHERE link_id = ? AND session_token = ? AND verified = 1 ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $linkId, $sessionToken);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row ? $row["email"] : null;
}

$verifiedEmail = get_verified_email($link, $batch["id"], $_COOKIE[$cookieName] ?? null);

// ---------------------------------------------------------------
// Paso A: solicitar codigo por correo
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "request_otp") {
    $email = trim($_POST["email"] ?? "");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Ingresa un correo valido.";
    } else {
        $code = str_pad((string) random_int(0, 999999), 6, "0", STR_PAD_LEFT);
        $expires = date("Y-m-d H:i:s", time() + 600);

        $ins = mysqli_prepare($link, "INSERT INTO validation_otp_codes (link_id, email, code, expires_at) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "isss", $batch["id"], $email, $code, $expires);
        mysqli_stmt_execute($ins);

        $body = "<p>Tu codigo de verificacion para validar contactos en Detallia es:</p>" .
                "<h2 style='letter-spacing:4px;'>" . $code . "</h2>" .
                "<p>Este codigo vence en 10 minutos. Lote: " . htmlspecialchars($batch["label"]) . "</p>";
        $sendResult = send_app_mail($email, $email, "Codigo de verificacion - Detallia", $body);

        if ($sendResult === true) {
            $otp_sent_to = $email;
            $info_msg = "Te enviamos un codigo de 6 digitos a $email. Revisa tu bandeja de entrada (y spam).";
        } else {
            $error_msg = $sendResult;
        }
    }
}

// ---------------------------------------------------------------
// Paso B: verificar codigo
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "verify_otp") {
    $email = trim($_POST["email"] ?? "");
    $code  = trim($_POST["code"] ?? "");

    $stmt = mysqli_prepare($link, "SELECT id FROM validation_otp_codes
                                    WHERE link_id = ? AND email = ? AND code = ? AND verified = 0 AND expires_at >= NOW()
                                    ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "iss", $batch["id"], $email, $code);
    mysqli_stmt_execute($stmt);
    $otpRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$otpRow) {
        $error_msg = "El codigo es invalido o expiro. Solicita uno nuevo.";
        $otp_sent_to = $email;
    } else {
        $sessionToken = bin2hex(random_bytes(24));
        $upd = mysqli_prepare($link, "UPDATE validation_otp_codes SET verified = 1, session_token = ? WHERE id = ?");
        $otpId = (int) $otpRow["id"];
        mysqli_stmt_bind_param($upd, "si", $sessionToken, $otpId);
        mysqli_stmt_execute($upd);

        setcookie($cookieName, $sessionToken, time() + 60 * 60 * 24 * 30, "/", "", isset($_SERVER['HTTPS']), true);
        header("location: validate.php?token=" . urlencode($token));
        exit;
    }
}

// ---------------------------------------------------------------
// Confirmar / rechazar un contacto (requiere email verificado)
// ---------------------------------------------------------------
// ---------------------------------------------------------------
// Reversar un contacto ya confirmado/rechazado (vuelve a pendiente)
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "revert" && $verifiedEmail && $batch["active"]) {
    $pendingId = (int) ($_POST["pending_id"] ?? 0);
    if ($pendingId > 0) {
        $upd = mysqli_prepare($link, "UPDATE pending_clients
                                       SET status='pendiente', validated_by_email=NULL, validated_at=NULL
                                       WHERE id=? AND link_id=? AND imported=0");
        mysqli_stmt_bind_param($upd, "ii", $pendingId, $batch["id"]);
        mysqli_stmt_execute($upd);
        $info_msg = "Contacto devuelto a la lista de pendientes.";
    }
}

// ---------------------------------------------------------------
// Guardar cambios (nombre / contacto interno / demas campos) sin cambiar el estado
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update" && $verifiedEmail && $batch["active"]) {
    $pendingId = (int) ($_POST["pending_id"] ?? 0);
    $name      = trim($_POST["name"] ?? "");
    $contactoInterno = trim($_POST["contacto_interno"] ?? "");
    $ciudad    = trim($_POST["ciudad"] ?? "");
    $address   = trim($_POST["address"] ?? "");
    $notes     = trim($_POST["notes"] ?? "");
    $brandId   = (int) ($_POST["brand_id"] ?? 0);
    $brandId   = $brandId > 0 ? $brandId : null;
    $classId   = (int) ($_POST["classification_id"] ?? 0);
    $classId   = $classId > 0 ? $classId : null;

    if ($pendingId > 0 && $name !== "") {
        $upd = mysqli_prepare($link, "UPDATE pending_clients
                                       SET name=?, contacto_interno=?, ciudad=?, address=?, notes=?, brand_id=?, classification_id=?
                                       WHERE id=? AND link_id=? AND imported=0");
        mysqli_stmt_bind_param($upd, "sssssiiii", $name, $contactoInterno, $ciudad, $address, $notes, $brandId, $classId, $pendingId, $batch["id"]);
        mysqli_stmt_execute($upd);
        $info_msg = "Cambios guardados.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($_POST["action"] ?? "", ["confirm", "reject"], true) && $verifiedEmail && $batch["active"]) {
    $pendingId = (int) ($_POST["pending_id"] ?? 0);
    $name      = trim($_POST["name"] ?? "");
    $contactoInterno = trim($_POST["contacto_interno"] ?? "");
    $ciudad    = trim($_POST["ciudad"] ?? "");
    $address   = trim($_POST["address"] ?? "");
    $notes     = trim($_POST["notes"] ?? "");
    $brandId   = (int) ($_POST["brand_id"] ?? 0);
    $brandId   = $brandId > 0 ? $brandId : null;
    $classId   = (int) ($_POST["classification_id"] ?? 0);
    $classId   = $classId > 0 ? $classId : null;
    $newStatus = $_POST["action"] === "confirm" ? "confirmado" : "rechazado";

    if ($pendingId > 0 && $name !== "") {
        $upd = mysqli_prepare($link, "UPDATE pending_clients
                                       SET name=?, contacto_interno=?, ciudad=?, address=?, notes=?, brand_id=?, classification_id=?, status=?, validated_by_email=?, validated_at=NOW()
                                       WHERE id=? AND link_id=?");
        mysqli_stmt_bind_param($upd, "sssssiissii", $name, $contactoInterno, $ciudad, $address, $notes, $brandId, $classId, $newStatus, $verifiedEmail, $pendingId, $batch["id"]);
        mysqli_stmt_execute($upd);
        $info_msg = "Contacto actualizado.";
    }
}

$isCollection = ($batch["mode"] ?? "validacion") === "recoleccion";

// ---------------------------------------------------------------
// Modo recoleccion: el encargado agrega un contacto desde cero
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_contact" && $verifiedEmail && $batch["active"]) {
    $name            = trim($_POST["name"] ?? "");
    $contact_name    = trim($_POST["contact_name"] ?? "");
    $contacto_interno = trim($_POST["contacto_interno"] ?? "");
    $ciudad          = trim($_POST["ciudad"] ?? "");
    $address         = trim($_POST["address"] ?? "");
    $brandId         = (int) ($_POST["brand_id"] ?? 0);
    $brandId         = $brandId > 0 ? $brandId : null;
    $classId         = (int) ($_POST["classification_id"] ?? 0);
    $classId         = $classId > 0 ? $classId : null;

    if ($name === "") {
        $error_msg = "La razon social es obligatoria.";
    } elseif ($classId === null) {
        $error_msg = "Selecciona la categoria de cliente.";
    } elseif ($brandId === null) {
        $error_msg = "Selecciona la marca.";
    } else {
        $ins = mysqli_prepare($link, "INSERT INTO pending_clients
                (link_id, name, contact_name, contacto_interno, ciudad, address, brand_id, classification_id, status, validated_by_email, validated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, NOW())");
        mysqli_stmt_bind_param($ins, "isssssiis", $batch["id"], $name, $contact_name, $contacto_interno, $ciudad, $address, $brandId, $classId, $verifiedEmail);
        if (mysqli_stmt_execute($ins)) {
            $info_msg = "Contacto \"" . $name . "\" agregado. Puedes seguir agregando mas.";
        } else {
            $error_msg = "No se pudo guardar el contacto. Intenta de nuevo.";
        }
    }
}

// ---------------------------------------------------------------
// Modo recoleccion: eliminar un contacto propio aun no importado
// ---------------------------------------------------------------
if ($isCollection && $_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_own" && $verifiedEmail && $batch["active"]) {
    $pendingId = (int) ($_POST["pending_id"] ?? 0);
    if ($pendingId > 0) {
        $del = mysqli_prepare($link, "DELETE FROM pending_clients WHERE id = ? AND link_id = ? AND validated_by_email = ? AND imported = 0");
        mysqli_stmt_bind_param($del, "iis", $pendingId, $batch["id"], $verifiedEmail);
        mysqli_stmt_execute($del);
        $info_msg = "Contacto eliminado.";
    }
}

$brandsRes = mysqli_query($link, "SELECT id, name FROM brands ORDER BY name");
$classRes  = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY name");

if ($verifiedEmail) {
    if ($isCollection) {
        $stmtOwn = mysqli_prepare($link, "SELECT pc.*, b.name AS brand_name, cl.name AS classification_name
                                           FROM pending_clients pc
                                           LEFT JOIN brands b ON b.id = pc.brand_id
                                           LEFT JOIN client_classifications cl ON cl.id = pc.classification_id
                                           WHERE pc.link_id = ? AND pc.validated_by_email = ?
                                           ORDER BY pc.id DESC");
        mysqli_stmt_bind_param($stmtOwn, "is", $batch["id"], $verifiedEmail);
        mysqli_stmt_execute($stmtOwn);
        $ownContacts = mysqli_stmt_get_result($stmtOwn);
    } else {
        $pending = mysqli_query($link, "SELECT * FROM pending_clients WHERE link_id = " . (int) $batch["id"] . " ORDER BY status = 'pendiente' DESC, name ASC");
    }
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Validacion de contactos | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>

<div class="auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0 justify-content-center">
            <div class="<?php echo $verifiedEmail ? 'col-12' : 'col-xxl-7 col-lg-9'; ?>">
                <div class="auth-full-page-content d-flex p-sm-5 p-4">
                    <div class="w-100">

                        <div class="mb-4 text-center">
                            <img src="assets/images/logo-detallia.svg" alt="Detallia" height="34">
                        </div>

                        <?php if (!$batch["active"]): ?>
                            <div class="alert alert-warning text-center">Este enlace de validacion ya no esta disponible.</div>

                        <?php elseif (!$verifiedEmail): ?>
                            <div class="text-center mb-4">
                                <h5>Validacion de contactos</h5>
                                <p class="text-muted">Lote: <?php echo htmlspecialchars($batch["label"]); ?></p>
                            </div>

                            <?php if ($info_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($info_msg); ?></div><?php endif; ?>
                            <?php if ($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

                            <?php if ($otp_sent_to === ""): ?>
                                <form method="post" class="mx-auto" style="max-width:400px;">
                                    <input type="hidden" name="action" value="request_otp">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Correo institucional</label>
                                        <input type="email" name="email" class="form-control" required placeholder="nombre@empresa.com">
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Enviar codigo</button>
                                </form>
                            <?php else: ?>
                                <form method="post" class="mx-auto" style="max-width:400px;">
                                    <input type="hidden" name="action" value="verify_otp">
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($otp_sent_to); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Codigo de verificacion</label>
                                        <input type="text" name="code" class="form-control text-center" style="letter-spacing:6px;font-size:1.3rem;" maxlength="6" required autofocus>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Verificar</button>
                                </form>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                <div>
                                    <h5 class="mb-0">Lote: <?php echo htmlspecialchars($batch["label"]); ?></h5>
                                    <p class="text-muted mb-0">Validando como: <?php echo htmlspecialchars($verifiedEmail); ?></p>
                                </div>
                                <?php if (!$isCollection): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContactModal">
                                        <i class="mdi mdi-account-plus-outline me-1"></i> Agregar contacto
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($info_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($info_msg); ?></div><?php endif; ?>
                            <?php if ($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>

                            <?php if (!$isCollection): ?>
                                <!-- Modal: agregar un contacto nuevo a la lista de validacion -->
                                <div class="modal fade" id="addContactModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <form method="post" class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="mdi mdi-account-plus-outline me-1"></i> Agregar un contacto</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="add_contact">
                                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Razon social <span class="text-danger">*</span></label>
                                                        <input type="text" name="name" class="form-control" required>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Nombre de quien recibe</label>
                                                        <input type="text" name="contact_name" class="form-control">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Contacto (persona)</label>
                                                        <input type="text" name="contacto_interno" class="form-control" placeholder="Nombre de la persona de contacto">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Categoria de cliente <span class="text-danger">*</span></label>
                                                        <select name="classification_id" class="form-select" required>
                                                            <option value="">Selecciona...</option>
                                                            <?php mysqli_data_seek($classRes, 0); while ($c = mysqli_fetch_assoc($classRes)): ?>
                                                                <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Marca <span class="text-danger">*</span></label>
                                                        <select name="brand_id" class="form-select" required>
                                                            <option value="">Selecciona...</option>
                                                            <?php mysqli_data_seek($brandsRes, 0); while ($b = mysqli_fetch_assoc($brandsRes)): ?>
                                                                <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Ciudad</label>
                                                        <input type="text" name="ciudad" class="form-control">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label">Direccion</label>
                                                        <input type="text" name="address" class="form-control">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary"><i class="mdi mdi-content-save-outline me-1"></i> Agregar contacto</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($isCollection): ?>
                                <!-- ============ MODO RECOLECCION ============ -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3"><i class="mdi mdi-account-plus-outline me-1"></i> Agregar un contacto</h6>
                                        <form method="post" class="row g-3">
                                            <input type="hidden" name="action" value="add_contact">
                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                            <div class="col-md-6">
                                                <label class="form-label">Razon social <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Nombre de quien recibe</label>
                                                <input type="text" name="contact_name" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Contacto (persona)</label>
                                                <input type="text" name="contacto_interno" class="form-control" placeholder="Nombre de la persona de contacto">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Categoria de cliente <span class="text-danger">*</span></label>
                                                <select name="classification_id" class="form-select" required>
                                                    <option value="">Selecciona...</option>
                                                    <?php mysqli_data_seek($classRes, 0); while ($c = mysqli_fetch_assoc($classRes)): ?>
                                                        <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Marca <span class="text-danger">*</span></label>
                                                <select name="brand_id" class="form-select" required>
                                                    <option value="">Selecciona...</option>
                                                    <?php mysqli_data_seek($brandsRes, 0); while ($b = mysqli_fetch_assoc($brandsRes)): ?>
                                                        <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Ciudad</label>
                                                <input type="text" name="ciudad" class="form-control">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Direccion</label>
                                                <input type="text" name="address" class="form-control">
                                            </div>
                                            <div class="col-12 text-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="mdi mdi-content-save-outline me-1"></i> Agregar contacto
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <h6 class="text-muted">Contactos que has ingresado (<?php echo mysqli_num_rows($ownContacts); ?>)</h6>
                                <?php if (mysqli_num_rows($ownContacts) === 0): ?>
                                    <div class="alert alert-info">Aun no has ingresado contactos. Usa el formulario de arriba.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Razon social</th>
                                                    <th>Quien recibe</th>
                                                    <th>Contacto</th>
                                                    <th>Categoria</th>
                                                    <th>Marca</th>
                                                    <th>Ciudad</th>
                                                    <th>Direccion</th>
                                                    <th>Estado</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php mysqli_data_seek($ownContacts, 0); while ($o = mysqli_fetch_assoc($ownContacts)): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($o["name"]); ?></td>
                                                        <td><?php echo htmlspecialchars($o["contact_name"] ?? ""); ?></td>
                                                        <td><?php echo htmlspecialchars($o["contacto_interno"] ?? ""); ?></td>
                                                        <td><?php echo htmlspecialchars($o["classification_name"] ?? "—"); ?></td>
                                                        <td><?php echo htmlspecialchars($o["brand_name"] ?? "—"); ?></td>
                                                        <td><?php echo htmlspecialchars($o["ciudad"] ?? ""); ?></td>
                                                        <td><?php echo htmlspecialchars($o["address"] ?? ""); ?></td>
                                                        <td>
                                                            <?php if ($o["imported"]): ?>
                                                                <span class="badge bg-primary">En Clientes</span>
                                                            <?php else: ?>
                                                                <?php $bc = ["pendiente" => "warning", "confirmado" => "success", "rechazado" => "danger"][$o["status"]] ?? "secondary"; ?>
                                                                <span class="badge bg-<?php echo $bc; ?>"><?php echo ucfirst($o["status"]); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?php if (!$o["imported"] && $o["status"] === "pendiente"): ?>
                                                                <form method="post" onsubmit="return confirm('¿Eliminar este contacto?');">
                                                                    <input type="hidden" name="action" value="delete_own">
                                                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                                                    <input type="hidden" name="pending_id" value="<?php echo (int) $o['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="mdi mdi-delete"></i></button>
                                                                </form>
                                                            <?php else: ?>
                                                                <span class="text-muted small">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                            <!-- ============ MODO VALIDACION (existente) ============ -->
                            <?php
                                mysqli_data_seek($pending, 0);
                                $anyPending = false;
                                $pendingRows = [];
                                while ($p = mysqli_fetch_assoc($pending)) {
                                    if ($p["status"] === "pendiente") {
                                        $pendingRows[] = $p;
                                        $anyPending = true;
                                    }
                                }
                            ?>

                            <?php if ($anyPending): ?>
                                <div class="mb-3" style="max-width:420px;">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                        <input type="text" id="tableSearch" class="form-control" placeholder="Buscar en la tabla (nombre, contacto interno, ciudad...)">
                                    </div>
                                    <small class="text-muted"><span id="visibleCount"><?php echo count($pendingRows); ?></span> de <?php echo count($pendingRows); ?> contactos</small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle" id="pendingTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width:110px">Acciones</th>
                                                <th style="min-width:160px">Nombre</th>
                                                <th style="min-width:140px">Empresa/Grupo</th>
                                                <th style="min-width:110px">Oficina</th>
                                                <th style="min-width:90px">Zona</th>
                                                <th style="min-width:130px">Contacto interno</th>
                                                <th style="min-width:90px">Meses fact.</th>
                                                <th style="min-width:180px">Detalle meses</th>
                                                <th style="min-width:150px">Alerta</th>
                                                <th style="min-width:120px">Ciudad</th>
                                                <th style="min-width:140px">Direccion</th>
                                                <th style="min-width:160px">Notas</th>
                                                <th style="min-width:140px">Marca</th>
                                                <th style="min-width:130px">Clasificacion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingRows as $p): ?>
                                                <?php $rowFormId = "rowform" . (int) $p['id']; ?>
                                                <tr>
                                                    <td class="text-nowrap">
                                                        <button type="submit" form="<?php echo $rowFormId; ?>" name="action" value="confirm" class="btn btn-success btn-sm" title="Aprobar">
                                                            <i class="mdi mdi-check-bold"></i>
                                                        </button>
                                                        <button type="submit" form="<?php echo $rowFormId; ?>" name="action" value="update" class="btn btn-outline-primary btn-sm" title="Solo guardar cambios">
                                                            <i class="mdi mdi-content-save-outline"></i>
                                                        </button>
                                                        <button type="submit" form="<?php echo $rowFormId; ?>" name="action" value="reject" class="btn btn-outline-danger btn-sm" title="Rechazar">
                                                            <i class="mdi mdi-close"></i>
                                                        </button>
                                                    </td>
                                                    <td>
                                                        <input type="hidden" form="<?php echo $rowFormId; ?>" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                                        <input type="hidden" form="<?php echo $rowFormId; ?>" name="pending_id" value="<?php echo (int) $p['id']; ?>">
                                                        <input type="text" form="<?php echo $rowFormId; ?>" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['name']); ?>" required>
                                                    </td>
                                                    <td><input type="text" form="<?php echo $rowFormId; ?>" name="contact_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['contact_name'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['oficina'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['zona'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" form="<?php echo $rowFormId; ?>" name="contacto_interno" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['contacto_interno'] ?? ''); ?>"></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['meses_fact'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['detalle_meses'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['alerta'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" form="<?php echo $rowFormId; ?>" name="ciudad" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['ciudad'] ?? ''); ?>"></td>
                                                    <td><input type="text" form="<?php echo $rowFormId; ?>" name="address" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['address'] ?? ''); ?>"></td>
                                                    <td><input type="text" form="<?php echo $rowFormId; ?>" name="notes" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['notes'] ?? ''); ?>"></td>
                                                    <td>
                                                        <select form="<?php echo $rowFormId; ?>" name="brand_id" class="form-select form-select-sm">
                                                            <option value="">Sin marca</option>
                                                            <?php mysqli_data_seek($brandsRes, 0); while ($b = mysqli_fetch_assoc($brandsRes)): ?>
                                                                <option value="<?php echo (int) $b['id']; ?>" <?php echo $p['brand_id'] == $b['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select form="<?php echo $rowFormId; ?>" name="classification_id" class="form-select form-select-sm">
                                                            <option value="">Sin clasificacion</option>
                                                            <?php mysqli_data_seek($classRes, 0); while ($c = mysqli_fetch_assoc($classRes)): ?>
                                                                <option value="<?php echo (int) $c['id']; ?>" <?php echo $p['classification_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php foreach ($pendingRows as $p): ?>
                                    <form id="rowform<?php echo (int) $p['id']; ?>" method="post"></form>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">Ya no quedan contactos pendientes en este lote. Gracias por tu ayuda.</div>
                            <?php endif; ?>

                            <hr>
                            <h6 class="text-muted">Ya revisados</h6>
                            <div class="mb-3" style="max-width:420px;">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                    <input type="text" id="reviewedSearch" class="form-control" placeholder="Buscar en revisados (nombre, empresa, contacto...)">
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm" id="reviewedTable">
                                    <thead>
                                        <tr><th>Nombre</th><th>Empresa/Grupo</th><th>Contacto interno</th><th>Estado</th><th>Validado por</th><th>Acciones</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php mysqli_data_seek($pending, 0); while ($p = mysqli_fetch_assoc($pending)): ?>
                                            <?php if ($p["status"] === "pendiente") continue; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($p["name"]); ?></td>
                                                <td><?php echo htmlspecialchars($p["contact_name"] ?? ""); ?></td>
                                                <td><?php echo htmlspecialchars($p["contacto_interno"] ?? ""); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $p["status"] === "confirmado" ? "success" : "danger"; ?>">
                                                        <?php echo ucfirst($p["status"]); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($p["validated_by_email"] ?? ""); ?></td>
                                                <td>
                                                    <?php if (!$p["imported"]): ?>
                                                        <form method="post" onsubmit="return confirm('¿Devolver este contacto a la lista de pendientes?');">
                                                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                                            <input type="hidden" name="pending_id" value="<?php echo (int) $p['id']; ?>">
                                                            <input type="hidden" name="action" value="revert">
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                                <i class="mdi mdi-undo me-1"></i> Reversar
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Ya importado</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; /* fin modo validacion */ ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/vendor-scripts.php'; ?>

<script>
(function () {
    var search = document.getElementById('tableSearch');
    var table = document.getElementById('pendingTable');
    var counter = document.getElementById('visibleCount');
    if (!search || !table) return;

    var rows = table.querySelectorAll('tbody tr');

    function rowText(row) {
        var parts = [];
        // Texto plano de las celdas
        parts.push(row.innerText || row.textContent || '');
        // Valores de inputs y selects (la mayoria de columnas son campos)
        row.querySelectorAll('input, textarea').forEach(function (el) {
            if (el.type !== 'hidden') parts.push(el.value || '');
        });
        row.querySelectorAll('select').forEach(function (sel) {
            if (sel.selectedIndex >= 0) parts.push(sel.options[sel.selectedIndex].text || '');
        });
        return parts.join(' ').toLowerCase();
    }

    // Cachear el texto de cada fila una sola vez
    rows.forEach(function (row) { row.dataset.search = rowText(row); });

    // Recordar el filtro por lote para no perderlo al aprobar/rechazar (la pagina se recarga por POST)
    var storeKey = 'dtv_filter_<?php echo substr(md5($token), 0, 16); ?>';

    function applyFilter() {
        var q = search.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
            var match = q === '' || row.dataset.search.indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (counter) counter.innerText = visible;
    }

    search.addEventListener('input', function () {
        try { localStorage.setItem(storeKey, this.value); } catch (e) {}
        applyFilter();
    });

    // Restaurar el filtro guardado al cargar la pagina
    try {
        var saved = localStorage.getItem(storeKey);
        if (saved) { search.value = saved; }
    } catch (e) {}
    applyFilter();
})();

// Buscador de la tabla "Ya revisados"
(function () {
    var search = document.getElementById('reviewedSearch');
    var table = document.getElementById('reviewedTable');
    if (!search || !table) return;

    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function (row) {
        row.dataset.search = (row.innerText || row.textContent || '').toLowerCase();
    });

    search.addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        rows.forEach(function (row) {
            row.style.display = (q === '' || row.dataset.search.indexOf(q) !== -1) ? '' : 'none';
        });
    });
})();
</script>

</body>

</html>
