<?php
$pageTitle = 'Admission Landing Page';
$pageSubtitle = 'Manage the non-repeater content for the admissions landing page.';

require_once 'includes/db.php';
require_once __DIR__ . '/../../includes/portal_page_sections.php';

$flash = ['type' => '', 'message' => ''];
$content = portal_page_get_content($pdo ?? null, 'admission_landing');

function save_admission_section(PDO $pdo, string $sectionKey, array $content): void
{
    $stmt = $pdo->prepare("
        INSERT INTO portal_page_sections (page_key, section_key, content_json, is_active)
        VALUES ('admission_landing', ?, ?, 1)
        ON DUPLICATE KEY UPDATE content_json = VALUES(content_json), is_active = VALUES(is_active)
    ");
    $stmt->execute([$sectionKey, json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function lines_to_array(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        $section = $_POST['section'] ?? '';
        if ($section === 'topbar') {
            save_admission_section($pdo, 'topbar', ['meta' => lines_to_array($_POST['meta'] ?? ''), 'right_text' => trim($_POST['right_text'] ?? '')]);
        } elseif ($section === 'navbar') {
            save_admission_section($pdo, 'navbar', [
                'logo' => trim($_POST['logo'] ?? ''),
                'name_lines' => lines_to_array($_POST['name_lines'] ?? ''),
                'sub' => trim($_POST['sub'] ?? ''),
                'nav_items' => json_decode((string) ($_POST['nav_items_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
                'actions' => json_decode((string) ($_POST['actions_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            ]);
        } elseif ($section === 'hero') {
            save_admission_section($pdo, 'hero', [
                'background_image' => trim($_POST['background_image'] ?? ''),
                'title' => trim($_POST['title'] ?? ''),
                'text' => trim($_POST['text'] ?? ''),
                'primary_label' => trim($_POST['primary_label'] ?? ''),
                'primary_url' => trim($_POST['primary_url'] ?? ''),
                'secondary_label' => trim($_POST['secondary_label'] ?? ''),
                'secondary_url' => trim($_POST['secondary_url'] ?? ''),
            ]);
        } elseif ($section === 'process') {
            save_admission_section($pdo, 'process', [
                'title' => trim($_POST['title'] ?? ''),
                'subtitle' => trim($_POST['subtitle'] ?? ''),
                'steps' => json_decode((string) ($_POST['steps_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            ]);
        } elseif ($section === 'cta') {
            save_admission_section($pdo, 'cta', [
                'title' => trim($_POST['title'] ?? ''),
                'text' => trim($_POST['text'] ?? ''),
                'button_label' => trim($_POST['button_label'] ?? ''),
                'button_url' => trim($_POST['button_url'] ?? ''),
            ]);
        } elseif ($section === 'footer') {
            save_admission_section($pdo, 'footer', [
                'columns' => json_decode((string) ($_POST['columns_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
                'bottom_text' => trim($_POST['bottom_text'] ?? ''),
            ]);
        }

        $content = portal_page_get_content($pdo, 'admission_landing');
        $flash = ['type' => 'success', 'message' => 'Admission landing page content updated successfully.'];
    } catch (Throwable $e) {
        $flash = ['type' => 'danger', 'message' => $e->getMessage()];
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
require_once 'includes/topbar.php';
?>
<section class="page-hero">
    <div>
        <h1>Admission Landing Page</h1>
        <p class="panel-muted">This controls the wrapper content for `APPLICANT/ADMISSIONS/index.php`.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(app_url('APPLICANT/ADMISSIONS/index.php')); ?>" target="_blank">View Admission Page</a>
    </div>
</section>

<?php if ($flash['message'] !== ''): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
<?php endif; ?>

<section class="panel" id="page-settings">
    <div class="panel-header"><div><h3 class="panel-title">Topbar and Navbar</h3></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="topbar">
            <div class="col-md-8"><label class="form-label">Topbar Meta</label><textarea class="form-control" name="meta" rows="3"><?php echo htmlspecialchars(implode(PHP_EOL, $content['topbar']['meta'] ?? [])); ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Right Text</label><textarea class="form-control" name="right_text" rows="3"><?php echo htmlspecialchars($content['topbar']['right_text'] ?? ''); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Topbar</button></div>
        </form>
        <hr>
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="navbar">
            <div class="col-md-4"><label class="form-label">Logo Path</label><input class="form-control" name="logo" value="<?php echo htmlspecialchars($content['navbar']['logo'] ?? ''); ?>"></div>
            <div class="col-md-4"><label class="form-label">Name Lines</label><textarea class="form-control" name="name_lines" rows="3"><?php echo htmlspecialchars(implode(PHP_EOL, $content['navbar']['name_lines'] ?? [])); ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Subtitle</label><textarea class="form-control" name="sub" rows="3"><?php echo htmlspecialchars($content['navbar']['sub'] ?? ''); ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Nav Items JSON</label><textarea class="form-control" name="nav_items_json" rows="8"><?php echo htmlspecialchars(json_encode($content['navbar']['nav_items'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Action Buttons JSON</label><textarea class="form-control" name="actions_json" rows="8"><?php echo htmlspecialchars(json_encode($content['navbar']['actions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Navbar</button></div>
        </form>
    </div>
</section>

<section class="panel form-section" id="hero">
    <div class="panel-header"><div><h3 class="panel-title">Hero</h3></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="hero">
            <div class="col-md-4"><label class="form-label">Background Image</label><input class="form-control" name="background_image" value="<?php echo htmlspecialchars($content['hero']['background_image'] ?? ''); ?>"></div>
            <div class="col-md-4"><label class="form-label">Hero Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?>"></div>
            <div class="col-md-4"><label class="form-label">Hero Text</label><textarea class="form-control" name="text" rows="2"><?php echo htmlspecialchars($content['hero']['text'] ?? ''); ?></textarea></div>
            <div class="col-md-3"><label class="form-label">Primary Label</label><input class="form-control" name="primary_label" value="<?php echo htmlspecialchars($content['hero']['primary_label'] ?? ''); ?>"></div>
            <div class="col-md-3"><label class="form-label">Primary URL</label><input class="form-control" name="primary_url" value="<?php echo htmlspecialchars($content['hero']['primary_url'] ?? ''); ?>"></div>
            <div class="col-md-3"><label class="form-label">Secondary Label</label><input class="form-control" name="secondary_label" value="<?php echo htmlspecialchars($content['hero']['secondary_label'] ?? ''); ?>"></div>
            <div class="col-md-3"><label class="form-label">Secondary URL</label><input class="form-control" name="secondary_url" value="<?php echo htmlspecialchars($content['hero']['secondary_url'] ?? ''); ?>"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Hero</button></div>
        </form>
    </div>
</section>

<section class="panel form-section" id="process-section">
    <div class="panel-header"><div><h3 class="panel-title">How to Apply</h3></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="process">
            <div class="col-md-6"><label class="form-label">Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($content['process']['title'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Subtitle</label><textarea class="form-control" name="subtitle" rows="2"><?php echo htmlspecialchars($content['process']['subtitle'] ?? ''); ?></textarea></div>
            <div class="col-12"><label class="form-label">Steps JSON</label><textarea class="form-control" name="steps_json" rows="10"><?php echo htmlspecialchars(json_encode($content['process']['steps'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Process</button></div>
        </form>
    </div>
</section>

<section class="panel form-section" id="footer">
    <div class="panel-header"><div><h3 class="panel-title">CTA and Footer</h3></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="cta">
            <div class="col-md-6"><label class="form-label">CTA Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($content['cta']['title'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">CTA Text</label><textarea class="form-control" name="text" rows="2"><?php echo htmlspecialchars($content['cta']['text'] ?? ''); ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Button Label</label><input class="form-control" name="button_label" value="<?php echo htmlspecialchars($content['cta']['button_label'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Button URL</label><input class="form-control" name="button_url" value="<?php echo htmlspecialchars($content['cta']['button_url'] ?? ''); ?>"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save CTA</button></div>
        </form>
        <hr>
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="footer">
            <div class="col-12"><label class="form-label">Footer Columns JSON</label><textarea class="form-control" name="columns_json" rows="10"><?php echo htmlspecialchars(json_encode($content['footer']['columns'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-12"><label class="form-label">Footer Bottom Text</label><textarea class="form-control" name="bottom_text" rows="2"><?php echo htmlspecialchars($content['footer']['bottom_text'] ?? ''); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Footer</button></div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
