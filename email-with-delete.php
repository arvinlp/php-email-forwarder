<?php
/*
 * @Author: Arvin Loripour - ViraEcosystem
 * @Date: 2025-11-08 11:37:19
 * Copyright by Arvin Loripour
 * WebSite : http://www.arvinlp.ir
 * @Last Modified by: Claude AI
 * @Last Modified time: 2025-11-09
 * @Version: 2.0 - PHP 8 Optimized with Junk Support
 */

declare(strict_types=1);

// ======= CONFIGURATION =======
final class EmailForwarderConfig
{
    public function __construct(
        public readonly string $imapHost = 'MailServerDomain',
        public readonly int $imapPort = 993,
        public readonly string $imapUser = 'YourEmail@domain.ltd',
        public readonly string $imapPass = 'YourEmailPassword',
        public readonly array $forwardTo = ['YourForwardEmail@domain.ltd'],
        public readonly string $smtpHost = 'MailServerDomain',
        public readonly int $smtpPort = 465,
        public readonly string $smtpUser = 'YourEmail@domain.ltd',
        public readonly string $smtpPass = 'YourEmailPassword',
        public readonly string $smtpSecure = 'ssl',
        public readonly bool $processJunk = true, // فعال/غیرفعال کردن پردازش Junk
        public readonly array $foldersToCheck = ['INBOX', 'Junk', 'Spam'], // پوشه‌هایی که چک می‌شوند
        public readonly bool $deleteAfterForward = true, // حذف ایمیل پس از فوروارد
        public readonly int $deleteAfterDays = 1, // حذف پس از چند روز (پیش‌فرض: 1 روز)
        public readonly string $logFile = '/home/manize/forwarder/forwarded_emails.log' // فایل لاگ ایمیل‌های فوروارد شده
    ) {}
}

// ======= MAIN CLASS =======
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/home/manize/forwarder/vendor/autoload.php';

final class EmailForwarder
{
    private mixed $connection;

    public function __construct(
        private readonly EmailForwarderConfig $config
    ) {}

