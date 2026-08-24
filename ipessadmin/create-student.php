<?php
session_start();
require_once "db.php";
if (!isset($pdo)) {
    $pdo = db();
}
require_once __DIR__ . "/../app/helpers/auth.php";

$currentRole = function_exists("normalize_role") ? normalize_role($_SESSION["roleid"] ?? "") : strtoupper(trim($_SESSION["roleid"] ?? ""));
if (!in_array($currentRole, ["PG_SCHOOL_OFFICER", "ADMISSIONS_OFFICER", "PORTAL_ADMIN", "SUPER_ADMIN", "DEVELOPER"], true)) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";

$studyModes = [];
$degreeTypes = [];
$courses = [];
try {
    $studyModes = $pdo->query("SELECT mode_id, mode_name FROM study_modes ORDER BY mode_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $degreeTypes = $pdo->query("SELECT degree_id, degree_name FROM degree_types ORDER BY degree_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $courses = $pdo->query("
        SELECT c.course_id, c.course_title, c.degree_id, c.dept_id, d.faculty_id
        FROM courses c
        LEFT JOIN departments d ON d.dept_id = c.dept_id
        ORDER BY c.course_title ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email     = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
    $password  = $_POST["password"] ?? "";
    $surname   = trim($_POST["surname"] ?? "");
    $firstName = trim($_POST["first_name"] ?? "");
    $otherName = trim($_POST["other_name"] ?? "");
    $phone     = trim($_POST["phone"] ?? "");
    $programme = (int)($_POST["programme"] ?? 0);
    $degree    = (int)($_POST["degree_type"] ?? 0);
    $mode      = (int)($_POST["mode_of_study"] ?? 0);

    if (empty($email) || empty($password) || empty($surname) || empty($firstName) || empty($phone) || $programme <= 0) {
        $error = "Please fill all required fields (*).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email is already registered.";
            } else {
                $signupData = [
                    "email"            => $email,
                    "password"         => $password,
                    "surname"          => $surname,
                    "first_name"       => $firstName,
                    "other_name"       => $otherName,
                    "phone"            => $phone,
                    "programme"        => $programme,
                    "programme_option" => $degree,
                    "mode_of_study"    => $mode,
                    "faculty"          => 0,
                    "department"       => 0
                ];

                require_once __DIR__ . "/../APPLICANT/ADMISSIONS/includes/register_user_helper.php";
                $regResult = register_new_student($pdo, $signupData);

                if ($regResult["success"]) {
                    $success = "Student Account (<strong>$email</strong>) created successfully!";
                    
                    try {
                        require_once __DIR__ . "/../includes/user_activity_logger.php";
                        log_from_session($pdo, "Create Student Account", "Admin manually created student account for: {$email}", "user", $email);
                    } catch (Throwable $eLog) {}

                    $email = $password = $surname = $firstName = $otherName = $phone = "";
                    $programme = $degree = $mode = 0;
                } else {
                    $error = "Registration helper failed: " . $regResult["message"];
                }
            }
        } catch (Throwable $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

$pageTitle = "Create Student Account";
$pageSubtitle = "Manually provision new student profiles and initial draft applications.";
require_once "includes/dev_header.php";
require_once "includes/sidebar.php";
require_once "includes/dev_topbar.php";
?>

<section class="page-hero">
    <div>
        <h1>Create Student Account</h1>
        <p class="panel-muted">Admin tool to manually register student profiles, even when public applications are closed.</p>
    </div>
</section>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <section class="panel">
            <div class="panel-header">
                <h3 class="panel-title">New Student Account Provision</h3>
            </div>
            
            <div class="panel-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation">
                    <h5 class="mb-3 text-primary" style="font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <i class="fas fa-user-shield me-2"></i>Security &amp; Login Details
                    </h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 500;">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email ?? ""); ?>" placeholder="student@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 500;">Login Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="password" id="studentPassword" class="form-control" required value="<?php echo htmlspecialchars($password ?? ""); ?>" placeholder="Password">
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePass()">Generate</button>
                            </div>
                        </div>
                    </div>

                    <h5 class="mb-3 text-primary" style="font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <i class="fas fa-address-card me-2"></i>Personal Profile Details
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label" style="font-weight: 500;">Surname <span class="text-danger">*</span></label>
                            <input type="text" name="surname" class="form-control" required value="<?php echo htmlspecialchars($surname ?? ""); ?>" placeholder="Surname">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-weight: 500;">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($firstName ?? ""); ?>" placeholder="First Name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" style="font-weight: 500;">Other Name</label>
                            <input type="text" name="other_name" class="form-control" value="<?php echo htmlspecialchars($otherName ?? ""); ?>" placeholder="Other Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 500;">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($phone ?? ""); ?>" placeholder="e.g. 08012345678">
                        </div>
                    </div>

                    <h5 class="mb-3 text-primary" style="font-size: 1.05rem; font-weight: 600; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <i class="fas fa-graduation-cap me-2"></i>Programme Choice
                    </h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label" style="font-weight: 500;">Programme / Course Option <span class="text-danger">*</span></label>
                            <select name="programme" class="form-select select2" required>
                                <option value="">-- Choose Course --</option>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c["course_id"]; ?>" <?php echo (isset($programme) && $programme === (int)$c["course_id"]) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($c["course_title"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 500;">Degree Level</label>
                            <select name="degree_type" class="form-select">
                                <option value="">-- Choose Option --</option>
                                <?php foreach ($degreeTypes as $dt): ?>
                                    <option value="<?php echo $dt["degree_id"]; ?>" <?php echo (isset($degree) && $degree === (int)$dt["degree_id"]) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($dt["degree_name"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 500;">Mode of Study</label>
                            <select name="mode_of_study" class="form-select">
                                <option value="">-- Choose Option --</option>
                                <?php foreach ($studyModes as $sm): ?>
                                    <option value="<?php echo $sm["mode_id"]; ?>" <?php echo (isset($mode) && $mode === (int)$sm["mode_id"]) ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($sm["mode_name"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="user-management.php" class="btn btn-light">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>Create Student Profile</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
function generatePass() {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
    let password = "";
    for (let i = 0; i < 10; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById("studentPassword").value = password;
}
</script>

<?php require_once "includes/dev_footer.php"; ?>
