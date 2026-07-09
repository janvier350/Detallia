<?php
include 'layouts/session.php';
require_once 'layouts/config.php';
require_once 'layouts/auth-guard.php';
require_once 'layouts/helpers.php';

$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current  = trim($_POST["current_password"] ?? "");
    $new      = trim($_POST["new_password"] ?? "");
    $confirm  = trim($_POST["confirm_password"] ?? "");

    if ($current === "" || $new === "" || $confirm === "") {
        $error_msg = "Completa todos los campos.";
    } elseif (strlen($new) < 6) {
        $error_msg = "La nueva contrasena debe tener al menos 6 caracteres.";
    } elseif ($new !== $confirm) {
        $error_msg = "La confirmacion no coincide con la nueva contrasena.";
    } else {
        $stmt = mysqli_prepare($link, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$row || !password_verify($current, $row["password"])) {
            $error_msg = "La contrasena actual no es correcta.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($link, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $hash, $_SESSION["id"]);
            mysqli_stmt_execute($upd);
            $success_msg = "Tu contrasena fue actualizada correctamente.";
        }
    }
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Cambiar contrasena | Detallia</title>
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
                            <h4 class="mb-sm-0 font-size-18">Cambiar contrasena</h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="index.php">Detallia</a></li>
                                    <li class="breadcrumb-item active">Cambiar contrasena</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">

                                <?php if ($success_msg): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                                <?php endif; ?>
                                <?php if ($error_msg): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                                <?php endif; ?>

                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Contrasena actual</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nueva contrasena</label>
                                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirmar nueva contrasena</label>
                                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Actualizar contrasena</button>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>

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
