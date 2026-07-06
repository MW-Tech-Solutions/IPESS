<?php
$pageTitle = 'Content Management';
$pageSubtitle = 'Manage programmes, notices, requirements, important dates, and FAQs for the admissions page.';

require_once 'includes/db.php';

$flash = ['type' => '', 'message' => ''];
$editEntity = $_GET['edit_entity'] ?? '';
$editId = (int) ($_GET['edit_id'] ?? 0);
$editRecord = null;

$entityMap = [
    'programme' => ['table' => 'admissions_programmes', 'pk' => 'programme_id', 'fields' => ['title', 'description', 'sort_order', 'is_active']],
    'requirement' => ['table' => 'admissions_requirements', 'pk' => 'requirement_id', 'fields' => ['title', 'description', 'sort_order', 'is_active']],
    'date' => ['table' => 'admissions_important_dates', 'pk' => 'date_id', 'fields' => ['title', 'event_date', 'sort_order', 'is_active']],
    'faq' => ['table' => 'admissions_faqs', 'pk' => 'faq_id', 'fields' => ['question', 'answer', 'sort_order', 'is_active']],
    'notice' => ['table' => 'admissions_notices', 'pk' => 'notice_id', 'fields' => ['title', 'message', 'button_label', 'button_url', 'sort_order', 'starts_at', 'ends_at', 'is_active']],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $entity = $_POST['entity'] ?? '';
    $action = $_POST['action'] ?? 'save';

    try {
        if (!isset($entityMap[$entity])) {
            throw new RuntimeException('Invalid content type.');
        }

        $config = $entityMap[$entity];
        $table = $config['table'];
        $pk = $config['pk'];

        if (!admissions_content_table_exists($pdo, $table)) {
            throw new RuntimeException('Admissions content tables are missing. Run the migration first.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid record selected for deletion.');
            }
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE {$pk} = ?");
            $stmt->execute([$id]);
            $flash = ['type' => 'success', 'message' => 'Record deleted successfully.'];
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $data = [];
            foreach ($config['fields'] as $field) {
                if ($field === 'is_active') {
                    $data[$field] = isset($_POST[$field]) ? 1 : 0;
                    continue;
                }
                $data[$field] = trim((string) ($_POST[$field] ?? ''));
            }

            if (in_array($entity, ['programme', 'requirement'], true) && $data['title'] === '') {
                throw new RuntimeException('Title is required.');
            }
            if ($entity === 'date' && ($data['title'] === '' || $data['event_date'] === '')) {
                throw new RuntimeException('Date title and event date are required.');
            }
            if ($entity === 'faq' && ($data['question'] === '' || $data['answer'] === '')) {
                throw new RuntimeException('Question and answer are required.');
            }
            if ($entity === 'notice' && ($data['title'] === '' || $data['message'] === '')) {
                throw new RuntimeException('Notice title and message are required.');
            }

            foreach (['starts_at', 'ends_at', 'button_label', 'button_url'] as $nullableField) {
                if (array_key_exists($nullableField, $data) && $data[$nullableField] === '') {
                    $data[$nullableField] = null;
                }
            }

            $columns = array_keys($data);
            if ($id > 0) {
                $setClause = implode(', ', array_map(static fn($col) => "{$col} = ?", $columns));
                $values = array_values($data);
                $values[] = $id;
                $stmt = $pdo->prepare("UPDATE {$table} SET {$setClause} WHERE {$pk} = ?");
                $stmt->execute($values);
                $flash = ['type' => 'success', 'message' => 'Record updated successfully.'];
            } else {
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $columnList = implode(', ', $columns);
                $stmt = $pdo->prepare("INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})");
                $stmt->execute(array_values($data));
                $flash = ['type' => 'success', 'message' => 'Record created successfully.'];
            }
        }
    } catch (Throwable $e) {
        $flash = ['type' => 'danger', 'message' => $e->getMessage()];
    }
}

