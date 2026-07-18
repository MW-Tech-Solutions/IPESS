<?php
// APPLICANT/ADMISSIONS/includes/register_user_helper.php

function register_new_student(PDO $pdo, array $signupData) {
    try {
        $hash = password_hash($signupData['password'], PASSWORD_DEFAULT);
        $fullName = trim(($signupData['surname'] ?? '') . ' ' . ($signupData['first_name'] ?? '') . ' ' . ($signupData['other_name'] ?? ''));
        $roleValue = 'STUDENT';
        $hasRole = false;
        $hasRoleId = false;
        $userId = null;

        $columnExists = static function (PDO $pdo, string $table, string $column): bool {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return (bool) $stmt->fetchColumn();
        };

        $tableExists = static function (PDO $pdo, string $table): bool {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        };

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'");
        $stmt->execute();
        $hasRole = (bool) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role_id'");
        $stmt->execute();
        $hasRoleId = (bool) $stmt->fetchColumn();

        $hasFullName = $columnExists($pdo, 'users', 'full_name');

        if ($hasRole) {
            if ($hasFullName) {
                $stmt = $pdo->prepare("INSERT INTO users (email, full_name, password_hash, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$signupData['email'], $fullName, $hash, $roleValue]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
                $stmt->execute([$signupData['email'], $hash, $roleValue]);
            }
        } elseif ($hasRoleId) {
            // Ensure STUDENT role exists (auto-seed if roles table is empty on remote server)
            $pdo->exec("INSERT IGNORE INTO roles (role_key, role_name) VALUES ('STUDENT', 'Student')");

            $roleId = null;
            $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE role_key = ? LIMIT 1");
            $roleStmt->execute([$roleValue]);
            $roleId = $roleStmt->fetchColumn();

            if ($roleId) {
                if ($hasFullName) {
                    $stmt = $pdo->prepare("INSERT INTO users (email, full_name, password_hash, role_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$signupData['email'], $fullName, $hash, $roleId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role_id) VALUES (?, ?, ?)");
                    $stmt->execute([$signupData['email'], $hash, $roleId]);
                }
            } else {
                if ($hasFullName) {
                    $stmt = $pdo->prepare("INSERT INTO users (email, full_name, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([$signupData['email'], $fullName, $hash]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
                    $stmt->execute([$signupData['email'], $hash]);
                }
            }
        } else {
            if ($hasFullName) {
                $stmt = $pdo->prepare("INSERT INTO users (email, full_name, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$signupData['email'], $fullName, $hash]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
                $stmt->execute([$signupData['email'], $hash]);
            }
        }

        $userId = (int) $pdo->lastInsertId();

        if ($tableExists($pdo, 'applications')) {
            $applicationNumber = 'APP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare("INSERT INTO applications (user_id, application_number, status, current_step) VALUES (?, ?, 'Draft', 1)");
            $stmt->execute([$userId, $applicationNumber]);
            $applicationId = (int) $pdo->lastInsertId();

            if ($tableExists($pdo, 'personal_details')) {
                $stmt = $pdo->prepare("
                    INSERT INTO personal_details (application_id, surname, first_name, other_name, dob, phone)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $applicationId,
                    $signupData['surname'] ?? '',
                    $signupData['first_name'] ?? '',
                    ($signupData['other_name'] ?? null) ?: null,
                    '1900-01-01',
                    $signupData['phone'] ?? '',
                ]);
            }

            if ($tableExists($pdo, 'programme_choices')) {
                $faculty = (int) ($signupData['faculty'] ?? 0);
                $department = (int) ($signupData['department'] ?? 0);
                $course = (int) ($signupData['programme'] ?? 0);
                $degree = (int) ($signupData['programme_option'] ?? 0);
                $mode = (int) ($signupData['mode_of_study'] ?? 0);

                if (($faculty <= 0 || $department <= 0) && $course > 0 && $tableExists($pdo, 'courses')) {
                    $stmt = $pdo->prepare("
                        SELECT c.dept_id, d.faculty_id
                        FROM courses c
                        LEFT JOIN departments d ON d.dept_id = c.dept_id
                        WHERE c.course_id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$course]);
                    $courseMeta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $department = $department > 0 ? $department : (int) ($courseMeta['dept_id'] ?? 0);
                    $faculty = $faculty > 0 ? $faculty : (int) ($courseMeta['faculty_id'] ?? 0);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO programme_choices (application_id, faculty, department, degree_type, mode_of_study, course)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $applicationId,
                    $faculty ?: null,
                    $department ?: null,
                    $degree ?: null,
                    $mode ?: null,
                    $course ?: null,
                ]);
            }
        }

        $pdo->commit();
        return ['success' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
