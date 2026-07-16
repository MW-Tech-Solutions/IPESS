<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../config/urls.php';

// Check if admissions module is active
$admissions_closed = false;
try {
    $modStmt = $pdo->prepare("SELECT is_active FROM system_modules WHERE module_key = 'admissions'");
    $modStmt->execute();
    $modVal = $modStmt->fetchColumn();
    $admissions_closed = ($modVal !== false && (int)$modVal === 0);
} catch (Throwable $e) {
    $admissions_closed = false;
}

if ($admissions_closed) {
    // Show a closed page instead of the registration form
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admissions Closed - IPESS FUAM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .card { background: rgba(255,255,255,0.95); border-radius: 16px; padding: 3rem; max-width: 520px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .icon-circle { width: 80px; height: 80px; background: linear-gradient(135deg, #782D32, #a04050); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem; color: white; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon-circle">🔒</div>
    <h2 class="fw-bold text-danger mb-2">Admissions Closed</h2>
    <p class="text-muted mb-3">The Admissions Exercise is currently <strong>not accepting new registrations</strong>. Please check back later or contact the admissions office for more information.</p>
    <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary mt-2">
        Back to Login
    </a>
</div>
</body>
</html>
<?php
    exit;
}

$studyModes = [];
$degreeTypes = [];
$courses = [];

try {
    $studyModes = $pdo->query("SELECT mode_id, mode_name FROM study_modes ORDER BY mode_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $studyModes = [];
}

try {
    $degreeTypes = $pdo->query("SELECT degree_id, degree_name FROM degree_types ORDER BY degree_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $degreeTypes = [];
}

try {
    $courses = $pdo->query("
        SELECT
            c.course_id,
            c.course_title,
            c.degree_id,
            c.dept_id,
            d.faculty_id
        FROM courses c
        LEFT JOIN departments d ON d.dept_id = c.dept_id
        ORDER BY c.course_title ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - IPESS FUAM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-green: #6EB533;
            --accent-burgundy: #782D32;
            --light-overlay: rgba(255, 255, 255, 0.95);
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0.15)), 
                        url('./images/auditorium.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 14px;
        }

        .login-card {
            background: var(--light-overlay);
            width: 100%;
            max-width: 1050px;
            padding: 30px 20px;
            border-radius: 8px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            color: #333333;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .logo-container {
            width: 85px;
            height: 85px;
            background-color: white;
            border-radius: 50%;
            margin: 20px auto 15px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 2px solid var(--primary-green);
        }

        .uni-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--accent-burgundy);
            text-align: center;
            background: transparent;
            padding: 0 10px;
        }

        .form-area {
            padding: 15px 22px 34px;
        }

        .registration-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 36px;
            row-gap: 10px;
        }

        .form-control,
        .form-select {
            height: 43px;
            border-radius: 4px !important;
            font-size: 1.08rem !important;
            border: 1px solid #dcdcdc;
            color: #1f2937;
            background-color: #ffffff;
            box-shadow: none !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-green);
        }

        .form-control::placeholder {
            color: #8b8f96;
        }

        .input-group-text {
            background-color: #ffffff !important;
            border: 1px solid #dcdcdc;
            border-left: none;
            color: #666;
            border-radius: 0 4px 4px 0 !important;
            padding-right: 15px;
            cursor: pointer;
        }

        .password-toggle .form-control {
            border-radius: 4px 0 0 4px !important;
            border-right: none;
        }

        .password-toggle:hover .input-group-text {
            color: var(--primary-green);
        }

        .btn-signin {
            background-color: var(--primary-green);
            border: none;
            height: 43px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 4px;
            margin-top: 0;
            color: white;
            transition: all 0.2s ease-in-out;
            padding: 0;
            display: inline-flex;
            align-items: center;
            overflow: hidden;
        }
        
        .btn-signin:hover {
            background-color: #5c972a;
            color: white;
            transform: translateY(-1px);
        }

        .footer-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 34px;
            gap: 16px;
        }

        .footer-links a, .btn-link {
            color: var(--accent-burgundy);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }
        
        .btn-link:hover {
            color: var(--primary-green);
        }

        .verification-step { display: none; }
        
        .otp-input { 
            width: 45px; 
            height: 55px;
            color: #000; 
            font-size: 22px; 
            font-weight: bold; 
            text-align: center; 
            border: 1px solid #dcdcdc; 
            border-radius: 8px; 
            margin: 0 4px; 
            transition: all 0.3s; 
            background-color: #ffffff;
        }
        
        .otp-input:focus { 
            outline: none; 
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(110, 181, 51, 0.2);
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
            color: #6b7280 !important;
        }
        
        .text-primary-custom {
            color: var(--primary-green) !important;
        }

        .login-link {
            display: inline-flex;
            align-items: center;
            background: var(--primary-green);
            color: #fff;
            border-radius: 4px;
            overflow: hidden;
            font-weight: 600;
            min-height: 43px;
            line-height: 1;
            transition: all 0.2s ease-in-out;
        }

        .login-link:hover {
            background-color: #5c972a;
            color: white;
            transform: translateY(-1px);
        }

        .login-link span,
        .create-label {
            padding: 0 18px;
            display: inline-flex;
            align-items: center;
            height: 43px;
        }

        .login-link i,
        .create-icon {
            background: rgba(0, 0, 0, 0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 43px;
            font-size: .98rem;
        }

        .verification-step {
            padding: 34px;
            color: #333333;
        }

        @media (max-width: 768px) {
            body { align-items: flex-start; padding: 12px; }
            .login-card { border-radius: 10px; }
            .form-area { padding: 18px 14px 22px; }
            .registration-grid { grid-template-columns: 1fr; gap: 10px; }
            .form-control,
            .form-select { height: 46px; font-size: 1rem !important; }
            .footer-links { flex-direction: column-reverse; align-items: stretch; margin-top: 20px; }
            .login-link,
            .btn-signin { justify-content: center; width: 100%; }
            .login-link span,
            .create-label { flex:1; justify-content:center; padding: 0 12px; }
            .login-link i,
            .create-icon { flex:0 0 42px; }
            .login-title { font-size: 1.45rem; }
            .otp-input { width: 38px; height: 50px; margin: 0 2px; }
        }
    </style>
</head>
<body>

<main class="login-card">
    
    <div id="step1-container">
        <header>
            <div class="logo-container">
                <img src="<?= htmlspecialchars(app_url('asset/homepage/ipess_logo.png'), ENT_QUOTES, 'UTF-8'); ?>" alt="IPESS FUAM Logo" class="uni-logo">
            </div>
            <h1 class="login-title">IPESS JOSTUM <h5> Create Account</h5></h1>
        </header>

        <div class="form-area">
        <div id="js-error-1"></div>

        <form onsubmit="event.preventDefault(); initiateSignup();">
            <div class="registration-grid">
                <input type="text" id="surname" class="form-control" placeholder="Surname" required>
                <input type="text" id="first_name" class="form-control" placeholder="First Name" required>
                <input type="text" id="other_name" class="form-control" placeholder="Other Name">
                <input type="email" id="email" class="form-control" placeholder="Email" required>
                <input type="tel" id="phone" class="form-control" placeholder="Phone Number" required>
                <select id="mode_of_study" class="form-select" required>
                    <?php foreach ($studyModes as $mode): ?>
                        <?php if (stripos((string) $mode['mode_name'], 'Full') !== false): ?>
                            <option value="<?php echo (int) $mode['mode_id']; ?>" selected>
                                <?php echo htmlspecialchars($mode['mode_name']); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <select id="programme_option" class="form-select" required>
                    <option value="">Programme Option</option>
                    <?php foreach ($degreeTypes as $degree): ?>
                        <option value="<?php echo (int) $degree['degree_id']; ?>">
                            <?php echo htmlspecialchars($degree['degree_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="programme" class="form-select" required>
                    <option value="">Programme</option>
                </select>
                <div class="input-group password-toggle">
                    <input type="password" id="password" class="form-control" placeholder="Password" required>
                    <span class="input-group-text" onclick="togglePassword('password', this)">
                        <i class="bi bi-eye-fill"></i>
                    </span>
                </div>
                <div class="input-group password-toggle">
                    <input type="password" id="confirm_password" class="form-control" placeholder="Confirm Password" required>
                    <span class="input-group-text" onclick="togglePassword('confirm_password', this)">
                        <i class="bi bi-eye-fill"></i>
                    </span>
                </div>
            </div>

            <footer class="footer-links">
                <a href="<?= htmlspecialchars(app_absolute_url('APPLICANT/ADMISSIONS/login.php')) ?>" class="login-link">
                    <i class="bi bi-box-arrow-in-right"></i><span>Already Have An Account? Sign In</span>
                </a>
                <button type="button" onclick="initiateSignup()" id="sendBtn" class="btn btn-signin">
                    <span class="create-icon"><i class="bi bi-person-plus-fill"></i></span><span class="create-label">Create Account</span>
                </button>
            </footer>


                        




        </form>
        </div>
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
    const courseOptions = <?php echo json_encode(array_map(static function (array $course): array {
        return [
            'id' => (int) $course['course_id'],
            'title' => (string) $course['course_title'],
            'degree_id' => (int) $course['degree_id'],
            'dept_id' => (int) $course['dept_id'],
            'faculty_id' => (int) ($course['faculty_id'] ?? 0),
        ];
    }, $courses)); ?>;
    const inputs = document.querySelectorAll('.otp-input');
    const programmeOption = document.getElementById('programme_option');
    const programme = document.getElementById('programme');

    function populateProgrammes() {
        const degreeId = Number(programmeOption.value || 0);
        const matchingCourses = courseOptions.filter(course => course.degree_id === degreeId);
        programme.innerHTML = '<option value="">Programme</option>';

        matchingCourses.forEach(course => {
            const option = document.createElement('option');
            option.value = course.id;
            option.textContent = course.title;
            option.dataset.deptId = course.dept_id;
            option.dataset.facultyId = course.faculty_id;
            programme.appendChild(option);
        });

        programme.disabled = !degreeId || matchingCourses.length === 0;
    }

    programmeOption?.addEventListener('change', populateProgrammes);
    populateProgrammes();

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
        const surname = document.getElementById('surname').value.trim();
        const firstName = document.getElementById('first_name').value.trim();
        const otherName = document.getElementById('other_name').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const email = document.getElementById('email').value;
        const modeOfStudy = document.getElementById('mode_of_study').value;
        const programmeOptionValue = programmeOption.value;
        const programmeValue = programme.value;
        const selectedProgramme = programme.options[programme.selectedIndex];
        const pass = document.getElementById('password').value;
        const confirm = document.getElementById('confirm_password').value;
        const btn = document.getElementById('sendBtn');

        if (!surname || !firstName || !phone || !email || !modeOfStudy || !programmeOptionValue || !programmeValue || !pass || pass !== confirm) {
            return showAlert("Please complete all required fields and confirm your passwords.", "js-error-1");
        }

        btn.disabled = true;
        btn.innerHTML = 'Processing...';

        const payload = {
            surname,
            first_name: firstName,
            other_name: otherName,
            phone,
            email,
            mode_of_study: modeOfStudy,
            programme_option: programmeOptionValue,
            programme: programmeValue,
            department: selectedProgramme?.dataset.deptId || '',
            faculty: selectedProgramme?.dataset.facultyId || '',
            password: pass,
            confirm
        };

        try {
            const response = await fetch('send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
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
                btn.innerHTML = '<span class="create-icon"><i class="bi bi-person-plus-fill"></i></span><span class="create-label">Create Account</span>';
            }
        } catch (e) {
            showAlert("Server connection failed.", "js-error-1");
            btn.disabled = false;
            btn.innerHTML = '<span class="create-icon"><i class="bi bi-person-plus-fill"></i></span><span class="create-label">Create Account</span>';
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
