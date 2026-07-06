<?php
session_start();
// Redirect if user is already verified or hasn't registered yet
// if (!isset($_SESSION['temp_email'])) {
//     header("Location: register.php");
//     exit();
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Email - PG Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>:root{--bs-primary:#6EB533;--bs-primary-rgb:110,181,51}.btn-primary{background-color:#6EB533;border-color:#6EB533}.btn-primary:hover{background-color:#5a9e28;border-color:#5a9e28}.text-primary{color:#6EB533!important}</style>
    <style>
        body { background-color: #f1f4f8; display: flex; align-items: center; min-height: 100vh; }
        .verification-card { 
            background: #fff; padding: 40px; border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 100%; max-width: 450px; margin: auto; 
        }
        .otp-input-group input {
            width: 45px; height: 55px; font-size: 24px; font-weight: bold;
            text-align: center; border: 2px solid #dee2e6; border-radius: 8px; margin: 0 5px;
        }
        .otp-input-group input:focus { border-color: #6EB533; outline: none; box-shadow: 0 0 8px rgba(110,181,51,0.25); }
    </style>
</head>
<body>

<div class="verification-card text-center">
    <div class="mb-4">
        <div class="display-4 text-primary"><i class="bi bi-shield-lock"></i></div>
        <h3 class="fw-bold mt-2">Email Verification</h3>
        <p class="text-muted">We sent a 6-digit code to <br><strong><?php echo $_SESSION['temp_email']; ?></strong></p>
    </div>

    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger p-2 small"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <form action="verify_handler.php" method="POST" id="otpForm">
        <div class="otp-input-group d-flex justify-content-center mb-4">
            <input type="text" name="otp[]" maxlength="1" pattern="\d*" required autofocus>
            <input type="text" name="otp[]" maxlength="1" pattern="\d*" required>
            <input type="text" name="otp[]" maxlength="1" pattern="\d*" required>
            <input type="text" name="otp[]" maxlength="1" pattern="\d*" required>
            <input type="text" name="otp[]" maxlength="1" pattern="\d*" required>
            <input type="text" name="otp[]" maxlength="1" pattern="\d*" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
            Verify & Continue
        </button>
    </form>

    <div class="mt-4">
        <p class="small text-muted mb-1">Didn't receive the code?</p>
        <button id="resendBtn" class="btn btn-link btn-sm text-decoration-none" onclick="resendOTP()">Resend New Code</button>
        <div id="timer" class="small text-secondary mt-1"></div>
    </div>
</div>

<!-- <script>
    // Auto-focus next input
    const inputs = document.querySelectorAll('.otp-input-group input');
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });

    // Simple 60s Timer for Resend
    let timeLeft = 60;
    const timerElem = document.getElementById('timer');
    const resendBtn = document.getElementById('resendBtn');

    function startTimer() {
        resendBtn.style.pointerEvents = 'none';
        resendBtn.classList.add('text-muted');
        const interval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(interval);
                timerElem.innerHTML = "";
                resendBtn.style.pointerEvents = 'auto';
                resendBtn.classList.remove('text-muted');
                timeLeft = 60;
            } else {
                timerElem.innerHTML = `Wait ${timeLeft}s to resend`;
                timeLeft--;
            }
        }, 1000);
    }
    
    startTimer();

    function resendOTP() {
        // Implementation: AJAX call to resend_otp.php
        alert("A new code has been sent!");
        startTimer();
    }
</script> -->
<script>
    const inputs = document.querySelectorAll('.otp-input-group input');

    // Handle Individual Input and Auto-Focus
    inputs.forEach((input, index) => {
        // Handle typing numbers
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        // Handle Backspace
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                inputs[index - 1].focus();
            }
        });

        // --- NEW: Handle Copy/Paste functionality ---
        input.addEventListener('paste', (e) => {
            e.preventDefault(); // Stop the default paste action
            
            // Get the data from clipboard
            const pasteData = e.clipboardData.getData('text').trim();
            
            // Check if the pasted content is numeric and roughly the right length
            if (!/^\d+$/.test(pasteData)) return; 

            // Split the string into characters
            const characters = pasteData.split('');

            // Start filling from the current focused box onwards
            characters.forEach((char, i) => {
                if (index + i < inputs.length) {
                    inputs[index + i].value = char;
                }
            });

            // Set focus to the last filled box or the final box
            const lastFilledIndex = Math.min(index + characters.length - 1, inputs.length - 1);
            inputs[lastFilledIndex].focus();
            
            // Optional: Automatically submit the form if 6 digits are pasted
            if (characters.length === 6) {
                // Uncomment the line below to auto-submit on paste
                // document.getElementById('otpForm').submit();
            }
        });
    });

    // --- Resend Timer Logic ---
    let timeLeft = 60;
    const timerElem = document.getElementById('timer');
    const resendBtn = document.getElementById('resendBtn');

    function startTimer() {
        resendBtn.style.pointerEvents = 'none';
        resendBtn.classList.add('text-muted');
        const interval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(interval);
                timerElem.innerHTML = "";
                resendBtn.style.pointerEvents = 'auto';
                resendBtn.classList.remove('text-muted');
                timeLeft = 60;
            } else {
                timerElem.innerHTML = `Wait ${timeLeft}s to resend`;
                timeLeft--;
            }
        }, 1000);
    }
    
    startTimer();
</script>
</body>
</html>