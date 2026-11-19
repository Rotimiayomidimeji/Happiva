# Happiva Project Handoff

## Test Accounts

Use these accounts only in local XAMPP. Do not create or use them on production.

| Role | Login email | Password | Intended destination |
|---|---|---|---|
| Attendee | `qa-attendee@example.test` | `Happiva-QA-2026!` | Homepage and attendee features |
| Organizer | `qa-organizer@example.test` | `Happiva-QA-2026!` | Organizer Dashboard |
| Admin | `qa-admin@example.test` | `Happiva-QA-2026!` | Admin Dashboard, approvals, Support Inbox |

Login: `http://localhost/Happiva/login.php`

Before production hosting, delete these accounts and their test support records from the database. The temporary account seeder has been removed; do not recreate it on a public host.

## What Works

- PHP/MySQL event platform with sessions, roles, and approval states
- Separate login and registration pages
- Three-step registration with password confirmation and email OTP
- Gmail SMTP with PHPMailer; vendor dependencies are installed locally
- Attendee, organizer, and admin login routing
- Attendee tickets, profile, messages, and checkout pages
- Organizer event creation and QR check-in tools
- Admin organizer approval, event approval, and Support Inbox
- Contact form stored in `contact_submissions` and visible to admins
- Shared desktop/mobile footer and role-compressed navigation
- Paystack integration path in test/demo mode

## Important Data and Security Notes

- `config.local.php` is ignored by Git and holds local database/SMTP settings.
- Never commit Gmail App Passwords or Paystack secret keys.
- Composer dependencies are in `vendor/`, generated locally; `vendor/` is ignored by Git.
- `composer.lock` is committed for reproducible dependency installs.
- Public images are ignored under `uploads/`.
- Live ticket/payment values are never trusted from browser input; checkout rechecks server-side event and capacity data.
- Organizer accounts remain pending until admin approval; standard users can request organizer access.

## SMTP Status

Gmail SMTP is configured locally and a real OTP was sent successfully to a test address. The local SMTP values are outside Git:

- Host: `smtp.gmail.com`
- Port: `587`
- Encryption: `tls`
- Sender: `dimejirotimi5@gmail.com`
- App password: stored locally only

For InfinityFree, copy the Gmail and database settings into `config.local.php` or the host equivalent. SMTP port 587 and external SMTP should be tested on the deployed account.

## Hosting Checklist

1. Create an InfinityFree MySQL database.
2. Import `database.sql` in phpMyAdmin after selecting the created database.
3. Copy `config.local.example.php` to `config.local.php` and enter hosted DB, URL, SMTP, and Paystack values.
4. Upload project files and `vendor/` to the host `htdocs` folder.
5. Keep `config.local.php` outside Git and deny direct web access with `.htaccess`.
6. Set Paystack callback/public/secret values for test mode.
7. Register a real user and complete OTP delivery.
8. Create and approve an organizer event.
9. Complete a test booking and verify QR ticket/check-in behavior.
10. Submit a contact message and resolve it in the admin dashboard.

## GitHub

- Repository: https://github.com/Rotimiayomidimeji/Happiva.git
- Branch: `main`
- Secret config and vendor dependencies are excluded.
