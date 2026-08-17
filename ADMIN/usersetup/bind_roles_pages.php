<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Assign Page to Role';
$con = db();

$msg = '';

if (isset($_POST['add'])) {
    $tabID = $_POST['tabcode'] ?? '';
    $pagescode = $_POST['pagescode'] ?? '';
    $rolecode = $_POST['rolecode'] ?? '';

    $getpages = $con->prepare("SELECT * FROM page_main_menus WHERE pageID = ?");
    $getpages->execute([$pagescode]);
    $page = $getpages->fetch(PDO::FETCH_ASSOC);

    if ($page) {
        $page_url = $page['page_url'];
        $menu_name = $page['menu_name'];
        $keep_active = $page['keep_active'];
        $page_status = $page['page_status'];
        $pageType = $page['pageType'];

        try {
            $check = $con->prepare("SELECT COUNT(*) FROM right_page_main_menus WHERE tabID = ? AND pageID = ? AND roleID = ?");
            $check->execute([$tabID, $pagescode, $rolecode]);
            if ($check->fetchColumn() < 1) {
                $add = $con->prepare("
                    INSERT INTO right_page_main_menus (pageID, menu_name, roleID, page_status, pageType, tabID, page_url, keep_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $add->execute([$pagescode, $menu_name, $rolecode, $page_status, $pageType, $tabID, $page_url, $keep_active]);

                $msg = '<div class="alert alert-success"><strong>Success!</strong> Page assigned to role successfully.</div>';
            } else {
                $msg = '<div class="alert alert-warning"><strong>Warning!</strong> Page is already assigned to this role.</div>';
            }
        } catch (PDOException $exp) {
            $msg = '<div class="alert alert-danger"><strong>Error!</strong> ' . htmlspecialchars($exp->getMessage()) . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger"><strong>Error!</strong> Selected page not found.</div>';
    }
}

require_once __DIR__ . '/../super-admin/includes/header.php';
require_once __DIR__ . '/../super-admin/includes/topbar.php';
require_once __DIR__ . '/../super-admin/includes/sidebar.php';
?>

<main class="content-body p-4">
    <section class="page-hero mb-4">
        <div>
            <h1>Assign Page to Role</h1>
            <p class="panel-muted">Map system pages and directories to specific user roles.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Assign Page Form</h5>
                    
                    <?php echo $msg; ?>

                    <form method="POST" class="row g-3">
                        <div class="col-12 mb-3">
                            <label for="tabcode" class="form-label font-weight-bold">Select Tab</label>
                            <select name="tabcode" class="form-select form-control" id="tabcode" onchange="this.form.submit();" required>
                                <option value="">Select Option</option>
                                <?php
                                $selectedTab = $_POST['tabcode'] ?? '';
                                $gettab = $con->query("SELECT * FROM page_menu_tab WHERE tab_status = '1' ORDER BY tab_name");
                                while ($readtab = $gettab->fetch(PDO::FETCH_ASSOC)) {
                                    $selected = ($readtab['ID'] == $selectedTab) ? 'selected' : '';
                                    echo '<option value="' . $readtab['ID'] . '" ' . $selected . '>' . htmlspecialchars($readtab['tab_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="pagescode" class="form-label font-weight-bold">Select Page</label>
                            <select name="pagescode" class="form-select form-control" id="pagescode" required>
                                <option value="">Select Page</option>
                                <?php
                                if ($selectedTab !== '') {
                                    $stmt = $con->prepare("SELECT * FROM page_main_menus WHERE tabID = ? AND page_status = 1 ORDER BY menu_name");
                                    $stmt->execute([$selectedTab]);
                                    while ($page = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . $page['pageID'] . '">' . htmlspecialchars($page['menu_name']) . ' (' . htmlspecialchars($page['page_url']) . ')</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-12 mb-4">
                            <label for="rolecode" class="form-label font-weight-bold">Select Role</label>
                            <select name="rolecode" class="form-select form-control" id="rolecode" required>
                                <option value="">Select Role</option>
                                <?php
                                $roles = $con->query("SELECT role_key, role_name FROM roles ORDER BY role_name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($roles as $role) {
                                    echo '<option value="' . htmlspecialchars($role['role_key']) . '">' . htmlspecialchars($role['role_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="text-start">
                            <button type="submit" name="add" class="btn btn-primary px-4">Assign Page</button>
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
