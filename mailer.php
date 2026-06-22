<?php
// mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

function stuurAutomatischeEmail($toEmail, $subject, $body) {
    global $pdo;

    // Haal de live SMTP instellingen op uit de database
    $settings = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $host   = $settings['smtp_host'] ?? '';
    $port   = $settings['smtp_port'] ?? '465';
    $user   = $settings['smtp_user'] ?? '';
    $pass   = $settings['smtp_pass'] ?? '';
    $secure = $settings['smtp_secure'] ?? 'ssl';
    $company= $settings['company_name'] ?? 'MST Logistics';

    if (empty($host) || empty($user) || empty($pass)) {
        // Als er geen SMTP is ingesteld, geef een foutmelding terug
        return "SMTP-instellingen zijn incompleet in de database.";
    }

    $mail = new PHPMailer(true);

    try {
        // Server instellingen
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$port;
        $mail->CharSet    = 'UTF-8';

        // Afzender & Ontvanger
        $mail->setFrom($user, $company);
        $mail->addAddress($toEmail);
        $mail->addReplyTo($user, $company);

        // Inhoud
        $mail->isHTML(false); // We sturen pure tekst, net als je mailto link
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true; // Succes!
    } catch (Exception $e) {
        return "E-mail kon niet worden verzonden. Mailer Error: {$mail->ErrorInfo}";
    }
}