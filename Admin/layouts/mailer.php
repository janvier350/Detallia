<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

// Envia un correo usando las credenciales de layouts/config.php ($gmailid, $gmailpassword, $gmailusername).
// Devuelve true en exito, o un string con el error en caso de falla.
function send_app_mail($toEmail, $toName, $subject, $htmlBody)
{
    global $gmailid, $gmailpassword, $gmailusername;

    if (empty($gmailid) || empty($gmailpassword)) {
        return "El envio de correo no esta configurado (falta gmailid/gmailpassword en layouts/config.php).";
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;

        $mail->Username = $gmailid;
        $mail->Password = $gmailpassword;

        $mail->setFrom($gmailid, $gmailusername ?: 'Detallia');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "No se pudo enviar el correo: " . $mail->ErrorInfo;
    }
}
