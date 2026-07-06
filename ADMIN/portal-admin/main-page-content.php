<?php
$pageTitle = 'Main Page';
$pageSubtitle = 'Manage the public main landing page content.';

require_once 'includes/db.php';
require_once __DIR__ . '/../../includes/portal_page_sections.php';

$flash = ['type' => '', 'message' => ''];
$content = portal_page_get_content($pdo ?? null, 'main_landing');

function save_page_section(PDO $pdo, string $pageKey, string $sectionKey, array $content): void
{
    $stmt = $pdo->prepare("
        INSERT INTO portal_page_sections (page_key, section_key, content_json, is_active)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE content_json = VALUES(content_json), is_active = VALUES(is_active)
    ");
    $stmt->execute([$pageKey, $sectionKey, json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

function textarea_lines(string $value): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];
    return array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        $section = $_POST['section'] ?? '';
        if ($section === 'topbar') {
            save_page_section($pdo, 'main_landing', 'topbar', [
                'meta' => textarea_lines($_POST['meta'] ?? ''),
                'social' => textarea_lines($_POST['social'] ?? ''),
            ]);
        } elseif ($section === 'header') {
            save_page_section($pdo, 'main_landing', 'header', [
                'logo' => trim($_POST['logo'] ?? ''),
                'name_lines' => textarea_lines($_POST['name_lines'] ?? ''),
                'sub' => trim($_POST['sub'] ?? ''),
                'nav_items' => json_decode((string) ($_POST['nav_items_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
                'portal_links' => json_decode((string) ($_POST['portal_links_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            ]);
        } elseif ($section === 'hero') {
            save_page_section($pdo, 'main_landing', 'hero', [
                'title' => trim($_POST['title'] ?? ''),
                'text' => trim($_POST['text'] ?? ''),
                'primary_label' => trim($_POST['primary_label'] ?? ''),
                'primary_url' => trim($_POST['primary_url'] ?? ''),
                'secondary_label' => trim($_POST['secondary_label'] ?? ''),
                'secondary_url' => trim($_POST['secondary_url'] ?? ''),
                'images' => textarea_lines($_POST['images'] ?? ''),
            ]);
        } elseif ($section === 'vc_message') {
            save_page_section($pdo, 'main_landing', 'vc_message', [
                'image' => trim($_POST['image'] ?? ''),
                'image_alt' => trim($_POST['image_alt'] ?? ''),
                'title' => trim($_POST['title'] ?? ''),
                'paragraphs' => textarea_lines($_POST['paragraphs'] ?? ''),
                'signature_name' => trim($_POST['signature_name'] ?? ''),
                'signature_role' => trim($_POST['signature_role'] ?? ''),
            ]);
        } elseif ($section === 'events') {
            save_page_section($pdo, 'main_landing', 'events', json_decode((string) ($_POST['events_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR));
        } elseif ($section === 'cta') {
            save_page_section($pdo, 'main_landing', 'cta', [
                'title' => trim($_POST['title'] ?? ''),
                'text' => trim($_POST['text'] ?? ''),
                'actions' => json_decode((string) ($_POST['actions_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            ]);
        } elseif ($section === 'footer') {
            save_page_section($pdo, 'main_landing', 'footer', [
                'columns' => json_decode((string) ($_POST['columns_json'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
                'bottom_text' => trim($_POST['bottom_text'] ?? ''),
            ]);
        }

        $content = portal_page_get_content($pdo, 'main_landing');
        $flash = ['type' => 'success', 'message' => 'Main page content updated successfully.'];
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
        <h1>Main Page Content</h1>
        <p class="panel-muted">This controls the public page at `http://localhost/JOSTUM/index.php`.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(app_url('index.php')); ?>" target="_blank">View Main Page</a>
    </div>
</section>

<?php if ($flash['message'] !== ''): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
<?php endif; ?>

<section class="panel" id="header-topbar">
    <div class="panel-header"><div><h3 class="panel-title">Topbar</h3><div class="panel-muted">One item per line.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="topbar">
            <div class="col-md-6"><label class="form-label">Meta Items</label><textarea class="form-control" name="meta" rows="4"><?php echo htmlspecialchars(implode(PHP_EOL, $content['topbar']['meta'] ?? [])); ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Social Items</label><textarea class="form-control" name="social" rows="4"><?php echo htmlspecialchars(implode(PHP_EOL, $content['topbar']['social'] ?? [])); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Topbar</button></div>
        </form>
    </div>
</section>

<section class="panel form-section" id="hero">
    <div class="panel-header"><div><h3 class="panel-title">Header and Hero</h3><div class="panel-muted">Complex menu and portal links use JSON.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="header">
            <div class="col-md-4"><label class="form-label">Logo Path</label><input class="form-control" name="logo" value="<?php echo htmlspecialchars($content['header']['logo'] ?? ''); ?>"></div>
            <div class="col-md-4"><label class="form-label">Name Lines</label><textarea class="form-control" name="name_lines" rows="3"><?php echo htmlspecialchars(implode(PHP_EOL, $content['header']['name_lines'] ?? [])); ?></textarea></div>
            <div class="col-md-4"><label class="form-label">Subtitle</label><textarea class="form-control" name="sub" rows="3"><?php echo htmlspecialchars($content['header']['sub'] ?? ''); ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Nav Items JSON</label><textarea class="form-control" name="nav_items_json" rows="10"><?php echo htmlspecialchars(json_encode($content['header']['nav_items'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Portal Links JSON</label><textarea class="form-control" name="portal_links_json" rows="10"><?php echo htmlspecialchars(json_encode($content['header']['portal_links'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Header</button></div>
        </form>

        <hr>

        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="hero">
            <div class="col-md-6"><label class="form-label">Hero Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($content['hero']['title'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">Hero Text</label><textarea class="form-control" name="text" rows="2"><?php echo htmlspecialchars($content['hero']['text'] ?? ''); ?></textarea></div>
            <div class="col-md-3"><label class="form-label">Primary Label</label><input class="form-control" name="primary_label" value="<?php echo htmlspecialchars($content['hero']['primary_label'] ?? ''); ?>"></div>
            <div class="col-md-3"><label class="form-label">Primary URL</label><input class="form-control" name="primary_url" value="<?php echo htmlspecialchars($content['hero']['primary_url'] ?? ''); ?>"></div>
            <div class="col-md-3"><label class="form-label">Secondary Label</label><input class="form-control" name="secondary_label" value="<?php echo htmlspecialchars($content['hero']['secondary_label'] ?? ''); ?>"></div>
            <div class="col-md-3"><label class="form-label">Secondary URL</label><input class="form-control" name="secondary_url" value="<?php echo htmlspecialchars($content['hero']['secondary_url'] ?? ''); ?>"></div>
            <div class="col-12"><label class="form-label">Hero Images</label><textarea class="form-control" name="images" rows="5"><?php echo htmlspecialchars(implode(PHP_EOL, $content['hero']['images'] ?? [])); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Hero</button></div>
        </form>
    </div>
</section>

<section class="panel form-section" id="vc-message">
    <div class="panel-header"><div><h3 class="panel-title">VC Message</h3></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="vc_message">
            <div class="col-md-4"><label class="form-label">Image</label><input class="form-control" name="image" value="<?php echo htmlspecialchars($content['vc_message']['image'] ?? ''); ?>"></div>
            <div class="col-md-4"><label class="form-label">Image Alt</label><input class="form-control" name="image_alt" value="<?php echo htmlspecialchars($content['vc_message']['image_alt'] ?? ''); ?>"></div>
            <div class="col-md-4"><label class="form-label">Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($content['vc_message']['title'] ?? ''); ?>"></div>
            <div class="col-md-8"><label class="form-label">Paragraphs</label><textarea class="form-control" name="paragraphs" rows="5"><?php echo htmlspecialchars(implode(PHP_EOL, $content['vc_message']['paragraphs'] ?? [])); ?></textarea></div>
            <div class="col-md-2"><label class="form-label">Signature Name</label><input class="form-control" name="signature_name" value="<?php echo htmlspecialchars($content['vc_message']['signature_name'] ?? ''); ?>"></div>
            <div class="col-md-2"><label class="form-label">Signature Role</label><input class="form-control" name="signature_role" value="<?php echo htmlspecialchars($content['vc_message']['signature_role'] ?? ''); ?>"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save VC Message</button></div>
        </form>
    </div>
</section>

<section class="panel form-section" id="events">
    <div class="panel-header"><div><h3 class="panel-title">Events</h3><div class="panel-muted">Edit as JSON array.</div></div></div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="section" value="events">
            <label class="form-label">Events JSON</label>
            <textarea class="form-control" name="events_json" rows="14"><?php echo htmlspecialchars(json_encode($content['events'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea>
            <button class="btn btn-primary mt-3" type="submit">Save Events</button>
        </form>
    </div>
</section>

<section class="panel form-section" id="footer">
    <div class="panel-header"><div><h3 class="panel-title">CTA and Footer</h3><div class="panel-muted">Footer columns and CTA actions use JSON arrays.</div></div></div>
    <div class="panel-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="cta">
            <div class="col-md-6"><label class="form-label">CTA Title</label><input class="form-control" name="title" value="<?php echo htmlspecialchars($content['cta']['title'] ?? ''); ?>"></div>
            <div class="col-md-6"><label class="form-label">CTA Text</label><textarea class="form-control" name="text" rows="2"><?php echo htmlspecialchars($content['cta']['text'] ?? ''); ?></textarea></div>
            <div class="col-12"><label class="form-label">CTA Actions JSON</label><textarea class="form-control" name="actions_json" rows="8"><?php echo htmlspecialchars(json_encode($content['cta']['actions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save CTA</button></div>
        </form>
        <hr>
        <form method="post" class="row g-3">
            <input type="hidden" name="section" value="footer">
            <div class="col-12"><label class="form-label">Footer Columns JSON</label><textarea class="form-control" name="columns_json" rows="12"><?php echo htmlspecialchars(json_encode($content['footer']['columns'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></textarea></div>
            <div class="col-12"><label class="form-label">Footer Bottom Text</label><textarea class="form-control" name="bottom_text" rows="2"><?php echo htmlspecialchars($content['footer']['bottom_text'] ?? ''); ?></textarea></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Save Footer</button></div>
        </form>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
