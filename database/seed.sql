-- JOSTUM production seed data.
-- Default password for the seeded Super Admin: ChangeMe123!
-- Change it immediately after first login.

INSERT INTO roles (role_key, role_name)
VALUES
    ('SUPER_ADMIN', 'Super Admin'),
    ('ICT_ADMIN', 'ICT Admin'),
    ('PORTAL_ADMIN', 'Portal Admin'),
    ('REGISTRY', 'Registry'),
    ('ADMISSIONS_OFFICER', 'Admissions Officer'),
    ('BURSARY', 'Bursary'),
    ('PG_SCHOOL_OFFICER', 'PG School Officer'),
    ('FACULTY_OFFICER', 'Faculty Officer'),
    ('DEPARTMENT_ADMIN', 'Department Admin'),
    ('HOD', 'Head of Department'),
    ('SUPERVISOR', 'Supervisor'),
    ('REVIEWER', 'Reviewer'),
    ('STUDENT', 'Student')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

INSERT INTO users (email, full_name, role_id, password_hash, account_status, created_at)
SELECT
    'superadmin@jostum.edu.ng',
    'Default Super Admin',
    r.role_id,
    '$2y$10$W7bkpqPOh.eRm20bnF0hreJVkV/gtykCjtGKff0E/NlN4K5AMjsN2',
    'Active',
    NOW()
FROM roles r
WHERE r.role_key = 'SUPER_ADMIN'
ON DUPLICATE KEY UPDATE
    role_id = VALUES(role_id),
    account_status = 'Active';
