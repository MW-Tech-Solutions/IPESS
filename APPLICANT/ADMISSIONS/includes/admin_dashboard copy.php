<?php
session_start();
require 'db.php'; // Reuse your PDO connection

// Simple Admin Auth Mock (Replace with real Auth system)
// if (!isset($_SESSION['admin_logged_in'])) { die("Access Denied"); }

$stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Application Manager</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; padding: 20px; }
        .dashboard { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2c3e50; color: white; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-submitted { background: #d4edda; color: #155724; }
        .btn-view { text-decoration: none; color: #3498db; font-weight: bold; }
    </style>
</head>
<body>

<div class="dashboard">
    <h2>Academic Admissions Dashboard</h2>
    <table>
        <thead>
            <tr>
                <th>Ref Number</th>
                <th>Applicant Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($app['reference_number']); ?></strong></td>
                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                <td><?php echo htmlspecialchars($app['email']); ?></td>
                <td><span class="status-badge status-submitted"><?php echo $app['status']; ?></span></td>
                <td><a href="admin_view_applicant.php?id=<?php echo $app['id']; ?>" class="btn-view">View Files →</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>