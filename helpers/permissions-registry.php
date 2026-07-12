<?php
/**
 * IPESS Permissions Registry
 * ─────────────────────────────────────────────────────────────────
 * Single canonical source of truth for ALL permissions in the system.
 * Used by:
 *   - Role management duty-assignment modal (grouped checkbox UI)
 *   - has_permission() fallback in auth.php
 *   - Database bootstrapper (seed data)
 *   - Any future API permission checks
 *
 * Structure: array of groups, each group has a 'label' and 'permissions'
 * array where each entry is:
 *   'key'         => string  (stored in role_permissions.permission_key)
 *   'name'        => string  (human readable label)
 *   'description' => string  (tooltip / sub-label)
 */

function get_all_permission_groups(): array
{
    return [
        'ict_system' => [
            'label' => 'ICT & System Administration',
            'icon'  => 'fas fa-server',
            'permissions' => [
                [
                    'key'         => 'reset_authenticator',
                    'name'        => 'Reset Authenticator App',
                    'description' => 'Clear the 2FA/TOTP secret for any staff or applicant account, even when they know their password.',
                ],
                [
                    'key'         => 'manage_users',
                    'name'        => 'Manage Staff Users',
                    'description' => 'Create, edit, activate, and deactivate administrative and reviewer staff accounts.',
                ],
                [
                    'key'         => 'manage_roles',
                    'name'        => 'Manage Roles & Duties',
                    'description' => 'Create custom roles and assign permission duties to each role.',
                ],
                [
                    'key'         => 'view_audit_logs',
                    'name'        => 'View Audit Logs',
                    'description' => 'Access the full system audit trail, including login events and sensitive actions.',
                ],
                [
                    'key'         => 'settings',
                    'name'        => 'System Settings',
                    'description' => 'Configure system-wide settings such as session policy, email config, and portal flags.',
                ],
                [
                    'key'         => 'workflow_configuration',
                    'name'        => 'Workflow Configuration',
                    'description' => 'Define and modify the admission processing workflow stages.',
                ],
            ],
        ],

        'student_management' => [
            'label' => 'Student Management',
            'icon'  => 'fas fa-user-graduate',
            'permissions' => [
                [
                    'key'         => 'manage_students',
                    'name'        => 'Full Student Management',
                    'description' => 'Create, edit, deactivate, and export enrolled student records completely.',
                ],
                [
                    'key'         => 'view_students',
                    'name'        => 'View Students',
                    'description' => 'Read-only access to view student list and individual student profiles.',
                ],
                [
                    'key'         => 'export_students',
                    'name'        => 'Export Student Data',
                    'description' => 'Export student records to PDF, Excel, or CSV formats.',
                ],
                [
                    'key'         => 'verify_applicants',
                    'name'        => 'Verify Applicants',
                    'description' => 'Mark submitted applicant credentials and documents as verified.',
                ],
            ],
        ],

        'academic_structure' => [
            'label' => 'Academic Structure',
            'icon'  => 'fas fa-university',
            'permissions' => [
                [
                    'key'         => 'manage_faculties',
                    'name'        => 'Manage Faculties / Institutes',
                    'description' => 'Add, rename, and remove faculties or institutes in the system.',
                ],
                [
                    'key'         => 'manage_departments',
                    'name'        => 'Manage Departments',
                    'description' => 'Add, rename, and remove departments under each faculty.',
                ],
                [
                    'key'         => 'manage_programmes',
                    'name'        => 'Manage Programme Types & Levels',
                    'description' => 'Configure degree types (MSc, PhD, PGD, etc.) and study levels.',
                ],
                [
                    'key'         => 'manage_courses',
                    'name'        => 'Manage Courses',
                    'description' => 'Add, edit, and remove course offerings linked to departments and programme types.',
                ],
                [
                    'key'         => 'manage_academics',
                    'name'        => 'General Academic Configuration',
                    'description' => 'Broad academic administration including capacities and course settings.',
                ],
            ],
        ],

        'supervisor' => [
            'label' => 'Supervisor Management',
            'icon'  => 'fas fa-user-tie',
            'permissions' => [
                [
                    'key'         => 'manage_supervisors',
                    'name'        => 'Manage Supervisors',
                    'description' => 'Add and remove supervisor accounts; set supervisor availability.',
                ],
                [
                    'key'         => 'assign_supervisor',
                    'name'        => 'Assign Supervisor to Student',
                    'description' => 'Match an admitted student to a specific supervisor.',
                ],
                [
                    'key'         => 'change_supervisor',
                    'name'        => 'Change Supervisor Assignment',
                    'description' => 'Reassign a student\'s supervisor to a different staff member.',
                ],
                [
                    'key'         => 'remove_supervisor',
                    'name'        => 'Remove Supervisor Assignment',
                    'description' => 'Unlink a supervisor from a student record.',
                ],
                [
                    'key'         => 'bulk_supervisor_allocation',
                    'name'        => 'Bulk Supervisor Allocation',
                    'description' => 'Allocate supervisors to multiple students in a single operation.',
                ],
            ],
        ],

        'admissions' => [
            'label' => 'Admissions',
            'icon'  => 'fas fa-file-alt',
            'permissions' => [
                [
                    'key'         => 'view_applications',
                    'name'        => 'View Applications',
                    'description' => 'Access the dashboard and view the list and details of applicant profiles.',
                ],
                [
                    'key'         => 'manage_admissions',
                    'name'        => 'Manage Admissions',
                    'description' => 'Process, approve, reject, and activate admission offers.',
                ],
                [
                    'key'         => 'download_applications',
                    'name'        => 'Download Application PDFs',
                    'description' => 'Download or print full application form summaries as PDF.',
                ],
                [
                    'key'         => 'download_documents',
                    'name'        => 'Download Uploaded Credentials',
                    'description' => 'Download uploaded certificates, transcripts, O-levels, and NYSC documents.',
                ],
                [
                    'key'         => 'ict_processing',
                    'name'        => 'ICT Processing Stage',
                    'description' => 'Handle the ICT processing stage (matric number, activation of letters).',
                ],
                [
                    'key'         => 'generate_matric_number',
                    'name'        => 'Generate Matriculation Numbers',
                    'description' => 'Trigger matric number generation for admitted students.',
                ],
                [
                    'key'         => 'admission_letter',
                    'name'        => 'Activate Admission Letters',
                    'description' => 'Set admission letter status to Active so students can download.',
                ],
                [
                    'key'         => 'acceptance_letter',
                    'name'        => 'Activate Acceptance Letters',
                    'description' => 'Set acceptance letter status to Active so students can download.',
                ],
            ],
        ],

        'review' => [
            'label' => 'Application Review',
            'icon'  => 'fas fa-tasks',
            'permissions' => [
                [
                    'key'         => 'review_applications',
                    'name'        => 'Review Applications (General)',
                    'description' => 'Score and approve or reject applications at the assigned review stage.',
                ],
                [
                    'key'         => 'faculty_review',
                    'name'        => 'Faculty / Institute Review',
                    'description' => 'Approve applications at the faculty/institute review level.',
                ],
                [
                    'key'         => 'department_review',
                    'name'        => 'Department Review',
                    'description' => 'Approve applications at the department/HOD review level.',
                ],
                [
                    'key'         => 'pg_review',
                    'name'        => 'PG School Review',
                    'description' => 'Approve applications at the Postgraduate School review level.',
                ],
            ],
        ],

        'reports' => [
            'label' => 'Reports & Exports',
            'icon'  => 'fas fa-chart-bar',
            'permissions' => [
                [
                    'key'         => 'reports',
                    'name'        => 'View Reports',
                    'description' => 'Access the reports section and view admission/student analytics.',
                ],
                [
                    'key'         => 'export_pdf',
                    'name'        => 'Export PDF Reports',
                    'description' => 'Download reports and data exports as PDF files.',
                ],
                [
                    'key'         => 'export_excel',
                    'name'        => 'Export Excel / CSV Reports',
                    'description' => 'Download reports and data exports as Excel or CSV files.',
                ],
            ],
        ],
    ];
}

