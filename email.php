<?php
/*
 * @Author: Arvin Loripour - ViraEcosystem 
 * @Date: 2025-11-08 11:37:19 
 * Copyright by Arvin Loripour 
 * WebSite : http://www.arvinlp.ir 
 * @Last Modified by:   Arvin.Loripour 
 * @Last Modified time: 2025-11-08 11:37:19 
 */

// ======= CONFIGURATION =======
$imap_host = 'mail.yourdomain.com';
$imap_port = 993;
$imap_user = 'info@yourdomain.com';
$imap_pass = 'YOUR_PASSWORD';

$forward_to = [
    'target1@gmail.com',
    'target2@example.com'
];

// SMTP settings (use Gmail, Zoho, or your domain)
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_user = 'yourgmail@gmail.com';
$smtp_pass = 'YOUR_APP_PASSWORD'; // use app password for Gmail
$smtp_secure = 'tls'; // or 'ssl'
// =============================================

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/home/username/vendor/autoload.php'; // adjust path if using Composer

// Connect to IMAP
$mailbox = "{" . $imap_host . ":" . $imap_port . "/imap/ssl}INBOX";
$inbox = imap_open($mailbox, $imap_user, $imap_pass) or die("Cannot connect: " . imap_last_error());

// Search unread emails
$emails = imap_search($inbox, 'UNSEEN');

if ($emails) {
    foreach ($emails as $email_number) {
        $overview = imap_fetch_overview($inbox, $email_number, 0)[0];
        $message = imap_fetchbody($inbox, $email_number, 1);

        $subject = 'FWD: ' . (isset($overview->subject) ? $overview->subject : '(No Subject)');
        $from = $overview->from;
        $body = "Forwarded message from $from\n\n" . $message;

        // Send via SMTP
        foreach ($forward_to as $target) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_user;
                $mail->Password = $smtp_pass;
                $mail->SMTPSecure = $smtp_secure;
                $mail->Port = $smtp_port;

                $mail->setFrom($imap_user, 'Auto Forwarder');
                $mail->addAddress($target);
                $mail->Subject = $subject;
                $mail->Body = $body;

                $mail->send();
                echo "Forwarded to $target: $subject\n";
            } catch (Exception $e) {
                echo "Mailer Error to $target: {$mail->ErrorInfo}\n";
            }
        }

        // mark email as seen
        imap_setflag_full($inbox, $email_number, "\\Seen");
    }
} else {
    echo "No new messages.\n";
}

imap_close($inbox);
