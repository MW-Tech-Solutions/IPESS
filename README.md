# JOSTUM Postgraduate Portal

This is a PHP/MySQL web application for postgraduate admissions, academic tracking, and supervision at Joseph Sarwuan Tarka University, Makurdi (JOSTUM). It includes:

- Applicant admission workflow (submission → review → decision).
- Student academic portal with supervision workflow (chapters, milestones, messaging).
- Admin, department admin, supervisor, reviewer, and super admin dashboards.
- Email notifications via PHPMailer.
- Optional 2FA (TOTP) support.
- Inactivity auto-logout with warning countdown.
- Email OTP flows for registration and login.

## Tech Stack

   - PHP (requires **8.2+** for composer dependencies)
   - MySQL / MariaDB
   - XAMPP or any LAMP/WAMP stack
   - Composer dependencies already in `vendor/`
   - PHPMailer included locally (no composer required for mailer)

## Project Structure (high level)

- `index.php`, `login.php`, `register.php`: public entry points
- `dashboard.php`: applicant dashboard (admission portal)
- `ADMIN/`: admin area (admin, super-admin, supervisor, dept-admin, reviewer)
- `APPLICANT/ACADEMICS/student-portal/`: student academic portal
- `APPLICANT/ADMISSIONS/`: admission-related portal assets
- `config/`: environment + DB config
- `uploads/`: uploaded files (documents, supervision files, etc.)
- `ADMIN/pg.sql`: main database schema & seed data
- `workflow_migration.sql`: legacy migration helpers

## Requirements

- PHP **8.2+**
- MySQL
- Composer (optional if `vendor/` is already present)
- XAMPP with Apache + MySQL recommended

## Setup (Local)

1. **Clone or copy project into XAMPP**

   Example:
   ```
   c:\xampp\htdocs\JOSTUM
   ```

2. **Create Database**

   Create database named `pg` (or your preferred name) in MySQL.

3. **Import Schema**

   Import `ADMIN/pg.sql` into your database.

4. **Configure Environment**

   Update `.env` in project root:

   ```
   DB_HOST=localhost
   DB_NAME=pg
   DB_USER=root
   DB_PASS=your_password
   APP_BASE_URL=http://localhost/JOSTUM
   ```

5. **Ensure PHP Version**

   Composer dependencies require **PHP >= 8.2**.  
   If you see `Composer detected issues ... requires PHP >= 8.2`, update your PHP version in XAMPP.

6. **Launch**

   Start Apache and MySQL in XAMPP.  
   Visit:
   ```
   http://localhost/JOSTUM
   ```

## Mail Configuration

Mailer is handled by `ADMIN/includes/mailer.php`, which reads SMTP values from `.env`.

Required `.env` variables:

```
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password
SMTP_FROM_NAME=JOSTUM-PG
SMTP_FROM_EMAIL=your_email@gmail.com
```

If SMTP is missing, mail will fail with a descriptive error.

## Main URLs

Public:
- `http://localhost/JOSTUM/`
- `http://localhost/JOSTUM/login.php`
- `http://localhost/JOSTUM/register.php`
- `http://localhost/JOSTUM/dashboard.php` (applicant dashboard)

Admin:
- `http://localhost/JOSTUM/ADMIN/admin/dashboard.php` (admin)
- `http://localhost/JOSTUM/ADMIN/super-admin/dashboard.php` (super admin)
- `http://localhost/JOSTUM/ADMIN/supervisor/dashboard.php` (supervisor)

Student Academic Portal:
- `http://localhost/JOSTUM/APPLICANT/ACADEMICS/student-portal/index.php#dashboard`
- `http://localhost/JOSTUM/APPLICANT/ACADEMICS/student-portal/index.php#supervision`

## How the System Works (Overview)

### 1) Admissions Workflow

- Applicants register and submit applications.
- Admin reviews and updates status.
- Admission decision updates `applications.status` and `applications.current_status`.
- Applicant dashboard reflects stage completion and final decision.

### 2) Authentication, OTP, 2FA, and Sessions

- **Email OTP (Registration/Login)**
  - New student registration uses OTP sent via email.
  - OTP is verified before the account is activated.
  - Login can require OTP (depending on role and flow).

- **Password Reset**
  - Password reset requests are emailed to users.
  - Reset tokens are stored in `password_resets`.

- **2FA (Google Authenticator / TOTP)**
  - Supported via `robthree/twofactorauth` and `pragmarx/google2fa`.
  - Users enter a setup key.
  - Secret stored in `users.totp_secret`.
  - Verification must use the same secret, digits, algorithm, and period.

- **Inactivity Auto-Logout**
  - Sessions auto-expire after inactivity.
  - Warning modal appears before logout with a countdown.
  - Users can stay logged in by clicking “Stay Logged In”.

### 3) Student Academic Portal

Once admitted, applicants access the Academic Portal:

- **Supervision Workflow**
  - Students upload PDF chapters (Chapter 1 → Chapter 5).
  - Next chapter unlocks only after supervisor approval.
  - Supervisor can approve or request changes.

- **Messages & Notifications**
  - Supervisor and student can message each other.
  - Notifications stored in `student_notifications`.

- **Milestones**
  - Supervisor assigns milestones.
  - Students acknowledge milestones.
  - Milestone status and timestamps tracked in `supervisor_milestones`.

- **Tracking**
  - Students submit tracking updates.
- Overall progress tracked in `student_tracking_updates`.

### 4) Supervision & Assignment

Supervisors are assigned to students based on department and admission status:

- `supervisor_students` links supervisor and student.
- `student_user_id` and `supervisor_user_id` are used as reliable identifiers.

Supervisor dashboard supports:

- Student list
- Progress tracking
- Milestones
- Student interaction (chapter review, messaging)

### 5) Super Admin User Provisioning

Super Admin can create users for:

- Admins
- Department Admins
- Supervisors
- Reviewers

Flow:

1. Super Admin creates the user with role and department.
2. User receives email with reset link or credentials.
3. User sets password and completes 2FA setup.

## Database Notes

Core tables:

- `users` (all user accounts with role IDs)
- `applications` (admission status per applicant)
- `supervisor_students` (supervisor ↔ student mapping)
- `supervisor_messages` (messages)
- `supervisor_milestones` (milestones + acknowledgements)
- `chapter_submissions` (student chapter uploads)
- `student_tracking_updates`
- `student_notifications`

## 2FA (TOTP)

The system includes TOTP support via:

- `robthree/twofactorauth`
- `pragmarx/google2fa`
- `bacon/bacon-qr-code`

Ensure generated QR codes use correct `otpauth://` format if you enable 2FA.

## Session & Security Features

- Inactivity timeout + warning modal.
- OTP for student registration.
- OTP for login where configured.
- TOTP 2FA after password setup.

## Common Issues

1. **PHP version too low**
   - Composer requires PHP 8.2+. Update XAMPP PHP.

2. **Mailer fails**
   - Ensure `.env` has correct SMTP settings.

3. **Uploads fail**
   - Check `uploads/` permissions.
   - Student chapter uploads accept PDF only.

4. **Database errors**
   - Ensure DB name matches `.env`.
   - Import `ADMIN/pg.sql` if tables are missing.

5. **OTP / 2FA issues**
   - Ensure SMTP is configured correctly.
   - Check that TOTP QR content uses Base32 secret and correct otpauth URI format.

---
