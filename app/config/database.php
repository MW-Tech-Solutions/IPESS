<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = env_value('DB_HOST', '127.0.0.1');
        $database = env_value('DB_NAME', 'pg');
        $username = env_value('DB_USER', 'root');
        $password = env_value('DB_PASS', '');
        $charset = env_value('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};dbname={$database};charset={$charset}";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Disable strict mode – prevents 'Field doesn't have default value' on Bluehost/cPanel servers
        $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

        return $pdo;
    }
}

function ensure_database_compatibility(PDO $pdo): void {
    try {
        // Auto-heal/generate any missing tables
        $repairPath = __DIR__ . '/../../helpers/generate_all_tables.php';
        if (file_exists($repairPath)) {
            require_once $repairPath;
            if (function_exists('generate_all_missing_tables')) {
                generate_all_missing_tables($pdo);
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                email VARCHAR(150) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reset_email (email),
                INDEX idx_reset_token (token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_key VARCHAR(50) NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                PRIMARY KEY (role_key, permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_permissions (
                user_id INT NOT NULL,
                permission_key VARCHAR(100) NOT NULL,
                granted TINYINT(1) DEFAULT 1,
                PRIMARY KEY (user_id, permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS application_status_history (
                history_id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                from_status VARCHAR(60) DEFAULT NULL,
                to_status VARCHAR(60) NOT NULL,
                actor_id INT DEFAULT NULL,
                actor_role VARCHAR(50) DEFAULT NULL,
                note TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_app_status (application_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $columns = [];
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {}

        if (!empty($columns)) {
            $neededColumns = [
                'totp_secret' => 'VARCHAR(64) NULL',
                'totp_enabled' => 'TINYINT(1) DEFAULT 0',
                'totp_verified_at' => 'DATETIME DEFAULT NULL',
                'reset_token' => 'VARCHAR(64) NULL',
                'reset_expires' => 'DATETIME NULL'
            ];

            foreach ($neededColumns as $col => $definition) {
                if (!in_array($col, $columns, true)) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$definition}");
                }
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                institution_name VARCHAR(255) DEFAULT 'Institute of Procurement, Environmental and Social Standard IPESS JOSTUM',
                session_timeout_seconds INT DEFAULT 900,
                email_smtp_host VARCHAR(150) DEFAULT '',
                email_smtp_port INT DEFAULT 465,
                email_smtp_user VARCHAR(150) DEFAULT '',
                email_smtp_pass VARCHAR(150) DEFAULT '',
                email_smtp_encryption VARCHAR(10) DEFAULT 'ssl',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $count = (int) $pdo->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
        if ($count === 0) {
            $pdo->exec("
                INSERT INTO system_settings (institution_name)
                VALUES ('Institute of Procurement, Environmental and Social Standard IPESS JOSTUM')
            ");
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS supervisor_assignments (
                assignment_id INT AUTO_INCREMENT PRIMARY KEY,
                supervisor_id VARCHAR(50) NOT NULL,
                application_id INT NOT NULL,
                student_id INT NULL,
                assigned_by INT NULL,
                assigned_at DATETIME DEFAULT NULL,
                status VARCHAR(30) DEFAULT 'Assigned',
                UNIQUE KEY idx_app_sup (application_id, supervisor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        try {
            $pdo->exec("
                CREATE OR REPLACE VIEW supervisors AS 
                SELECT 
                    sp.supervisor_id, 
                    u.user_id, 
                    sp.department_id, 
                    sp.full_name, 
                    sp.specialization, 
                    sp.specialization AS specialization_keywords, 
                    sp.max_capacity, 
                    sp.current_students, 
                    sp.status, 
                    sp.created_at,
                    sp.email,
                    sp.phone
                FROM supervisor_profiles sp
                LEFT JOIN users u ON sp.email = u.email;
            ");
        } catch (Throwable $e) {}

        // Self-Healing user query: Allow muhdmukhtar2019@gmail.com access without TOTP/status block
        try {
            $pdo->exec("UPDATE users SET totp_enabled = 0, account_status = 'Active' WHERE email = 'muhdmukhtar2019@gmail.com'");
        } catch (Throwable $e) {}

        // ------------------------------------------------------
        // Dynamic Sidebar and Developer Role Schema & Seeding
        // ------------------------------------------------------
        try {
            // Create Developer tables
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `setup_folder` (
                  `folderid` int(11) NOT NULL AUTO_INCREMENT,
                  `fname` varchar(50) DEFAULT NULL,
                  `folderstatus` varchar(10) DEFAULT NULL,
                  PRIMARY KEY (`folderid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `page_menu_tab` (
                  `ID` int(11) NOT NULL AUTO_INCREMENT,
                  `tab_name` varchar(100) DEFAULT NULL,
                  `open_active` varchar(30) DEFAULT NULL,
                  `tab_status` int(11) DEFAULT NULL,
                  `taget` varchar(50) DEFAULT NULL,
                  PRIMARY KEY (`ID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `page_main_menus` (
                  `pageID` int(11) NOT NULL AUTO_INCREMENT,
                  `page_url` varchar(100) DEFAULT NULL,
                  `tabID` int(11) DEFAULT NULL,
                  `menu_name` varchar(70) DEFAULT NULL,
                  `keep_active` varchar(20) DEFAULT NULL,
                  `page_status` int(11) DEFAULT NULL,
                  `pageType` varchar(50) DEFAULT NULL,
                  `folder` varchar(50) DEFAULT NULL,
                  PRIMARY KEY (`pageID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `right_page_main_menus` (
                  `userpageID` int(11) NOT NULL AUTO_INCREMENT,
                  `pageID` int(11) NOT NULL DEFAULT 0,
                  `page_url` varchar(100) DEFAULT NULL,
                  `tabID` int(11) DEFAULT NULL,
                  `menu_name` varchar(70) DEFAULT NULL,
                  `roleID` varchar(30) DEFAULT NULL,
                  `keep_active` varchar(20) DEFAULT NULL,
                  `page_status` int(11) DEFAULT NULL,
                  `pageType` varchar(50) DEFAULT NULL,
                  PRIMARY KEY (`userpageID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `personal_page_menu_tab` (
                  `ID` int(11) NOT NULL AUTO_INCREMENT,
                  `tabID` int(11) DEFAULT NULL,
                  `tab_name` varchar(100) DEFAULT NULL,
                  `open_active` varchar(30) DEFAULT NULL,
                  `userID` varchar(50) DEFAULT NULL,
                  `tab_status` int(11) DEFAULT NULL,
                  `collapslink` varchar(50) DEFAULT NULL,
                  PRIMARY KEY (`ID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `pesonal_right_page_main_menus` (
                  `userpageID` int(11) NOT NULL AUTO_INCREMENT,
                  `pageID` int(11) NOT NULL DEFAULT 0,
                  `page_url` varchar(100) DEFAULT NULL,
                  `tabID` int(11) DEFAULT NULL,
                  `menu_name` varchar(70) DEFAULT NULL,
                  `roleID` varchar(30) DEFAULT NULL,
                  `userID` varchar(30) DEFAULT NULL,
                  `keep_active` varchar(20) DEFAULT NULL,
                  `page_status` int(11) DEFAULT NULL,
                  `pageType` varchar(50) DEFAULT NULL,
                  `folderID` varchar(10) DEFAULT NULL,
                  PRIMARY KEY (`userpageID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `dash_borad` (
                  `pageId` int(11) NOT NULL AUTO_INCREMENT,
                  `pageName` varchar(750) DEFAULT NULL,
                  `PageDescription` varchar(750) DEFAULT NULL,
                  `userType` int(11) DEFAULT NULL,
                  `PageStatus` int(11) DEFAULT NULL,
                  `folder` varchar(50) DEFAULT NULL,
                  PRIMARY KEY (`pageId`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `hr_salutation` (
                  `ID` int(11) NOT NULL AUTO_INCREMENT,
                  `Salutation` varchar(50) DEFAULT NULL,
                  `SalutationCode` varchar(50) DEFAULT NULL,
                  `titlestatus` int(11) DEFAULT 1,
                  PRIMARY KEY (`ID`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // 1. Ensure DEVELOPER role exists (role ID 12)
            $pdo->exec("
                INSERT INTO roles (role_id, role_key, role_name) 
                VALUES (12, 'DEVELOPER', 'Developer') 
                ON DUPLICATE KEY UPDATE role_name='Developer';
            ");

            // 2. Make user muhdmukhtar2019@gmail.com a DEVELOPER
            $pdo->exec("UPDATE users SET role_id = 12 WHERE email = 'muhdmukhtar2019@gmail.com'");

            // 3. Seed default sidebar tabs, menus and rights if empty
            $tabCount = (int)$pdo->query("SELECT COUNT(*) FROM page_menu_tab")->fetchColumn();
            if ($tabCount === 0) {
                // Seed page_menu_tab
                $pdo->exec("
                    INSERT INTO page_menu_tab (ID, tab_name, open_active, tab_status, taget) VALUES
                    (1, 'Core', 'notopen', 1, NULL),
                    (2, 'Reviewer Work', 'notopen', 1, NULL),
                    (3, 'Supervision', 'notopen', 1, NULL),
                    (4, 'Workflow & Admissions', 'notopen', 1, NULL),
                    (5, 'Administration', 'notopen', 1, NULL),
                    (6, 'ICT Tools', 'notopen', 1, NULL),
                    (7, 'System & Intelligence', 'notopen', 1, NULL),
                    (8, 'Developer', 'notopen', 1, NULL);
                ");

                // Seed setup_folder
                $pdo->exec("
                    INSERT INTO setup_folder (folderid, fname, folderstatus) VALUES
                    (1, 'ipessadmin/usersetup', '1'),
                    (2, 'ipessadmin/super-admin', '1'),
                    (3, 'ipessadmin/reviewer', '1'),
                    (4, 'ipessadmin/supervisor', '1'),
                    (5, 'ipessadmin/dept-admin', '1'),
                    (6, 'ipessadmin/faculty', '1'),
                    (7, 'ipessadmin/pg-admin', '1'),
                    (8, 'ipessadmin/ict-staff', '1'),
                    (9, 'ipessadmin/admin', '1'),
                    (10, 'APPLICANT/ADMISSIONS', '1'),
                    (11, 'ipessadmin/general', '1');
                ");

                // Seed page_main_menus
                $pages = [
                    // Tab 2: Reviewer Work (ID: 2)
                    [1, 'ipessadmin/reviewer/assigned-applications.php', 2, 'Assigned Applications', '1', 'link', '3'],
                    [2, 'ipessadmin/reviewer/feedback-management.php', 2, 'Feedback Management', '1', 'link', '3'],
                    [3, 'ipessadmin/reviewer/review-history.php', 2, 'Review History', '1', 'link', '3'],

                    // Tab 3: Supervision (ID: 3)
                    [4, 'ipessadmin/supervisor/my-students.php', 3, 'My Students', '1', 'link', '4'],
                    [5, 'ipessadmin/supervisor/student-interaction.php', 3, 'Student Interaction', '1', 'link', '4'],
                    [6, 'ipessadmin/supervisor/chats.php', 3, 'Chats', '1', 'link', '4'],
                    [7, 'ipessadmin/supervisor/progress-tracking.php', 3, 'Progress Tracking', '1', 'link', '4'],
                    [8, 'ipessadmin/supervisor/milestones.php', 3, 'Milestones', '1', 'link', '4'],

                    // Tab 4: Workflow & Admissions (ID: 4)
                    [9, 'ipessadmin/admin/application-management.php', 4, 'Application Management', '1', 'link', '9'],
                    [10, 'ipessadmin/admin/document-verification.php', 4, 'Document Verification', '1', 'link', '9'],
                    [11, 'ipessadmin/admin/referees.php', 4, 'Referees', '1', 'link', '9'],
                    [12, 'ipessadmin/admin/admission-decisions.php', 4, 'Admission Decisions', '1', 'link', '9'],
                    [13, 'ipessadmin/dept-admin/department-applications.php', 4, 'Department Vetting', '1', 'link', '5'],
                    [14, 'ipessadmin/faculty/applications.php', 4, 'Faculty Review', '1', 'link', '6'],
                    [15, 'ipessadmin/pg-admin/applications.php', 4, 'PG School Review', '1', 'link', '7'],
                    [16, 'ipessadmin/ict-staff/admissions.php', 4, 'Admissions Processing', '1', 'link', '8'],
                    [17, 'ipessadmin/dept-admin/supervisor-management.php', 4, 'Supervisor Assignment', '1', 'link', '5'],

                    // Tab 5: Administration (ID: 5)
                    [18, 'ipessadmin/super-admin/user-management.php', 5, 'User Management', '1', 'link', '2'],
                    [19, 'ipessadmin/super-admin/role-management.php', 5, 'Role Management', '1', 'link', '2'],
                    [20, 'ipessadmin/super-admin/manage-students.php', 5, 'Manage Students', '1', 'link', '2'],

                    // Tab 6: ICT Tools (ID: 6)
                    [21, 'ipessadmin/super-admin/activate-admissions.php', 6, 'Activate Admissions', '1', 'link', '2'],
                    [22, 'ipessadmin/super-admin/reset-authenticator.php', 6, 'Reset Authenticator', '1', 'link', '2'],
                    [23, 'ipessadmin/super-admin/modules.php', 6, 'Module Settings', '1', 'link', '2'],

                    // Tab 7: System & Intelligence (ID: 7)
                    [24, 'ipessadmin/super-admin/reports.php', 7, 'Reports', '1', 'link', '2'],
                    [25, 'ipessadmin/super-admin/audit-logs.php', 7, 'Audit Logs', '1', 'link', '2'],
                    [26, 'ipessadmin/super-admin/settings.php', 7, 'System Settings', '1', 'link', '2'],

                    // Tab 8: Developer (ID: 8)
                    [27, 'ipessadmin/usersetup/bind_roles_pages.php', 8, 'Assign Page to Role', '1', 'link', '1'],
                    [28, 'ipessadmin/usersetup/createpage.php', 8, 'Add New Page', '1', 'link', '1'],
                    [29, 'ipessadmin/usersetup/createdashboard.php', 8, 'Add Dashboard', '1', 'link', '1'],
                    [30, 'ipessadmin/usersetup/createtabs.php', 8, 'Add New Tabs', '1', 'link', '1'],
                    [31, 'ipessadmin/usersetup/createrole.php', 8, 'Add Role', '1', 'link', '1'],
                    [32, 'ipessadmin/usersetup/create_salutation.php', 8, 'Add User Title', '1', 'link', '1'],
                    [33, 'ipessadmin/usersetup/upload_menu_files.php', 8, 'Upload Project Files', '1', 'link', '1'],
                    [34, 'ipessadmin/usersetup/download_files.php', 8, 'Download Project File', '1', 'link', '1'],
                    [35, 'ipessadmin/usersetup/manage_access.php', 8, 'Manage Pages', '1', 'link', '1'],
                ];

                $stmt = $pdo->prepare("
                    INSERT INTO page_main_menus (pageID, page_url, tabID, menu_name, page_status, pageType, folder) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($pages as $p) {
                    $stmt->execute($p);
                }

                // Seed right_page_main_menus (which page belongs to which role ID)
                // roleID values: 1 (SUPER_ADMIN), 5 (HOD), 6 (COLLEGE_ADMIN), 7 (PG_ADMIN), 8 (ICT_ADMIN), 10 (SUPERVISOR), 11 (ICT_STAFF), 12 (DEVELOPER)
                $rights = [
                    // Reviewer Work (Tab 2)
                    [1, 2, 'SUPER_ADMIN', 1, 'link'],
                    [1, 2, 'ICT_ADMIN', 1, 'link'],
                    [1, 2, 'PG_ADMIN', 1, 'link'],
                    [2, 2, 'SUPER_ADMIN', 1, 'link'],
                    [2, 2, 'ICT_ADMIN', 1, 'link'],
                    [2, 2, 'PG_ADMIN', 1, 'link'],
                    [3, 2, 'SUPER_ADMIN', 1, 'link'],
                    [3, 2, 'ICT_ADMIN', 1, 'link'],
                    [3, 2, 'PG_ADMIN', 1, 'link'],

                    // Supervision (Tab 3)
                    [4, 3, 'SUPER_ADMIN', 1, 'link'],
                    [4, 3, 'SUPERVISOR', 1, 'link'],
                    [5, 3, 'SUPER_ADMIN', 1, 'link'],
                    [5, 3, 'SUPERVISOR', 1, 'link'],
                    [6, 3, 'SUPER_ADMIN', 1, 'link'],
                    [6, 3, 'SUPERVISOR', 1, 'link'],
                    [7, 3, 'SUPER_ADMIN', 1, 'link'],
                    [7, 3, 'SUPERVISOR', 1, 'link'],
                    [8, 3, 'SUPER_ADMIN', 1, 'link'],
                    [8, 3, 'SUPERVISOR', 1, 'link'],

                    // Workflow & Admissions (Tab 4)
                    [9, 4, 'SUPER_ADMIN', 1, 'link'],
                    [9, 4, 'ICT_ADMIN', 1, 'link'],
                    [9, 4, 'PG_ADMIN', 1, 'link'],
                    [10, 4, 'SUPER_ADMIN', 1, 'link'],
                    [10, 4, 'ICT_ADMIN', 1, 'link'],
                    [10, 4, 'PG_ADMIN', 1, 'link'],
                    [11, 4, 'SUPER_ADMIN', 1, 'link'],
                    [11, 4, 'ICT_ADMIN', 1, 'link'],
                    [11, 4, 'PG_ADMIN', 1, 'link'],
                    [12, 4, 'SUPER_ADMIN', 1, 'link'],
                    [12, 4, 'ICT_ADMIN', 1, 'link'],
                    [12, 4, 'PG_ADMIN', 1, 'link'],
                    [13, 4, 'SUPER_ADMIN', 1, 'link'],
                    [13, 4, 'HOD', 1, 'link'],
                    [14, 4, 'SUPER_ADMIN', 1, 'link'],
                    [14, 4, 'COLLEGE_ADMIN', 1, 'link'],
                    [15, 4, 'SUPER_ADMIN', 1, 'link'],
                    [15, 4, 'PG_ADMIN', 1, 'link'],
                    [16, 4, 'SUPER_ADMIN', 1, 'link'],
                    [16, 4, 'ICT_STAFF', 1, 'link'],
                    [17, 4, 'SUPER_ADMIN', 1, 'link'],
                    [17, 4, 'HOD', 1, 'link'],

                    // Administration (Tab 5)
                    [18, 5, 'SUPER_ADMIN', 1, 'link'],
                    [18, 5, 'ICT_ADMIN', 1, 'link'],
                    [19, 5, 'SUPER_ADMIN', 1, 'link'],
                    [19, 5, 'ICT_ADMIN', 1, 'link'],
                    [20, 5, 'SUPER_ADMIN', 1, 'link'],
                    [20, 5, 'ICT_ADMIN', 1, 'link'],

                    // ICT Tools (Tab 6)
                    [21, 6, 'SUPER_ADMIN', 1, 'link'],
                    [21, 6, 'ICT_ADMIN', 1, 'link'],
                    [21, 6, 'ICT_STAFF', 1, 'link'],
                    [22, 6, 'SUPER_ADMIN', 1, 'link'],
                    [22, 6, 'ICT_ADMIN', 1, 'link'],
                    [23, 6, 'SUPER_ADMIN', 1, 'link'],

                    // System & Intelligence (Tab 7)
                    [24, 7, 'SUPER_ADMIN', 1, 'link'],
                    [24, 7, 'ICT_ADMIN', 1, 'link'],
                    [25, 7, 'SUPER_ADMIN', 1, 'link'],
                    [25, 7, 'ICT_ADMIN', 1, 'link'],
                    [26, 7, 'SUPER_ADMIN', 1, 'link'],
                    [26, 7, 'ICT_ADMIN', 1, 'link'],

                    // Developer (Tab 8)
                    [27, 8, 'DEVELOPER', 1, 'link'],
                    [28, 8, 'DEVELOPER', 1, 'link'],
                    [29, 8, 'DEVELOPER', 1, 'link'],
                    [30, 8, 'DEVELOPER', 1, 'link'],
                    [31, 8, 'DEVELOPER', 1, 'link'],
                    [32, 8, 'DEVELOPER', 1, 'link'],
                    [33, 8, 'DEVELOPER', 1, 'link'],
                    [34, 8, 'DEVELOPER', 1, 'link'],
                    [35, 8, 'DEVELOPER', 1, 'link'],
                ];

                $stmt = $pdo->prepare("
                    INSERT INTO right_page_main_menus (pageID, tabID, roleID, page_status, pageType) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($rights as $r) {
                    $stmt->execute($r);
                }

                // Seed dash_borad
                $dashboards = [
                    ['ipessadmin/usersetup/bind_roles_pages.php', 'Developer Dashboard', 'DEVELOPER', 1, 'ipessadmin/usersetup'],
                    ['ipessadmin/super-admin/dashboard.php', 'Super Admin Dashboard', 'SUPER_ADMIN', 1, 'ipessadmin/super-admin'],
                    ['ipessadmin/ict-admin/dashboard.php', 'ICT Admin Dashboard', 'ICT_ADMIN', 1, 'ipessadmin/ict-admin'],
                    ['ipessadmin/portal-admin/dashboard.php', 'Portal Admin Dashboard', 'PORTAL_ADMIN', 1, 'ipessadmin/portal-admin'],
                    ['ipessadmin/general/dashboard.php', 'General Dashboard', 'GENERAL', 1, 'ipessadmin/general'],
                    ['APPLICANT/ADMISSIONS/dashboard.php', 'Student Dashboard', 'STUDENT', 1, 'APPLICANT/ADMISSIONS'],
                    ['ipessadmin/icto/dashboard.php', 'ICTO Dashboard', 'ICTO', 1, 'ipessadmin/icto'],
                    ['ipessadmin/faculty/dashboard.php', 'Faculty Dashboard', 'COLLEGE_ADMIN', 1, 'ipessadmin/faculty'],
                    ['ipessadmin/pg-admin/dashboard.php', 'PG School Dashboard', 'PG_ADMIN', 1, 'ipessadmin/pg-admin'],
                    ['ipessadmin/ict-staff/dashboard.php', 'ICT Staff Dashboard', 'ICT_STAFF', 1, 'ipessadmin/ict-staff'],
                    ['ipessadmin/dept-admin/dashboard.php', 'Department Dashboard', 'HOD', 1, 'ipessadmin/dept-admin'],
                    ['ipessadmin/supervisor/dashboard.php', 'Supervisor Dashboard', 'SUPERVISOR', 1, 'ipessadmin/supervisor'],
                ];
                $stmt = $pdo->prepare("
                    INSERT INTO dash_borad (pageName, PageDescription, userType, PageStatus, folder) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($dashboards as $d) {
                    $stmt->execute($d);
                }
            }
            
            // Heal any missing page_url / menu_name in right_page_main_menus and personal tables
            $pdo->exec("
                UPDATE right_page_main_menus r
                JOIN page_main_menus p ON r.pageID = p.pageID
                SET r.page_url = p.page_url, r.menu_name = p.menu_name
                WHERE r.page_url IS NULL OR r.menu_name IS NULL
            ");
            $pdo->exec("
                UPDATE pesonal_right_page_main_menus r
                JOIN page_main_menus p ON r.pageID = p.pageID
                SET r.page_url = p.page_url, r.menu_name = p.menu_name
                WHERE r.page_url IS NULL OR r.menu_name IS NULL
            ");
        } catch (Throwable $e) {
            error_log("Dynamic Sidebar Auto-Correction Error: " . $e->getMessage());
        }
    } catch (Throwable $e) {
        error_log("Database Auto-Correction Error: " . $e->getMessage());
    }
}

$pdo = db();

if (isset($pdo)) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (empty($_SESSION['db_compat_checked'])) {
            ensure_database_compatibility($pdo);
            $_SESSION['db_compat_checked'] = true;
        }
    } else {
        ensure_database_compatibility($pdo);
    }
}
