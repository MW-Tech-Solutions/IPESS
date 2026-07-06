# JOSTUM POST-UTME Online Screening Portal

Enterprise PHP/PDO portal for Joseph Sarwuan Tarka University, Makurdi.

## Stack

- PHP 8+ with PDO
- MySQL/MariaDB
- Bootstrap CDN and Tailwind CDN
- Local private storage for development
- Remita/Paystack-ready payment abstraction

## Setup

1. Start Apache and MySQL in XAMPP.
2. Confirm database settings in `app/config.php`.
3. Import the base schema:

```powershell
Get-Content database\schema.sql | C:\xampp\mysql\bin\mysql.exe -u root
```

4. Run the enterprise upgrade/migration:

```powershell
C:\xampp\php\php.exe database\upgrade.php
```

5. Open `http://localhost/postutme-jostum/`.

Default staff password is `ChangeMe@123`. Change it before production.

## Staff Accounts

- `admin@jostum.edu.ng` Super Admin
- `ict@jostum.edu.ng` ICT Admin
- `admissions@jostum.edu.ng` Admissions Officer
- `finance@jostum.edu.ng` Finance Officer

## Workflow

Applicant: Verify JAMB -> Profile -> Payment -> Bio-data -> O Level -> Uploads -> Review -> Submit -> Status.

Admin: Import JAMB batches, manage candidates/applicants, reconcile payments, compute screening results, review applications, configure settings, export reports.

## Important Files

- `database/schema.sql` base schema and seed data
- `database/enterprise_upgrade.sql` enterprise tables
- `database/upgrade.php` idempotent upgrade runner for existing installs
- `app/helpers.php` validation, security, payment, upload, screening helpers
- `app/importer.php` CSV/XLSX/XLS import support
- `api/verify-jamb.php` candidate verification endpoint
- `api/status.php` application status endpoint
- `api/payment-callback.php` gateway callback placeholder

## Security Notes

- Passwords use bcrypt.
- Forms use CSRF tokens.
- PDO prepared statements are used for database access.
- Uploads are MIME-checked, renamed, size-limited, and stored privately.
- Login attempts are rate-limited.
- Staff actions are audit-logged.
- Use HTTPS and real environment-based secrets before production.

## Design Style

Use JOSTUM official identity. The interface should feel like a modern Nigerian federal university portal: formal, secure, clean, and trustworthy. Use deep green as primary, royal blue as secondary, gold as accent, white backgrounds, black text, light gray borders, rounded cards, professional tables, and responsive dashboards. The portal must look good on mobile first, then tablet and desktop.
