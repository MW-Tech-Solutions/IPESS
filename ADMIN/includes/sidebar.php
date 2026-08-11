<?php
require_once __DIR__ . '/../../app/bootstrap.php';

$userRole = normalize_role(current_user_role());

$currentPage = basename($_SERVER['PHP_SELF']);
$sidebarDisplayName = 'Admin Desk';
$sidebarSubName = 'Admin Panel';

try {
    require_once __DIR__ . '/../../admin/includes/db.php';
    $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0 && isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.email, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.role_id 
            WHERE u.user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$sessionUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $name = trim((string) ($row['full_name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $roleName = trim((string) ($row['role_name'] ?? ''));
        
        if ($name !== '') {
            $sidebarDisplayName = $name;
        } elseif ($email !== '') {
            $sidebarDisplayName = $email;
        }
        if ($roleName !== '') {
            $sidebarSubName = $roleName;
        }
    }
} catch (Exception $e) {
}

// Dynamically determine the dynamic Reports URL
$reportsUrl = 'ADMIN/admin/reports.php';
if (in_array($userRole, ['SUPER_ADMIN', 'ICT_ADMIN'], true)) {
    $reportsUrl = 'ADMIN/super-admin/reports.php';
} elseif (in_array($userRole, ['DEPARTMENT_ADMIN', 'HOD'], true)) {
    $reportsUrl = 'ADMIN/dept-admin/department-reports.php';
} elseif ($userRole === 'REVIEWER') {
    $reportsUrl = 'ADMIN/reviewer/reviewer-reports.php';
} elseif ($userRole === 'SUPERVISOR') {
    $reportsUrl = 'ADMIN/supervisor/supervisor-reports.php';
}

// Define the available pages and the permissions needed to view them
$menuSections = [
    'core' => [
        'label' => 'Core',
        'items' => [
            [
                'label' => 'Dashboard',
                'url' => dashboard_for_role($userRole),
                'icon' => 'fas fa-tachometer-alt',
                'condition' => true
            ]
        ]
    ],
    'reviewer' => [
        'label' => 'Reviewer Work',
        'items' => [
            [
                'label' => 'Assigned Applications',
                'url' => 'ADMIN/reviewer/assigned-applications.php',
                'icon' => 'fas fa-folder-open',
                'condition' => ($userRole === 'REVIEWER' || has_permission('review_applications'))
            ],
            [
                'label' => 'Feedback Management',
                'url' => 'ADMIN/reviewer/feedback-management.php',
                'icon' => 'fas fa-comments',
                'condition' => ($userRole === 'REVIEWER' || has_permission('review_applications'))
            ],
            [
                'label' => 'Review History',
                'url' => 'ADMIN/reviewer/review-history.php',
                'icon' => 'fas fa-history',
                'condition' => ($userRole === 'REVIEWER' || has_permission('review_applications'))
            ]
        ]
    ],
    'supervisor' => [
        'label' => 'Supervision',
        'items' => [
            [
                'label' => 'My Students',
                'url' => 'ADMIN/supervisor/my-students.php',
                'icon' => 'fas fa-users',
                'condition' => ($userRole === 'SUPERVISOR' || has_permission('view_students'))
            ],
            [
                'label' => 'Student Interaction',
                'url' => 'ADMIN/supervisor/student-interaction.php',
                'icon' => 'fas fa-comments',
                'condition' => ($userRole === 'SUPERVISOR' || has_permission('manage_supervision'))
            ],
            [
                'label' => 'Chats',
                'url' => 'ADMIN/supervisor/chats.php',
                'icon' => 'fas fa-comment-dots',
                'condition' => ($userRole === 'SUPERVISOR')
            ],
            [
                'label' => 'Progress Tracking',
                'url' => 'ADMIN/supervisor/progress-tracking.php',
                'icon' => 'fas fa-chart-line',
                'condition' => ($userRole === 'SUPERVISOR')
            ],
            [
                'label' => 'Milestones',
                'url' => 'ADMIN/supervisor/milestones.php',
                'icon' => 'fas fa-flag-checkered',
                'condition' => ($userRole === 'SUPERVISOR')
            ]
        ]
    ],
    'workflow' => [
        'label' => 'Workflow & Admissions',
        'module' => 'admissions',
        'items' => [
            [
                'label' => 'Application Management',
                'url' => 'ADMIN/admin/application-management.php',
                'icon' => 'fas fa-file-alt',
                'permissions' => ['view_applications', 'view_applicants']
            ],
            [
                'label' => 'Document Verification',
                'url' => 'ADMIN/admin/document-verification.php',
                'icon' => 'fas fa-check-circle',
                'permissions' => ['verify_applicants']
            ],
            [
                'label' => 'Referees',
                'url' => 'ADMIN/admin/referees.php',
                'icon' => 'fas fa-user-check',
                'permissions' => ['view_applications']
            ],
            [
                'label' => 'Admission Decisions',
                'url' => 'ADMIN/admin/admission-decisions.php',
                'icon' => 'fas fa-gavel',
                'permissions' => ['manage_admissions']
            ],
            [
                'label' => 'Department Vetting',
                'url' => 'ADMIN/dept-admin/department-applications.php',
                'icon' => 'fas fa-folder-open',
                'permissions' => ['department_review']
            ],
            [
                'label' => 'Faculty Review',
                'url' => 'ADMIN/faculty/applications.php',
                'icon' => 'fas fa-university',
                'permissions' => ['faculty_review']
            ],
            [
                'label' => 'PG School Review',
                'url' => 'ADMIN/pg-admin/applications.php',
                'icon' => 'fas fa-graduation-cap',
                'permissions' => ['pg_review', 'review_applications']
            ],
            [
                'label' => 'Admissions Processing',
                'url' => 'ADMIN/ict-staff/admissions.php',
                'icon' => 'fas fa-id-card',
                'permissions' => ['ict_processing']
            ],
            [
                'label' => 'Supervisor Assignment',
                'url' => 'ADMIN/dept-admin/supervisor-management.php',
                'icon' => 'fas fa-user-plus',
                'permissions' => ['assign_supervisor', 'supervisor_management']
            ]
        ]
    ],
    'admin' => [
        'label' => 'Administration',
        'items' => [
            [
                'label' => 'User Management',
                'url' => 'ADMIN/super-admin/user-management.php',
                'icon' => 'fas fa-users-cog',
                'permissions' => ['manage_users', 'user_management']
            ],
            [
                'label' => 'Role Management',
                'url' => 'ADMIN/super-admin/role-management.php',
                'icon' => 'fas fa-user-shield',
                'permissions' => ['manage_roles', 'role_management']
            ],
            [
                'label' => 'Manage Students',
                'url' => 'ADMIN/super-admin/manage-students.php',
                'icon' => 'fas fa-user-graduate',
                'permissions' => ['manage_students', 'view_students']
            ]
        ]
    ],
    'ict' => [
        'label' => 'ICT Tools',
        'items' => [
            [
                'label' => 'Activate Admissions',
                'url' => 'ADMIN/super-admin/activate-admissions.php',
                'icon' => 'fas fa-toggle-on',
                'permissions' => ['ict_processing', 'generate_matric_number', 'admission_letter', 'acceptance_letter']
            ],
            [
                'label' => 'Reset Authenticator',
                'url' => 'ADMIN/super-admin/reset-authenticator.php',
                'icon' => 'fas fa-mobile-alt',
                'permissions' => ['reset_authenticator']
            ],
            [
                'label' => 'Module Settings',
                'url' => 'ADMIN/super-admin/modules.php',
                'icon' => 'fas fa-cubes',
                'condition' => ($userRole === 'SUPER_ADMIN')
            ]
        ]
    ],
    'intel' => [
        'label' => 'System & Intelligence',
        'items' => [
            [
                'label' => 'Reports',
                'url' => $reportsUrl,
                'icon' => 'fas fa-chart-bar',
                'permissions' => ['reports']
            ],
            [
                'label' => 'Audit Logs',
                'url' => 'ADMIN/super-admin/audit-logs.php',
                'icon' => 'fas fa-shield-alt',
                'permissions' => ['view_audit_logs']
            ],
            [
                'label' => 'System Settings',
                'url' => 'ADMIN/super-admin/settings.php',
                'icon' => 'fas fa-cog',
                'permissions' => ['settings']
            ]
        ]
    ]
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <img src="<?php echo app_url('ADMIN/images/ipess_logo.png'); ?>" alt="IPESS Logo" class="sidebar-brand-logo">
        </div>
        <div class="brand-text">
            <span class="brand-name">IPESS JOSTUM</span>
            <span class="brand-sub"><?php echo htmlspecialchars($sidebarSubName, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <?php foreach ($menuSections as $sectionKey => $section): ?>
        <?php
        // Skip module-specific section if the module is disabled
        if (isset($section['module']) && !is_module_accessible($section['module'])) {
            continue;
        }

        // Filter items based on permission check
        $visibleItems = [];
        foreach ($section['items'] as $item) {
            $showItem = false;
            
            if (isset($item['condition'])) {
                $showItem = (bool) $item['condition'];
            } elseif (isset($item['permissions'])) {
                foreach ($item['permissions'] as $perm) {
                    if (has_permission($perm)) {
                        $showItem = true;
                        break;
                    }
                }
            } else {
                $showItem = true;
            }

            if ($showItem) {
                $visibleItems[] = $item;
            }
        }

        // If no items in this section are visible to the user, skip rendering the section header entirely
        if (empty($visibleItems)) {
            continue;
        }
        ?>
        <div class="sidebar-section">
            <div class="sidebar-label"><?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?></div>
            <ul class="sidebar-nav">
                <?php foreach ($visibleItems as $item): ?>
                    <?php
                    $requestSelf = $_SERVER['PHP_SELF'] ?? '';
                    $isActive = (strpos($requestSelf, $item['url']) !== false);
                    ?>
                    <li>
                        <a class="<?php echo $isActive ? 'active' : ''; ?>" href="<?php echo app_url($item['url']); ?>">
                            <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>

    <div class="sidebar-footer">
        <div><?php echo htmlspecialchars($sidebarDisplayName, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</aside>
<style>
.sidebar-brand-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 10px;
}
</style>
