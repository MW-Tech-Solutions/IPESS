# Walkthrough - ICT Admin Refactoring & Referee Verification

We have successfully decoupled and modularized the sidebars for all user roles, stripped excessive permissions from the `ICT_ADMIN` role, and resolved referee form evaluation data saving bugs.

## Summary of Changes

### 1. Decoupled Sidebar System for All Folders
Previously, pages in `/ADMIN/super-admin/`, `/ADMIN/admin/`, and `/ADMIN/general/` directories hardcoded the inclusion of their folder's specific `includes/sidebar.php`, resulting in role leaks (e.g. `ICT_ADMIN` opening referees/reports seeing the Super Admin sidebar).
We decoupled this globally:
- **Delegated Sidebar Loaders**:
  - [ADMIN/super-admin/includes/sidebar.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/super-admin/includes/sidebar.php)
  - [ADMIN/admin/includes/sidebar.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/admin/includes/sidebar.php)
  - [ADMIN/general/includes/sidebar.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/general/includes/sidebar.php)
  - All three wrapper files have been replaced to dynamically delegate sidebar rendering to the central mapping system at [ADMIN/includes/sidebar.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/includes/sidebar.php).
- **Dedicated Sidebar Files**:
  - Moved the Super Admin sidebar markup to its own dedicated file at [ADMIN/includes/sidebars/super_admin.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/includes/sidebars/super_admin.php).
  - Moved the General Desk sidebar markup to its own dedicated file at [ADMIN/includes/sidebars/general_admin.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/includes/sidebars/general_admin.php).
  - Kept the custom `ICT_ADMIN` sidebar configuration inside [ADMIN/includes/sidebars/ict_admin.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/includes/sidebars/ict_admin.php).
- **Result**: No matter which subfolder a page resides in, users will **always** see their own role-based sidebar (e.g., `ICT_ADMIN` will only ever see the ICT Admin sidebar containing Document Verification, Referees, Admissions Activation, Reports, and Audit intelligence, with absolutely no references or access to User Management, Role Management, or Academics).

### 2. Custom Dashboard Path
- **Custom Dashboard**: [ADMIN/ict-admin/dashboard.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/ict-admin/dashboard.php)
  - Created a new dashboard landing file at `ADMIN/ict-admin/dashboard.php`.
  - Displays metrics for Total Applications, Submitted, Admitted, Documents Uploaded, and Pending Verification.
  - Lists recent program-wide applicant entries and active notification logs.
- **Auth Routing**: [app/helpers/auth.php](file:///c:/xampp/htdocs/JOSTUM/app/helpers/auth.php)
  - Configured `dashboard_for_role` to route `ICT_ADMIN` to `ADMIN/ict-admin/dashboard.php` (separated from `SUPER_ADMIN`).
- **Sidebar Selector**: [ADMIN/includes/sidebar.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/includes/sidebar.php)
  - Mapped the `ICT_ADMIN` role to render `ict_admin.php`.
- **System-Wide Mappings**:
  - [ADMIN/profile.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/profile.php) & [ADMIN/relogin.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/relogin.php): Resolved dashboard redirects dynamically using `dashboard_for_role`.
  - [ADMIN/admin/api/global-search.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/admin/api/global-search.php): Handled custom navigation redirects for `ICT_ADMIN` search results.
  - [ADMIN/admin/includes/header.php](file:///c:/xampp/htdocs/JOSTUM/ADMIN/admin/includes/header.php): Added `ICT_ADMIN` to the list of authorized admin panel roles.

### 3. Database Permission Clean Up for ICT Admin
- **Database Table**: `role_permissions`
- **Modifications**: Removed the following administrative permissions from the `ICT_ADMIN` role:
  - `role_management` (Role configuration)
  - `permission_management`
  - `user_management` (User registration/editing)
  - `workflow_configuration`
  - **All Academics Management permissions**: `assign_supervisor`, `bulk_supervisor_allocation`, `change_supervisor`, `remove_supervisor`, `supervisor_management`.

### 4. Referee Verification Updates
- **Referee Evaluation Save Bug Resolved**:
  - Corrected the form variable keys in [referee_verify.php](file:///c:/xampp/htdocs/JOSTUM/referee_verify.php) and [APPLICANT/ADMISSIONS/referee_verify.php](file:///c:/xampp/htdocs/JOSTUM/APPLICANT/ADMISSIONS/referee_verify.php) to match the submitted criteria keys. Character, Competence, Leadership, Communication, and Stability are now properly saved to the database.
- **Referee Individual & Bulk Verification**:
  - Extended the referees api `action === 'verify'` to support bulk selection array parameter.
  - Configured a bypass so that `SUPER_ADMIN` and `ICT_ADMIN` can verify referee reports at any time.
  - Added selection checkboxes and a **Bulk Verify Selected** action button to the referees list.

---

## Verification Results
- Executed database update query successfully.
- Validated PHP syntax of all modified files.
- Regenerated and verified `schema.sql`.
