<?php
// Database connection
$host = '127.0.0.1';
$dbname = 'jostum';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

class DashboardController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getSystemOverview() {
        // Use prepared statements
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM applications");
        $stmt->execute();
        $total_applications = $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE status = 'admitted'");
        $stmt->execute();
        $total_admitted = $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'supervisor'");
        $stmt->execute();
        $total_supervisors = $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
        $stmt->execute();
        $total_admins = $stmt->fetch()['total'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM departments");
        $stmt->execute();
        $total_departments = $stmt->fetch()['total'];

        return [
            'total_applications' => $total_applications,
            'total_admitted' => $total_admitted,
            'total_supervisors' => $total_supervisors,
            'total_admins' => $total_admins,
            'total_departments' => $total_departments
        ];
    }

    public function getUsers() {
        $stmt = $this->pdo->prepare("SELECT id, name, role, status FROM users WHERE role IN ('admin', 'department_admin')");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApplications($filters = []) {
        $query = "SELECT id, applicant_name as applicant, status, department, programme FROM applications";
        $conditions = [];
        $params = [];

        if (!empty($filters['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['department'])) {
            $conditions[] = "department = ?";
            $params[] = $filters['department'];
        }
        if (!empty($filters['programme'])) {
            $conditions[] = "programme = ?";
            $params[] = $filters['programme'];
        }

        if ($conditions) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuditLogs() {
        $cols = $this->auditColumns();
        if (in_array('event', $cols, true)) {
            $stmt = $this->pdo->prepare("SELECT event, user, timestamp FROM audit_logs ORDER BY timestamp DESC LIMIT 100");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (in_array('action', $cols, true)) {
            $stmt = $this->pdo->prepare("SELECT action AS event, user_id AS user, created_at AS timestamp FROM audit_logs ORDER BY created_at DESC LIMIT 100");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    public function createAdmin($data) {
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, role, status) VALUES (?, ?, 'admin', 'active')");
        $stmt->execute([$data['name'], $data['email']]);
        return ['success' => true, 'message' => 'Admin created'];
    }

    public function createDeptAdmin($data) {
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, role, department, status) VALUES (?, ?, 'department_admin', ?, 'active')");
        $stmt->execute([$data['name'], $data['email'], $data['department']]);
        return ['success' => true, 'message' => 'Department Admin created'];
    }

    public function changeRole($userId, $newRole) {
        $stmt = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$newRole, $userId]);
        // Log to audit
        $this->logAudit('Role Change', $_SESSION['user_id'], "Changed role of user $userId to $newRole");
        return ['success' => true, 'message' => 'Role changed'];
    }

    public function toggleStatus($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'suspended' ELSE 'active' END WHERE id = ?");
        $stmt->execute([$userId]);
        $this->logAudit('Status Change', $_SESSION['user_id'], "Toggled status of user $userId");
        return ['success' => true, 'message' => 'Status updated'];
    }

    private function logAudit($event, $user, $details) {
        $cols = $this->auditColumns();
        if (in_array('event', $cols, true)) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO audit_logs (event, user, details, timestamp) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$event, $user, $details]);
                return;
            } catch (PDOException $e) {
                // Fall back to legacy insert.
            }
        }
        if (in_array('action', $cols, true)) {
            $stmt = $this->pdo->prepare("INSERT INTO audit_logs (action, user_id, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$event, $user, $details]);
        }
    }

    private function auditColumns() {
        $stmt = $this->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'audit_logs'");
        $stmt->execute();
        return array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
?>
