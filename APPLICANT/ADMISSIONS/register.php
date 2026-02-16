<?php
session_start();
require_once __DIR__ . '/../../config/urls.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - JOSTUM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-blue: #21a1f1;
            --navy-overlay: rgba(18, 36, 62, 0.75);
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.2)), 
                        url('./images/jostumgate-opt.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 15px;
        }

        .login-card {
            background: var(--navy-overlay);
            width: 100%;
            padding: 20px 20px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            color: white;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .logo-container {
            width: 85px;
            height: 85px;
            background-color: white;
            border-radius: 10%;
            margin: 0 auto 15px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .uni-logo {
            width: 100%;
            height: 100%;
            object-fit: fill;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .form-control {
            height: 54px;
            border-radius: 10px 0 0 10px !important;
            font-size: 16px !important; 
            border: 1px solid transparent;
        }

        .input-group-text {
            background-color: white !important;
            border: none;
            color: #666;
            border-radius: 0 10px 10px 0 !important;
            padding-right: 15px;
            cursor: pointer;
        }

        .password-toggle:hover .input-group-text {
            color: var(--primary-blue);
        }

        .btn-signin {
            background-color: var(--primary-blue);
            border: none;
            height: 54px;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 10px;
            margin-top: 10px;
            color: white;
            transition: opacity 0.2s;
        }
        
        .btn-signin:hover {
            background-color: #1a8acb;
            color: white;
        }

        .footer-links {
            margin-top: 20px;
            font-size: 0.95rem;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a, .btn-link {
            color: var(--primary-blue);
            text-decoration: none;
        }
        
        .btn-link:hover {
            color: #fff;
        }

        .verification-step { display: none; }
        
        .otp-input { 
            width: 45px; 
            height: 55px;
            color: #000; 
            font-size: 22px; 
            font-weight: bold; 
            text-align: center; 
            border: none; 
            border-radius: 8px; 
            margin: 0 4px; 
            transition: all 0.3s; 
            background-color: rgba(255, 255, 255, 0.9);
        }
        
        .otp-input:focus { 
            outline: none; 
            background: #fff;
            box-shadow: 0 0 0 3px rgba(33, 161, 241, 0.5);
        }
        
        .otp-input.filled { background-color: #fff; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-7px); }
            40%, 80% { transform: translateX(7px); }
        }
        .otp-error { 
            border: 2px solid #dc3545 !important; 
            color: #dc3545 !important; 
            animation: shake 0.4s ease-in-out; 
        }
        
        .otp-input.is-valid { 
            border: 2px solid #198754 !important; 
            background-color: #e8f5e9 !important; 
            color: #198754 !important; 
        }

        .text-muted-custom {
            color: rgba(255, 255, 255, 0.6) !important;
        }
        
        .text-primary-custom {
            color: var(--primary-blue) !important;
        }

        @media (min-width: 576px) {
            .login-card { max-width: 400px; padding: 40px; }
            .logo-container { width: 95px; height: 95px; }
            .login-title { font-size: 1.8rem; }
            .footer-links { flex-direction: row; justify-content: center; gap: 0; }
            .login-link::before { content: "Already have an account? "; color: rgba(255,255,255,0.7); margin-right: 5px;}
        }
    </style>
</head>
<body>

<main class="login-card">
    
    <div id="step1-container">
        <header>
            <div class="logo-container">
                <img src="./images/jostum.jpeg" alt="JOSTUM Logo" class="uni-logo">
            </div>
            <h1 class="login-title">Create Account</h1>
        </header>

        <div id="js-error-1"></div>

        <form onsubmit="event.preventDefault(); initiateSignup();">
            <div class="input-group mb-3">
                <input type="email" id="email" class="form-control" placeholder="Email Address" required>
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            </div>

            <div class="input-group mb-3 password-toggle">
                <input type="password" id="password" class="form-control" placeholder="Password" required>
                <span class="input-group-text" onclick="togglePassword('password', this)">
                    <i class="bi bi-eye-fill"></i>
                </span>
            </div>

            <div class="input-group mb-4 password-toggle">
                <input type="password" id="confirm_password" class="form-control" placeholder="Confirm Password" required>
                <span class="input-group-text" onclick="togglePassword('confirm_password', this)">
                    <i class="bi bi-eye-fill"></i>
                </span>
            </div>

            <button type="button" onclick="initiateSignup()" id="sendBtn" class="btn btn-signin w-100">Sign Up</button>
        </form>
        
        <footer class="footer-links">
            <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php')) ?>" class="login-link">Login here</a>
        </footer>
    </div>

    <div id="step2-container" class="verification-step text-center">
        <div id="js-error-2"></div>
        
        <div class="mb-3 text-primary-custom"><i class="bi bi-envelope-check-fill display-4"></i></div>
        <h4 class="fw-bold mb-2">Enter Code</h4>
        <p class="text-muted-custom small mb-4">We sent a 6-digit code to your email.</p>
        
        <div class="d-flex justify-content-center mb-4">
            <input type="text" maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]*">
            <input type="text" maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]*">
            <input type="text" maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]*">
            <input type="text" maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]*">
            <input type="text" maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]*">
            <input type="text" maxlength="1" class="otp-input" inputmode="numeric" pattern="[0-9]*">
        </div>

        <div class="mb-3">
            <span id="resend-timer-text" class="text-muted-custom small">Resend code in <b id="timer-num" class="text-white">60</b>s</span>
            <button type="button" id="resend-btn" class="btn btn-link btn-sm fw-bold p-0" style="display:none;" onclick="resendOTP()">Resend Code</button>
        </div>

        <div id="verifying-spinner" style="display:none;" class="mb-3">
            <div class="spinner-border text-primary-custom" role="status"></div>
            <div class="small fw-bold text-primary-custom mt-1">Verifying...</div>
        </div>
        
        <button type="button" class="btn btn-link text-muted-custom btn-sm" onclick="location.reload()">Wrong email?</button>
    </div>
