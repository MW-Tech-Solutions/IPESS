<?php

session_start();


const ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf'
];

const MAX_FILE_SIZE = 2 * 1024 * 1024;

const UPLOAD_BASE_DIR = __DIR__ . '/uploads/';

const UPLOAD_MAP = [
    'passport_file'   => 'passports',
    'olevel_file'     => 'olevel',
    'olevel_file_2'   => 'olevel',
    'degree_file'     => 'degree',
    'transcript_file' => 'transcripts',
    'nysc_file'       => 'nysc',
    'proposal_file'   => 'proposals'
];

$response = [
    'status' => false,
    'errors' => [],
    'success_messages' => []
];

if (!isset($_SESSION['form_data']['step_8'])) {
    $_SESSION['form_data']['step_8'] = [];
}

/**
 * @param array 
 * @param string 
 * @return array 
 */
function processFileUpload($file_input, $category_folder) {
    if (!isset($file_input['error']) || is_array($file_input['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameters.'];
    }

    switch ($file_input['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'message' => 'No file sent.', 'code' => UPLOAD_ERR_NO_FILE];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'message' => 'File exceeds size limit.'];
        default:
            return ['success' => false, 'message' => 'Unknown upload error.'];
    }

    if ($file_input['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds 2MB limit.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file_input['tmp_name']);

    if (!array_key_exists($mime_type, ALLOWED_MIME_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file format. Only JPG, PNG, and PDF allowed.'];
    }

    $target_dir = UPLOAD_BASE_DIR . $category_folder . '/';
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create storage directory.'];
        }
    }

    // 5. Generate Safe Filename (Collision-safe & Sanitized)
    // Format: timestamp_randomhash.extension
    $extension = ALLOWED_MIME_TYPES[$mime_type];
    $filename = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(8)), $extension);
    $target_path = $target_dir . $filename;

    // 6. Move File
    if (!move_uploaded_file($file_input['tmp_name'], $target_path)) {
        return ['success' => false, 'message' => 'Failed to save file.'];
    }

    // Return relative path for database storage/retrieval
    return [
        'success' => true, 
        'path' => 'uploads/' . $category_folder . '/' . $filename,
        'message' => 'Upload successful.'
    ];
}

// --- Main Request Processing ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Define Mandatory Fields
    // 'olevel_file_2' logic is handled dynamically based on presence
    $mandatory_fields = ['passport_file', 'olevel_file', 'degree_file', 'transcript_file'];
    
    // Check if any files were actually uploaded
    if (empty($_FILES)) {
        $response['errors'][] = "No files received.";
    } else {
        
        foreach (UPLOAD_MAP as $field_name => $folder) {
            
            // Skip if file input is not present in the request
            if (!isset($_FILES[$field_name])) {
                // If it's mandatory and missing from $_FILES completely (rare in standard forms), flag error
                if (in_array($field_name, $mandatory_fields)) {
                    $response['errors'][$field_name] = "Missing required file input.";
                }
                continue;
            }

            $file_data = $_FILES[$field_name];

            // Attempt Upload
            $result = processFileUpload($file_data, $folder);

            if ($result['success']) {
                // Store path in Session
                $_SESSION['form_data']['step_8'][$field_name] = $result['path'];
                $response['success_messages'][$field_name] = "Uploaded successfully.";
            } else {
                // Handle Errors
                
                // If the error is "No file sent"
                if (isset($result['code']) && $result['code'] === UPLOAD_ERR_NO_FILE) {
                    // If mandatory, it's an error
                    if (in_array($field_name, $mandatory_fields)) {
                        $response['errors'][$field_name] = "This file is required.";
                    }
                    // Special logic for O'Level 2: Check if "Two Sittings" was selected previously
                    // Assuming $_POST['sittings_count'] or similar flag exists, OR just ignore if optional
                    elseif ($field_name === 'olevel_file_2') {
                        // If user indicated 2 sittings in a previous step/post field, this is required
                        if (isset($_POST['sittings_mode']) && $_POST['sittings_mode'] === 'two' && empty($_SESSION['form_data']['step_8'][$field_name])) {
                            $response['errors'][$field_name] = "Second O'Level result is required for two sittings.";
                        }
                    }
                } else {
                    // Genuine validation/upload error (wrong type, too big, etc.)
                    $response['errors'][$field_name] = $result['message'];
                }
            }
        }
    }

    // 2. Final Status Check
    if (empty($response['errors'])) {
        $response['status'] = true;
        // Optional: Redirect to next step
        // header("Location: review.php");
        // exit;
    }
}

// --- Output Handling (JSON for AJAX or HTML for Standard Post) ---

// Check if request expects JSON
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<meta charset="UTF-8">
    <title>Document Upload Status</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; max-width: 800px; margin: 2rem auto; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-success { color: #3c763d; background-color: #dff0d8; border-color: #d6e9c6; }
        .alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
        .file-list { margin-top: 20px; }
        code { background: #f4f4f4; padding: 2px 5px; }
    </style>
</head>
<body>
    <h2>Upload Status</h2>

    <?php if (!empty($response['errors'])): ?>
        <div class="alert alert-danger">
            <strong>Upload Failed:</strong>
            <ul>
                <?php foreach ($response['errors'] as $field => $msg): ?>
                    <li><strong><?php echo htmlspecialchars($field); ?>:</strong> <?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $response['status']): ?>
        <div class="alert alert-success">
            <strong>Success!</strong> All valid documents have been uploaded and saved to the session.
        </div>
    <?php endif; ?>

    <div class="file-list">
        <h3>Current Session Data (Step 8)</h3>
        <?php if (!empty($_SESSION['form_data']['step_8'])): ?>
            <ul>
                <?php foreach ($_SESSION['form_data']['step_8'] as $key => $path): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($key); ?>:</strong> 
                        <a href="<?php echo htmlspecialchars($path); ?>" target="_blank">View File</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No files uploaded yet.</p>
        <?php endif; ?>
    </div>
    
    <p><a href="javascript:history.back()">Go Back to Form</a></p>
</body>
</html>