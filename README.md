# Multi-Account Email Forwarder

**Version:** 3.0 | **PHP:** 8.0+ | **Author:** Arvin Loripour - ViraEcosystem

---

## 🇬🇧 English Documentation

### 📋 Overview

A powerful PHP 8+ email forwarding system that supports:
- ✅ Multiple email accounts management
- ✅ Automatic forwarding from INBOX and Junk/Spam folders
- ✅ Auto-deletion of forwarded emails after specified days
- ✅ Complete logging system
- ✅ Individual settings per account
- ✅ Error handling and recovery

### 🚀 Features

#### Multi-Account Support
- Manage unlimited email accounts from single script
- Each account has independent configuration
- Enable/disable accounts individually
- Multiple forward destinations per account

#### Smart Forwarding
- Forward from INBOX, Junk, and Spam folders
- Mark emails as read after forwarding
- Add account identifier to subject line
- Preserve original email metadata

#### Auto-Cleanup
- Automatically delete forwarded emails after X days
- Safe deletion with verification
- Log-based tracking system
- Separate cleanup per account

#### Comprehensive Logging
- Forwarded emails log with full details
- Separate error log file
- Timestamp and account tracking
- Easy troubleshooting

### 📦 Requirements

```bash
PHP 8.0 or higher
PHPMailer library
IMAP extension enabled
OpenSSL support
```

### 🔧 Installation

#### Step 1: Install Dependencies

```bash
cd /home/YourUserName/forwarder
composer require phpmailer/phpmailer
```

#### Step 2: Enable PHP IMAP Extension

**Ubuntu/Debian:**
```bash
sudo apt-get install php-imap
sudo phpenmod imap
sudo systemctl restart apache2  # or php-fpm
```

**CentOS/RHEL:**
```bash
sudo yum install php-imap
sudo systemctl restart httpd
```

#### Step 3: Create Required Directories

```bash
mkdir -p /home/YourUserName/forwarder
chmod 755 /home/YourUserName/forwarder
touch /home/YourUserName/forwarder/forwarded_emails.log
touch /home/YourUserName/forwarder/error_log.txt
chmod 644 /home/YourUserName/forwarder/*.log
```

### ⚙️ Configuration

Edit the execution section at the bottom of the script:

```php
$accounts = [
    new EmailAccount(
        name: 'Account-Name',           // Friendly name for identification
        imapHost: 'mail.domain.com',    // IMAP server
        imapPort: 993,                  // IMAP port (usually 993 for SSL)
        imapUser: 'email@domain.com',   // Email address
        imapPass: 'your-password',      // Email password
        smtpHost: 'mail.domain.com',    // SMTP server
        smtpPort: 465,                  // SMTP port (465 for SSL, 587 for TLS)
        smtpUser: 'email@domain.com',   // SMTP username
        smtpPass: 'your-password',      // SMTP password
        smtpSecure: 'ssl',              // 'ssl' or 'tls'
        forwardTo: [                    // Destination email(s)
            'destination1@gmail.com',
            'destination2@gmail.com'
        ],
        foldersToCheck: ['INBOX', 'Junk'],  // Folders to monitor
        processJunk: true,              // Process Junk/Spam folders
        enabled: true                   // Enable this account
    ),
    // Add more accounts...
];

$config = new EmailForwarderConfig(
    accounts: $accounts,
    deleteAfterForward: true,           // Enable auto-deletion
    deleteAfterDays: 1,                 // Delete after X days
    logFile: '/home/YourUserName/forwarder/forwarded_emails.log',
    errorLogFile: '/home/YourUserName/forwarder/error_log.txt'
);
```

### 🏃 Usage

#### Manual Execution

```bash
php /home/YourUserName/forwarder/email_forwarder.php
```

#### Automated Execution (Cron Job)

Add to crontab for automatic execution:

