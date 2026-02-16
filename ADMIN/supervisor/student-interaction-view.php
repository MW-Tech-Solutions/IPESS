<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPERVISOR') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Student Review';
$pageSubtitle = 'Review chapter submissions and send feedback.';

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

$studentUserId = (int) ($_GET['student_user_id'] ?? 0);
$student = null;
$submissions = [];
$userId = (int) ($_SESSION['user_id'] ?? 0);
$useUserId = false;
$supervisorName = '';
$chapterTitles = [
    1 => 'Chapter 1: Introduction',
    2 => 'Chapter 2: Literature Review',
    3 => 'Chapter 3: Methodology',
    4 => 'Chapter 4: Results & Discussion',
    5 => 'Chapter 5: Conclusion & Recommendations',
    6 => 'Final Submission (Complete Copy)',
];

if ($pdo && $studentUserId > 0 && table_exists($pdo, 'supervisor_students')) {
    $useUserId = column_exists($pdo, 'supervisor_students', 'supervisor_user_id');
    $supervisorName = resolve_supervisor_name($pdo, $userId);
    
    if ($useUserId && $userId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM supervisor_students WHERE student_user_id = ? AND supervisor_user_id = ? LIMIT 1");
        $stmt->execute([$studentUserId, $userId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM supervisor_students WHERE student_user_id = ? AND supervisor_name = ? LIMIT 1");
        $stmt->execute([$studentUserId, $supervisorName]);
    }
    $student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($student && table_exists($pdo, 'chapter_submissions')) {
    $stmt = $pdo->prepare("SELECT * FROM chapter_submissions WHERE student_user_id = ? ORDER BY chapter_no ASC, version_no DESC, submitted_at DESC");
    $stmt->execute([$studentUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $latestByChapter = [];
    foreach ($rows as $row) {
        $chapterNo = (int) ($row['chapter_no'] ?? 0);
        if ($chapterNo >= 1 && $chapterNo <= 5 && !isset($latestByChapter[$chapterNo])) {
            $latestByChapter[$chapterNo] = $row;
        }
    }
    foreach ($chapterTitles as $num => $label) {
        $submissions[] = [
            'chapter_no' => $num,
            'title' => $label,
            'submission' => $latestByChapter[$num] ?? null,
        ];
    }
}
?>

<section class="page-hero">
    <div>
        <h1>Student Review</h1>
        <p class="panel-muted">Review uploads and send responses to the student.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-light" href="student-interaction.php"><i class="fas fa-arrow-left me-2"></i>Back</a>
    </div>
</section>

<?php if (!$student): ?>
    <section class="panel">
        <div class="panel-body text-muted">Student not found or not assigned to you.</div>
    </section>
<?php else: ?>
    <section class="panel">
        <div class="panel-header">
            <div>
                <h3 class="panel-title"><?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?></h3>
                <div class="panel-muted"><?php echo htmlspecialchars($student['programme'] ?? ''); ?></div>
            </div>
            <div class="panel-muted">Application: <?php echo htmlspecialchars($student['application_number'] ?? $student['student_id'] ?? ''); ?></div>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Chapter</th>
                            <th>Status</th>
                            <th>Last Submission</th>
                            <th>File</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $item): ?>
                            <?php $sub = $item['submission']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['title']); ?></td>
                                <td><?php echo htmlspecialchars($sub['status'] ?? 'Not Submitted'); ?></td>
                                <td><?php echo !empty($sub['submitted_at']) ? date('Y-m-d H:i', strtotime($sub['submitted_at'])) : '—'; ?></td>
                                <td>
                                    <?php if (!empty($sub['file_path'])): ?>
                                        <a href="preview-chapter.php?id=<?php echo (int) $sub['id']; ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($sub): ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#review-<?php echo (int) $sub['id']; ?>">Review</button>
                                    <?php else: ?>
                                        <span class="text-muted">Waiting upload</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($sub): ?>
                                <tr class="collapse" id="review-<?php echo (int) $sub['id']; ?>">
                                    <td colspan="5">
                                        <form class="review-form" data-submission-id="<?php echo (int) $sub['id']; ?>">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" name="status">
                                                        <?php $currentStatus = $sub['status'] ?? 'Under Review'; ?>
                                                        <option value="Under Review" <?php echo $currentStatus === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                                        <option value="Changes Requested" <?php echo $currentStatus === 'Changes Requested' ? 'selected' : ''; ?>>Changes Requested</option>
                                                        <option value="Approved" <?php echo $currentStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Supervisor Note</label>
                                                    <input class="form-control" type="text" name="note" value="<?php echo htmlspecialchars($sub['supervisor_note'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Attach Review (optional)</label>
                                                    <input class="form-control" type="file" name="review_file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                                </div>
                                            </div>
                                            <div class="mt-3 d-flex justify-content-between align-items-center">
                                                <div class="text-muted small review-feedback"></div>
                                                <button type="submit" class="btn btn-primary">Save Review</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h3 class="panel-title">Send Notification</h3>
        </div>
        <div class="panel-body">
            <form id="notificationForm">
                <input type="hidden" name="student_user_id" value="<?php echo (int) $studentUserId; ?>">
                <div class="mb-3">
                    <label class="form-label">Notification Title</label>
                    <input class="form-control" type="text" name="title" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notification Message</label>
                    <textarea class="form-control" name="message" rows="3" required></textarea>
                </div>
                <button class="btn btn-outline-secondary" type="submit">Send Notification</button>
                <div class="text-muted small mt-2" id="notificationFeedback"></div>
            </form>
        </div>
    </section>

    <script>
    document.querySelectorAll('.review-form').forEach(form => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const feedback = form.querySelector('.review-feedback');
            const data = new FormData(form);
            data.append('submission_id', form.dataset.submissionId);
            try {
                const res = await fetch('handlers/review-chapter.php', { method: 'POST', body: data });
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Unable to save review.');
                if (feedback) {
                    feedback.textContent = 'Review saved.';
                }
            } catch (err) {
                if (feedback) feedback.textContent = err.message;
            }
        });
    });

    document.getElementById('notificationForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const feedback = document.getElementById('notificationFeedback');
        const data = new FormData(event.target);
        try {
            const res = await fetch('handlers/send-notification.php', { method: 'POST', body: data });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Unable to send notification.');
            if (feedback) feedback.textContent = 'Notification sent.';
            event.target.reset();
        } catch (err) {
            if (feedback) feedback.textContent = err.message;
        }
    });

    </script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
