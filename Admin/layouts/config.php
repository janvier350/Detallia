<?php
// Zona horaria de Ecuador para toda la aplicacion (PHP y MySQL)
date_default_timezone_set('America/Guayaquil');

/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'buadnetc_detallia_user');
define('DB_PASSWORD', '~zFr@W$anxq1#y;1');
define('DB_NAME', 'buadnetc_detallia');

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Ecuador esta en UTC-5 todo el ano (sin horario de verano)
mysqli_query($link, "SET time_zone = '-05:00'");

$gmailid = ''; // YOUR gmail email
$gmailpassword = ''; // YOUR gmail password
$gmailusername = ''; // YOUR gmail User name

?>