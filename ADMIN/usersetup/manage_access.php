<?php
require_once __DIR__ . '/../../app/bootstrap.php';
enforce_session_timeout(900, 'ADMIN/login.php');
require_role(['DEVELOPER'], 'ADMIN/login.php');

$pageTitle = 'Manage Pages';
$con = db();

$msg = '';

if (isset($_POST['remove_pages'])) {
    $tabid = $_POST['tabcode'] ?? '';
    $pagesToDelete = $_POST['page'] ?? [];

    if (!empty($pagesToDelete) && $tabid !== '') {
        $con->beginTransaction();
        try {
            foreach ($pagesToDelete as $pageID) {
                // Delete from main menus
                $del1 = $con->prepare("DELETE FROM page_main_menus WHERE pageID = ?");
                $del1->execute([$pageID]);

                // Delete from right mapping
                $del2 = $con->prepare("DELETE FROM right_page_main_menus WHERE pageID = ? AND tabID = ?");
                $del2->execute([$pageID, $tabid]);

                // Delete from personal menus
                $del3 = $con->prepare("DELETE FROM pesonal_right_page_main_menus WHERE pageID = ? AND tabID = ?");
                $del3->execute([$pageID, $tabid]);
            }

            // If no pages left under this tab for anyone, clear personal tabs
            $checkRemaining = $con->prepare("SELECT COUNT(*) FROM pesonal_right_page_main_menus WHERE tabID = ?");
            $checkRemaining->execute([$tabid]);
            if ($checkRemaining->fetchColumn() < 1) {
                $delTab = $con->prepare("DELETE FROM personal_page_menu_tab WHERE tabID = ?");
                $delTab->execute([$tabid]);
            }

            $con->commit();
            $msg = '<div class="alert alert-success"><strong>Success!</strong> Selected page(s) removed successfully.</div>';
        } catch (Exception $e) {
            $con->rollBack();
            $msg = '<div class="alert alert-danger"><strong>Error!</strong> Failed to remove pages: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning"><strong>Warning!</strong> No pages selected for removal.</div>';
    }
}

require_once __DIR__ . '/../super-admin/includes/header.php';
require_once __DIR__ . '/../super-admin/includes/topbar.php';
require_once __DIR__ . '/../super-admin/includes/sidebar.php';
?>

<main class="content-body p-4">
    <section class="page-hero mb-4">
        <div>
            <h1>Manage Pages</h1>
            <p class="panel-muted">Select a menu category/tab and delete registered pages from the system.</p>
        </div>
    </section>

    <div class="row">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <h5 class="card-title mb-4">Select Tab to View Pages</h5>
                    
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

                        <?php if ($selectedTab !== ''): ?>
                            <div class="col-12 mt-4">
                                <h6 class="mb-3 font-weight-bold">Pages in Tab</h6>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" style="width: 80px;">S/N</th>
                                                <th scope="col">Page Name</th>
                                                <th scope="col">URL</th>
                                                <th scope="col" class="text-center" style="width: 100px;">Remove</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $con->prepare("SELECT * FROM page_main_menus WHERE tabID = ? ORDER BY menu_name");
                                            $stmt->execute([$selectedTab]);
                                            $sno = 0;
                                            $hasPages = false;
                                            while ($page = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $sno++;
                                                $hasPages = true;
                                                ?>
                                                <tr>
                                                    <td><?php echo $sno; ?></td>
                                                    <td class="font-weight-bold"><?php echo htmlspecialchars($page['menu_name']); ?></td>
                                                    <td><code><?php echo htmlspecialchars($page['page_url']); ?></code></td>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="page[]" class="form-check-input" value="<?php echo $page['pageID']; ?>">
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                            if (!$hasPages) {
                                                echo '<tr><td colspan="4" class="text-center text-muted">No pages found in this tab.</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <?php if ($hasPages): ?>
                                <div class="col-12 text-start mt-3">
                                    <button type="submit" name="remove_pages" class="btn btn-danger px-4" onclick="return confirm('Are you sure you want to delete the selected pages? This cannot be undone.');">Remove Selected Pages</button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/../super-admin/includes/footer.php';
?>
