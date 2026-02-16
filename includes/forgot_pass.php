<?php require_once __DIR__ . '/../config/urls.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
    <title>Forgot Password - JOSTUM Postgraduate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-blue: #2c4474;
            --accent-yellow: #f1b434;
            --overlay-blue: rgba(44, 68, 116, 0.88);
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: #cbd5e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .recovery-card {
            width: 100%;
            max-width: 450px;
            background: url('../images/jostumgate.jpeg');
            background-size: cover;
            background-position: center;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            position: relative;
        }

        .form-overlay {
            background: var(--overlay-blue);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
        }

        /* Custom Alert Styling */
        #statusAlert {
            display: none;
            width: 100%;
            border-radius: 15px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: none;
        }

        .icon-header {
            font-size: 3rem;
            color: var(--accent-yellow);
            margin-bottom: 20px;
        }

        h2 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .instruction-text {
            font-size: 0.9rem;
            margin-bottom: 25px;
            opacity: 0.9;
        }

        .form-control-custom {
            border-radius: 25px;
            padding: 12px 25px;
            border: none;
            width: 100%;
            margin-bottom: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .btn-recover {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            border: none;
            border-radius: 25px;
            padding: 12px;
            font-weight: 800;
            width: 100%;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-recover:hover:not(:disabled) {
            background: #e0a320;
            transform: translateY(-2px);
        }

        .back-to-login {
            margin-top: 25px;
            font-size: 0.85rem;
        }

        .back-to-login a {
            color: #fff;
            text-decoration: none;
        }

        .footer-copy {
            margin-top: 30px;
            font-size: 0.7rem;
            opacity: 0.6;
        }
    </style>
</head>
<body>

<div class="recovery-card">
    <div class="form-overlay">
        
        <div id="statusAlert" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Reset link sent to your email!
            <button type="button" class="btn-close" onclick="closeAlert()"></button>
        </div>

        <div class="icon-header" id="formIcon">
            <i class="fa-solid fa-key"></i>
        </div>
        
        <h2>Reset Password</h2>
        <p class="instruction-text">Enter your credentials to receive a recovery link.</p>
        
        <form id="recoveryForm" style="width: 100%;">
            <input type="email" id="emailInput" class="form-control-custom" placeholder="Email Address" required>

            <button type="submit" id="submitBtn" class="btn btn-recover">SEND RESET LINK</button>
        </form>

        <div class="back-to-login">
            <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php')) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Back to Login</a>
        </div>

        <div class="footer-copy">
            &copy; 2024 JOSTUM Postgraduate Portal
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const recoveryForm = document.getElementById('recoveryForm');
    const statusAlert = document.getElementById('statusAlert');
    const submitBtn = document.getElementById('submitBtn');
    const formIcon = document.getElementById('formIcon');

    recoveryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('emailInput').value;
        
        // UI Loading state
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> SENDING...';
        submitBtn.disabled = true;

        // Send to PHP via AJAX
        fetch('send_recovery.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.innerHTML = 'SEND RESET LINK';
            submitBtn.disabled = false;

            if (data.status === 'success') {
                statusAlert.innerText = data.message;
                statusAlert.className = "alert alert-success fade show mt-3";
                statusAlert.style.display = 'block';
                formIcon.style.color = '#2ecc71'; // Green for success
                recoveryForm.reset();
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Connection error. Please try again.");
            submitBtn.innerHTML = 'SEND RESET LINK';
            submitBtn.disabled = false;
        });
    });
</script>

</body>
</html>