/**
 * Returns a flat list of all permission keys for use in seeding/validation.
 */
function get_all_permission_keys(): array
{
    $keys = [];
    foreach (get_all_permission_groups() as $group) {
        foreach ($group['permissions'] as $perm) {
            $keys[] = $perm['key'];
        }
    }
    return $keys;
}

/**
 * Returns the default role-permission mappings for seeding.
 * Keys are role_key strings; values are arrays of permission_key strings.
 */
function get_default_role_permissions(): array
{
    return [
        'SUPER_ADMIN' => get_all_permission_keys(), // Full access

        'ICT_ADMIN' => get_all_permission_keys(),   // Full access

        'ICT_SUPPORT' => [
            'reset_authenticator',
            'view_audit_logs',
            'manage_users',
            'view_applications',
            'view_students',
        ],

        'STUDENT_MANAGER' => [
            'manage_students',
            'view_students',
            'export_students',
            'verify_applicants',
            'view_applications',
            'download_applications',
        ],

        'ACADEMIC_MANAGER' => [
            'manage_faculties',
            'manage_departments',
            'manage_programmes',
            'manage_courses',
            'manage_academics',
            'view_applications',
        ],

        'SUPERVISOR_MANAGER' => [
            'manage_supervisors',
            'assign_supervisor',
            'change_supervisor',
            'remove_supervisor',
            'bulk_supervisor_allocation',
            'view_students',
            'view_applications',
        ],

        'PG_SCHOOL_OFFICER' => [
            'view_applications',
            'manage_admissions',
            'download_applications',
            'download_documents',
            'pg_review',
            'review_applications',
            'verify_applicants',
            'reports',
            'export_pdf',
            'export_excel',
        ],

        'ADMISSIONS_OFFICER' => [
            'view_applications',
            'manage_admissions',
            'download_applications',
            'download_documents',
            'reports',
        ],

        'REVIEWER' => [
            'view_applications',
            'review_applications',
            'download_applications',
            'download_documents',
        ],

        'FACULTY_OFFICER' => [
            'view_applications',
            'faculty_review',
            'download_applications',
        ],

        'DEPARTMENT_ADMIN' => [
            'view_applications',
            'department_review',
            'manage_supervisors',
            'assign_supervisor',
            'change_supervisor',
            'remove_supervisor',
            'bulk_supervisor_allocation',
            'view_students',
            'reports',
        ],

        'HOD' => [
            'view_applications',
            'department_review',
            'manage_supervisors',
            'assign_supervisor',
            'change_supervisor',
            'view_students',
            'reports',
        ],

        'ICT_STAFF' => [
            'view_applications',
            'ict_processing',
            'generate_matric_number',
            'admission_letter',
            'acceptance_letter',
            'verify_applicants',
        ],

        'PORTAL_ADMIN' => [
            'view_applications',
            'manage_admissions',
            'download_applications',
            'download_documents',
            'reports',
            'export_pdf',
            'export_excel',
        ],

        'REGISTRY' => [
            'view_applications',
            'download_applications',
            'download_documents',
            'verify_applicants',
        ],

        'SUPERVISOR' => [
            'view_students',
        ],
    ];
}

