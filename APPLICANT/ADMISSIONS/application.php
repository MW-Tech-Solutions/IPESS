<?php require_once __DIR__ . '/../../config/urls.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="/JOSTUM/ADMIN/images/logo.jpeg">
<title>Postgraduate Application Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f1f4f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 60px;
        }
        .form-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            padding: 40px;
            margin-top: 30px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            list-style-type: none;
            padding: 0;
            position: relative;
        }
        .step-indicator li {
            text-align: center;
            width: 10%;
            position: relative;
        }
        .step-indicator li .step-icon {
            width: 35px;
            height: 35px;
            line-height: 32px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            margin: 0 auto 10px auto;
            font-weight: bold;
            display: block;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
            font-size: 14px;
        }
        .step-indicator li.active .step-icon {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }
        .step-indicator li.completed .step-icon {
            background: #198754;
            color: #fff;
            border-color: #198754;
        }
        .step-indicator li small {
            display: block;
            font-size: 10px;
            color: #6c757d;
            font-weight: 600;
        }
        
        .tab-pane {
            display: none;
            animation: fadeIn 0.4s;
        }
        .tab-pane.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
            font-weight: 700;
            color: #0d6efd;
        }

        .referee-card {
            background: #fff;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .exam-section {
            background: #fdfdfe;
            border: 1px solid #eaellf;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .ls-wide { letter-spacing: 0.05rem; }
        .cursor-pointer { cursor: pointer; }
        #captchaCanvas { background: #eee; filter: contrast(120%); }
    
        .is-invalid-shake {
            animation: shake 0.4s ease-in-out;
            border-color: #dc3545 !important;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.alert-primary {
    background-color: #e7f1ff;
    border: 1px dashed #0d6efd;
    color: #0d6efd;
}
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="form-container">
                
                <h3 class="text-center mb-2 text-primary">Postgraduate Application</h3>
                <p class="text-center text-muted mb-4">Complete the form below to apply</p>

                <div class="progress mb-4" style="height: 5px;">
                    <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                </div>

                <ul class="step-indicator">
                    <li class="active"><span class="step-icon">1</span><small>Personal</small></li>
                    <li><span class="step-icon">2</span><small>Programme</small></li>
                    <li><span class="step-icon">3</span><small>Academic</small></li>
                    <li><span class="step-icon">4</span><small>NYSC</small></li>
                    <li><span class="step-icon">5</span><small>Work</small></li>
                    <li><span class="step-icon">6</span><small>Motivation</small></li>
                    <li><span class="step-icon">7</span><small>Referees</small></li>
                    <li><span class="step-icon">8</span><small>Uploads</small></li>
                    <li><span class="step-icon">9</span><small>Finish</small></li>
                </ul>

                <form id="regForm" action="#" method="POST" enctype="multipart/form-data">
                    
                    
                    <div class="tab-pane active">
                        <h5 class="section-header">Personal Information</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Surname</label>
                                <input type="text" class="form-control" name="surname" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="firstName" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Other Name</label>
                                <input type="text" class="form-control" name="otherName">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sex</label>
                                <select class="form-select" name="sex" required>
                                    <option value="" selected disabled>Select...</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nationality</label>
                                <select class="form-select" name="nationality" required>
                                    <option value="Nigerian" selected>Nigerian</option>
                                    <option value="Non-Nigerian">Non-Nigerian</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">State of Origin</label>
                                <select
                                    class="form-select"
                                    id="stateSelect"
                                    required>
                                    <option value="" selected disabled>Select State...</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Local Govt. Area (LGA)</label>
                                <select
                                    class="form-select"
                                    id="lgaSelect"
                                    required>
                                    <option value="" selected disabled>Select State First</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" placeholder="+234..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword" placeholder="Enter password" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm password" required>
                                <div id="passwordFeedback" class="form-text"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address (Verification Required)</label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="emailInput" name="email" placeholder="name@example.com" required>
                                    <button class="btn btn-primary" type="button" id="sendOtpBtn" onclick="sendOTP()">
                                        Send OTP  
                                    </button>
                                </div>
                                
                                <div id="otpBox" class="mt-3 p-3 border rounded bg-light d-none">
                                    <label class="form-label small fw-bold">Enter the 6-digit code sent to your email:</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="otpInput" maxlength="6" placeholder="000000">
                                        <button class="btn btn-success" type="button" onclick="verifyOTP()">Verify</button>
                                    </div>
                                    <div id="otpSuccessMsg" class="text-success mt-1 d-none small">
                                        <i class="bi bi-check-circle-fill"></i> Email Verified Successfully!
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Residential Address</label>
                                <textarea class="form-control" rows="2" required></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">Programme Information</h5>
                        <div class="mb-3 col-md-6">
                            <label for="courseApplied" class="form-label">Course Applied For</label>
                            <select class="form-select" id="courseApplied" required>
                                <option value="" selected disabled>Select a course...</option>
                                <option value="msc_cs">M.Sc Computer Science</option>
                                <option value="mba">MBA</option>
                                <option value="phd_eng">PhD Engineering</option>
                            </select>
                        </div>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">Higher Education</h5>
                        <div class="row g-3 mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Highest Qualification</label>
                                <select class="form-select" required>
                                    <option value="">Choose...</option>
                                    <option value="BSc">B.Sc / B.A</option>
                                    <option value="MSc">M.Sc / M.A</option>
                                    <option value="HND">HND</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Course of Study</label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Institution Attended</label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Year of Graduation</label>
                                <input type="number" class="form-control" min="1950" max="2025" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class of Degree / CGPA</label>
                                <input type="text" class="form-control" placeholder="e.g. 4.5/5.0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mode of Study</label>
                                <select class="form-select" required>
                                    <option value="" selected disabled>Select Mode...</option>
                                    <option value="FT">Full Time (FT)</option>
                                    <option value="PT">Part Time (PT)</option>
                                </select>
                            </div>
                        </div>

                        <h5 class="section-header">SSCE 1 O'Level Results</h5>
                        <div class="exam-section">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name of School</label>
                                    <input type="text" class="form-control" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Exam Year</label>
                                    <input type="number" class="form-control" placeholder="YYYY" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Result Type</label>
                                    <select class="form-select" onchange="toggleOtherInput(this, 'otherType1')" required>
                                        <option value="">Select...</option>
                                        <option value="WAEC">WAEC</option>
                                        <option value="NECO">NECO</option>
                                        <option value="NABTEB">NABTEB</option>
                                        <option value="Others">Others (Specify)</option>
                                    </select>
                                    <input type="text" id="otherType1" class="form-control mt-2 d-none" placeholder="Specify Result Type">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Center Number</label>
                                    <input type="text" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Registration Number</label>
                                    <input type="text" class="form-control" required>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="olevelTable1">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">S/N</th>
                                            <th>Subject</th>
                                            <th>Grade</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="sn text-center align-middle">1</td>
                                            <td>
                                                <select class="form-select form-select-sm subject-select" required>
                                                    <option value="">Select Subject...</option>
                                                    </select>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" required>
                                                    <option value="">Grade...</option>
                                                    <option value="A1">A1</option>
                                                    <option value="A2">A2</option>
                                                    <option value="A3">A3</option>
                                                    <option value="B2">B2</option>
                                                    <option value="B3">B3</option>
                                                    <option value="C4">C4</option>
                                                    <option value="C5">C5</option>
                                                    <option value="C6">C6</option>
                                                    <option value="D7">D7</option>
                                                    <option value="E8">E8</option>
                                                    <option value="F9">F9</option>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, 'olevelTable1')"><i class="bi bi-trash"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addOLevelRow('olevelTable1')"><i class="bi bi-plus-circle"></i> Add Subject</button>
                        </div>

                        <div class="form-check form-switch my-4 ms-2">
                            <input class="form-check-input" type="checkbox" id="ssce2Toggle" onclick="toggleSSCE2()">
                            <label class="form-check-label fw-bold text-secondary" for="ssce2Toggle"> Combine a 2nd Sitting? (Optional)</label>
                        </div>

                        <div id="ssce2Container" class="d-none">
                            <h5 class="section-header text-secondary">SSCE 2 O'Level Results</h5>
                            <div class="exam-section bg-light border-0">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Name of School</label>
                                        <input type="text" class="form-control ssce2-input" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Exam Year</label>
                                        <input type="number" class="form-control ssce2-input" placeholder="YYYY" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Result Type</label>
                                        <select class="form-select ssce2-input" onchange="toggleOtherInput(this, 'otherType2')" required>
                                            <option value="">Select...</option>
                                            <option value="WAEC">WAEC</option>
                                            <option value="NECO">NECO</option>
                                            <option value="NABTEB">NABTEB</option>
                                            <option value="Others">Others (Specify)</option>
                                        </select>
                                        <input type="text" id="otherType2" class="form-control mt-2 d-none" placeholder="Specify Result Type">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Center Number</label>
                                        <input type="text" class="form-control ssce2-input" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Registration Number</label>
                                        <input type="text" class="form-control ssce2-input" required>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm" id="olevelTable2">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;">S/N</th>
                                                <th>Subject</th>
                                                <th>Grade</th>
                                                <th style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="sn text-center align-middle">1</td>
                                                <td>
                                                    <select class="form-select form-select-sm ssce2-input subject-select" required>
                                                        <option value="">Select Subject...</option>
                                                        </select>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm ssce2-input" required>
                                                        <option value="">Grade...</option>
                                                        <option value="A1">A1</option>
                                                        <option value="B2">B2</option>
                                                        <option value="B3">B3</option>
                                                        <option value="C4">C4</option>
                                                        <option value="C5">C5</option>
                                                        <option value="C6">C6</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, 'olevelTable2')"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addOLevelRow('olevelTable2')"><i class="bi bi-plus-circle"></i> Add Subject</button>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">NYSC / Eligibility</h5>
                        <div class="mb-3 col-md-6">
                            <label class="form-label">NYSC Status</label>
                            <select class="form-select" required>
                                <option value="Completed">Completed</option>
                                <option value="Exempted">Exempted</option>
                                <option value="Not Yet">Not Yet</option>
                            </select>
                        </div>
                        <div class="mb-3 col-md-6">
                            <label class="form-label">NYSC Certificate Number</label>
                            <input type="text" class="form-control" placeholder="If applicable">
                        </div>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">Employment & Experience</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employment Status</label>
                                <select class="form-select">
                                    <option value="Employed">Employed</option>
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="SelfEmployed">Self-Employed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Years of Relevant Experience</label>
                                <input type="number" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Current Employer / Organization</label>
                                <input type="text" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position Held</label>
                                <input type="text" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">Research & Motivation</h5>
                        <div class="mb-3">
                            <label class="form-label">Statement of Purpose</label>
                            <textarea class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Proposed Area of Research / Interest</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Career Objectives After Graduation</label>
                            <textarea class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">Referees</h5>
                        <div id="refereeContainer">
                            <div class="referee-card">
                                <h6 class="text-primary mb-3">Referee 1 (Mandatory)</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Relationship</label>
                                        <select class="form-select" required>
                                            <option value="">Select Relationship...</option>
                                            <option value="Academic Supervisor">Academic Supervisor</option>
                                            <option value="Employer">Employer</option>
                                            <option value="Clergy">Clergy</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-primary mt-2" id="addRefBtn" onclick="addReferee()">
                            <i class="bi bi-person-plus-fill"></i> Add Referee
                        </button>
                    </div>

                    <div class="tab-pane">
                        <h5 class="section-header">Upload Documents</h5>
                        <div class="mb-3">
                            <label class="form-label">Passport Photograph</label>
                            <input class="form-control" type="file" accept="image/*" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Transcript(s)</label>
                            <input class="form-control" type="file" accept=".pdf,.jpg,.png" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Degree Certificate</label>
                            <input class="form-control" type="file" accept=".pdf,.jpg,.png" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SSCE Certificate(s)</label>
                            <input class="form-control" type="file" accept=".pdf,.jpg,.png" multiple>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">NYSC Certificate</label>
                            <input class="form-control" type="file" accept=".pdf,.jpg,.png">
                        </div>
                    </div>
                    
                    
                    <div class="tab-pane">
    <h5 class="section-header">Finalize Application</h5>
    
    <div class="card border-0 bg-light mb-4">
        <div class="card-body p-3">
            <h6 class="fw-bold text-dark d-flex align-items-center mb-2">
                <i class="bi bi-patch-check-fill text-primary me-2"></i>
                Declaration
            </h6>
          
                        <div class="alert alert-light border">
                            <p class="mb-0 small text-muted">I hereby declare that the details furnished above are true and correct to the best of my knowledge and belief. I undertake the responsibility to inform about any changes therein, immediately.</p>
                        </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="declarationCheck" required>
                <label class="form-check-label small text-muted" for="declarationCheck">
                    I confirm the information provided is authentic and correct.
                </label>
            </div>
        </div>
    </div>

 
    <div class="row g-3">
    <div class="col-12">
        <label class="form-label small fw-bold text-uppercase text-muted">Security Check</label>
    </div>
    
    <div class="col-md-6">
        <div class="d-inline-flex align-items-center gap-2 p-2 bg-white border rounded">
            <canvas id="captchaCanvas" width="180" height="50" class="rounded" style="background: #eee;"></canvas>
            <button type="button" class="btn btn-light border-0" onclick="drawCaptcha()" title="Refresh">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </div>

    <div class="w-100"></div>

    <div class="col-md-6 mt-2"> 
        <div class="input-group" style="max-width: 300px;"> <input type="text" id="captchaInput" class="form-control" placeholder="Enter code" maxlength="6" autocomplete="off">
            <button class="btn btn-primary" type="button" id="verifyCaptchaBtn">
                Verify
            </button>
        </div>
        <div id="captchaStatus" class="mt-1 small fw-bold"></div>
    </div>