if ($pdo && $editEntity !== '' && isset($entityMap[$editEntity]) && $editId > 0) {
    try {
        $config = $entityMap[$editEntity];
        if (admissions_content_table_exists($pdo, $config['table'])) {
            $stmt = $pdo->prepare("SELECT * FROM {$config['table']} WHERE {$config['pk']} = ? LIMIT 1");
            $stmt->execute([$editId]);
            $editRecord = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $e) {
    }
}

$programmes = $requirements = $importantDates = $faqs = $notices = [];
if ($pdo) {
    try {
        if (admissions_content_table_exists($pdo, 'admissions_programmes')) {
            $programmes = $pdo->query("SELECT * FROM admissions_programmes ORDER BY sort_order ASC, programme_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (admissions_content_table_exists($pdo, 'admissions_requirements')) {
            $requirements = $pdo->query("SELECT * FROM admissions_requirements ORDER BY sort_order ASC, requirement_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (admissions_content_table_exists($pdo, 'admissions_important_dates')) {
            $importantDates = $pdo->query("SELECT * FROM admissions_important_dates ORDER BY sort_order ASC, event_date ASC, date_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (admissions_content_table_exists($pdo, 'admissions_faqs')) {
            $faqs = $pdo->query("SELECT * FROM admissions_faqs ORDER BY sort_order ASC, faq_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (admissions_content_table_exists($pdo, 'admissions_notices')) {
            $notices = $pdo->query("SELECT * FROM admissions_notices ORDER BY sort_order ASC, notice_id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        if ($flash['message'] === '') {
            $flash = ['type' => 'danger', 'message' => $e->getMessage()];
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';

function form_value(?array $record, string $field, string $default = ''): string
{
    if ($record && in_array($field, ['starts_at', 'ends_at'], true) && !empty($record[$field])) {
        return htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $record[$field])), ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars((string) ($record[$field] ?? $default), ENT_QUOTES, 'UTF-8');
}

function checked_value(?array $record, string $field, bool $default = true): string
{
    $value = $record[$field] ?? ($default ? 1 : 0);
    return (int) $value === 1 ? 'checked' : '';
}
?>

<section class="page-hero">
    <div>
        <h1>Admissions Content Manager</h1>
        <p class="panel-muted">All key sections under the public admissions page are editable here by the portal-admin role.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ADMISSIONS/index.php')); ?>" target="_blank">View Public Page</a>
    </div>
</section>

<?php if ($flash['message'] !== ''): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
<?php endif; ?>

<section class="panel form-section" id="programmes-section">
    <div class="panel-header"><div><h3 class="panel-title">Programmes</h3><div class="panel-muted">Manage the cards shown under the Programmes section.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="entity" value="programme">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editEntity === 'programme' ? (int) ($editRecord['programme_id'] ?? 0) : 0; ?>">
            <div class="col-md-4"><label class="form-label">Title</label><input type="text" class="form-control" name="title" value="<?php echo $editEntity === 'programme' ? form_value($editRecord, 'title') : ''; ?>" required></div>
            <div class="col-md-5"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?php echo $editEntity === 'programme' ? form_value($editRecord, 'description') : ''; ?></textarea></div>
            <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo $editEntity === 'programme' ? form_value($editRecord, 'sort_order', '0') : '0'; ?>"></div>
            <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $editEntity === 'programme' ? checked_value($editRecord, 'is_active') : 'checked'; ?>><label class="form-check-label">Active</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo $editEntity === 'programme' ? 'Update Programme' : 'Add Programme'; ?></button><?php if ($editEntity === 'programme'): ?><a class="btn btn-light" href="content-management.php">Cancel</a><?php endif; ?></div>
        </form>
        <div class="table-responsive mt-4">
            <table class="table align-middle">
                <thead><tr><th>Title</th><th>Description</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($programmes as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo (int) $row['sort_order']; ?></td>
                        <td><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                        <td class="text-end">
                            <a class="btn btn-light btn-sm" href="content-management.php?edit_entity=programme&edit_id=<?php echo (int) $row['programme_id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this programme?');">
                                <input type="hidden" name="entity" value="programme">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $row['programme_id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel form-section" id="notices-section">
    <div class="panel-header"><div><h3 class="panel-title">Quick Notice Modal</h3><div class="panel-muted">Active notices appear as a modal when applicants visit the page.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="entity" value="notice">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editEntity === 'notice' ? (int) ($editRecord['notice_id'] ?? 0) : 0; ?>">
            <div class="col-md-4"><label class="form-label">Title</label><input type="text" class="form-control" name="title" value="<?php echo $editEntity === 'notice' ? form_value($editRecord, 'title') : ''; ?>" required></div>
            <div class="col-md-8"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="2" required><?php echo $editEntity === 'notice' ? form_value($editRecord, 'message') : ''; ?></textarea></div>
            <div class="col-md-3"><label class="form-label">Button Label</label><input type="text" class="form-control" name="button_label" value="<?php echo $editEntity === 'notice' ? form_value($editRecord, 'button_label') : ''; ?>"></div>
            <div class="col-md-5"><label class="form-label">Button URL</label><input type="text" class="form-control" name="button_url" value="<?php echo $editEntity === 'notice' ? form_value($editRecord, 'button_url') : ''; ?>"></div>
            <div class="col-md-2"><label class="form-label">Starts At</label><input type="datetime-local" class="form-control" name="starts_at" value="<?php echo $editEntity === 'notice' ? form_value($editRecord, 'starts_at') : ''; ?>"></div>
            <div class="col-md-2"><label class="form-label">Ends At</label><input type="datetime-local" class="form-control" name="ends_at" value="<?php echo $editEntity === 'notice' ? form_value($editRecord, 'ends_at') : ''; ?>"></div>
            <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo $editEntity === 'notice' ? form_value($editRecord, 'sort_order', '0') : '0'; ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $editEntity === 'notice' ? checked_value($editRecord, 'is_active') : 'checked'; ?>><label class="form-check-label">Active</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo $editEntity === 'notice' ? 'Update Notice' : 'Add Notice'; ?></button><?php if ($editEntity === 'notice'): ?><a class="btn btn-light" href="content-management.php">Cancel</a><?php endif; ?></div>
        </form>
        <div class="table-responsive mt-4">
            <table class="table align-middle">
                <thead><tr><th>Title</th><th>Window</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($notices as $row): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['title']); ?></strong><div class="text-muted small"><?php echo htmlspecialchars($row['message']); ?></div></td>
                        <td><?php echo htmlspecialchars($row['starts_at'] ?: 'Immediate'); ?> to <?php echo htmlspecialchars($row['ends_at'] ?: 'No expiry'); ?></td>
                        <td><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                        <td class="text-end">
                            <a class="btn btn-light btn-sm" href="content-management.php?edit_entity=notice&edit_id=<?php echo (int) $row['notice_id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this notice?');">
                                <input type="hidden" name="entity" value="notice">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $row['notice_id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel form-section" id="requirements-section">
    <div class="panel-header"><div><h3 class="panel-title">Admission Requirements</h3><div class="panel-muted">This replaces the old hard-coded postgraduate-only requirements text.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="entity" value="requirement">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editEntity === 'requirement' ? (int) ($editRecord['requirement_id'] ?? 0) : 0; ?>">
            <div class="col-md-4"><label class="form-label">Title</label><input type="text" class="form-control" name="title" value="<?php echo $editEntity === 'requirement' ? form_value($editRecord, 'title') : ''; ?>" required></div>
            <div class="col-md-5"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?php echo $editEntity === 'requirement' ? form_value($editRecord, 'description') : ''; ?></textarea></div>
            <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo $editEntity === 'requirement' ? form_value($editRecord, 'sort_order', '0') : '0'; ?>"></div>
            <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $editEntity === 'requirement' ? checked_value($editRecord, 'is_active') : 'checked'; ?>><label class="form-check-label">Active</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo $editEntity === 'requirement' ? 'Update Requirement' : 'Add Requirement'; ?></button><?php if ($editEntity === 'requirement'): ?><a class="btn btn-light" href="content-management.php">Cancel</a><?php endif; ?></div>
        </form>
        <div class="table-responsive mt-4">
            <table class="table align-middle">
                <thead><tr><th>Title</th><th>Description</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($requirements as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><?php echo (int) $row['sort_order']; ?></td>
                        <td><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                        <td class="text-end">
                            <a class="btn btn-light btn-sm" href="content-management.php?edit_entity=requirement&edit_id=<?php echo (int) $row['requirement_id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this requirement?');">
                                <input type="hidden" name="entity" value="requirement">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $row['requirement_id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel form-section" id="dates-section">
    <div class="panel-header"><div><h3 class="panel-title">Important Dates</h3><div class="panel-muted">Maintain official admissions dates without touching code.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="entity" value="date">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editEntity === 'date' ? (int) ($editRecord['date_id'] ?? 0) : 0; ?>">
            <div class="col-md-4"><label class="form-label">Title</label><input type="text" class="form-control" name="title" value="<?php echo $editEntity === 'date' ? form_value($editRecord, 'title') : ''; ?>" required></div>
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" class="form-control" name="event_date" value="<?php echo $editEntity === 'date' ? form_value($editRecord, 'event_date') : ''; ?>" required></div>
            <div class="col-md-3"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo $editEntity === 'date' ? form_value($editRecord, 'sort_order', '0') : '0'; ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $editEntity === 'date' ? checked_value($editRecord, 'is_active') : 'checked'; ?>><label class="form-check-label">Active</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo $editEntity === 'date' ? 'Update Date' : 'Add Date'; ?></button><?php if ($editEntity === 'date'): ?><a class="btn btn-light" href="content-management.php">Cancel</a><?php endif; ?></div>
        </form>
        <div class="table-responsive mt-4">
            <table class="table align-middle">
                <thead><tr><th>Title</th><th>Date</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($importantDates as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['event_date']); ?></td>
                        <td><?php echo (int) $row['sort_order']; ?></td>
                        <td><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                        <td class="text-end">
                            <a class="btn btn-light btn-sm" href="content-management.php?edit_entity=date&edit_id=<?php echo (int) $row['date_id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this date?');">
                                <input type="hidden" name="entity" value="date">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $row['date_id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel form-section" id="faqs-section">
    <div class="panel-header"><div><h3 class="panel-title">FAQs</h3><div class="panel-muted">Manage the frequently asked questions displayed to applicants.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="entity" value="faq">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $editEntity === 'faq' ? (int) ($editRecord['faq_id'] ?? 0) : 0; ?>">
            <div class="col-md-4"><label class="form-label">Question</label><input type="text" class="form-control" name="question" value="<?php echo $editEntity === 'faq' ? form_value($editRecord, 'question') : ''; ?>" required></div>
            <div class="col-md-5"><label class="form-label">Answer</label><textarea class="form-control" name="answer" rows="2" required><?php echo $editEntity === 'faq' ? form_value($editRecord, 'answer') : ''; ?></textarea></div>
            <div class="col-md-2"><label class="form-label">Sort Order</label><input type="number" class="form-control" name="sort_order" value="<?php echo $editEntity === 'faq' ? form_value($editRecord, 'sort_order', '0') : '0'; ?>"></div>
            <div class="col-md-1 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?php echo $editEntity === 'faq' ? checked_value($editRecord, 'is_active') : 'checked'; ?>><label class="form-check-label">Active</label></div></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><?php echo $editEntity === 'faq' ? 'Update FAQ' : 'Add FAQ'; ?></button><?php if ($editEntity === 'faq'): ?><a class="btn btn-light" href="content-management.php">Cancel</a><?php endif; ?></div>
        </form>
        <div class="table-responsive mt-4">
            <table class="table align-middle">
                <thead><tr><th>Question</th><th>Answer</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($faqs as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['question']); ?></td>
                        <td><?php echo htmlspecialchars($row['answer']); ?></td>
                        <td><?php echo (int) $row['sort_order']; ?></td>
                        <td><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Hidden'; ?></td>
                        <td class="text-end">
                            <a class="btn btn-light btn-sm" href="content-management.php?edit_entity=faq&edit_id=<?php echo (int) $row['faq_id']; ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this FAQ?');">
                                <input type="hidden" name="entity" value="faq">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo (int) $row['faq_id']; ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
