<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin();
$q = trim($_GET['q'] ?? '');
$course = trim($_GET['course'] ?? '');
$state = trim($_GET['state'] ?? '');
$sql = 'SELECT jc.*, s.year_label FROM jamb_candidates jc JOIN admission_sessions s ON s.id = jc.admission_session_id';
$params = [];
$where = [];
if ($q !== '') {
    $where[] = '(jc.jamb_reg_no LIKE ? OR jc.surname LIKE ? OR jc.first_name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($course !== '') {
    $where[] = '(jc.course_name LIKE ? OR jc.course_applied LIKE ?)';
    array_push($params, "%$course%", "%$course%");
}
if ($state !== '') {
    $where[] = 'jc.state_origin LIKE ?';
    $params[] = "%$state%";
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY jc.id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="jamb-candidates.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['JAMB No', 'Surname', 'First Name', 'Gender', 'State', 'LGA', 'Course', 'Score']);
    foreach ($candidates as $candidate) {
        fputcsv($out, [$candidate['jamb_reg_no'], $candidate['surname'], $candidate['first_name'], $candidate['gender'], $candidate['state_origin'], $candidate['lga'], candidate_course($candidate), candidate_score($candidate)]);
    }
    exit;
}

render_header('Imported Candidates');
?>
<?php render_workspace_start('admin', $user, 'Candidates'); ?>
        <div class="portal-card">
            <div class="dashboard-head">
                <h1>Imported JAMB Candidates</h1>
                <form class="admin-filter">
                    <input name="q" class="form-control" value="<?= e($q) ?>" placeholder="Search JAMB no or name">
                    <input name="course" class="form-control" value="<?= e($course) ?>" placeholder="Course">
                    <input name="state" class="form-control" value="<?= e($state) ?>" placeholder="State">
                    <button class="btn btn-outline-secondary">Search</button>
                    <button name="export" value="csv" class="btn btn-portal-blue">Export CSV</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>JAMB No.</th><th>Name</th><th>Gender</th><th>State/LGA</th><th>Course</th><th>Score</th><th>Year</th><th>Verified</th></tr></thead>
                    <tbody>
                    <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td><?= e($candidate['jamb_reg_no']) ?></td>
                            <td><?= e($candidate['surname'] . ' ' . $candidate['first_name']) ?></td>
                            <td><?= e($candidate['gender'] ?? '') ?></td>
                            <td><?= e($candidate['state_origin'] . ' / ' . $candidate['lga']) ?></td>
                            <td><?= e(candidate_course($candidate)) ?></td>
                            <td><?= e(candidate_score($candidate)) ?></td>
                            <td><?= e($candidate['year_label']) ?></td>
                            <td><?= $candidate['verified_at'] ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
