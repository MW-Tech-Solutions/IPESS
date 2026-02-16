<?php
session_start();

/**
 * Senior Backend Logic: Data Aggregation
 * * In a real scenario, Steps 1-7 would have populated $_SESSION['form_data'].
 * For this demo, I will inject MOCK DATA for Steps 1-7 if they don't exist,
 * so you can see how the preview page renders.
 */

// --- MOCK DATA GENERATOR (Delete this block in production) ---
if (!isset($_SESSION['form_data']['personal_info'])) {
    $_SESSION['form_data']['personal_info'] = [
        'full_name' => 'Chinedu Adebayo',
        'email' => 'chinedu.dev@example.com',
        'phone' => '+234 800 123 4567',
        'dob' => '1998-05-12',
        'state' => 'Lagos'
    ];
    $_SESSION['form_data']['academic_info'] = [
        'institution' => 'University of Lagos',
        'degree' => 'B.Sc. Computer Science',
        'class' => 'First Class Honors',
        'year' => '2023'
    ];
}
// -------------------------------------------------------------

// Redirect if Step 8 (Documents) is empty
if (empty($_SESSION['form_data']['step_8'])) {
    // Ideally redirect back to upload step
    // header("Location: form_step_8.php");
    // exit;
}

$personal = $_SESSION['form_data']['personal_info'] ?? [];
$academic = $_SESSION['form_data']['academic_info'] ?? [];
$documents = $_SESSION['form_data']['step_8'] ?? [];

// Helper to make file keys readable (e.g., 'passport_file' -> 'Passport Photograph')
function formatLabel($key) {
    $labels = [
        'passport_file' => 'Passport Photograph',
        'olevel_file'   => 'O-Level Certificate (Sitting 1)',
        'olevel_file_2' => 'O-Level Certificate (Sitting 2)',
        'degree_file'   => 'Degree Certificate',
        'transcript_file' => 'Academic Transcript',
        'nysc_file'     => 'NYSC Certificate',
        'proposal_file' => 'PhD Research Proposal'
    ];
    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Preview</title>
    <style>
        :root { --primary: #2c3e50; --accent: #27ae60; --light: #ecf0f1; }
        body { font-family: 'Segoe UI', sans-serif; background: #dfe6e9; padding: 20px; }
        
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        
        .header { background: var(--primary); color: white; padding: 20px 30px; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header p { margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem; }

        .section { padding: 30px; border-bottom: 1px solid #eee; }
        .section:last-child { border-bottom: none; }
        
        h3 { color: var(--primary); border-left: 4px solid var(--accent); padding-left: 10px; margin-bottom: 20px; }

        .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .data-row { margin-bottom: 10px; }
        .label { font-weight: bold; color: #7f8c8d; font-size: 0.85rem; display: block; text-transform: uppercase; }
        .value { color: #2c3e50; font-size: 1rem; }

        /* Document Specific Styles */
        .doc-list { list-style: none; padding: 0; }
        .doc-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f9f9f9; border: 1px solid #eee; margin-bottom: 10px; border-radius: 4px; }
        .doc-name { font-weight: 600; }
        .doc-link { text-decoration: none; color: white; background: #3498db; padding: 5px 12px; border-radius: 4px; font-size: 0.85rem; }
        .doc-link:hover { background: #2980b9; }

        .actions { background: #f1f2f6; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 12px 25px; border-radius: 4px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; font-size: 1rem; }
        .btn-edit { background: #95a5a6; color: white; }
        .btn-submit { background: var(--accent); color: white; }
        .btn-submit:hover { background: #219150; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Application Review</h1>
        <p>Please review your details before final submission.</p>
    </div>

    <div class="section">
        <h3>1. Personal Information</h3>
        <div class="data-grid">
            <div class="data-row">
                <span class="label">Full Name</span>
                <span class="value"><?php echo htmlspecialchars($personal['full_name'] ?? '-'); ?></span>
            </div>
            <div class="data-row">
                <span class="label">Email Address</span>
                <span class="value"><?php echo htmlspecialchars($personal['email'] ?? '-'); ?></span>
            </div>
            <div class="data-row">
                <span class="label">Phone Number</span>
                <span class="value"><?php echo htmlspecialchars($personal['phone'] ?? '-'); ?></span>
            </div>
            <div class="data-row">
                <span class="label">Date of Birth</span>
                <span class="value"><?php echo htmlspecialchars($personal['dob'] ?? '-'); ?></span>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>2. Academic History</h3>
        <div class="data-grid">
            <div class="data-row">
                <span class="label">Institution</span>
                <span class="value"><?php echo htmlspecialchars($academic['institution'] ?? '-'); ?></span>
            </div>
            <div class="data-row">
                <span class="label">Degree Obtained</span>
                <span class="value"><?php echo htmlspecialchars($academic['degree'] ?? '-'); ?></span>
            </div>
            <div class="data-row">
                <span class="label">Graduation Year</span>
                <span class="value"><?php echo htmlspecialchars($academic['year'] ?? '-'); ?></span>
            </div>
            <div class="data-row">
                <span class="label">Class of Degree</span>
                <span class="value"><?php echo htmlspecialchars($academic['class'] ?? '-'); ?></span>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>3. Uploaded Documents</h3>
        <?php if (empty($documents)): ?>
            <p style="color:red;">No documents uploaded. Please go back.</p>
        <?php else: ?>
            <ul class="doc-list">
                <?php foreach ($documents as $key => $path): ?>
                    <li class="doc-item">
                        <span class="doc-name"><?php echo formatLabel($key); ?></span>
                        <a href="<?php echo htmlspecialchars($path); ?>" target="_blank" class="doc-link">View / Verify</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <form action="submit_application.php" method="POST" class="actions">
        <a href="form_step_8.php" class="btn btn-edit">← Edit Documents</a>
        
        <button type="submit" name="final_submit" class="btn btn-submit" onclick="return confirm('Are you sure? This action cannot be undone.');">
            Submit Application 🚀
        </button>
    </form>
</div>

</body>
</html>