```bash
# Edit crontab
crontab -e

# Run every hour
0 * * * * /usr/bin/php /home/YourUserName/forwarder/email_forwarder.php >> /home/YourUserName/forwarder/cron.log 2>&1

# Run every 15 minutes
*/15 * * * * /usr/bin/php /home/YourUserName/forwarder/email_forwarder.php >> /home/YourUserName/forwarder/cron.log 2>&1

# Run every 5 minutes (more frequent)
*/5 * * * * /usr/bin/php /home/YourUserName/forwarder/email_forwarder.php >> /home/YourUserName/forwarder/cron.log 2>&1
```

### 📊 Output Example

```
=== Multi-Account Email Forwarder Started ===
Total accounts: 2

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Processing account: Company-Main (info@company.com)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ Connected to IMAP server
  → Checking folder: INBOX
    • Found 5 unread email(s) in INBOX
      ✓ Forwarded to admin@gmail.com
      ✓ Forwarded to backup@gmail.com
      ...
  → Checking folder: Junk
    • Found 2 unread email(s) in Junk
      ✓ Forwarded to admin@gmail.com
  ✓ Deleted 3 old email(s)
Account stats: 7 processed, 7 forwarded

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Processing account: Support-Email (support@company.com)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ Connected to IMAP server
  → Checking folder: INBOX
    • No unread emails in INBOX

=== Summary ===
Total emails processed: 7
Total emails forwarded: 7
=== Forwarding process completed ===
```

### 📝 Log Files

#### Forwarded Emails Log
Format: `timestamp|account_name|email_number|folder|subject|from`

```
2025-11-09 10:30:15|Company-Main|123|INBOX|Important Message|client@example.com
2025-11-09 10:31:20|Company-Main|124|Junk|Spam Email|spam@test.com
2025-11-09 10:32:10|Support-Email|45|INBOX|Support Request|user@gmail.com
```

#### Error Log
Format: `[timestamp] [account_name] error_message`

```
[2025-11-09 10:35:20] [Company-Main] Failed to forward to admin@gmail.com: SMTP Error
[2025-11-09 10:40:15] [Support-Email] Cannot connect to IMAP: Authentication failed
```

### 🔒 Security Best Practices

1. **Use App-Specific Passwords** (especially for Gmail)
   - Don't use your main email password
   - Generate app passwords in your email provider settings

2. **File Permissions**
   ```bash
   chmod 600 email_forwarder.php  # Only owner can read/write
   chmod 644 *.log                # Logs readable by owner
   ```

3. **Secure Credentials**
   - Consider using environment variables
   - Never commit passwords to version control
   - Use `.gitignore` for config files

4. **SSL/TLS**
   - Always use encrypted connections (SSL/TLS)
   - Verify SSL certificates

### 🐛 Troubleshooting

#### IMAP Connection Failed
```
Error: Cannot connect to IMAP: Authentication failed
```
**Solutions:**
- Verify credentials are correct
- Check if IMAP is enabled in email settings
- Try using app-specific password
- Verify firewall allows port 993

#### SMTP Send Failed
```
Error: Failed to forward: SMTP Error: Could not authenticate
```
**Solutions:**
- Check SMTP credentials
- Verify SMTP port (465 for SSL, 587 for TLS)
- Enable "Less secure apps" or use app password
- Check SMTP server allows your IP

#### PHP IMAP Extension Missing
```
Fatal error: Call to undefined function imap_open()
```
**Solution:**
```bash
sudo apt-get install php-imap
sudo phpenmod imap
sudo systemctl restart apache2
```

#### Permission Denied on Log Files
```
Warning: file_put_contents(): failed to open stream: Permission denied
```
**Solution:**
```bash
sudo chown www-data:www-data /home/YourUserName/forwarder/*.log
chmod 644 /home/YourUserName/forwarder/*.log
```

### 📧 Email Subject Format

Forwarded emails will have subjects in this format:
```
[Account-Name] FWD: Original Subject
[Account-Name] [JUNK] FWD: Spam Subject  # For junk emails
```