</div>
</div>

    </div>

                    <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                        <button type="button" class="btn btn-secondary px-4" id="prevBtn" onclick="nextPrev(-1)">Previous</button>
                        <button type="button" class="btn btn-primary px-4" id="nextBtn" onclick="nextPrev(1)">Next Step</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="successModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
            <div class="modal-body">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                </div>
                <h3 class="fw-bold mb-2">Application Received!</h3>
                <p class="text-muted">Your application has been successfully submitted.</p>
                
                <div class="alert alert-primary d-inline-block px-4 py-2 my-3">
                    <span class="small text-uppercase text-muted fw-bold d-block">Application ID</span>
                    <span class="fs-4 fw-bold" id="generatedAppId">PG-....-....</span>
                </div>

                <p class="small text-muted mb-4">
                    An email has been sent to <span id="userEmailDisplay" class="fw-bold"></span> with this ID.
                    <br>Please check your inbox/spam folder.
                </p>

                <div class="d-grid gap-2">
                    <a href="<?= htmlspecialchars(app_url('APPLICANT/ADMISSIONS/login.php')) ?>" class="btn btn-primary fw-bold">
                        Login to Track Status
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Acknowledgement
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="asset/states.js"></script>
<script src="asset/subjects.js"></script>

<script>
    let currentStep = 0; 
    const tabs = document.querySelectorAll(".tab-pane");
    const stepIndicators = document.querySelectorAll(".step-indicator li");
    const progressBar = document.getElementById("progressBar");
    const nextBtn = document.getElementById("nextBtn");
    const prevBtn = document.getElementById("prevBtn");

    // Initialize Form
    showTab(currentStep);
    const dateField = document.getElementById('dateField');
    if(dateField) dateField.valueAsDate = new Date();

    function showTab(n) {
        tabs.forEach(tab => tab.classList.remove("active"));
        tabs[n].classList.add("active");

        stepIndicators.forEach((indicator, index) => {
            indicator.classList.remove("active");
            if(index < n) indicator.classList.add("completed"); 
            else indicator.classList.remove("completed");
            
            if(index === n) indicator.classList.add("active");
        });

        const progress = ((n + 1) / tabs.length) * 100;
        progressBar.style.width = progress + "%";

        prevBtn.disabled = (n === 0);
        
        if (n === (tabs.length - 1)) {
            nextBtn.innerHTML = "Submit Application";
            nextBtn.classList.replace("btn-primary", "btn-success");
            // Hide the next button on last step as we have a specific submit button
            nextBtn.style.display = "block";
        } else {
            nextBtn.innerHTML = "Next Step";
            nextBtn.classList.replace("btn-success", "btn-primary");
            nextBtn.style.display = "block";
        }
    }

 
    function nextPrev(n) {
    if (n === 1 && !validateCurrentTab()) return false;
    if (currentStep === 0 && n === 1) {
        if (!isEmailVerified) {
            alert("You must verify your email address before you can proceed.");
            document.getElementById('emailInput').focus();
            return false;
        }
    }
    if (currentStep === (tabs.length - 1) && n === 1) {
        handleFinalSubmission(); 
        return false;
    }

    tabs[currentStep].classList.remove("active");
    currentStep = currentStep + n;

    if (currentStep === (tabs.length - 1)) {
        showTab(currentStep); 
        setTimeout(() => {
            drawCaptcha(); 
        }, 150);
    } else {
        showTab(currentStep);
    }
}
    function validateCurrentTab() {
        let valid = true;
        const currentTab = tabs[currentStep];
        const inputs = currentTab.querySelectorAll("input, select, textarea");

        inputs.forEach(input => {
            if (input.offsetParent !== null && input.hasAttribute('required') && input.value.trim() === "") {
                input.classList.add("is-invalid");
                valid = false;
            } else {
                input.classList.remove("is-invalid");
            }
        });

        if (!valid) {
            alert("Please fill in all required fields.");
        }
        return valid;
    }

    function toggleSSCE2() {
        const toggle = document.getElementById("ssce2Toggle");
        const container = document.getElementById("ssce2Container");
        const inputs = container.querySelectorAll(".ssce2-input");

        if (toggle.checked) {
            container.classList.remove("d-none");
            inputs.forEach(input => input.setAttribute("required", "required"));
        } else {
            container.classList.add("d-none");
            inputs.forEach(input => input.removeAttribute("required"));
        }
    }


    document.addEventListener("DOMContentLoaded", () => {
        const stateSelect = document.getElementById("stateSelect");
        const lgaSelect = document.getElementById("lgaSelect");
        
        if (typeof stateLgas !== 'undefined') {
            Object.keys(stateLgas).sort().forEach(state => {
                const option = document.createElement("option");
                option.value = state;
                option.textContent = state === "FCT" ? "Abuja (FCT)" : state;
                stateSelect.appendChild(option);
            });
        }

        stateSelect.addEventListener("change", function () {
            const selectedState = this.value;
            lgaSelect.innerHTML = '<option value="" selected disabled>Select LGA...</option>';

            if (stateLgas && stateLgas[selectedState]) {
                stateLgas[selectedState].forEach(lga => {
                    const option = document.createElement("option");
                    option.value = lga;
                    option.textContent = lga;
                    lgaSelect.appendChild(option);
                });
            } else {
                 const option = document.createElement("option");
                 option.value = "Others";
                 option.textContent = "Others";
                 lgaSelect.appendChild(option);
            }
        });

        populateSubjects();
    });

    function populateSubjects() {
        const subjectSelects = document.querySelectorAll('.subject-select');
        
        subjectSelects.forEach(select => {
            if(select.options.length <= 1 && typeof subjects !== 'undefined') {
                subjects.forEach(subject => {
                    const option = document.createElement("option");
                    option.value = subject;
                    option.textContent = subject;
                    select.appendChild(option);
                });
            }
        });
    }

    function toggleOtherInput(selectEl, inputId) {
        const inputEl = document.getElementById(inputId);
        if (selectEl.value === 'Others') {
            inputEl.classList.remove('d-none');
            inputEl.setAttribute('required', 'required');
        } else {
            inputEl.classList.add('d-none');
            inputEl.removeAttribute('required');
        }
    }
    
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const feedback = document.getElementById('passwordFeedback');

    function validatePasswords() {
        if (confirmPassword.value === '') {
            feedback.textContent = '';
            confirmPassword.classList.remove('is-valid', 'is-invalid');
            return;
        }

        if (newPassword.value === confirmPassword.value) {
            feedback.textContent = 'Passwords match!';
            feedback.className = 'form-text text-success';
            confirmPassword.classList.remove('is-invalid');
            confirmPassword.classList.add('is-valid');
        } else {
            feedback.textContent = 'Passwords do not match.';
            feedback.className = 'form-text text-danger';
            confirmPassword.classList.remove('is-valid');
            confirmPassword.classList.add('is-invalid');
        }
    }

    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);

    function addOLevelRow(tableId) {
        const table = document.getElementById(tableId).getElementsByTagName('tbody')[0];
        const rowCount = table.rows.length;
        const row = table.insertRow(rowCount);
        
        const inputClass = tableId === 'olevelTable2' ? 'form-select form-select-sm ssce2-input' : 'form-select form-select-sm';
        const isRequired = tableId === 'olevelTable2' ? (document.getElementById("ssce2Toggle").checked ? 'required' : '') : 'required';
        const subjectClass = tableId === 'olevelTable2' ? 'form-select form-select-sm ssce2-input subject-select' : 'form-select form-select-sm subject-select';

        let subjectOptions = '<option value="">Select Subject...</option>';
        if (typeof subjects !== 'undefined') {
            subjects.forEach(subject => {
                subjectOptions += `<option value="${subject}">${subject}</option>`;
            });
        }

        row.innerHTML = `
            <td class="sn text-center align-middle">${rowCount + 1}</td>
            <td>
                <select class="${subjectClass}" ${isRequired}>
                    ${subjectOptions}
                </select>
            </td>
            <td>
                <select class="${inputClass}" ${isRequired}>
                    <option value="">Grade...</option>
                    <option value="A1">A1</option>
                    <option value="B2">B2</option>
                    <option value="B3">B3</option>
                    <option value="C4">C4</option>
                    <option value="C5">C5</option>
                    <option value="C6">C6</option>
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, '${tableId}')"><i class="bi bi-trash"></i></button>
            </td>
        `;
    }

    function removeRow(btn, tableId) {
        const row = btn.parentNode.parentNode;
        row.parentNode.removeChild(row);
        
        const table = document.getElementById(tableId);
        const rows = table.getElementsByTagName('tbody')[0].rows;
        for (let i = 0; i < rows.length; i++) {
            rows[i].cells[0].innerText = i + 1;
        }
    }

    function addReferee() {
        const container = document.getElementById("refereeContainer");
        const count = container.children.length + 1;
        
        const div = document.createElement('div');
        div.className = 'referee-card';
        div.innerHTML = `
            <button type="button" class="btn btn-sm btn-danger remove-ref-btn" onclick="this.parentElement.remove()">
                <i class="bi bi-x"></i> Remove
            </button>
            <h6 class="text-primary mb-3">Referee ${count}</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Relationship</label>
                    <select class="form-select" required>
                        <option value="">Select...</option>
                        <option value="Academic Supervisor">Academic Supervisor</option>
                        <option value="Employer">Employer</option>
                        <option value="Clergy">Clergy</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" required>
                </div>
            </div>
        `;
        container.appendChild(div);
    }

    let isEmailVerified = false;

    
    async function sendOTP() {
    const emailInput = document.getElementById('emailInput');
    const email = emailInput.value;
    const sendBtn = document.getElementById('sendOtpBtn');

    if (!email || !email.includes('@')) {
        alert("Please enter a valid email address.");
        return;
    }

    sendBtn.disabled = true;
    sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

    try {
        const response = await fetch('send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email })
        });

        const text = await response.text(); 
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            throw new Error("Server returned non-JSON response: " + text);
        }

        if (result.success) {
            alert("OTP sent successfully to " + email);
            document.getElementById('otpBox').classList.remove('d-none');
            document.getElementById('otpInput').focus();
        } else {
            alert("Error: " + result.message);
        }
    } catch (err) {
        console.error(err);
        alert("Server connection failed. Check console for details.");
    } finally {
        sendBtn.disabled = false;
        sendBtn.innerText = "Resend Code";
    }
}

