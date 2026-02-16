<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../includes/referee_service.php';

$message = "";
$messageType = "";
$showForm = false;

// 1. Validate the Request
$refId = filter_input(INPUT_GET, 'rid', FILTER_VALIDATE_INT);
$authHash = $_GET['auth'] ?? '';
$token = $_GET['token'] ?? '';

if (!$token && (!$refId || !$authHash)) {
    die("Invalid access link. Please check your email and try again.");
}

try {
    // UPDATED QUERY: Added JOIN to documents table for the photo
    if ($token) {
        $stmt = $pdo->prepare("
            SELECT r.*, req.application_id, pd.first_name, pd.surname, acc.email as app_email, d.file_path as photo_path, req.token, req.expires_at
            FROM referee_requests req
            JOIN referees r ON r.referee_id = req.referee_id
            JOIN applications a ON r.application_id = a.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            JOIN users acc ON a.user_id = acc.user_id
            LEFT JOIN documents d ON a.application_id = d.application_id AND d.document_type = 'passport'
            WHERE req.token = ? AND (req.expires_at IS NULL OR req.expires_at >= NOW())
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $refId = (int) $data['referee_id'];
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT r.*, pd.first_name, pd.surname, acc.email as app_email, d.file_path as photo_path
            FROM referees r
            JOIN applications a ON r.application_id = a.application_id
            JOIN personal_details pd ON a.application_id = pd.application_id
            JOIN users acc ON a.user_id = acc.user_id
            LEFT JOIN documents d ON a.application_id = d.application_id AND d.document_type = 'passport'
            WHERE r.referee_id = ?
        ");
        $stmt->execute([$refId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$data) {
        die("Referee record not found.");
    }

    if (!$token) {
        // Legacy security check
        $expectedHash = md5($data['email'] . "JOSTUM_SALT_2024");
        if ($authHash !== $expectedHash) {
            die("Security authentication failed. Unauthorized access.");
        }
    }

    // Check if already verified
    $checkStmt = $pdo->prepare("SELECT verified_status FROM referee_uploads WHERE referee_id = ?");
    $checkStmt->execute([$refId]);
    $status = $checkStmt->fetchColumn();

    if ($status === 'Verified') {
        $message = "You have already completed this verification process. Thank you!";
        $messageType = "success";
    } else {
        $showForm = true;
    }

    // 3. Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_verification'])) {
        $maxFileSize = 250 * 1024; 
        $file = $_FILES['id_card'];
        $passportFile = $_FILES['passport_photo'] ?? null;
        $workEmail = trim($_POST['work_email'] ?? '');

        if ($file['size'] > $maxFileSize) {
            $message = "Error: File is too large. Maximum size allowed is 250KB.";
            $messageType = "danger";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $message = "Error uploading file. Please try again.";
            $messageType = "danger";
        } else {
            $uploadDir = 'uploads/referee_ids/';
            $passportDir = 'uploads/referee_passports/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            if (!is_dir($passportDir)) mkdir($passportDir, 0777, true);

            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $file['name']);
            $targetPath = $uploadDir . $fileName;
            $fileExtension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'];

            $passportPath = null;
            if (in_array($fileExtension, $allowedTypes)) {
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    if ($passportFile && $passportFile['error'] === UPLOAD_ERR_OK && $passportFile['size'] <= $maxFileSize) {
                        $passportName = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", $passportFile['name']);
                        $passportTarget = $passportDir . $passportName;
                        if (move_uploaded_file($passportFile['tmp_name'], $passportTarget)) {
                            $passportPath = $passportTarget;
                        }
                    }

                    record_referee_submission($pdo, $refId, (int) $data['application_id'], $workEmail, $passportPath, $targetPath);
                    if ($token) {
                        $pdo->prepare("UPDATE referee_requests SET status = 'Submitted' WHERE token = ?")->execute([$token]);
                    }

                    $message = "Thank you! Your verification and ID card have been submitted successfully.";
                    $messageType = "success";
                    $showForm = false;
                } else {
                    $message = "Failed to save file to server.";
                    $messageType = "danger";
                }
            } else {
                $message = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
                $messageType = "danger";
            }
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<title>Referee Verification | JOSTUM PG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-color: #0d6efd; --bg-color: #f4f7f6; }
        body { background-color: var(--bg-color); font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .verify-container { max-width: 650px; margin: 60px auto; }
        .card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); overflow: hidden; }
        .header-box { background: var(--primary-color); color: white; padding: 40px 20px 60px; text-align: center; }
        
        /* Photo Styling */
        .applicant-profile { position: relative; margin-top: -50px; text-align: center; margin-bottom: 20px; }
        .photo-circle { 
            width: 110px; height: 110px; 
            border-radius: 50%; 
            border: 5px solid white; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            object-fit: cover;
            background: #fff;
        }
        
        .applicant-info { background: #f8f9fa; border: 1px solid #eee; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #444; }
        .btn-primary { padding: 12px; font-weight: 600; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container verify-container">
    <div class="card">
        <div class="header-box">
            <i class="fas fa-shield-halved fa-3x mb-3"></i>
            <h2 class="h4 mb-1">JOSTUM PG PORTAL</h2>
            <p class="mb-0 opacity-75">Secure Referee Verification</p>
        </div>
        
        <div class="card-body p-4 pt-0">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> text-center py-4 mt-4">
                    <i class="fas fa-<?= $messageType == 'success' ? 'check-circle' : 'exclamation-triangle' ?> fa-2x mb-2"></i>
                    <h4><?= $message ?></h4>
                </div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <div class="applicant-profile">
                    <?php 
                        $photo = !empty($data['photo_path']) ? $data['photo_path'] : 'assets/img/default-avatar.png';
                    ?>
                    <img src="<?= htmlspecialchars($photo) ?>" alt="Applicant Photo" class="photo-circle">
                </div>

                <div class="text-center mb-4">
                    <p class="text-muted small mb-1">REFEREE REQUEST FOR:</p>
                    <h4 class="fw-bold mb-0"><?= htmlspecialchars($data['first_name'] . ' ' . $data['surname']) ?></h4>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($data['app_email']) ?></span>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($data['phone']) ?></span>

                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="applicant-info">
                        <h5 class="border-bottom pb-2 mb-3">Your Details Confirmation</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control bg-white" value="<?= htmlspecialchars($data['full_name']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Organization</label>
                                <input type="text" class="form-control bg-white" value="<?= htmlspecialchars($data['organization']) ?>" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Work Email Address</label>
                        <input type="email" name="work_email" class="form-control mb-3" required placeholder="you@organization.com">

                        <label class="form-label">Upload Your Organization ID / Professional ID</label>
                        <input type="file" name="id_card" class="form-control" required accept=".jpg,.jpeg,.png,.pdf">
                        <div class="form-text text-danger italic">Max size: 250KB (JPG, PNG, or PDF)</div>

                        <label class="form-label mt-3">Upload Your Passport Photo (Optional)</label>
                        <input type="file" name="passport_photo" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmCheck" required>
                            <label class="form-check-label small" for="confirmCheck">
                                I confirm my acquaintance with <strong><?= htmlspecialchars($data['first_name']) ?></strong> and verify that the academic/professional reference provided is accurate.
                                <!-- I confirm that I know this applicant and the information provided in my reference (submitted separately) is true to the best of my knowledge. -->

                            </label>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" name="submit_verification" class="btn btn-primary btn-lg shadow-sm">
                            Complete Verification
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center text-muted small py-3 border-0">
            &copy; <?= date('Y') ?> Joseph Sarwuan Tarka University, Makurdi
        </div>
    </div>
</div>

</body>
</html>
