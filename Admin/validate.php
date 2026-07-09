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

$stmt = mysqli_prepare($link, "SELECT id, label, active, finished_at, finished_by FROM validation_links WHERE token = ?");
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
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "finish" && $verifiedEmail) {
    $upd = mysqli_prepare($link, "UPDATE validation_links SET finished_at = NOW(), finished_by = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $verifiedEmail, $batch["id"]);
    mysqli_stmt_execute($upd);
    $batch["finished_at"] = date("Y-m-d H:i:s");
    $batch["finished_by"] = $verifiedEmail;
    $info_msg = "Gracias, se registro que terminaste la validacion de este lote.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && in_array($_POST["action"] ?? "", ["confirm", "reject"], true) && $verifiedEmail && $batch["active"]) {
    $pendingId = (int) ($_POST["pending_id"] ?? 0);
    $name      = trim($_POST["name"] ?? "");
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
                                       SET name=?, ciudad=?, address=?, notes=?, brand_id=?, classification_id=?, status=?, validated_by_email=?, validated_at=NOW()
                                       WHERE id=? AND link_id=?");
        mysqli_stmt_bind_param($upd, "ssssiissii", $name, $ciudad, $address, $notes, $brandId, $classId, $newStatus, $verifiedEmail, $pendingId, $batch["id"]);
        mysqli_stmt_execute($upd);
        $info_msg = "Contacto actualizado.";
    }
}

$brandsRes = mysqli_query($link, "SELECT id, name FROM brands ORDER BY name");
$classRes  = mysqli_query($link, "SELECT id, name FROM client_classifications ORDER BY name");

if ($verifiedEmail) {
    $pending = mysqli_query($link, "SELECT * FROM pending_clients WHERE link_id = " . (int) $batch["id"] . " ORDER BY status = 'pendiente' DESC, name ASC");
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
            <div class="col-xxl-7 col-lg-9">
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
                                <?php if (!$batch["finished_at"]): ?>
                                    <form method="post" onsubmit="return confirm('¿Terminar la validacion de este lote? Los contactos que no revisaste quedaran pendientes.');">
                                        <input type="hidden" name="action" value="finish">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                            <i class="mdi mdi-flag-checkered me-1"></i> Terminar validacion
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if ($info_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($info_msg); ?></div><?php endif; ?>

                            <?php if ($batch["finished_at"]): ?>
                                <div class="alert alert-info">
                                    Marcaste este lote como terminado el <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($batch["finished_at"]))); ?>.
                                    Gracias por tu ayuda.
                                </div>
                            <?php endif; ?>

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

                            <?php if ($anyPending && !$batch["finished_at"]): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="min-width:160px">Nombre</th>
                                                <th style="min-width:140px">Empresa/Grupo</th>
                                                <th style="min-width:110px">Oficina</th>
                                                <th style="min-width:90px">Zona</th>
                                                <th style="min-width:130px">Contacto interno</th>
                                                <th style="min-width:90px">RUC/CI</th>
                                                <th style="min-width:120px">Ciudad</th>
                                                <th style="min-width:140px">Direccion</th>
                                                <th style="min-width:160px">Notas</th>
                                                <th style="min-width:140px">Marca</th>
                                                <th style="min-width:130px">Clasificacion</th>
                                                <th style="min-width:150px">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingRows as $p): ?>
                                                <?php $rowFormId = "rowform" . (int) $p['id']; ?>
                                                <tr>
                                                    <td>
                                                        <input type="hidden" form="<?php echo $rowFormId; ?>" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                                        <input type="hidden" form="<?php echo $rowFormId; ?>" name="pending_id" value="<?php echo (int) $p['id']; ?>">
                                                        <input type="text" form="<?php echo $rowFormId; ?>" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['name']); ?>" required>
                                                    </td>
                                                    <td><input type="text" form="<?php echo $rowFormId; ?>" name="contact_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['contact_name'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['oficina'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['zona'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['contacto_interno'] ?? ''); ?>" readonly></td>
                                                    <td><input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['ruc_ci'] ?? ''); ?>" readonly></td>
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
                                                    <td class="text-nowrap">
                                                        <button type="submit" form="<?php echo $rowFormId; ?>" name="action" value="confirm" class="btn btn-success btn-sm">
                                                            <i class="mdi mdi-check-bold"></i>
                                                        </button>
                                                        <button type="submit" form="<?php echo $rowFormId; ?>" name="action" value="reject" class="btn btn-outline-danger btn-sm">
                                                            <i class="mdi mdi-close"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php foreach ($pendingRows as $p): ?>
                                    <form id="rowform<?php echo (int) $p['id']; ?>" method="post"></form>
                                <?php endforeach; ?>
                            <?php elseif (!$batch["finished_at"]): ?>
                                <div class="alert alert-info">Ya no quedan contactos pendientes en este lote. Gracias por tu ayuda.</div>
                            <?php endif; ?>

                            <hr>
                            <h6 class="text-muted">Ya revisados</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr><th>Nombre</th><th>Estado</th><th>Validado por</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php mysqli_data_seek($pending, 0); while ($p = mysqli_fetch_assoc($pending)): ?>
                                            <?php if ($p["status"] === "pendiente") continue; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($p["name"]); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $p["status"] === "confirmado" ? "success" : "danger"; ?>">
                                                        <?php echo ucfirst($p["status"]); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($p["validated_by_email"] ?? ""); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/vendor-scripts.php'; ?>

</body>

</html>