### 🔄 Update & Maintenance

**Check Logs Regularly:**
```bash
tail -f /home/YourUserName/forwarder/forwarded_emails.log
tail -f /home/YourUserName/forwarder/error_log.txt
```

**Clean Old Logs:**
```bash
# Keep last 1000 lines
tail -n 1000 /home/YourUserName/forwarder/forwarded_emails.log > temp.log
mv temp.log /home/YourUserName/forwarder/forwarded_emails.log
```

### 📄 License

Copyright by Arvin Loripour - ViraEcosystem
Website: http://www.arvinlp.ir

---

## 🇮🇷 مستندات فارسی

### 📋 معرفی

سیستم قدرتمند فوروارد ایمیل با PHP 8+ که از موارد زیر پشتیبانی می‌کند:
- ✅ مدیریت چندین اکانت ایمیل
- ✅ فوروارد خودکار از INBOX و پوشه‌های Junk/Spam
- ✅ حذف خودکار ایمیل‌های فوروارد شده پس از مدت مشخص
- ✅ سیستم لاگ‌گذاری کامل
- ✅ تنظیمات مستقل برای هر اکانت
- ✅ مدیریت خطا و بازیابی

### 🚀 امکانات

#### پشتیبانی از چند اکانت
- مدیریت تعداد نامحدود اکانت ایمیل از یک اسکریپت
- تنظیمات مستقل برای هر اکانت
- فعال/غیرفعال کردن جداگانه اکانت‌ها
- چندین مقصد فوروارد برای هر اکانت

#### فوروارد هوشمند
- فوروارد از پوشه‌های INBOX، Junk و Spam
- علامت‌گذاری ایمیل‌ها به عنوان خوانده شده پس از فوروارد
- افزودن شناسه اکانت به موضوع ایمیل
- حفظ اطلاعات اصلی ایمیل

#### پاکسازی خودکار
- حذف خودکار ایمیل‌های فوروارد شده پس از X روز
- حذف امن با تأیید
- سیستم ردیابی مبتنی بر لاگ
- پاکسازی جداگانه برای هر اکانت

#### لاگ‌گذاری جامع
- لاگ ایمیل‌های فوروارد شده با جزئیات کامل
- فایل لاگ جداگانه برای خطاها
- ردیابی زمان و اکانت
- عیب‌یابی آسان

### 📦 پیش‌نیازها

```bash
PHP 8.0 یا بالاتر
کتابخانه PHPMailer
فعال بودن افزونه IMAP
پشتیبانی OpenSSL
```

### 🔧 نصب

#### مرحله 1: نصب وابستگی‌ها

```bash
cd /home/YourUserName/forwarder
composer require phpmailer/phpmailer
```

#### مرحله 2: فعال‌سازی افزونه IMAP

**اوبونتو/دبیان:**
```bash
sudo apt-get install php-imap
sudo phpenmod imap
sudo systemctl restart apache2  # یا php-fpm
```

**سنت‌اواس/ردهت:**
```bash
sudo yum install php-imap
sudo systemctl restart httpd
```

#### مرحله 3: ایجاد دایرکتوری‌های مورد نیاز

```bash
mkdir -p /home/YourUserName/forwarder
chmod 755 /home/YourUserName/forwarder
touch /home/YourUserName/forwarder/forwarded_emails.log
touch /home/YourUserName/forwarder/error_log.txt
chmod 644 /home/YourUserName/forwarder/*.log
```

### ⚙️ پیکربندی

قسمت اجرا در انتهای اسکریپت را ویرایش کنید:

