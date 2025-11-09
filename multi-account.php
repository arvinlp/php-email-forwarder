<?php
/*
 * @Author: Arvin Loripour - ViraEcosystem
 * @Date: 2025-11-08 11:37:19
 * Copyright by Arvin Loripour
 * WebSite : http://www.arvinlp.ir
 * @Last Modified by: Claude AI
 * @Last Modified time: 2025-11-09
 * @Version: 3.0 - Multi-Account Support
 */

declare(strict_types=1);

// ======= ACCOUNT CONFIGURATION =======
final class EmailAccount
{
    public function __construct(
        public readonly string $name,              // نام اکانت (برای شناسایی)
        public readonly string $imapHost,
        public readonly int $imapPort,
        public readonly string $imapUser,
        public readonly string $imapPass,
        public readonly string $smtpHost,
        public readonly int $smtpPort,
        public readonly string $smtpUser,
        public readonly string $smtpPass,
        public readonly string $smtpSecure,
        public readonly array $forwardTo,          // آدرس‌های مقصد برای این اکانت
        public readonly array $foldersToCheck = ['INBOX', 'Junk'],
        public readonly bool $processJunk = true,
        public readonly bool $enabled = true       // فعال/غیرفعال کردن این اکانت
    ) {}
}

// ======= GLOBAL CONFIGURATION =======
final class EmailForwarderConfig
{
    public function __construct(
        public readonly array $accounts = [],      // لیست اکانت‌های ایمیل
        public readonly bool $deleteAfterForward = true,
        public readonly int $deleteAfterDays = 1,
        public readonly string $logFile = '/home/YourUserName/forwarder/forwarded_emails.log',
        public readonly string $errorLogFile = '/home/YourUserName/forwarder/error_log.txt'
    ) {}
}

// ======= MAIN CLASS =======
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '/home/YourUserName/forwarder/vendor/autoload.php';

final class MultiAccountEmailForwarder
{
    private mixed $connection;
    private ?EmailAccount $currentAccount = null;

    public function __construct(
        private readonly EmailForwarderConfig $config
    ) {}

    public function run(): void
    {
        echo "=== Multi-Account Email Forwarder Started ===\n";
        echo "Total accounts: " . count($this->config->accounts) . "\n\n";

        $totalProcessed = 0;
        $totalForwarded = 0;

        foreach ($this->config->accounts as $account) {
            if (!$account->enabled) {
                echo "⊘ Skipping disabled account: {$account->name}\n\n";
                continue;
            }

            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "Processing account: {$account->name} ({$account->imapUser})\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

            try {
                $this->currentAccount = $account;
                $stats = $this->processAccount($account);
                $totalProcessed += $stats['processed'];
                $totalForwarded += $stats['forwarded'];

                echo "Account stats: {$stats['processed']} processed, {$stats['forwarded']} forwarded\n\n";
            } catch (Exception $e) {
                $this->logError($account->name, $e->getMessage());
                echo "✗ Error processing account {$account->name}: {$e->getMessage()}\n\n";
            }
        }

        echo "=== Summary ===\n";
        echo "Total emails processed: $totalProcessed\n";
        echo "Total emails forwarded: $totalForwarded\n";
        echo "=== Forwarding process completed ===\n";
    }

    private function processAccount(EmailAccount $account): array
    {
        $processed = 0;
        $forwarded = 0;

        try {
            $this->connect($account);

            foreach ($account->foldersToCheck as $folder) {
                // برای Junk فقط اگر فعال باشد پردازش می‌کنیم
                if (!$account->processJunk && in_array($folder, ['Junk', 'Spam'])) {
                    continue;
                }

                echo "  → Checking folder: $folder\n";
                $stats = $this->processFolderEmails($folder);
                $processed += $stats['processed'];
                $forwarded += $stats['forwarded'];
            }

            // حذف ایمیل‌های قدیمی که فوروارد شده‌اند
            if ($this->config->deleteAfterForward) {
                $this->deleteOldForwardedEmails($account);
            }
        } finally {
            $this->disconnect();
        }

        return ['processed' => $processed, 'forwarded' => $forwarded];
    }

    private function connect(EmailAccount $account): void
    {
        $mailbox = sprintf(
            "{%s:%d/imap/ssl}",
            $account->imapHost,
            $account->imapPort
        );

        $this->connection = imap_open(
            $mailbox,
            $account->imapUser,
            $account->imapPass
        ) or throw new Exception("Cannot connect to IMAP: " . imap_last_error());

        echo "  ✓ Connected to IMAP server\n";
    }