async function verifyOTP() {
    const otpInput = document.getElementById('otpInput');
    const userInput = otpInput.value;
    const emailField = document.getElementById('emailInput');
    const sendBtn = document.getElementById('sendOtpBtn');

    if (userInput.length < 6) {
        alert("Please enter the full 6-digit code.");
        return;
    }

   try {
        const response = await fetch('verify_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otp: userInput })
        });
        
        const result = await response.json();

        if (result.success) {
            isEmailVerified = true;
            document.getElementById('otpSuccessMsg').classList.remove('d-none');
            otpInput.classList.add('is-valid');
            otpInput.disabled = true;
            emailField.readOnly = true;
            emailField.classList.add('bg-light');
            sendBtn.disabled = true;
            sendBtn.innerText = "Verified";
            alert("Email verified successfully!");
        } else {
            alert("Invalid OTP. Please try again.");
        }
    } catch (err) {
        alert("Error verifying OTP.");
    }
}

   

    let captchaCode = "";
    let isCaptchaVerified = false;

    showTab(currentStep);

   
    function drawCaptcha() {
    const canvas = document.getElementById("captchaCanvas");
    if(!canvas) return;
    
    const ctx = canvas.getContext("2d");
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    for (let i = 0; i < 50; i++) {
        ctx.fillStyle = `rgba(${Math.random()*255}, ${Math.random()*255}, ${Math.random()*255}, 0.3)`;
        ctx.beginPath();
        ctx.arc(Math.random() * canvas.width, Math.random() * canvas.height, Math.random() * 2, 0, Math.PI * 2);
        ctx.fill();
    }

    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    captchaCode = "";
    for (let i = 0; i < 6; i++) {
        captchaCode += chars.charAt(Math.floor(Math.random() * chars.length));
    }

    for(let i=0; i<5; i++) {
        ctx.strokeStyle = `rgba(${Math.random()*100}, ${Math.random()*100}, ${Math.random()*100}, 0.5)`;
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(0, Math.random() * canvas.height);
        ctx.bezierCurveTo(
            canvas.width / 3, Math.random() * canvas.height,
            (canvas.width / 3) * 2, Math.random() * canvas.height,
            canvas.width, Math.random() * canvas.height
        );
        ctx.stroke();
    }

    let x = 15;
    const fonts = ["Arial", "Verdana", "Courier New", "Georgia", "Times New Roman"];
    
    for(let i=0; i<captchaCode.length; i++) {
        const fontSize = Math.floor(Math.random() * 10) + 22; 
        const fontName = fonts[Math.floor(Math.random() * fonts.length)];
        
        ctx.save();
        ctx.font = `bold ${fontSize}px ${fontName}`;
        ctx.fillStyle = `rgb(${Math.random()*100}, ${Math.random()*100}, ${Math.random()*100})`; // Darker colors for readability
        
        const angle = (Math.random() - 0.5) * 0.6; 
        const yOffset = (Math.random() - 0.5) * 10;
        
        ctx.translate(x, 25 + yOffset);
        ctx.rotate(angle);
        ctx.fillText(captchaCode[i], 0, 0);
        
        ctx.restore();
        x += 24 + (Math.random() * 4); 
    }
    
    isCaptchaVerified = false;
    document.getElementById("captchaInput").value = "";
    document.getElementById("captchaInput").classList.remove("is-valid", "is-invalid");
    document.getElementById("captchaStatus").innerHTML = "";
}
document.addEventListener("DOMContentLoaded", () => {
    const verifyBtn = document.getElementById("verifyCaptchaBtn");
    
    if (verifyBtn) {
        verifyBtn.addEventListener("click", function() {
            const input = document.getElementById("captchaInput");
            const status = document.getElementById("captchaStatus");
            const val = input.value.toUpperCase().trim();
            
            if(val === captchaCode && captchaCode !== "") {
                isCaptchaVerified = true;
                input.classList.remove("is-invalid");
                input.classList.add("is-valid");
                status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Verified! You can now submit.</span>';
                
                input.disabled = true;
                this.disabled = true;
            } else {
                isCaptchaVerified = false;
                input.classList.add("is-invalid-shake"); 
                input.classList.add("is-invalid");
                status.innerHTML = '<span class="text-danger">Incorrect code. Please try again.</span>';
                
                setTimeout(() => input.classList.remove("is-invalid-shake"), 500);
                
                drawCaptcha(); 
                input.value = "";
            }
        });
    }
});

    function handleFinalSubmission() {
        const declCheck = document.getElementById("declarationCheck");
        if(!declCheck.checked) {
            alert("Please check the declaration box.");
            declCheck.focus();
            return;
        }

        if (!isCaptchaVerified) {
            alert("Please verify the security code first.");
            document.getElementById("captchaInput").focus();
            return;
        }

        const form = document.getElementById("regForm");
        const formData = new FormData(form);
        
        const submitBtn = document.getElementById("nextBtn");
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

        fetch('submit_application.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('generatedAppId').innerText = data.appId;
                document.getElementById('userEmailDisplay').innerText = data.email;
                
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            } else {
                alert("Error: " + data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Connection error. Please try again.");
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

</script>


</body>
</html>
