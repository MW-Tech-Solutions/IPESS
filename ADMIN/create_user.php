<?php
require_once __DIR__ . '/../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['PG_SCHOOL_OFFICER', 'ADMISSIONS_OFFICER', 'PORTAL_ADMIN', 'SUPER_ADMIN'], 'ADMIN/login.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($email) || empty($password) || empty($role)) {
        $error = 'All fields are required.';
    } else {
        try {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':role', $role);

            if ($stmt->execute()) {
                $success = 'User created successfully!';
            } else {
                $error = 'Failed to create user.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate email)
                $error = 'Email already exists.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
            error_log("User Creation Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Create a New User</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="APPLICANT">Applicant</option>
                    <option value="REVIEWER">Reviewer</option>
                    <option value="SUPERVISOR">Supervisor</option>
                    <option value="DEPARTMENT_ADMIN">Department Admin</option>
                    <option value="ADMIN">Admin</option>
                    <option value="SUPER_ADMIN">Super Admin</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
</body>
</html>
