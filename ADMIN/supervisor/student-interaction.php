<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Student Interaction';
$pageSubtitle = 'Review chapter submissions, send feedback, and message students.';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
require_once __DIR__ . '/../admin/includes/db.php';

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function resolve_supervisor_name(PDO $pdo, int $userId): string {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $name = trim((string) ($row['full_name'] ?? ''));
    $email = trim((string) ($row['email'] ?? ''));
    return $name !== '' ? $name : $email;
}

$students = [];
if ($pdo && table_exists($pdo, 'supervisor_students')) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $useUserId = column_exists($pdo, 'supervisor_students', 'supervisor_user_id');
    $supervisorName = resolve_supervisor_name($pdo, $userId);

    if ($useUserId && $userId > 0) {
        $stmt = $pdo->prepare("SELECT student_user_id, student_id, full_name, programme, status, last_submission FROM supervisor_students WHERE supervisor_user_id = ? ORDER BY full_name ASC");
        $stmt->execute([$userId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($supervisorName !== '') {
        $stmt = $pdo->prepare("SELECT student_user_id, student_id, full_name, programme, status, last_submission FROM supervisor_students WHERE supervisor_name = ? ORDER BY full_name ASC");
        $stmt->execute([$supervisorName]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<section class="page-hero">
    <div>
        <h1>Student Interaction</h1>
        <p class="panel-muted">Open a student profile to review chapters, approve, and send feedback.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-light" onclick="location.reload()"><i class="fas fa-sync me-2"></i>Refresh</button>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Assigned Students</h3>
            <div class="panel-muted">Students under your supervision.</div>
        </div>
    </div>
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Programme</th>
                        <th>Status</th>
                        <th>Last Submission</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students): ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['programme'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['status'] ?? 'Pending'); ?></td>
                                <td><?php echo !empty($student['last_submission']) ? date('Y-m-d H:i', strtotime($student['last_submission'])) : '—'; ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="student-interaction-view.php?student_user_id=<?php echo (int) ($student['student_user_id'] ?? 0); ?>">
                                        View
                                    </a>
                                    <a class="btn btn-sm btn-outline-secondary" href="chats.php?student_user_id=<?php echo (int) ($student['student_user_id'] ?? 0); ?>#messages">
                                        Messages
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-muted text-center">No students assigned yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
