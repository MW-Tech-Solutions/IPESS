<?php
require 'db.php';

$app_id = $_GET['id'] ?? null;

if (!$app_id) { die("Invalid ID"); }

// 1. Fetch Applicant Details
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
$stmt->execute([$app_id]);
$applicant = $stmt->fetch();

if (!$applicant) { die("Applicant not found"); }

// 2. Fetch Associated Files
$stmt_files = $pdo->prepare("SELECT * FROM uploads WHERE application_id = ?");
$stmt_files->execute([$app_id]);
$files = $stmt_files->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Viewing: <?php echo htmlspecialchars($applicant['full_name']); ?></title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 40px; }
        .card { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .doc-box { border: 1px solid #eee; padding: 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; }
        .doc-box:hover { background: #f9f9f9; }
        .btn-download { background: #27ae60; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="card">
    <a href="admin_dashboard.php" style="color: #7f8c8d; text-decoration: none;">← Back to Dashboard</a>
    <hr>
    <h2>Applicant Details</h2>
    <div class="info-grid">
        <div><strong>Name:</strong> <?php echo htmlspecialchars($applicant['full_name']); ?></div>
        <div><strong>Ref:</strong> <?php echo htmlspecialchars($applicant['reference_number']); ?></div>
        <div><strong>Email:</strong> <?php echo htmlspecialchars($applicant['email']); ?></div>
        <div><strong>Date:</strong> <?php echo $applicant['created_at']; ?></div>
    </div>

    <h3>Verification Documents</h3>
    <?php foreach ($files as $file): ?>
        <div class="doc-box">
            <div>
                <strong><?php echo $file['document_type']; ?></strong><br>
                <small style="color: #95a5a6;"><?php echo basename($file['file_path']); ?></small>
            </div>
            <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn-download">Open File</a>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>