    public function run(): void
    {
        try {
            $this->connect();

            foreach ($this->config->foldersToCheck as $folder) {
                // برای Junk فقط اگر فعال باشد پردازش می‌کنیم
                if (!$this->config->processJunk && in_array($folder, ['Junk', 'Spam'])) {
                    continue;
                }

                echo "Checking folder: $folder\n";
                $this->processFolderEmails($folder);
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $mailbox = sprintf(
            "{%s:%d/imap/ssl}",
            $this->config->imapHost,
            $this->config->imapPort
        );

        $this->connection = imap_open(
            $mailbox,
            $this->config->imapUser,
            $this->config->imapPass
        ) or throw new Exception("Cannot connect to IMAP: " . imap_last_error());

        echo "Connected to IMAP server successfully.\n";
    }

    private function disconnect(): void
    {
        if (isset($this->connection) && $this->connection !== false) {
            imap_close($this->connection);
            echo "Disconnected from IMAP server.\n";
        }
    }

    private function processFolderEmails(string $folder): void
    {
        // سوئیچ به پوشه مورد نظر
        if ($folder !== 'INBOX') {
            imap_reopen($this->connection, sprintf(
                "{%s:%d/imap/ssl}%s",
                $this->config->imapHost,
                $this->config->imapPort,
                $folder
            ));
        }

        // جستجوی ایمیل‌های خوانده نشده
        $emails = imap_search($this->connection, 'UNSEEN');

        if (!$emails) {
            echo "No unread emails in $folder.\n";
            return;
        }

        echo sprintf("Found %d unread email(s) in %s.\n", count($emails), $folder);

        foreach ($emails as $emailNumber) {
            $this->processEmail($emailNumber, $folder);
        }
    }

    private function processEmail(int $emailNumber, string $folder): void
    {
        try {
            $overview = imap_fetch_overview($this->connection, "{$emailNumber}", 0)[0] ?? null;

            if (!$overview) {
                echo "Failed to fetch email #$emailNumber\n";
                return;
            }

            $emailData = $this->extractEmailData($overview, $emailNumber, $folder);
            $this->forwardEmail($emailData);

            // علامت‌گذاری به عنوان خوانده شده
            imap_setflag_full($this->connection, (string)$emailNumber, "\\Seen");
        } catch (Exception $e) {
            echo "Error processing email #$emailNumber: " . $e->getMessage() . "\n";
        }
    }

    private function extractEmailData(object $overview, int $emailNumber, string $folder): array
    {
        // دریافت بدنه ایمیل (HTML و Plain Text)
        $structure = imap_fetchstructure($this->connection, $emailNumber);
        $body = $this->getEmailBody($emailNumber, $structure);

        $subject = isset($overview->subject)
            ? $this->decodeSubject($overview->subject)
            : '(No Subject)';

        $from = $overview->from ?? 'Unknown';
        $date = $overview->date ?? date('Y-m-d H:i:s');

        // اضافه کردن برچسب Junk به موضوع
        $folderPrefix = in_array($folder, ['Junk', 'Spam']) ? '[JUNK] ' : '';

        return [
            'subject' => $folderPrefix . 'FWD: ' . $subject,
            'from' => $from,
            'date' => $date,
            'body' => $body,
            'folder' => $folder,
            'original_subject' => $subject
        ];
    }

    private function getEmailBody(int $emailNumber, object $structure): string
    {
        $body = '';

        // تلاش برای دریافت HTML
        $htmlBody = imap_fetchbody($this->connection, $emailNumber, '1.2');
        if (empty($htmlBody)) {
            $htmlBody = imap_fetchbody($this->connection, $emailNumber, '1');
        }

        // تلاش برای دریافت Plain Text
        $textBody = imap_fetchbody($this->connection, $emailNumber, '1.1');
        if (empty($textBody)) {
            $textBody = imap_fetchbody($this->connection, $emailNumber, '1');
        }

        // ترجیح HTML به Text
        $body = !empty($htmlBody) ? $htmlBody : $textBody;

        // Decode if needed
        if (!empty($structure->parts[0]->encoding)) {
            $body = $this->decodeBody($body, $structure->parts[0]->encoding);
        }

        return $body ?: 'Empty message body';
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            1 => imap_8bit($body),
            2 => imap_binary($body),
            3 => imap_base64($body),
            4 => quoted_printable_decode($body),
            default => $body
        };
    }

    private function decodeSubject(string $subject): string
    {
        $decoded = imap_mime_header_decode($subject);
        $result = '';

        foreach ($decoded as $part) {
            $charset = ($part->charset === 'default') ? 'UTF-8' : $part->charset;
            $result .= mb_convert_encoding($part->text, 'UTF-8', $charset);
        }

        return $result;
    }

    private function forwardEmail(array $emailData): void
    {
        $forwardMessage = sprintf(
            "=== Forwarded Message ===\n" .
                "From: %s\n" .
                "Date: %s\n" .
                "Folder: %s\n" .
                "Original Subject: %s\n" .
                "========================\n\n%s",
            $emailData['from'],
            $emailData['date'],
            $emailData['folder'],
            $emailData['original_subject'],
            $emailData['body']
        );

        foreach ($this->config->forwardTo as $recipient) {
            $this->sendEmail($recipient, $emailData['subject'], $forwardMessage);
        }
    }

    private function sendEmail(string $recipient, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);

        try {
            // تنظیمات SMTP
            $mail->isSMTP();
            $mail->Host = $this->config->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->config->smtpUser;
            $mail->Password = $this->config->smtpPass;
            $mail->SMTPSecure = $this->config->smtpSecure;
            $mail->Port = $this->config->smtpPort;
            $mail->CharSet = 'UTF-8';

            // تنظیمات ایمیل
            $mail->setFrom($this->config->imapUser, 'Auto Email Forwarder');
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            $mail->send();
            echo "✓ Forwarded to $recipient: $subject\n";
        } catch (Exception $e) {
            echo "✗ Failed to forward to $recipient: {$mail->ErrorInfo}\n";
        }
    }
}

// ======= EXECUTION =======
try {
    $config = new EmailForwarderConfig(
        processJunk: true, // true = فوروارد Junk هم انجام شود
        foldersToCheck: ['INBOX', 'Junk'] // پوشه‌های مورد نظر
    );

    $forwarder = new EmailForwarder($config);
    $forwarder->run();

    echo "\n=== Forwarding process completed ===\n";
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
