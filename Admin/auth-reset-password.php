<?php
require_once "layouts/config.php";

$token = trim($_GET["token"] ?? $_POST["token"] ?? "");
$error_msg = "";
$success_msg = "";

if ($token === "") {
    http_response_code(404);
    die("Enlace invalido.");
}

$stmt = mysqli_prepare($link, "SELECT id, username FROM users WHERE token = ?");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    http_response_code(404);
    die("Este enlace no es valido o ya fue utilizado.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new     = trim($_POST["new_password"] ?? "");
    $confirm = trim($_POST["confirm_password"] ?? "");

    if ($new === "" || $confirm === "") {
        $error_msg = "Completa todos los campos.";
    } elseif (strlen($new) < 6) {
        $error_msg = "La contrasena debe tener al menos 6 caracteres.";
    } elseif ($new !== $confirm) {
        $error_msg = "Las contrasenas no coinciden.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = mysqli_prepare($link, "UPDATE users SET password = ?, token = NULL WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $hash, $user["id"]);
        mysqli_stmt_execute($upd);
        $success_msg = "Tu contrasena fue actualizada. Ya puedes iniciar sesion.";
    }
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Restablecer contrasena | Detallia</title>
    <?php include 'layouts/head.php'; ?>
    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>
<div class="auth-page">
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-xxl-3 col-lg-4 col-md-5">
                <div class="auth-full-page-content d-flex p-sm-5 p-4">
                    <div class="w-100">
                        <div class="d-flex flex-column h-100">
                            <div class="mb-4 mb-md-5 text-center">
                                <a href="auth-login.php" class="d-block auth-logo">
                                    <img src="assets/images/logo-detallia.svg" alt="Detallia" height="34">
                                </a>
                            </div>
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <h5 class="mb-0">Restablecer contrasena</h5>
                                    <p class="text-muted mt-2">Hola, <?php echo htmlspecialchars($user["username"]); ?>. Elige tu nueva contrasena.</p>
                                </div>

                                <?php if ($success_msg): ?>
                                    <div class="alert alert-success text-center my-4"><?php echo htmlspecialchars($success_msg); ?></div>
                                    <div class="text-center">
                                        <a href="auth-login.php" class="btn btn-primary">Ir a iniciar sesion</a>
                                    </div>
                                <?php else: ?>
                                    <?php if ($error_msg): ?>
                                        <div class="alert alert-danger text-center my-4"><?php echo htmlspecialchars($error_msg); ?></div>
                                    <?php endif; ?>
                                    <form class="mt-4" method="post">
                                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                                        <div class="mb-3">
                                            <label class="form-label">Nueva contrasena</label>
                                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Confirmar contrasena</label>
                                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                        </div>
                                        <div class="mb-3 mt-4">
                                            <button class="btn btn-primary w-100 waves-effect waves-light" type="submit">Actualizar contrasena</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <div class="mt-5 text-center">
                                    <p class="text-muted mb-0"><a href="auth-login.php" class="text-primary fw-semibold">Volver a iniciar sesion</a></p>
                                </div>
                            </div>
                            <div class="mt-4 mt-md-5 text-center">
                                <p class="mb-0 text-muted">© <?php echo date('Y'); ?> Detallia</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/vendor-scripts.php'; ?>

</body>

</html>
