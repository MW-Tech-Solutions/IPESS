<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Upload Project Files';
$con = db();

$msg = '';

if (isset($_POST['add'])) {
    $folder = $_POST['foldername'] ?? '';
    if ($folder !== '' && substr($folder, -1) !== '/') {
        $folder .= '/';
    }
    
    $target_dir = JOSTUM_ROOT . '/' . ltrim($folder, '/');
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    if (isset($_FILES['fileToUpload'])) {
        $file_name = $_FILES['fileToUpload']['name'];
        $file_size = $_FILES['fileToUpload']['size'];
        $file_tmp = $_FILES['fileToUpload']['tmp_name'];
        $file_type = $_FILES['fileToUpload']['type'];
        
        $path_info = pathinfo($file_name);
        $file_ext = strtolower($path_info['extension'] ?? '');

        $extensions = ['php', 'js', 'css', 'html', 'json'];

        if (in_array($file_ext, $extensions, true)) {
            if ($file_size < 5 * 1024 * 1024) { // 5MB limit
                $target_file = $target_dir . $file_name;
                if (move_uploaded_file($file_tmp, $target_file)) {
                    $msg = '<div class="alert alert-success"><strong>Success!</strong> File uploaded successfully to ' . htmlspecialchars($folder . $file_name) . '</div>';
                } else {
                    $msg = '<div class="alert alert-danger"><strong>Error!</strong> Failed to move uploaded file. Check folder permissions.</div>';
                }
            } else {
                $msg = '<div class="alert alert-danger"><strong>Error!</strong> File too large. Maximum size is 5MB.</div>';
            }
        } else {
            $msg = '<div class="alert alert-danger"><strong>Error!</strong> File format not supported. Only PHP, JS, CSS, HTML, and JSON files are allowed.</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger"><strong>Error!</strong> Please select a file to upload.</div>';
    }
}

require_once __DIR__ . '/../super-admin/includes/header.php';
require_once __DIR__ . '/../super-admin/includes/topbar.php';
require_once __DIR__ . '/../super-admin/includes/sidebar.php';
?>

<main class="content-body p-4">
    <section class="page-hero mb-4">
        <div>
            <h1>Upload Project Files</h1>
            <p class="panel-muted">Upload code files directly to specified directories inside the application workspace.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Upload File Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" enctype="multipart/form-send" class="row g-3" action="">
                        <!-- Note: enctype must be multipart/form-data for file uploads! -->
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                document.querySelector("form").setAttribute("enctype", "multipart/form-data");
                            });
                        </script>
                        
                        <div class="col-12 mb-3">
                            <label for="foldername" class="form-label font-weight-bold">Target Relative Directory</label>
                            <input type="text" name="foldername" class="form-control" id="foldername" placeholder="e.g. ADMIN/usersetup" required>
                        </div>

                        <div class="col-12 mb-4">
                            <label for="fileToUpload" class="form-label font-weight-bold">Select File</label>
                            <input type="file" name="fileToUpload" class="form-control" id="fileToUpload" required>
                        </div>

                        <div class="text-start">
                            <button type="submit" name="add" class="btn btn-primary px-4">Upload File</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../super-admin/includes/footer.php';
?>