    private function disconnect(): void
    {
        if (isset($this->connection) && $this->connection !== false) {
            imap_close($this->connection);
        }
    }

    private function processFolderEmails(string $folder): array
    {
        $processed = 0;
        $forwarded = 0;

        // سوئیچ به پوشه مورد نظر
        if ($folder !== 'INBOX') {
            imap_reopen($this->connection, sprintf(
                "{%s:%d/imap/ssl}%s",
                $this->currentAccount->imapHost,
                $this->currentAccount->imapPort,
                $folder
            ));
        }

        // جستجوی ایمیل‌های خوانده نشده
        $emails = imap_search($this->connection, 'UNSEEN');

        if (!$emails) {
            echo "    • No unread emails in $folder\n";
            return ['processed' => 0, 'forwarded' => 0];
        }

        echo "    • Found " . count($emails) . " unread email(s) in $folder\n";

        foreach ($emails as $emailNumber) {
            if ($this->processEmail($emailNumber, $folder)) {
                $forwarded++;
            }
            $processed++;
        }

        return ['processed' => $processed, 'forwarded' => $forwarded];
    }

    private function processEmail(int $emailNumber, string $folder): bool
    {
        try {
            $overview = imap_fetch_overview($this->connection, "{$emailNumber}", 0)[0] ?? null;

            if (!$overview) {
                echo "      ✗ Failed to fetch email #$emailNumber\n";
                return false;
            }

            $emailData = $this->extractEmailData($overview, $emailNumber, $folder);
            $success = $this->forwardEmail($emailData);

            if ($success) {
                // ثبت ایمیل فوروارد شده در لاگ
                $this->logForwardedEmail($emailNumber, $emailData, $folder);
            }

            // علامت‌گذاری به عنوان خوانده شده
            imap_setflag_full($this->connection, (string)$emailNumber, "\\Seen");

            return $success;
        } catch (Exception $e) {
            $this->logError($this->currentAccount->name, "Error processing email #$emailNumber: " . $e->getMessage());
            echo "      ✗ Error processing email #$emailNumber: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function extractEmailData(object $overview, int $emailNumber, string $folder): array
    {
        // دریافت بدنه ایمیل
        $structure = imap_fetchstructure($this->connection, $emailNumber);
        $body = $this->getEmailBody($emailNumber, $structure);

        $subject = isset($overview->subject)
            ? $this->decodeSubject($overview->subject)
            : '(No Subject)';

        $from = $overview->from ?? 'Unknown';
        $date = $overview->date ?? date('Y-m-d H:i:s');

        // اضافه کردن برچسب
        $folderPrefix = in_array($folder, ['Junk', 'Spam']) ? '[JUNK] ' : '';
        $accountPrefix = "[{$this->currentAccount->name}] ";

        return [
            'subject' => $accountPrefix . $folderPrefix . 'FWD: ' . $subject,
            'from' => $from,
            'date' => $date,
            'body' => $body,
            'folder' => $folder,
            'original_subject' => $subject,
            'account_name' => $this->currentAccount->name
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
        if (!empty($structure->parts[0]->encoding ?? null)) {
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

    private function forwardEmail(array $emailData): bool
    {
        $forwardMessage = sprintf(
            "<p><b>Account:</b> %s</p>
         <p><b>From:</b> %s</p>
         <p><b>Date:</b> %s</p>
         <p><b>Folder:</b> %s</p>
         <p><b>Original Subject:</b> %s</p>
         <hr>
         %s",
            $emailData['account_name'],
            $emailData['from'],
            $emailData['date'],
            $emailData['folder'],
            $emailData['original_subject'],
            $emailData['body'] // HTML body
        );

        $allSuccess = true;

        foreach ($this->currentAccount->forwardTo as $recipient) {
            if (!$this->sendEmail($recipient, $emailData['subject'], $forwardMessage)) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    private function sendEmail(string $recipient, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->currentAccount->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->currentAccount->smtpUser;
            $mail->Password = $this->currentAccount->smtpPass;
            $mail->SMTPSecure = $this->currentAccount->smtpSecure;
            $mail->Port = $this->currentAccount->smtpPort;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->currentAccount->imapUser, "Auto Forwarder - {$this->currentAccount->name}");
            $mail->addAddress($recipient);
            $mail->Subject = $subject;
            $mail->isHTML(true); // ✅ ایمیل HTML

            $mail->Body = $body;

            $mail->send();
            echo "      ✓ Forwarded to $recipient\n";
            return true;
        } catch (Exception $e) {
            $this->logError($this->currentAccount->name, "Failed to forward to $recipient: {$mail->ErrorInfo}");
            echo "      ✗ Failed to forward to $recipient: {$mail->ErrorInfo}\n";
            return false;
        }
    }

    private function logForwardedEmail(int $emailNumber, array $emailData, string $folder): void
    {
        $logEntry = sprintf(
            "%s|%s|%d|%s|%s|%s\n",
            date('Y-m-d H:i:s'),
            $this->currentAccount->name,
            $emailNumber,
            $folder,
            $emailData['original_subject'],
            $emailData['from']
        );

        file_put_contents($this->config->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function logError(string $accountName, string $error): void
    {
        $logEntry = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $accountName,
            $error
        );

        file_put_contents($this->config->errorLogFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function deleteOldForwardedEmails(EmailAccount $account): void
    {
        if (!file_exists($this->config->logFile)) {
            return;
        }

        $logContent = file_get_contents($this->config->logFile);
        $logLines = explode("\n", trim($logContent));
        $newLogLines = [];
        $deletedCount = 0;

        $cutoffDate = strtotime("-{$this->config->deleteAfterDays} days");

        foreach ($logLines as $line) {
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) < 4) continue;

            [$timestamp, $accountName, $emailNumber, $folder] = $parts;

            // فقط ایمیل‌های این اکانت را پردازش کن
            if ($accountName !== $account->name) {
                $newLogLines[] = $line;
                continue;
            }

            $forwardedTime = strtotime($timestamp);

            // اگر زمان فوروارد بیشتر از حد تعیین شده گذشته باشد
            if ($forwardedTime < $cutoffDate) {
                // سوئیچ به پوشه مربوطه
                $mailboxPath = sprintf(
                    "{%s:%d/imap/ssl}%s",
                    $account->imapHost,
                    $account->imapPort,
                    $folder
                );

                imap_reopen($this->connection, $mailboxPath);

                // حذف ایمیل
                if (imap_delete($this->connection, (string)$emailNumber)) {
                    $deletedCount++;
                } else {
                    // اگر حذف موفق نبود، در لاگ نگه داریم
                    $newLogLines[] = $line;
                }
            } else {
                // ایمیل هنوز در بازه زمانی حذف نیست
                $newLogLines[] = $line;
            }
        }

        // اعمال تغییرات حذف
        imap_expunge($this->connection);

        if ($deletedCount > 0) {
            echo "  ✓ Deleted $deletedCount old email(s)\n";
        }

        // به‌روزرسانی فایل لاگ
        file_put_contents($this->config->logFile, implode("\n", $newLogLines) . "\n", LOCK_EX);
    }
}

// ======= EXECUTION =======
try {
    // تعریف اکانت‌های ایمیل
    $accounts = [
        new EmailAccount(
            name: 'Example-Account',
            imapHost: 'mail.example.com',
            imapPort: 993,
            imapUser: 'user@example.com',
            imapPass: 'password123',
            smtpHost: 'mail.example.com',
            smtpPort: 465,
            smtpUser: 'user@example.com',
            smtpPass: 'password123',
            smtpSecure: 'ssl',
            forwardTo: ['destination@gmail.com', 'backup@example.com'],
            foldersToCheck: ['INBOX'],
            processJunk: false,
            enabled: false // غیرفعال برای مثال
        ),

        // اکانت دوم - نمونه
        new EmailAccount(
            name: 'Example-Account',
            imapHost: 'mail.example.com',
            imapPort: 993,
            imapUser: 'user@example.com',
            imapPass: 'password123',
            smtpHost: 'mail.example.com',
            smtpPort: 465,
            smtpUser: 'user@example.com',
            smtpPass: 'password123',
            smtpSecure: 'ssl',
            forwardTo: ['destination@gmail.com', 'backup@example.com'],
            foldersToCheck: ['INBOX'],
            processJunk: false,
            enabled: false // غیرفعال برای مثال
        ),

        // می‌توانید اکانت‌های بیشتری اضافه کنید...
    ];

    // تنظیمات کلی
    $config = new EmailForwarderConfig(
        accounts: $accounts,
        deleteAfterForward: true,
        deleteAfterDays: 1,
        logFile: '/home/YourUserName/forwarder/forwarded_emails.log',
        errorLogFile: '/home/YourUserName/forwarder/error_log.txt'
    );

    $forwarder = new MultiAccountEmailForwarder($config);
    $forwarder->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
