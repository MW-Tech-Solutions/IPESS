<?php
/*
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'SUPER_ADMIN') {
    header('Location: /login.php');
    exit;
}
*/
$pageTitle = 'Profile';
$pageSubtitle = 'Update your identity, avatar, and contact information.';

require_once 'includes/db.php';

$profile = [
    'full_name' => '',
    'email' => '',
    'avatar_url' => '',
    'role_name' => 'User'
];
$saved = false;

if ($pdo) {
    $sessionUserId = $_SESSION['user_id'] ?? null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $avatar = trim($_POST['avatar_url'] ?? '');

        if ($fullName && $email) {
            if ($sessionUserId) {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, avatar_url = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $avatar, $sessionUserId]);
            }
            $saved = true;
        }
    }

    if ($sessionUserId) {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.email, u.avatar_url, r.role_name
            FROM users u
            LEFT JOIN roles r ON r.role_id = u.role_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sessionUserId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: $profile;
    } else {
        $profile = $pdo->query("
            SELECT u.full_name, u.email, u.avatar_url, r.role_name
            FROM users u
            LEFT JOIN roles r ON r.role_id = u.role_id
            ORDER BY u.user_id ASC
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC) ?: $profile;
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>

<section class="page-hero">
    <div>
        <h1>Profile</h1>
        <p class="panel-muted">Manage your identity and how it appears across the admin suite.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn-primary" type="submit" form="profileForm">Save Profile</button>
    </div>
</section>

<?php if ($saved): ?>
    <div class="alert alert-success">Profile updated successfully.</div>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <div>
            <h3 class="panel-title">Account Details</h3>
            <div class="panel-muted">Ensure your contact details stay current.</div>
        </div>
    </div>
    <div class="panel-body">
        <form id="profileForm" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['role_name'] ?? 'User'); ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Avatar URL</label>
                    <input type="text" class="form-control" name="avatar_url" value="<?php echo htmlspecialchars($profile['avatar_url'] ?? ''); ?>">
                </div>
            </div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
