<?php
// Start session to retrieve existing uploads
session_start();

// Helper to check if a file is already uploaded
function getExistingFile($fieldName) {
    if (isset($_SESSION['form_data']['step_8'][$fieldName])) {
        $path = $_SESSION['form_data']['step_8'][$fieldName];
        $filename = basename($path);
        return "<div class='file-status success'>✅ <strong>Current File:</strong> <a href='$path' target='_blank'>$filename</a></div>";
    }
    return "";
}

// Helper to retrieve previous input values (e.g. sittings mode)
function getOldValue($key, $default = '') {
    return $_POST[$key] ?? $_SESSION['form_data']['step_8_meta'][$key] ?? $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 8: Document Uploads</title>
    <style>
        :root { --primary: #2c3e50; --accent: #3498db; --border: #ddd; --light: #f9f9f9; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; display: flex; justify-content: center; padding: 20px; }
        .upload-card { background: white; width: 100%; max-width: 700px; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        h2 { border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; color: var(--primary); }
        
        .form-group { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .form-group:last-child { border-bottom: none; }
        
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        .sub-label { display: block; font-size: 0.85em; color: #666; margin-bottom: 8px; }
        
        input[type="file"] { display: block; width: 100%; padding: 8px; background: var(--light); border: 1px solid var(--border); border-radius: 4px; }
        
        /* Radio/Select Styling */
        .radio-group { display: flex; gap: 15px; margin-bottom: 10px; }
        .radio-group label { font-weight: normal; cursor: pointer; }

        /* Status Styling */
        .file-status { margin-top: 8px; font-size: 0.9em; padding: 8px; background: #e8f6f3; border-left: 4px solid #1abc9c; border-radius: 2px; }
        .file-status a { color: #16a085; text-decoration: none; font-weight: bold; }
        .file-status a:hover { text-decoration: underline; }

        .hidden { display: none; }
        
        .btn-submit { background: var(--accent); color: white; border: none; padding: 12px 25px; font-size: 16px; border-radius: 4px; cursor: pointer; width: 100%; transition: background 0.3s; }
        .btn-submit:hover { background: #2980b9; }

        .note { font-size: 0.85rem; color: #e74c3c; margin-bottom: 20px; background: #fadbd8; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="upload-card">
    <h2>📂 Document Uploads</h2>
    
    <div class="note">
        <strong>Requirements:</strong> Max size <strong>2MB</strong> per file. Allowed: <strong>JPG, PNG, PDF</strong>.
    </div>

    <form action="upload_documents.php" method="POST" enctype="multipart/form-data" id="docForm">

        <div class="form-group">
            <label for="passport_file">Passport Photograph <span style="color:red">*</span></label>
            <span class="sub-label">White background, official appearance. (JPG/PNG only)</span>
            <input type="file" name="passport_file" id="passport_file" accept=".jpg,.jpeg,.png" <?php echo isset($_SESSION['form_data']['step_8']['passport_file']) ? '' : 'required'; ?>>
            <?php echo getExistingFile('passport_file'); ?>
        </div>

        <div class="form-group">
            <label>O'Level Sittings Mode</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="sittings_mode" value="one" checked onclick="toggleSittings('one')"> 
                    One Sitting
                </label>
                <label>
                    <input type="radio" name="sittings_mode" value="two" onclick="toggleSittings('two')"> 
                    Two Sittings
                </label>
            </div>
            
            <label for="olevel_file" style="margin-top:15px;">First Sitting Certificate <span style="color:red">*</span></label>
            <input type="file" name="olevel_file" id="olevel_file" accept=".pdf,.jpg,.jpeg,.png" <?php echo isset($_SESSION['form_data']['step_8']['olevel_file']) ? '' : 'required'; ?>>
            <?php echo getExistingFile('olevel_file'); ?>

            <div id="sitting_2_container" class="hidden" style="margin-top: 15px;">
                <label for="olevel_file_2">Second Sitting Certificate <span style="color:red">*</span></label>
                <input type="file" name="olevel_file_2" id="olevel_file_2" accept=".pdf,.jpg,.jpeg,.png">
                <?php echo getExistingFile('olevel_file_2'); ?>
            </div>
        </div>

        <div class="form-group">
            <label for="degree_file">Degree Certificate <span style="color:red">*</span></label>
            <input type="file" name="degree_file" id="degree_file" accept=".pdf,.jpg,.jpeg,.png" <?php echo isset($_SESSION['form_data']['step_8']['degree_file']) ? '' : 'required'; ?>>
            <?php echo getExistingFile('degree_file'); ?>
        </div>

        <div class="form-group">
            <label for="transcript_file">Academic Transcript <span style="color:red">*</span></label>
            <input type="file" name="transcript_file" id="transcript_file" accept=".pdf,.jpg,.jpeg,.png" <?php echo isset($_SESSION['form_data']['step_8']['transcript_file']) ? '' : 'required'; ?>>
            <?php echo getExistingFile('transcript_file'); ?>
        </div>

        <div class="form-group">
            <label for="nysc_file">NYSC Certificate / Exemption (Optional)</label>
            <input type="file" name="nysc_file" id="nysc_file" accept=".pdf,.jpg,.jpeg,.png">
            <?php echo getExistingFile('nysc_file'); ?>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="phd_trigger" onchange="togglePhD(this)"> 
                I am applying for a PhD program
            </label>

            <div id="proposal_container" class="hidden" style="margin-top: 10px;">
                <label for="proposal_file">Research Proposal <span style="color:red">*</span></label>
                <span class="sub-label">Upload your detailed research topic and methodology.</span>
                <input type="file" name="proposal_file" id="proposal_file" accept=".pdf">
                <?php echo getExistingFile('proposal_file'); ?>
            </div>
        </div>

        <button type="submit" class="btn-submit">Save & Upload Documents</button>
    </form>
</div>

<script>
    function toggleSittings(mode) {
        const container = document.getElementById('sitting_2_container');
        const input = document.getElementById('olevel_file_2');
        
        if (mode === 'two') {
            container.classList.remove('hidden');
            // Only require if not already uploaded (simplified logic here, better handled in backend)
            input.required = true; 
        } else {
            container.classList.add('hidden');
            input.required = false;
            input.value = ''; // Clear selection
        }
    }

    function togglePhD(checkbox) {
        const container = document.getElementById('proposal_container');
        const input = document.getElementById('proposal_file');

        if (checkbox.checked) {
            container.classList.remove('hidden');
            input.required = true;
        } else {
            container.classList.add('hidden');
            input.required = false;
        }
    }

    // Initialize state on load (in case browser cached the radio selection)
    window.addEventListener('DOMContentLoaded', () => {
        const twoSittings = document.querySelector('input[name="sittings_mode"][value="two"]');
        if (twoSittings && twoSittings.checked) {
            toggleSittings('two');
        }
    });
</script>

</body>
</html>