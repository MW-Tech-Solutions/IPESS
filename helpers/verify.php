<?php
session_start();
require 'db.php';


if (!isset($_GET['app_no'])) {
    header("Location: ../dashboard.php");
    exit();
}

$appNumber = $_GET['app_no'];

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.email as user_email, p.*, pc.*, n.*, w.*, r.*
        FROM applications a
        JOIN applicant_accounts u ON a.user_id = u.user_id
        LEFT JOIN personal_details p ON a.application_id = p.application_id
        LEFT JOIN programme_choices pc ON a.application_id = pc.application_id
        LEFT JOIN nysc_details n ON a.application_id = n.application_id
        LEFT JOIN work_experience w ON a.application_id = w.application_id
        LEFT JOIN research_details r ON a.application_id = r.application_id
        WHERE a.application_number = ?
    ");
    $stmt->execute([$appNumber]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$app) {
        die("Application not found.");
    }

    $appId = $app['application_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM olevel_exams WHERE application_id = ? ORDER BY sitting_number ASC");
    $stmt->execute([$appId]);
    $olevel_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM higher_education WHERE application_id = ?");
    $stmt->execute([$appId]);
    $education = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM referees WHERE application_id = ?");
    $stmt->execute([$appId]);
    $referees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$appId]);
    $uploaded_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function getFileIcon($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match($ext) {
            'pdf' => 'bi-file-earmark-pdf text-danger',
            'jpg', 'jpeg', 'png' => 'bi-file-earmark-image text-primary',
            'doc', 'docx' => 'bi-file-earmark-word text-primary',
            default => 'bi-file-earmark-text text-secondary',
        };
    }

} catch (PDOException $e) {
    die("Data Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<title>Review Applicant | <?php echo htmlspecialchars($appNumber); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #4f46e5;
            --bg-gray: #f1f5f9;
            --border: #e2e8f0;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--bg-gray);
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        @media (max-width: 991px) {
            body { padding-bottom: 90px; }
            .sticky-actions {
                position: fixed;
                bottom: 0; left: 0; right: 0;
                background: white;
                padding: 15px 20px;
                box-shadow: 0 -5px 15px rgba(0,0,0,0.08);
                z-index: 1050;
                display: flex; gap: 10px;
            }
        }

        .card-modern {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .card-header-modern {
            padding: 16px 20px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center;
            font-weight: 700; font-size: 0.75rem;
            text-transform: uppercase; color: var(--text-muted);
            letter-spacing: 0.05em;
        }

        .data-point { padding: 12px 20px; border-bottom: 1px solid #f8fafc; }
        .data-point:last-child { border-bottom: none; }
        .label-text { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 2px; font-weight: 600; }
        .value-text { font-weight: 500; font-size: 0.95rem; color: var(--text-main); }

        .doc-link {
            display: flex; align-items: center;
            padding: 14px 20px;
            text-decoration: none; color: inherit;
            border-bottom: 1px solid #f8fafc;
            transition: background 0.2s;
        }
        .doc-link:hover { background: #fdfdff; }
        .doc-icon { font-size: 1.5rem; margin-right: 15px; }

        .avatar-main {
            width: 56px; height: 56px;
            background: var(--primary); color: white;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; margin-right: 15px;
        }
    </style>
</head>
<body>

<div class="bg-white border-bottom py-3 mb-4 shadow-sm">
    <div class="container-xl">
        <div class="d-flex align-items-center">
            <div class="avatar-main">
                <?php echo substr($app['surname'], 0, 1) . substr($app['first_name'], 0, 1); ?>
            </div>
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-0 text-uppercase"><?php echo htmlspecialchars($app['surname'] . ' ' . $app['first_name']); ?></h5>
                <span class="text-muted small"><?php echo $appNumber; ?> • <?php echo $app['user_email']; ?></span>
            </div>
            <div class="d-none d-md-block">
                <span class="badge rounded-pill bg-success px-3 py-2"><?php echo $app['status']; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="container-xl">
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            
            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-info-circle me-2"></i> Programme Details</div>
                <div class="row g-0">
                    <div class="col-md-6 data-point border-end-md">
                        <span class="label-text">Degree Applied</span>
                        <div class="value-text"><?php echo $app['degree_type']; ?></div>
                    </div>
                    <div class="col-md-6 data-point">
                        <span class="label-text">Department</span>
                        <div class="value-text"><?php echo $app['department']; ?></div>
                    </div>
                    <div class="col-12 data-point">
                        <span class="label-text">Current Mode of Study</span>
                        <div class="value-text"><?php echo $app['mode_of_study']; ?></div>
                    </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-mortarboard me-2"></i> Tertiary Education</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Institution</th>
                                <th>Qualification</th>
                                <th class="text-end pe-4">CGPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($education as $edu): ?>
                            <tr>
                                <td class="ps-4 fw-bold"><?php echo $edu['institution']; ?></td>
                                <td><?php echo $edu['highest_qualification']; ?> (<?php echo $edu['grad_year']; ?>)</td>
                                <td class="text-end pe-4 fw-bold text-primary"><?php echo $edu['cgpa']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-card-checklist me-2"></i> Examination Results</div>
                <div class="p-3">
                    <div class="row g-3">
                        <?php foreach($olevel_exams as $exam): ?>
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light h-100">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-dark">Sitting <?php echo $exam['sitting_number']; ?></span>
                                    <span class="small fw-bold"><?php echo $exam['exam_type']; ?> (<?php echo $exam['exam_year']; ?>)</span>
                                </div>
                                <div class="text-muted small mb-3">No: <?php echo $exam['exam_number']; ?></div>
                                <?php
                                    $stmt_res = $pdo->prepare("SELECT * FROM olevel_results WHERE exam_id = ?");
                                    $stmt_res->execute([$exam['id']]);
                                    $grades = $stmt_res->fetchAll();
                                ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach($grades as $g): ?>
                                        <span class="badge bg-white text-dark border p-2"><?php echo $g['subject_name']; ?>: <strong><?php echo $g['grade']; ?></strong></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-lightbulb me-2"></i> Research Proposal</div>
                <div class="p-4">
                    <span class="label-text">Proposed Research Topic</span>
                    <div class="value-text mt-2" style="line-height: 1.6;"><?php echo $app['research_area'] ?: 'No topic provided.'; ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            
            <div class="card-modern border-primary border-opacity-25 shadow-sm">
                <div class="card-header-modern text-primary"><i class="bi bi-folder2-open me-2"></i> Verification Files</div>
                <div class="bg-light">
                    <?php if (count($uploaded_documents) > 0): ?>
                        <?php foreach($uploaded_documents as $doc): ?>
                            <?php 
                                $title = ucwords(str_replace('_', ' ', $doc['document_type']));
                                $icon = getFileIcon($doc['file_path']);
                            ?>
                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="doc-link">
                                <i class="<?php echo $icon; ?> doc-icon"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold small"><?php echo $title; ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;">Uploaded: <?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></div>
                                </div>
                                <i class="bi bi-box-arrow-up-right text-muted small"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted small">No documents uploaded.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern">Contextual Information</div>
                <div class="data-point">
                    <span class="label-text">NYSC Status</span>
                    <div class="value-text"><?php echo $app['nysc_status']; ?></div>
                    <div class="small text-muted font-monospace"><?php echo $app['certificate_number']; ?></div>
                </div>
                <div class="data-point">
                    <span class="label-text">Employer</span>
                    <div class="value-text"><?php echo $app['employer'] ?: 'Not Specified'; ?></div>
                </div>
                <div class="p-3 bg-light border-top">
                    <span class="label-text mb-2">Referees</span>
                    <?php foreach($referees as $ref): ?>
                        <div class="mb-2 last-child-mb-0 small">
                            <strong><?php echo $ref['full_name']; ?></strong><br>
                            <span class="text-muted"><?php echo $ref['organization']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sticky-actions mt-lg-4">
                <form action="process_decision.php" method="POST" class="w-100 d-flex flex-md-column gap-2">
                    <input type="hidden" name="app_id" value="<?php echo $appId; ?>">
                    
                    <button type="submit" name="decision" value="reject" class="btn btn-outline-danger flex-grow-1 py-3 fw-bold" onclick="return confirm('Reject this application?')">
                        <i class="bi bi-x-circle me-2"></i> Reject
                    </button>
                    
                    <button type="submit" name="decision" value="admit" class="btn btn-primary flex-grow-1 py-3 fw-bold" style="background: var(--primary);" onclick="return confirm('Admit this applicant?')">
                        <i class="bi bi-check-circle me-2"></i> Admit Applicant
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>