```php
$accounts = [
    new EmailAccount(
        name: 'نام-اکانت',              // نام دلخواه برای شناسایی
        imapHost: 'mail.domain.com',    // سرور IMAP
        imapPort: 993,                  // پورت IMAP (معمولاً 993 برای SSL)
        imapUser: 'email@domain.com',   // آدرس ایمیل
        imapPass: 'رمز-عبور',           // رمز عبور ایمیل
        smtpHost: 'mail.domain.com',    // سرور SMTP
        smtpPort: 465,                  // پورت SMTP (465 برای SSL، 587 برای TLS)
        smtpUser: 'email@domain.com',   // نام کاربری SMTP
        smtpPass: 'رمز-عبور',           // رمز عبور SMTP
        smtpSecure: 'ssl',              // 'ssl' یا 'tls'
        forwardTo: [                    // ایمیل(های) مقصد
            'destination1@gmail.com',
            'destination2@gmail.com'
        ],
        foldersToCheck: ['INBOX', 'Junk'],  // پوشه‌های مورد نظر
        processJunk: true,              // پردازش پوشه‌های Junk/Spam
        enabled: true                   // فعال کردن این اکانت
    ),
    // اکانت‌های بیشتر...
];

$config = new EmailForwarderConfig(
    accounts: $accounts,
    deleteAfterForward: true,           // فعال‌سازی حذف خودکار
    deleteAfterDays: 1,                 // حذف پس از X روز
    logFile: '/home/YourUserName/forwarder/forwarded_emails.log',
    errorLogFile: '/home/YourUserName/forwarder/error_log.txt'
);
```

### 🏃 استفاده

#### اجرای دستی

```bash
php /home/YourUserName/forwarder/email_forwarder.php
```

#### اجرای خودکار (Cron Job)

برای اجرای خودکار به crontab اضافه کنید:

```bash
# ویرایش crontab
crontab -e

# اجرا هر ساعت
0 * * * * /usr/bin/php /home/YourUserName/forwarder/email_forwarder.php >> /home/YourUserName/forwarder/cron.log 2>&1

# اجرا هر 15 دقیقه
*/15 * * * * /usr/bin/php /home/YourUserName/forwarder/email_forwarder.php >> /home/YourUserName/forwarder/cron.log 2>&1

# اجرا هر 5 دقیقه (مکرر‌تر)
*/5 * * * * /usr/bin/php /home/YourUserName/forwarder/email_forwarder.php >> /home/YourUserName/forwarder/cron.log 2>&1
```

### 📊 نمونه خروجی

```
=== Multi-Account Email Forwarder Started ===
Total accounts: 2

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Processing account: شرکت-اصلی (info@company.com)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  ✓ Connected to IMAP server
  → Checking folder: INBOX
    • Found 5 unread email(s) in INBOX
      ✓ Forwarded to admin@gmail.com
      ✓ Forwarded to backup@gmail.com
      ...
  → Checking folder: Junk
    • Found 2 unread email(s) in Junk
      ✓ Forwarded to admin@gmail.com
  ✓ Deleted 3 old email(s)
Account stats: 7 processed, 7 forwarded

=== Summary ===
Total emails processed: 7
Total emails forwarded: 7
=== Forwarding process completed ===
```

### 📝 فایل‌های لاگ

#### لاگ ایمیل‌های فوروارد شده
فرمت: `زمان|نام_اکانت|شماره_ایمیل|پوشه|موضوع|از`

```
2025-11-09 10:30:15|شرکت-اصلی|123|INBOX|پیام مهم|client@example.com
2025-11-09 10:31:20|شرکت-اصلی|124|Junk|ایمیل اسپم|spam@test.com
2025-11-09 10:32:10|پشتیبانی|45|INBOX|درخواست پشتیبانی|user@gmail.com
```

#### لاگ خطاها
فرمت: `[زمان] [نام_اکانت] پیام_خطا`

```
[2025-11-09 10:35:20] [شرکت-اصلی] خطا در ارسال به admin@gmail.com: خطای SMTP
[2025-11-09 10:40:15] [پشتیبانی] عدم اتصال به IMAP: احراز هویت ناموفق
```

### 🔒 نکات امنیتی

1. **استفاده از رمز عبور اختصاصی برنامه** (به خصوص برای Gmail)
   - از رمز اصلی ایمیل خود استفاده نکنید
   - رمز عبور برنامه را از تنظیمات ایمیل خود ایجاد کنید

