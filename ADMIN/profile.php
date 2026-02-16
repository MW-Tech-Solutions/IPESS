<?php
session_start();

require_once __DIR__ . '/admin/includes/db.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../config/urls.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    redirect_to('ADMIN/login.php');
}

$sessionRole = (string) $_SESSION['role'];
$allowedProfileRole = is_admin_role($sessionRole)
    || is_department_admin($sessionRole)
    || is_reviewer($sessionRole)
    || is_supervisor($sessionRole)
    || in_array(strtoupper($sessionRole), ['DEPT_ADMIN'], true);

if (!$allowedProfileRole) {
    redirect_to('ADMIN/login.php');
}

$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';

$stmt = $pdo->prepare("
    SELECT u.user_id, u.email, u.full_name, u.avatar_url, r.role_name
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect_to('ADMIN/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    $avatarUrl = (string) ($user['avatar_url'] ?? '');
    $hasFile = isset($_FILES['avatar']) && is_array($_FILES['avatar']) && (int) ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasFile) {
        $file = $_FILES['avatar'];
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $maxBytes = 2 * 1024 * 1024;

        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload image.';
        } else {
            $tmp = (string) ($file['tmp_name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            $name = (string) ($file['name'] ?? '');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $mime = mime_content_type($tmp) ?: '';
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

            if ($size <= 0 || $size > $maxBytes) {
                $errors[] = 'Avatar must be less than 2MB.';
            } elseif (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
                $errors[] = 'Only JPG, PNG, or WEBP images are allowed.';
            } else {
                $dir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR . 'avatars';
                if ($dir === DIRECTORY_SEPARATOR . 'avatars' || $dir === '' || $dir === false) {
                    $dir = __DIR__ . '/../uploads/avatars';
                }
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $filename = 'admin_' . $userId . '_' . time() . '.' . $ext;
                $target = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $filename;
                if (!@move_uploaded_file($tmp, $target)) {
                    $errors[] = 'Could not save uploaded image.';
                } else {
                    $avatarUrl = app_url('uploads/avatars/' . $filename);
                }
            }
        }
    }

    if (empty($errors)) {
        $update = $pdo->prepare("UPDATE users SET full_name = ?, avatar_url = ? WHERE user_id = ?");
        $update->execute([$fullName, $avatarUrl !== '' ? $avatarUrl : null, $userId]);

        $user['full_name'] = $fullName;
        $user['avatar_url'] = $avatarUrl;
        $success = 'Profile updated successfully.';
    }
}

$displayName = (string) ($user['full_name'] ?: $user['email']);
$avatarInitial = strtoupper(substr($displayName, 0, 1));
$roleKey = strtoupper((string) ($_SESSION['role'] ?? ''));
$dashboardMap = [
    'SUPER_ADMIN' => 'ADMIN/super-admin/dashboard.php',
    'ADMIN' => 'ADMIN/admin/dashboard.php',
    'DEPARTMENT_ADMIN' => 'ADMIN/dept-admin/dashboard.php',
    'SUPERVISOR' => 'ADMIN/supervisor/dashboard.php',
    'REVIEWER' => 'ADMIN/reviewer/dashboard.php'
];
$backUrl = app_url($dashboardMap[$roleKey] ?? 'ADMIN/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
    <title>Profile | JOSTUM PG School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f7f3; }
        .card-wrap { max-width: 760px; margin: 40px auto; }
        .avatar-box {
            width: 96px; height: 96px; border-radius: 50%;
            background: #0b5b3f; color: #fff; font-weight: 700;
            display: flex; align-items: center; justify-content: center; font-size: 2rem;
            overflow: hidden;
        }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container card-wrap">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">Profile Settings</h5>
                    <div class="text-muted small"><?php echo htmlspecialchars((string) ($user['role_name'] ?? $_SESSION['role'])); ?></div>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger mb-2"><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>

                <form method="post" enctype="multipart/form-data">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="avatar-box">
                            <?php if (!empty($user['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars((string) $user['avatar_url']); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo htmlspecialchars($avatarInitial); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($displayName); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars((string) $user['email']); ?></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars((string) ($user['full_name'] ?? '')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars((string) $user['email']); ?>" disabled>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="form-text">Allowed: JPG, PNG, WEBP. Max size: 2MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php if (!empty($success)): ?>
<script>
try {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'profile-updated' }, '*');
    }
} catch (e) {}
</script>
<?php endif; ?>
</body>
</html>
