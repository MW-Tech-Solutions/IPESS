# JOSTUM Restructure Report

Date: 2026-05-21

## What Changed

- Added production target folders: `public/`, `app/config/`, `app/includes/`, `app/helpers/`, `app/controllers/`, `modules/*`, `storage/uploads/`, and `database/`.
- Centralized bootstrap in `app/bootstrap.php`.
- Centralized database access in `app/config/database.php`; legacy `db.php` files now point to the shared connector.
- Centralized secure session handling in `app/includes/session.php`.
- Centralized authentication and authorization in `app/helpers/auth.php`.
- Added standardized roles:
  `SUPER_ADMIN`, `ICT_ADMIN`, `PORTAL_ADMIN`, `REGISTRY`, `ADMISSIONS_OFFICER`, `BURSARY`, `PG_SCHOOL_OFFICER`, `FACULTY_OFFICER`, `DEPARTMENT_ADMIN`, `HOD`, `SUPERVISOR`, `REVIEWER`, `STUDENT`.
- Added reusable functions:
  `require_login()`, `require_role()`, `has_permission()`, `dashboard_for_role()`, `normalize_role()`.
- Replaced the old root and admin duplicate dashboards with redirect shims:
  `dashboard.php`, `ADMIN/dashboard.php`.
- Updated major role layouts and headers to use the shared auth layer.
- Added `.env.example` and `.gitignore`.

## Archived Files

Files were moved to `archive/restructure-2026-05-21/` instead of being permanently deleted. The archive manifest is at `archive/restructure-2026-05-21/moved-files.txt`.

Archived groups include:

- Secrets/deploy artifacts: `.env`, `ADMIN/.env`, `JOSTUM.pem`, `jostum_deploy.tgz`.
- Old SQL/deploy scripts: `pg.sql`, `ADMIN/pg.sql`, `workflow_migration.sql`, `portal_admin_content_migration.sql`, `create_app_user.sql`, `import_pg.sh`, `jostum.nginx`, `seed_portal_page_sections.php`.
- Test/backup/copy files: `test.php`, `index1.php`, `*.backup`, `* copy.php`, `*(Copy).php`.
- Duplicate dashboards: old root `dashboard.php`, old `ADMIN/dashboard.php`, dashboard backups, applicant dashboard copy.

## Duplicate Files Found

- Duplicate admin dashboards existed at root, `ADMIN/`, `ADMIN/admin/`, and role folders.
- Copy/backup files existed under `ADMIN/`, `ADMIN/admin/`, `ADMIN/api/`, `ADMIN/includes/`, and `APPLICANT/ADMISSIONS/`.
- PHPMailer is duplicated in multiple folders: root `PhpMailer`, `asset/PhpMailer`, `ADMIN/PhpMailer`, `ADMIN/assets/PhpMailer`, `ADMIN/assets/css/PhpMailer`, and `APPLICANT/ADMISSIONS/PhpMailer`.
- Upload directories are duplicated/mirrored under root `uploads`, `ADMIN/uploads`, `ADMIN/admin/uploads`, `APPLICANT/ADMISSIONS/uploads`, and `helpers/uploads`.

## Database

- Created `database/schema.sql` from the newest root dump plus workflow/content migrations, excluding dumped data rows.
- Created `database/seed.sql` with the standard role set and one default Super Admin.
- Default Super Admin:
  - Email: `superadmin@jostum.edu.ng`
  - Password: `ChangeMe123!`
  - Must be changed after first login.

## Security Issues Fixed

- Removed deploy secrets and private key material from the deployable root.
- Removed SQL dumps and deployment archive from the web root.
- Removed obvious test, copy, and backup PHP files from deployable code.
- Added `.gitignore` rules to prevent committing secrets, archives, old backups, SQL dumps, and upload payloads.
- Replaced repeated role checks in main headers/layouts with centralized `require_role()`.
- Added secure session cookie defaults and central session timeout handling.

## Final Folder Structure

```text
public/
app/
  config/
  includes/
  helpers/
  controllers/
modules/
  admin/
  admissions/
  registry/
  bursary/
  academics/
  supervision/
storage/
  uploads/
database/
  schema.sql
  seed.sql
archive/
  restructure-2026-05-21/
```

## Remaining Issues

- Some older pages and API endpoints still contain inline legacy role checks or commented-out checks. They should be migrated module by module to `require_role()`.
- PHPMailer duplicates should be consolidated to Composer/autoload or one shared mailer wrapper.
- Upload paths should be migrated from public folders into `storage/uploads/`, with controlled download endpoints.
- `.git/` remains in the working repository for development, but it must not be included in deployment packages.
- Existing uploaded applicant documents were left in place to avoid breaking current file references.
- Module folders are present with protected dashboards, but legacy code still needs gradual movement into those modules.

## POST-UTME Module Import

Date: 2026-05-23

- Imported `C:\xampp\htdocs\postutme-jostum` into `modules/postutme/`, excluding the source deployment zip.
- Added a POST-UTME link to the main landing page portal links.
- Added POST-UTME Screening links to Super Admin, Admin, and Portal Admin sidebars.
- Updated the POST-UTME module URL base to `/JOSTUM/modules/postutme/`.
- Added POST-UTME database environment keys to `.env.example`.
- Created `database/postutme_schema.sql` for the module database.
- Kept the POST-UTME data model isolated in its own database by default (`postutme_jostum`) because it has table names that conflict with the postgraduate schema: `users`, `applicants`, `payments`, `audit_logs`, `notifications`, `password_resets`, and `olevel_results`.
- Added a JOSTUM ADMIN bridge. Logged-in JOSTUM roles `SUPER_ADMIN`, `ICT_ADMIN`, `PORTAL_ADMIN`, `ADMISSIONS_OFFICER`, `PG_SCHOOL_OFFICER`, and `BURSARY` can open POST-UTME admin pages. The module creates an internal shadow staff row for audit trails, approvals, payment verification, and reviewed-by fields.
- Archived copied sample upload/import files to `archive/postutme-import-2026-05-23/` and left only storage placeholders.