2. **دسترسی‌های فایل**
   ```bash
   chmod 600 email_forwarder.php  # فقط مالک می‌تواند بخواند/بنویسد
   chmod 644 *.log                # لاگ‌ها توسط مالک قابل خواندن
   ```

3. **امن‌سازی اطلاعات ورود**
   - استفاده از متغیرهای محیطی را در نظر بگیرید
   - هرگز رمزها را در version control قرار ندهید
   - از `.gitignore` برای فایل‌های پیکربندی استفاده کنید

4. **SSL/TLS**
   - همیشه از اتصالات رمزنگاری شده استفاده کنید
   - گواهی‌های SSL را تأیید کنید

### 🐛 عیب‌یابی

#### خطای اتصال IMAP
```
Error: Cannot connect to IMAP: Authentication failed
```
**راه‌حل‌ها:**
- اطلاعات ورود را بررسی کنید
- بررسی کنید که IMAP در تنظیمات ایمیل فعال باشد
- از رمز عبور اختصاصی برنامه استفاده کنید
- فایروال باید پورت 993 را مجاز کند

#### خطای ارسال SMTP
```
Error: Failed to forward: SMTP Error: Could not authenticate
```
**راه‌حل‌ها:**
- اطلاعات SMTP را بررسی کنید
- پورت SMTP را تأیید کنید (465 برای SSL، 587 برای TLS)
- "برنامه‌های کم امنیت" را فعال یا از رمز برنامه استفاده کنید
- بررسی کنید سرور SMTP IP شما را مجاز می‌کند

#### افزونه IMAP نصب نیست
```
Fatal error: Call to undefined function imap_open()
```
**راه‌حل:**
```bash
sudo apt-get install php-imap
sudo phpenmod imap
sudo systemctl restart apache2
```

#### خطای دسترسی به فایل‌های لاگ
```
Warning: file_put_contents(): failed to open stream: Permission denied
```
**راه‌حل:**
```bash
sudo chown www-data:www-data /home/YourUserName/forwarder/*.log
chmod 644 /home/YourUserName/forwarder/*.log
```

### 📧 فرمت موضوع ایمیل

ایمیل‌های فوروارد شده این فرمت موضوع را خواهند داشت:
```
[نام-اکانت] FWD: موضوع اصلی
[نام-اکانت] [JUNK] FWD: موضوع اسپم  # برای ایمیل‌های جانک
```

### 🔄 به‌روزرسانی و نگهداری

**بررسی منظم لاگ‌ها:**
```bash
tail -f /home/YourUserName/forwarder/forwarded_emails.log
tail -f /home/YourUserName/forwarder/error_log.txt
```

**پاکسازی لاگ‌های قدیمی:**
```bash
# نگه‌داری 1000 خط آخر
tail -n 1000 /home/YourUserName/forwarder/forwarded_emails.log > temp.log
mv temp.log /home/YourUserName/forwarder/forwarded_emails.log
```

### 📞 پشتیبانی

**Website:** http://www.arvinlp.ir  
**Email:** arvinlp91@gmail.com

### 📄 مجوز

Copyright by Arvin Loripour - ViraEcosystem  
تمامی حقوق محفوظ است.

---

## 🎯 Quick Start Guide / راهنمای سریع

### English
1. Install PHP 8+ and enable IMAP extension
2. Install PHPMailer via Composer
3. Configure your email accounts in the script
4. Test manually: `php email_forwarder.php`
5. Add to cron for automation

### فارسی
1. نصب PHP 8+ و فعال‌سازی افزونه IMAP
2. نصب PHPMailer از طریق Composer
3. پیکربندی اکانت‌های ایمیل در اسکریپت
4. تست دستی: `php email_forwarder.php`
5. افزودن به cron برای اجرای خودکار

---

**Last Updated:** November 9, 2025  
**Version:** 3.