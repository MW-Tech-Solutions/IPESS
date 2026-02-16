<?php
$pageTitle = 'Chats';
$pageSubtitle = 'Message students in real time from one dedicated chat workspace.';

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
$students = [];
$messages = [];
$activeStudent = null;

if ($pdo && table_exists($pdo, 'supervisor_students')) {
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $useUserId = column_exists($pdo, 'supervisor_students', 'supervisor_user_id');
    $supervisorName = resolve_supervisor_name($pdo, $userId);

    if ($useUserId && $userId > 0) {
        $stmt = $pdo->prepare("
            SELECT student_user_id, student_id, full_name, programme, application_number
            FROM supervisor_students
            WHERE supervisor_user_id = ? AND student_user_id IS NOT NULL
            ORDER BY full_name ASC
        ");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT student_user_id, student_id, full_name, programme, application_number
            FROM supervisor_students
            WHERE supervisor_name = ? AND student_user_id IS NOT NULL
            ORDER BY full_name ASC
        ");
        $stmt->execute([$supervisorName]);
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

if ($studentUserId <= 0 && !empty($students)) {
    $studentUserId = (int) ($students[0]['student_user_id'] ?? 0);
}

foreach ($students as $st) {
    if ((int) ($st['student_user_id'] ?? 0) === $studentUserId) {
        $activeStudent = $st;
        break;
    }
}

if ($activeStudent && $pdo && table_exists($pdo, 'supervisor_messages')) {
    $stmt = $pdo->prepare("
        SELECT sender_role, subject, message, created_at
        FROM supervisor_messages
        WHERE student_user_id = ?
        ORDER BY created_at ASC
        LIMIT 200
    ");
    $stmt->execute([$studentUserId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<section class="page-hero">
    <div>
        <h1>Chats</h1>
        <p class="panel-muted">Choose a student from the left and continue the supervision conversation.</p>
    </div>
</section>

<section class="panel" id="messages">
    <div class="panel-body p-0">
        <div class="sup-chat-layout">
            <aside class="sup-chat-sidebar">
                <h4 class="sup-chat-sidebar-title">Conversations</h4>
                <div class="sup-chat-list">
                    <?php if ($students): ?>
                        <?php foreach ($students as $student): ?>
                            <?php
                                $sid = (int) ($student['student_user_id'] ?? 0);
                                $isActive = $sid === $studentUserId;
                                $name = (string) ($student['full_name'] ?? 'Student');
                                $sub = (string) ($student['application_number'] ?? ($student['programme'] ?? ''));
                            ?>
                            <a class="sup-chat-contact <?php echo $isActive ? 'active' : ''; ?>" href="chats.php?student_user_id=<?php echo $sid; ?>#messages">
                                <div class="sup-chat-avatar"><?php echo htmlspecialchars(strtoupper(substr($name, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="sup-chat-contact-meta">
                                    <div class="sup-chat-contact-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="sup-chat-contact-preview"><?php echo htmlspecialchars($sub, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small">No assigned students.</div>
                    <?php endif; ?>
                </div>
            </aside>

            <section class="sup-chat-main">
                <header class="sup-chat-header">
                    <?php if ($activeStudent): ?>
                        <h5 class="mb-1"><?php echo htmlspecialchars((string) ($activeStudent['full_name'] ?? 'Student'), ENT_QUOTES, 'UTF-8'); ?></h5>
                        <div class="text-muted small"><?php echo htmlspecialchars((string) ($activeStudent['programme'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php else: ?>
                        <h5 class="mb-1">No Student Selected</h5>
                        <div class="text-muted small">Select a student from the conversations list.</div>
                    <?php endif; ?>
                </header>

                <div class="sup-chat-thread" id="supervisorChatThread">
                    <?php if ($activeStudent): ?>
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php
                                    $senderRole = strtoupper((string) ($msg['sender_role'] ?? 'STUDENT'));
                                    $isSupervisor = $senderRole === 'SUPERVISOR';
                                    $msgFrom = $isSupervisor ? 'You' : 'Student';
                                    $msgTime = !empty($msg['created_at']) ? date('M d, Y H:i', strtotime((string) $msg['created_at'])) : '';
                                ?>
                                <div class="sup-chat-row <?php echo $isSupervisor ? 'mine' : 'theirs'; ?>">
                                    <div class="sup-chat-bubble">
                                        <div class="sup-chat-meta">
                                            <span><?php echo htmlspecialchars($msgFrom, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <time><?php echo htmlspecialchars($msgTime, ENT_QUOTES, 'UTF-8'); ?></time>
                                        </div>
                                        <?php if (!empty($msg['subject'])): ?>
                                            <div class="sup-chat-subject"><?php echo htmlspecialchars((string) $msg['subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <div class="sup-chat-text"><?php echo nl2br(htmlspecialchars((string) ($msg['message'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted">No messages yet.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($activeStudent): ?>
                    <form id="messageForm" class="sup-chat-composer">
                        <input type="hidden" name="student_user_id" value="<?php echo (int) $studentUserId; ?>">
                        <div class="mb-2">
                            <input class="form-control" type="text" name="subject" placeholder="Message subject" value="Update" required>
                        </div>
                        <div class="d-flex gap-2 align-items-end">
                            <textarea class="form-control" name="message" rows="2" placeholder="Type message to student..." required></textarea>
                            <button class="btn btn-success sup-chat-send-btn" type="submit"><i class="fas fa-paper-plane me-1"></i>Send</button>
                        </div>
                        <div class="text-muted small mt-2" id="messageFeedback"></div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<style>
.sup-chat-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    min-height: 620px;
}
.sup-chat-sidebar {
    border-right: 1px solid #e2e8f0;
    background: #f7fafc;
    padding: 1rem;
}
.sup-chat-sidebar-title {
    font-size: 0.9rem;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.04em;
    margin-bottom: 0.8rem;
}
.sup-chat-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    max-height: 520px;
    overflow-y: auto;
}
.sup-chat-contact {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.75rem;
    border-radius: 12px;
    background: #edf7f1;
    border: 1px solid #d5eadc;
    color: inherit;
    text-decoration: none;
    transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.sup-chat-contact:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 14px rgba(21, 128, 61, 0.1);
    color: inherit;
}
.sup-chat-contact.active {
    background: #dff5e8;
    border-color: #bfe7cd;
}
.sup-chat-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #d8f3df;
    color: #166534;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}
.sup-chat-contact-name {
    font-weight: 600;
    color: #0f172a;
}
.sup-chat-contact-preview {
    font-size: 0.82rem;
    color: #475569;
}
.sup-chat-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.sup-chat-header {
    padding: 1rem 1.1rem;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}
.sup-chat-thread {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(22, 163, 74, 0.06), rgba(34, 197, 94, 0.04)), #f8fbf8;
}
.sup-chat-row {
    display: flex;
    margin-bottom: 0.8rem;
}
.sup-chat-row.theirs {
    justify-content: flex-start;
}
.sup-chat-row.mine {
    justify-content: flex-end;
}
.sup-chat-bubble {
    max-width: min(78%, 620px);
    border-radius: 16px;
    padding: 0.65rem 0.8rem;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
}
.sup-chat-row.theirs .sup-chat-bubble {
    background: #ffffff;
    border: 1px solid #e4ebf3;
    border-top-left-radius: 6px;
}
.sup-chat-row.mine .sup-chat-bubble {
    background: #198754;
    border: 1px solid #157347;
    border-top-right-radius: 6px;
}
.sup-chat-meta {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.72rem;
    color: #6b7280;
    margin-bottom: 0.2rem;
}
.sup-chat-row.mine .sup-chat-meta,
.sup-chat-row.mine .sup-chat-subject,
.sup-chat-row.mine .sup-chat-text {
    color: rgba(255, 255, 255, 0.95);
}
.sup-chat-subject {
    font-size: 0.82rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.2rem;
}
.sup-chat-text {
    font-size: 0.9rem;
    color: #1f2937;
    line-height: 1.45;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.sup-chat-composer {
    border-top: 1px solid #e2e8f0;
    padding: 0.85rem 1rem 1rem;
    background: #ffffff;
}
.sup-chat-send-btn {
    height: 42px;
    border-radius: 10px;
    min-width: 95px;
}
@media (max-width: 991.98px) {
    .sup-chat-layout {
        grid-template-columns: 1fr;
    }
    .sup-chat-sidebar {
        border-right: 0;
        border-bottom: 1px solid #e2e8f0;
    }
}
</style>

<script>
document.getElementById('messageForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const feedback = document.getElementById('messageFeedback');
    const data = new FormData(event.target);
    try {
        const res = await fetch('handlers/send-message.php', { method: 'POST', body: data });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Unable to send message.');
        if (feedback) feedback.textContent = 'Message sent.';
        event.target.reset();
        window.location.reload();
    } catch (err) {
        if (feedback) feedback.textContent = err.message;
    }
});

(function () {
    const thread = document.getElementById('supervisorChatThread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>

