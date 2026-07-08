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

// Credenciales de correo para el envio de notificaciones (recuperar contrasena,
// codigos de verificacion, etc). Puede ser Gmail o el correo institucional de
// Buadnet: cualquier proveedor que soporte SMTP funciona, solo ajusta SMTP_HOST,
// SMTP_PORT y SMTP_ENCRYPTION segun los datos que te de tu proveedor de correo.
$gmailid = ''; // Correo remitente, ej: notificaciones@buadnet.com
$gmailpassword = ''; // Contrasena o contrasena de aplicacion
$gmailusername = 'Detallia'; // Nombre que vera el destinatario como remitente

define('SMTP_HOST', 'smtp.gmail.com'); // ej: smtp.gmail.com o el que te de tu proveedor (ej: mail.buadnet.com)
define('SMTP_PORT', 587); // 587 (STARTTLS) o 465 (SSL), segun tu proveedor
define('SMTP_ENCRYPTION', 'tls'); // 'tls' o 'ssl'

?>