/**
 * Seed data for the `roles` table.
 */
function get_system_roles(): array
{
    return [
        ['key' => 'SUPER_ADMIN',       'name' => 'Super Admin',          'description' => 'Unrestricted access to all system features.',     'is_system' => 1],
        ['key' => 'ICT_ADMIN',         'name' => 'ICT Admin',            'description' => 'Full ICT and system administration access.',       'is_system' => 1],
        ['key' => 'ICT_SUPPORT',       'name' => 'ICT Support',          'description' => 'Reset authenticators and assist staff with login.', 'is_system' => 0],
        ['key' => 'STUDENT_MANAGER',   'name' => 'Student Manager',      'description' => 'Full control over enrolled student records.',       'is_system' => 0],
        ['key' => 'ACADEMIC_MANAGER',  'name' => 'Academic Manager',     'description' => 'Manages departments, faculties, programmes, courses.','is_system' => 0],
        ['key' => 'SUPERVISOR_MANAGER','name' => 'Supervisor Manager',   'description' => 'Manages and assigns supervisors to students.',      'is_system' => 0],
        ['key' => 'PG_SCHOOL_OFFICER', 'name' => 'PG School Officer',   'description' => 'Postgraduate school review and admissions.',        'is_system' => 0],
        ['key' => 'ADMISSIONS_OFFICER','name' => 'Admissions Officer',  'description' => 'Handles admissions processing tasks.',              'is_system' => 0],
        ['key' => 'REVIEWER',          'name' => 'Reviewer',            'description' => 'Assigned to review specific applications.',         'is_system' => 0],
        ['key' => 'FACULTY_OFFICER',   'name' => 'Faculty Officer',     'description' => 'Faculty-level application review.',                 'is_system' => 0],
        ['key' => 'DEPARTMENT_ADMIN',  'name' => 'Department Admin',    'description' => 'Department-level administration and review.',       'is_system' => 0],
        ['key' => 'HOD',               'name' => 'Head of Department',  'description' => 'Head of department access.',                        'is_system' => 0],
        ['key' => 'ICT_STAFF',         'name' => 'ICT Staff',           'description' => 'ICT processing stage handler.',                     'is_system' => 0],
        ['key' => 'PORTAL_ADMIN',      'name' => 'Portal Admin',        'description' => 'Portal content and admissions management.',         'is_system' => 0],
        ['key' => 'REGISTRY',          'name' => 'Registry Officer',    'description' => 'Registry access for verification.',                 'is_system' => 0],
        ['key' => 'SUPERVISOR',        'name' => 'Supervisor',          'description' => 'Student supervisor — limited read access.',         'is_system' => 0],
    ];
}
