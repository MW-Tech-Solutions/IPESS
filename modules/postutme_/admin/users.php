<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/layout.php';
require_once __DIR__ . '/_admin.php';

$user = require_admin(['super_admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $role = $_POST['role'] ?? '';
    if (in_array($role, ['admissions_officer', 'ict_admin', 'super_admin', 'finance_officer'], true)) {
        $stmt = db()->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([trim($_POST['name']), trim($_POST['email']), trim($_POST['phone']), password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $role]);
        flash('success', 'Staff user created.');
    }
}
$users = db()->query('SELECT * FROM users WHERE role <> "applicant" ORDER BY id DESC')->fetchAll();

render_header('Staff Users');
?>
<?php render_workspace_start('admin', $user, 'Staff Users'); ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="portal-card">
                    <h1>Create Staff User</h1>
                    <form method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-12"><input name="name" class="form-control" placeholder="Full name" required></div>
                        <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                        <div class="col-12"><input name="phone" class="form-control" placeholder="Phone"></div>
                        <div class="col-12"><input type="password" name="password" class="form-control" placeholder="Password" required minlength="8"></div>
                        <div class="col-12">
                            <select name="role" class="form-select">
                                <option value="admissions_officer">Admissions Officer</option>
                                <option value="ict_admin">ICT Admin</option>
                                <option value="finance_officer">Finance Officer</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        <div class="col-12"><button class="btn btn-portal-green w-100">Create User</button></div>
                    </form>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="portal-card">
                    <h2>Staff Accounts</h2>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
                            <tbody>
                            <?php foreach ($users as $staff): ?>
                                <tr><td><?= e($staff['name']) ?></td><td><?= e($staff['email']) ?></td><td><?= e(ucwords(str_replace('_', ' ', $staff['role']))) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
<?php render_workspace_end(); ?>
<?php render_footer(); ?>