</main>

<script>
    const inputs = document.querySelectorAll('.otp-input');

    function showAlert(msg, targetId) {
        document.getElementById(targetId).innerHTML = `<div class="alert alert-danger py-2 small" style="border-radius:10px;"><i class="bi bi-exclamation-circle me-1"></i> ${msg}</div>`;
    }

    function clearErrors() {
        document.getElementById('js-error-2').innerHTML = '';
        inputs.forEach(inp => {
            inp.classList.remove('otp-error');
        });
    }

    let countdown;

    function startResendTimer() {
        let timeLeft = 60;
        const timerNum = document.getElementById('timer-num');
        const timerText = document.getElementById('resend-timer-text');
        const resendBtn = document.getElementById('resend-btn');

        timerText.style.display = 'inline';
        resendBtn.style.display = 'none';
        
        clearInterval(countdown);
        countdown = setInterval(() => {
            timeLeft--;
            timerNum.innerText = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerText.style.display = 'none';
                resendBtn.style.display = 'inline';
            }
        }, 1000);
    }
 
    async function resendOTP() {
        const email = document.getElementById('email').value;
        const resendBtn = document.getElementById('resend-btn');
        resendBtn.innerText = "Sending...";
        resendBtn.disabled = true;

        try {
            const response = await fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, resend: true })
            });
            const data = await response.json();
            if (data.success) {
                startResendTimer();
                resendBtn.innerText = "Resend Code";
                resendBtn.disabled = false;
            }
        } catch (e) {
            showAlert("Failed to resend.", "js-error-2");
            resendBtn.disabled = false;
        }
    }

    async function initiateSignup() {
        const email = document.getElementById('email').value;
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const btn = document.getElementById('sendBtn');

        if (!email || !pass || pass !== confirm) {
            return showAlert("Please check your passwords and email.", "js-error-1");
        }

        btn.disabled = true;
        btn.innerHTML = 'Processing...';

        try {
            const response = await fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, password: pass, confirm: confirm })
            });
            const data = await response.json();

            if (data.success) {
                document.getElementById('step1-container').style.display = 'none';
                document.getElementById('step2-container').style.display = 'block';
                inputs[0].focus();
                startResendTimer();
            } else {
                showAlert(data.message, "js-error-1");
                btn.disabled = false;
                btn.innerText = "Sign Up";
            }
        } catch (e) {
            showAlert("Server connection failed.", "js-error-1");
            btn.disabled = false;
        }
    }

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            clearErrors();
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            checkAllFilled();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').trim();
            if (/^\d{6}$/.test(pasteData)) {
                const digits = pasteData.split('');
                inputs.forEach((inp, i) => {
                    inp.value = digits[i];
                    inp.classList.add('filled');
                });
                checkAllFilled();
            }
        });
    });

    async function checkAllFilled() {
        const otpValues = Array.from(inputs).map(inp => inp.value);
        if (otpValues.every(v => v.length === 1)) {
            const spinner = document.getElementById('verifying-spinner');
            spinner.style.display = 'block';
            inputs.forEach(inp => inp.readOnly = true);

            try {
                const response = await fetch('reg_verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        otp: otpValues, 
                        email: document.getElementById('email').value,
                        password: document.getElementById('password').value 
                    })
                });
                const data = await response.json();

                if (data.success) {
                    spinner.style.display = 'none';
                    inputs.forEach(inp => {
                        inp.classList.remove('otp-error');
                        inp.classList.add('is-valid');
                        inp.readOnly = true;
                    });

                    setTimeout(() => {
                        window.location.href = '<?= addslashes(app_url('APPLICANT/ADMISSIONS/login.php?success=1')) ?>';
                    }, 5000);

                } else {
                    spinner.style.display = 'none';
                    showAlert(data.message, "js-error-2");
                    
                    inputs.forEach(inp => {
                        inp.classList.add('otp-error');
                        inp.readOnly = false;
                        inp.value = '';
                    });
                    setTimeout(() => {
                        inputs.forEach(inp => inp.classList.remove('otp-error'));
                    }, 450);
                    inputs[0].focus();
                }
            } catch (e) {
                spinner.style.display = 'none';
                inputs.forEach(inp => inp.readOnly = false);
                showAlert("Verification failed.", "js-error-2");
            }
        }
    }

    function togglePassword(inputId, iconElement) {
        const passwordInput = document.getElementById(inputId);
        const icon = iconElement.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye-fill');
            icon.classList.add('bi-eye-slash-fill');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash-fill');
            icon.classList.add('bi-eye-fill');
        }
    }
</script>
</body>
</html>
