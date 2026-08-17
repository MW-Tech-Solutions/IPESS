<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Add New Page';
$con = db();

$msg = '';

if (isset($_POST['add'])) {
    $tabID = $_POST['tabcode'] ?? '';
    $tabname = $_POST['tabname'] ?? '';
    $foldername = $_POST['foldername'] ?? '';
    $pageType = $_POST['pageType'] ?? 'link';
    $url = $_POST['url'] ?? '';

    try {
        $check = $con->prepare("SELECT COUNT(*) FROM page_main_menus WHERE page_url = ?");
        $check->execute([$url]);
        if ($check->fetchColumn() < 1) {
            $add = $con->prepare("
                INSERT INTO page_main_menus (menu_name, page_status, pageType, tabID, page_url, folder) 
                VALUES (?, 1, ?, ?, ?, ?)
            ");
            $add->execute([$tabname, $pageType, $tabID, $url, $foldername]);

            $msg = '<div class="alert alert-success"><strong>Success!</strong> Page created successfully.</div>';
        } else {
            $msg = '<div class="alert alert-warning"><strong>Warning!</strong> Page with this URL already exists.</div>';
        }
    } catch (PDOException $exp) {
        $msg = '<div class="alert alert-danger"><strong>Error!</strong> ' . htmlspecialchars($exp->getMessage()) . '</div>';
    }
}

require_once __DIR__ . '/../super-admin/includes/header.php';
require_once __DIR__ . '/../super-admin/includes/topbar.php';
require_once __DIR__ . '/../super-admin/includes/sidebar.php';
?>

<main class="content-body p-4">
    <section class="page-hero mb-4">
        <div>
            <h1>Add New Page</h1>
            <p class="panel-muted">Define a new system file/page and place it in a menu tab.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Create Page Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12 mb-3">
                            <label for="tabcode" class="form-label font-weight-bold">Select Tab</label>
                            <select name="tabcode" class="form-select form-control" id="tabcode" required>
                                <option value="">Select Option</option>
                                <?php
                                $gettab = $con->query("SELECT * FROM page_menu_tab WHERE tab_status = '1' ORDER BY tab_name");
                                while ($readtab = $gettab->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . $readtab['ID'] . '">' . htmlspecialchars($readtab['tab_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="tabname" class="form-label font-weight-bold">Menu Display Name</label>
                            <input type="text" name="tabname" class="form-control" id="tabname" placeholder="e.g. User Profile" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="url" class="form-label font-weight-bold">Page Relative URL</label>
                            <input type="text" name="url" class="form-control" id="url" placeholder="e.g. ADMIN/usersetup/profile.php" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="foldername" class="form-label font-weight-bold">Folder Path / Module (Optional)</label>
                            <input type="text" name="foldername" class="form-control" id="foldername" placeholder="e.g. ADMIN/usersetup">
                        </div>

                        <div class="col-12 mb-4">
                            <label for="pageType" class="form-label font-weight-bold">Page Link Type</label>
                            <select name="pageType" class="form-select form-control" id="pageType" required>
                                <option value="link">Executable Link (Visible Menu Item)</option>
                                <option value="notlink">Non-link Route / Processing File</option>
                            </select>
                        </div>

                        <div class="text-start">
                            <button type="submit" name="add" class="btn btn-primary px-4">Create Page</button>
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
