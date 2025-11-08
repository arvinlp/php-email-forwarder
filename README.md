<div align="right">

[🇮🇷 فارسی](README.fa.md) | [🇬🇧 English](README.md)

</div>

# PHP Email Forwarder

A lightweight PHP script that automatically forwards incoming emails from an IMAP mailbox to one or more target email addresses using **PHPMailer** and **SMTP**.

> ✅ Works even when cPanel or hosting providers block standard forwarders or sendmail.  
> Ideal for shared hosting environments or restricted mail servers.

---

## 🚀 Features

- Connects to any IMAP mailbox (e.g., cPanel, Gmail, Zoho)
- Reads and forwards unread messages automatically
- Uses PHPMailer with secure SMTP authentication
- Supports multiple recipients
- Can run on shared hosting or VPS
- Easily scheduled via **cron job**

---

## 🧩 Requirements

- PHP ≥ 7.4 with IMAP and OpenSSL extensions enabled  
- Composer  
- Access to your mail server via IMAP (e.g., `mail.yourdomain.com:993`)  
- SMTP credentials (for Gmail, Zoho, or your domain)

---

## ⚙️ Installation

1. Clone or download the project:
```bash
   git clone https://github.com/arvinlp/php-email-forwarder.git
   cd php-email-forwarder
```
2. Install Dependency:
```bash
   composer install
```
3. Setup Config:
In File email.php Change this parameters:
```php
   $imap_host, $imap_user, $imap_pass
   $smtp_host, $smtp_user, $smtp_pass
   $forward_to
```
4. Manulay test:
```bash
   php imap_forwarder.php
```