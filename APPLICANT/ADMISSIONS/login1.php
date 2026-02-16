<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/ADMIN/images/logo.jpeg">
<title>PG Student Portal | JOSTUM</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        html, body { height: 100%; width: 100%; margin: 0; padding: 0; overflow: hidden; }

        .page-wrapper {
            display: flex; align-items: center; justify-content: center;
            height: 100vh; width: 100vw; background-color: #0f172a;
            background-size: cover; background-position: center;
            transition: background-image 1.5s ease-in-out;
        }

        .login-container {
            width: 95%; max-width: 860px; background: #fff;
            border-radius: 20px; display: flex; overflow: hidden;
            box-shadow: 0 40px 100px rgba(0,0,0,0.5); z-index: 10;
        }

        .brand-section {
            flex: 1; padding: 25px 25px 20px 25px; 
            background: #ffffff; display: flex; flex-direction: column;
            align-items: center; justify-content: flex-start; 
            border-right: 1px solid #f1f5f9;
        }

        .brand-header h1 {
            font-size: 0.85rem; font-weight: 700; color: #1e2f55;
            text-transform: uppercase; margin: 0; line-height: 1.2;
        }

        .pg-badge {
            display: inline-block; margin-top: 6px; padding: 2px 10px;
            background: #f1f5f9; border-radius: 100px; font-size: 0.65rem;
            font-weight: 600; color: #64748b;
        }

        .emblem-wrapper { flex-grow: 1; display: flex; align-items: center; }
        .main-logo { width: 100%; max-width: 190px; filter: drop-shadow(0 5px 10px rgba(0,0,0,0.05)); }

        .form-section {
            flex: 1.1; background: #1e2f55; padding: 35px 40px;
            color: white; display: flex; flex-direction: column; justify-content: center;
            position: relative;
        }

        #alert-container {
            position: absolute; top: 20px; left: 40px; right: 40px; z-index: 100;
        }

        .form-header { margin-bottom: 20px; }
        .form-header h2 { font-weight: 700; font-size: 1.5rem; margin-bottom: 4px; }
        .form-header p { color: #94a3b8; font-size: 0.8rem; }

        .input-group-custom { width: 100%; margin-bottom: 12px; position: relative; }
        .label-text { display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 5px; color: #cbd5e1; }
        
        .form-control-custom { 
            border-radius: 10px; padding: 10px 15px; border: 2px solid transparent;
            width: 100%; background: #f8fafc; color: #1e2f55; font-size: 0.9rem; outline: none;
            transition: 0.2s;
        }
        .form-control-custom:focus { border-color: #f1b434; background: #fff; }

        .password-toggle { position: absolute; right: 15px; bottom: 10px; color: #94a3b8; cursor: pointer; }

        .form-options {
            display: flex; justify-content: space-between; align-items: center;
            width: 100%; margin-bottom: 15px; font-size: 0.75rem;
        }

        .btn-login { 
            background: #f1b434; color: #1e2f55; border: none; border-radius: 10px; 
            padding: 12px; font-weight: 700; width: 100%; font-size: 0.95rem;
            transition: 0.2s; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px;
        }
        .btn-login:disabled { opacity: 0.8; cursor: not-allowed; }

        .footer-text { margin-top: 20px; font-size: 0.7rem; color: #94a3b8; text-align: center; }

        .spinner {
            width: 18px; height: 18px; border: 3px solid rgba(30, 47, 85, 0.3);
            border-radius: 50%; border-top-color: #1e2f55; animation: spin 0.8s linear infinite;
            display: none;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 850px) { .brand-section { display: none; } .login-container { max-width: 400px; } }
    </style>
</head>
<body>

<div class="page-wrapper" id="bgNode">
    <main class="login-container">
        <section class="brand-section text-center">
            <header class="brand-header">
                <h1>Joseph Sarwuan Tarka University Makurdi</h1>
                <span class="pg-badge">POSTGRADUATE SCHOOL</span>
            </header>
            <div class="emblem-wrapper">
                <img src="./images/jostum.png" alt="JOSTUM Emblem" class="main-logo">
            </div>
            <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 10px;">Established 1988</div>
        </section>

        <section class="form-section">
            <div id="alert-container"></div>

            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Access your postgraduate portal.</p>
            </div>

            <form id="loginForm">
                <div class="input-group-custom">
                    <label class="label-text">Username</label>
                    <input type="text" id="matricNo" class="form-control-custom" placeholder="general" required>
                </div>

                <div class="input-group-custom">
                    <label class="label-text">Password</label>
                    <input type="password" id="passwordField" class="form-control-custom" placeholder="general" required>
                    <i class="fa-regular fa-eye password-toggle" onclick="togglePassword()"></i>
                </div>

                <div class="form-options">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe">
                        <label class="form-check-label" for="rememberMe" style="color: #cbd5e1; cursor: pointer;">Remember me</label>
                    </div>
                    <a href="./includes/forgot_pass.php" style="color: #f1b434; text-decoration: none; font-weight: 600;">Forgot Password?</a>
                </div>

                <button type="submit" id="signInBtn" class="btn-login">
                    <span class="spinner" id="btnSpinner"></span>
                    <span id="btnText">Sign In to Portal</span>
                </button>
            </form>

            <div class="footer-text">&copy; 2026 JOSTUM ICT Directorate</div>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const images = ['jostumhall-opt.jpg', 'jostumgate-opt.jpg', 'jostumstudents-opt.jpg'];
    let currentIndex = 0;
    const bgNode = document.getElementById('bgNode');

    function changeBackground() {
        const imageUrl = `./images/${images[currentIndex]}`;
        bgNode.style.backgroundImage = `linear-gradient(rgba(0,0,0,0.65), rgba(0,0,0,0.65)), url('${imageUrl}')`;
        currentIndex = (currentIndex + 1) % images.length;
    }
    changeBackground();
    setInterval(changeBackground, 6000);

    function togglePassword() {
        const p = document.getElementById('passwordField');
        const i = document.querySelector('.password-toggle');
        p.type = p.type === 'password' ? 'text' : 'password';
        i.classList.toggle('fa-eye'); i.classList.toggle('fa-eye-slash');
    }

    function showAlert(message, type = 'danger') {
        const alertContainer = document.getElementById('alert-container');
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show shadow-sm border-0" role="alert" id="authAlert">
                <i class="fas fa-exclamation-circle me-2"></i> ${message}
            </div>
        `;
        alertContainer.innerHTML = alertHtml;

        setTimeout(() => {
            const alertElement = document.getElementById('authAlert');
            if (alertElement) {
                const bsAlert = new bootstrap.Alert(alertElement);
                bsAlert.close();
            }
        }, 5000);
    }

    const loginForm = document.getElementById('loginForm');
    const signInBtn = document.getElementById('signInBtn');
    const btnSpinner = document.getElementById('btnSpinner');
    const btnText = document.getElementById('btnText');

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const matric = document.getElementById('matricNo').value;
        const pass = document.getElementById('passwordField').value;

        signInBtn.disabled = true;
        btnSpinner.style.display = 'block';
        btnText.innerText = 'Authenticating...';

        setTimeout(() => {
            if (matric === 'general' && pass === 'general') {
                window.location.href = 'dashboard.php';
            } else {
                showAlert('Invalid Credentials! Please try again.');
                
                signInBtn.disabled = false;
                btnSpinner.style.display = 'none';
                btnText.innerText = 'Sign In to Portal';
            }
        }, 1500);
    });
</script>
</body>
</html>
