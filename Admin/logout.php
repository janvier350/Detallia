<?php
// Initialize the session
session_start();

// Registrar el cierre de sesion en la bitacora antes de destruirla
require_once "layouts/config.php";
require_once "layouts/helpers.php";
if (isset($_SESSION["id"])) {
    log_activity($link, "salio", "Sesion", "Cerro sesion");
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page
header("location: auth-login.php");
exit;
?>
