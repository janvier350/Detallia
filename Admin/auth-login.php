<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect him to his landing page
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: " . ((int) ($_SESSION["role_id"] ?? 0) === 4 ? "admin-requests-list.php" : "index.php"));
    exit;
}
// Include config file
require_once "layouts/config.php";

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT u.id, u.username, u.password, u.role_id, r.name AS role_name, u.status
                FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.username = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if username exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role_id, $role_name, $status);
                    if (mysqli_stmt_fetch($stmt)) {
                        if ($status !== 'activo') {
                            $username_err = "Esta cuenta esta inactiva. Contacta al administrador.";
                        } elseif (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role_id"] = $role_id;
                            $_SESSION["role_name"] = $role_name;

                            // Redirect user to welcome page
                            header("location: " . ((int) $role_id === 4 ? "admin-requests-list.php" : "index.php"));
                        } else {
                            // Display an error message if password is not valid
                            $password_err = "The password you entered was not valid.";
                        }
                    }
                } else {
                    // Display an error message if username doesn't exist
                    $username_err = "No account found with that username.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}
?>
<?php include 'layouts/head-main.php'; ?>

<head>

    <title>Detallia — Acceso</title>
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
                                <a href="index.php" class="d-block auth-logo">
                                    <img src="assets/images/logo-detallia.svg" alt="Detallia" height="34">
                                </a>
                            </div>
                            <div class="auth-content my-auto">
                                <div class="text-center">
                                    <h5 class="mb-0">Bienvenido de nuevo</h5>
                                    <p class="text-muted mt-2">Ingresa tus credenciales para continuar.</p>
                                </div>
                                <form class="mt-4 pt-2" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                    <div class="mb-3 <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                                        <label class="form-label" for="username">Usuario</label>
                                        <input type="text" class="form-control" id="username" placeholder="Ingresa tu usuario" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                        <?php if ($username_err): ?><span class="text-danger"><?php echo $username_err; ?></span><?php endif; ?>
                                    </div>
                                    <div class="mb-3 <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                                        <div class="float-end">
                                            <a href="auth-recoverpw.php" class="text-muted small">¿Olvidaste tu contrasena?</a>
                                        </div>
                                        <label class="form-label" for="password">Contrasena</label>
                                        <div class="input-group auth-pass-inputgroup">
                                            <input type="password" class="form-control" placeholder="Ingresa tu contrasena" name="password" aria-label="Password" aria-describedby="password-addon">
                                            <button class="btn btn-light ms-0" type="button" id="password-addon"><i class="mdi mdi-eye-outline"></i></button>
                                        </div>
                                        <?php if ($password_err): ?><span class="text-danger"><?php echo $password_err; ?></span><?php endif; ?>
                                    </div>
                                    <div class="mb-4">
                                        <button class="btn btn-primary w-100 waves-effect waves-light" type="submit">Iniciar sesion</button>
                                    </div>
                                </form>
                            </div>
                            <div class="mt-4 mt-md-5 text-center">
                                <p class="mb-0 text-muted">© <?php echo date('Y'); ?> Detallia</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end auth full page content -->
            </div>
            <!-- end col -->
            <div class="col-xxl-9 col-lg-8 col-md-7">
                <div class="auth-bg pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                    <ul class="bg-bubbles">
                        <li></li><li></li><li></li><li></li><li></li>
                        <li></li><li></li><li></li><li></li><li></li>
                    </ul>
                    <div class="row justify-content-center align-items-center">
                        <div class="col-xl-7">
                            <div class="p-0 p-sm-4 px-xl-0">
                                <div id="reviewcarouselIndicators" class="carousel slide" data-bs-ride="carousel">
                                    <div class="carousel-indicators carousel-indicators-rounded justify-content-start ms-0 mb-0">
                                        <button type="button" data-bs-target="#reviewcarouselIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                        <button type="button" data-bs-target="#reviewcarouselIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                        <button type="button" data-bs-target="#reviewcarouselIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                    </div>
                                    <div class="carousel-inner">

                                        <div class="carousel-item active">
                                            <div class="testi-contain text-white">
                                                <i class="bx bxs-gift text-success display-6"></i>
                                                <h4 class="mt-4 fw-medium lh-base text-white">
                                                    "Diseña kits de merchandising personalizados por marca, tipo de gestion y clasificacion de cliente. Cada detalle cuenta."
                                                </h4>
                                                <div class="mt-4 pt-3 pb-5">
                                                    <div class="d-flex align-items-center">
                                                        <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                            <i class="mdi mdi-gift-outline text-white font-size-20"></i>
                                                        </div>
                                                        <div class="ms-3">
                                                            <h5 class="font-size-16 text-white mb-0">Gestion de Kits</h5>
                                                            <p class="mb-0 text-white-50">Arma, personaliza y controla cada kit</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="carousel-item">
                                            <div class="testi-contain text-white">
                                                <i class="bx bxs-bar-chart-alt-2 text-success display-6"></i>
                                                <h4 class="mt-4 fw-medium lh-base text-white">
                                                    "Registra facturas de compra y compara precios por articulo en el tiempo. Toma decisiones de compra basadas en datos reales."
                                                </h4>
                                                <div class="mt-4 pt-3 pb-5">
                                                    <div class="d-flex align-items-center">
                                                        <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                            <i class="mdi mdi-chart-line text-white font-size-20"></i>
                                                        </div>
                                                        <div class="ms-3">
                                                            <h5 class="font-size-16 text-white mb-0">Historial de precios</h5>
                                                            <p class="mb-0 text-white-50">Compara costos entre proveedores y periodos</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="carousel-item">
                                            <div class="testi-contain text-white">
                                                <i class="bx bxs-truck text-success display-6"></i>
                                                <h4 class="mt-4 fw-medium lh-base text-white">
                                                    "Registra cada entrega a tus clientes: quien recibio, que kit, en que fecha y con que motivo. Tu historial de detalles en un solo lugar."
                                                </h4>
                                                <div class="mt-4 pt-3 pb-5">
                                                    <div class="d-flex align-items-center">
                                                        <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                            <i class="mdi mdi-send-outline text-white font-size-20"></i>
                                                        </div>
                                                        <div class="ms-3">
                                                            <h5 class="font-size-16 text-white mb-0">Control de Entregas</h5>
                                                            <p class="mb-0 text-white-50">Trazabilidad completa de cada obsequio entregado</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- JAVASCRIPT -->

<?php include 'layouts/vendor-scripts.php'; ?>
<!-- password addon init -->
<script src="assets/js/pages/pass-addon.init.js"></script>

</body>

</html>