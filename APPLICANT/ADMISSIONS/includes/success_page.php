<?php
session_start();

// If there is no reference number in the session, the user shouldn't be here
if (!isset($_SESSION['last_ref'])) {
    header("Location: form_step_8.php");
    exit;
}

$reference_number = $_SESSION['last_ref'];

// Optional: Clear the reference from session after displaying so it doesn't persist forever
// unset($_SESSION['last_ref']); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Submitted Successfully</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .success-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; }
        .icon-circle { width: 80px; height: 80px; background: #27ae60; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px; }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        p { color: #7f8c8d; line-height: 1.6; }
        .ref-box { background: #f8f9fa; border: 2px dashed #bdc3c7; padding: 15px; margin: 25px 0; border-radius: 8px; }
        .ref-label { display: block; font-size: 0.8rem; color: #95a5a6; text-transform: uppercase; letter-spacing: 1px; }
        .ref-number { font-size: 1.5rem; font-weight: bold; color: #2c3e50; font-family: 'Courier New', Courier, monospace; }
        .btn-home { display: inline-block; background: #3498db; color: white; padding: 12px 30px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background 0.3s; }
        .btn-home:hover { background: #2980b9; }
        .print-link { display: block; margin-top: 20px; color: #95a5a6; text-decoration: none; font-size: 0.9rem; }
        .print-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="success-card">
    <div class="icon-circle">✓</div>
    <h1>Submission Received!</h1>
    <p>Your application and documents have been successfully uploaded and queued for review by the admissions office.</p>
    
    <div class="ref-box">
        <span class="ref-label">Your Reference Number</span>
        <span class="ref-number" id="refNum"><?php echo htmlspecialchars($reference_number); ?></span>
    </div>

    <p>Please keep this reference number for your records. An acknowledgement email has been sent to your registered address.</p>

    <a href="index.php" class="btn-home">Return to Homepage</a>
    
    <a href="javascript:window.print()" class="print-link">🖨️ Print Confirmation</a>
</div>

</body>
</html>