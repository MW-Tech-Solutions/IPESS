<?php
session_start();

// 1. Database Configuration (Ideally move this to a separate config.php)
$host    = '127.0.0.1';
$db      = 'jostum_pg';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// 2. Security Check: Ensure form was submitted and session exists
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['form_data']['step_8'])) {
    header("Location: form_step_8.php");
    exit;
}

$data = $_SESSION['form_data'];
$files = $data['step_8'];

try {
    // START TRANSACTION
    // This ensures either EVERYTHING is saved, or NOTHING is.
    $pdo->beginTransaction();

    // 3. Insert into 'applications' table
    $ref_number = 'APP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    
    $sql_app = "INSERT INTO applications (reference_number, full_name, email, status) 
                VALUES (:ref, :name, :email, 'submitted')";
    
    $stmt_app = $pdo->prepare($sql_app);
    $stmt_app->execute([
        'ref'   => $ref_number,
        'name'  => $data['personal_info']['full_name'],
        'email' => $data['personal_info']['email']
    ]);
    
    $application_id = $pdo->lastInsertId();

    // 4. Map Session Keys to DB ENUM types
    $file_mapping = [
        'passport_file'   => 'Passport Photograph',
        'olevel_file'     => 'SSCE Certificate',
        'olevel_file_2'   => 'SSCE Certificate 2',
        'degree_file'     => 'Degree Certificate',
        'transcript_file' => 'Academic Transcript',
        'nysc_file'       => 'NYSC Certificate',
        'proposal_file'   => 'Research Proposal'
    ];

    // 5. Prepare the upload insertion statement
    $sql_file = "INSERT INTO uploads (application_id, document_type, file_path) 
                 VALUES (?, ?, ?)";
    $stmt_file = $pdo->prepare($sql_file);

    foreach ($file_mapping as $session_key => $db_enum) {
        // Only insert if the file was actually uploaded (handles optional fields like NYSC)
        if (!empty($files[$session_key])) {
            $stmt_file->execute([
                $application_id,
                $db_enum,
                $files[$session_key]
            ]);
        }
    }

    // COMMIT ALL CHANGES
    $pdo->commit();

    // 6. Success Handling
    // Clear the session so they can't double-submit
    unset($_SESSION['form_data']);
    
    // Redirect to a success page with the reference number
    $_SESSION['last_ref'] = $ref_number;
    header("Location: success_page.php");
    exit;

} catch (Exception $e) {
    // ROLLBACK if anything went wrong
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error and notify user
    error_log($e->getMessage());
    die("A technical error occurred during submission. Please try again later.");
}