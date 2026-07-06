<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/../app/importer.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin(['ict_admin', 'super_admin']);
$sessions = db()->query('SELECT * FROM admission_sessions ORDER BY id DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (empty($_FILES['jamb_file']['name']) || $_FILES['jamb_file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Please upload a valid JAMB file.');
        }
        $ext = strtolower(pathinfo($_FILES['jamb_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            throw new RuntimeException('Only CSV, XLSX, and XLS files are allowed.');
        }
        if (!is_dir(JAMB_IMPORT_PATH)) {
            mkdir(JAMB_IMPORT_PATH, 0775, true);
        }
        $stored = JAMB_IMPORT_PATH . '/jamb_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        move_uploaded_file($_FILES['jamb_file']['tmp_name'], $stored);
        $rows = spreadsheet_rows($stored, $ext);
        $batch = db()->prepare('INSERT INTO import_batches (admission_session_id, uploaded_by, original_filename, stored_path, total_rows) VALUES (?, ?, ?, ?, ?)');
        $batch->execute([(int) $_POST['admission_session_id'], $user['id'], $_FILES['jamb_file']['name'], $stored, max(0, count($rows) - 1)]);
        $batchId = (int) db()->lastInsertId();
        $result = import_jamb_candidates($stored, $ext, (int) $_POST['admission_session_id'], $batchId);
        db()->prepare('UPDATE import_batches SET successful_rows = ?, failed_rows = ?, duplicate_rows = 0 WHERE id = ?')->execute([$result['imported'], $result['skipped'], $batchId]);
        audit_log('imported jamb candidates', 'jamb_import');
        flash('success', 'Imported ' . $result['imported'] . ' candidate(s). Skipped ' . $result['skipped'] . '.');
        redirect('admin/import-jamb.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

render_header('Import JAMB');
?>
<?php render_workspace_start('admin', $user, 'JAMB Import'); ?>
        <div class="portal-card">
            <h1>Import JAMB Candidate Data</h1>
            <p class="text-muted">Upload the official JAMB CSV/Excel sheet before applicants can verify. Required columns include JAMB Reg No, Surname, and First Name.</p>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label">Admission Year</label>
                    <select name="admission_session_id" class="form-select">
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= e((string) $session['id']) ?>"><?= e($session['year_label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">CSV/XLSX/XLS File</label>
                    <input type="file" name="jamb_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-portal-green btn-lg">Import Candidates</button>
                </div>
            </form>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
