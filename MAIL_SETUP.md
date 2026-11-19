# Gmail OTP Setup

Happiva sends signup codes through Gmail SMTP using PHPMailer. Your Gmail address is configured as the default admin/sender: `dimejirotimi5@gmail.com`. Gmail's host, port, username, TLS mode, and Happiva sender name are already defaults in the app.

## 1. Create A Gmail App Password

Do not use your normal Gmail password and do not paste any password into this chat. In your Google Account, enable 2-Step Verification, then create an App Password for Happiva. Copy the generated 16-character app password; when you put it in the server config, remove any spaces Google displays between groups.

## 2. Install The Mail Library Once

Composer is only used to download PHPMailer, which the app imports to send email. It is not a service that needs to keep running.

Open PowerShell in `C:\xampp\htdocs\Happiva` and run:

```powershell
composer install
```

If PowerShell says `composer` is not recognized, install Composer for Windows once, then run that command again.

## 3. Add Gmail Settings To XAMPP

Open `C:\xampp\apache\conf\extra\httpd-xampp.conf` as administrator. Find the existing `<IfModule env_module>` block near the top, and add this line with the other `SetEnv` lines, just before `</IfModule>`. Replace the placeholder on your computer with the Google App Password; do not put it in a project file or commit it to Git.

```apache
SetEnv SMTP_PASSWORD "PASTE_YOUR_GOOGLE_APP_PASSWORD_HERE"
```

Save the file and restart Apache from the XAMPP Control Panel. No database settings need to change for local XAMPP.

## 4. Test It

Open `http://localhost/Happiva/register.php` and complete signup with an email address that is not already registered in Happiva. The code should arrive in that inbox. If you see the mail-configuration error, check the Apache error log and verify the app password, then restart Apache again.

## InfinityFree Deployment

1. On your computer, run `composer install` in `C:\xampp\htdocs\Happiva`. This creates `vendor/` locally; InfinityFree does not need Composer running.
2. Create a MySQL database in the InfinityFree control panel. Note the exact MySQL host, database name, username, and password, including any account prefixes.
3. Open phpMyAdmin from InfinityFree, select the newly created database, then import `database.sql`. The schema is portable and uses the database selected in phpMyAdmin.
4. Copy `config.local.example.php` to `config.local.php`. Fill in the InfinityFree database details, the hosted HTTPS URL, your Google App Password, and real Paystack keys. Do not put these secrets in the example file.
5. Upload the project files to the site's `htdocs` directory, including `vendor/`, `.htaccess`, and `config.local.php`. Keep `.gitignore` in place so `config.local.php` is never committed. The `.htaccess` rule blocks direct web access to the secret config.
6. Open the hosted site and test signup, email delivery, an event booking in Paystack test mode, and the admin/organizer screens before using real payments.

InfinityFree's official documentation says free hosting allows outbound SMTP on port 587 and HTTPS on 443, but restricts PHP `mail()` and has limited resources. This should support PHPMailer/Gmail SMTP and Paystack's HTTPS API, but test both on the actual hosted account. Free hosting is suitable for a demo, not a production ticketing/payment service.

## Settings

    - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`: database connection settings; set these in ignored `config.local.php` for InfinityFree
    - `SITE_URL`: public site root, without a trailing slash
    - `ADMIN_EMAIL`: defaults to your configured Gmail address
    - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_ENCRYPTION`, `SMTP_FROM`, `SMTP_FROM_NAME`: Gmail/Happiva defaults are already set; override only if needed
    - `SMTP_PASSWORD`: Google App Password, not your Gmail password

The code expires after 10 minutes and allows five verification attempts. Organizer signups notify admins after email verification and remain subject to the existing organizer approval process.
