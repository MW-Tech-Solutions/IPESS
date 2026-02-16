<?php
// Database Connection
$host = 'localhost';
$dbname = 'pg';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}

$message = "";

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Add Faculty
        if (isset($_POST['add_faculty'])) {
            $stmt = $pdo->prepare("INSERT INTO faculties (faculty_name) VALUES (?)");
            $stmt->execute([trim($_POST['faculty_name'])]);
            $message = "<div class='alert alert-success'>Faculty added!</div>";
        }
        
        // 2. Add Degree Type
        elseif (isset($_POST['add_degree'])) {
            $stmt = $pdo->prepare("INSERT INTO degree_types (degree_name) VALUES (?)");
            $stmt->execute([trim($_POST['degree_name'])]);
            $message = "<div class='alert alert-success'>Degree Type added!</div>";
        }

        // 3. Add Department
        elseif (isset($_POST['add_dept'])) {
            $stmt = $pdo->prepare("INSERT INTO departments (dept_name, faculty_id) VALUES (?, ?)");
            $stmt->execute([trim($_POST['dept_name']), $_POST['faculty_id']]);
            $message = "<div class='alert alert-success'>Department added!</div>";
        }

        // 4. Add Course
        elseif (isset($_POST['add_course'])) {
            $stmt = $pdo->prepare("INSERT INTO courses (course_title, dept_id, degree_id) VALUES (?, ?, ?)");
            $stmt->execute([
                trim($_POST['course_title']), 
                $_POST['dept_id'], 
                $_POST['degree_id']
            ]);
            $message = "<div class='alert alert-success'>Course added successfully!</div>";
        }

    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- FETCH DATA FOR DROPDOWNS & TABLE ---
// Get Faculties
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name")->fetchAll();

// Get Degree Types
$degrees = $pdo->query("SELECT * FROM degree_types ORDER BY degree_name")->fetchAll();

// Get Departments (Joined with Faculty for grouping)
$departments = $pdo->query("
    SELECT d.dept_id, d.dept_name, f.faculty_name 
    FROM departments d 
    JOIN faculties f ON d.faculty_id = f.faculty_id 
    ORDER BY f.faculty_name, d.dept_name
")->fetchAll();

// Get All Courses (Joined View for Display)
$courseList = $pdo->query("
    SELECT c.course_id, c.course_title, c.created_at, d.dept_name, f.faculty_name, deg.degree_name
    FROM courses c
    JOIN departments d ON c.dept_id = d.dept_id
    JOIN faculties f ON d.faculty_id = f.faculty_id
    JOIN degree_types deg ON c.degree_id = deg.degree_id
    ORDER BY c.course_id DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University PG Portal (3NF)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <h2 class="text-center mb-4 text-primary fw-bold">Postgraduate Course Manager</h2>
    
    <?= $message ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                        <li class="nav-item"><a class="nav-link active" id="course-tab" data-bs-toggle="tab" href="#add-course">Add Course</a></li>
                        <li class="nav-item"><a class="nav-link" id="dept-tab" data-bs-toggle="tab" href="#add-dept">Add Dept</a></li>
                        <li class="nav-item"><a class="nav-link" id="meta-tab" data-bs-toggle="tab" href="#add-meta">Setup</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        
                        <div class="tab-pane fade show active" id="add-course">
                            <form method="POST">
                                <div class="mb-3">
                                    <label>Select Department</label>
                                    <select name="dept_id" class="form-select" required>
                                        <option value="">-- Choose Dept --</option>
                                        <?php 
                                        $currentFac = "";
                                        foreach($departments as $dept) {
                                            if($currentFac != $dept['faculty_name']) {
                                                if($currentFac != "") echo "</optgroup>";
                                                $currentFac = $dept['faculty_name'];
                                                echo "<optgroup label='" . htmlspecialchars($currentFac) . "'>";
                                            }
                                            echo "<option value='{$dept['dept_id']}'>" . htmlspecialchars($dept['dept_name']) . "</option>";
                                        }
                                        if($currentFac != "") echo "</optgroup>";
                                        ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label>Degree Type</label>
                                    <select name="degree_id" class="form-select" required>
                                        <?php foreach($degrees as $d): ?>
                                            <option value="<?= $d['degree_id'] ?>"><?= htmlspecialchars($d['degree_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label>Course Title</label>
                                    <input type="text" name="course_title" class="form-control" placeholder="e.g. Advanced AI" required>
                                </div>
                                <button type="submit" name="add_course" class="btn btn-primary w-100">Save Course</button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="add-dept">
                            <form method="POST">
                                <div class="mb-3">
                                    <label>Select Faculty</label>
                                    <select name="faculty_id" class="form-select" required>
                                        <?php foreach($faculties as $f): ?>
                                            <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label>Department Name</label>
                                    <input type="text" name="dept_name" class="form-control" required>
                                </div>
                                <button type="submit" name="add_dept" class="btn btn-success w-100">Save Department</button>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="add-meta">
                            <h6 class="fw-bold mt-2">1. Add New Faculty</h6>
                            <form method="POST" class="mb-4">
                                <div class="input-group">
                                    <input type="text" name="faculty_name" class="form-control" placeholder="Faculty Name" required>
                                    <button type="submit" name="add_faculty" class="btn btn-outline-secondary">Add</button>
                                </div>
                            </form>

                            <h6 class="fw-bold">2. Add Degree Type</h6>
                            <form method="POST">
                                <div class="input-group">
                                    <input type="text" name="degree_name" class="form-control" placeholder="e.g. MBA" required>
                                    <button type="submit" name="add_degree" class="btn btn-outline-secondary">Add</button>
                                </div>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Registered Courses</h5>
                    <span class="badge bg-primary"><?= count($courseList) ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course Title</th>
                                <th>Degree</th>
                                <th>Department</th>
                                <th>Faculty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($courseList)): ?>
                                <tr><td colspan="4" class="text-center p-4">No courses found. Add some data!</td></tr>
                            <?php else: ?>
                                <?php foreach($courseList as $c): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($c['course_title']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($c['degree_name']) ?></span></td>
                                    <td class="small"><?= htmlspecialchars($c['dept_name']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($c['faculty_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>