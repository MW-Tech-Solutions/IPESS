<?php
$upload_base = __DIR__ . '/../uploads/resources';
$publications_file = $upload_base . '/publications.json';
$materials_file = $upload_base . '/materials.json';
$publications = [];
$materials = [];

if (file_exists($publications_file)) {
    $publications = json_decode(file_get_contents($publications_file), true) ?? [];
}
if (file_exists($materials_file)) {
    $materials = json_decode(file_get_contents($materials_file), true) ?? [];
}

if (!$publications) {
    $publications = [
        [
            'title' => 'Optimizing Cassava Yield with Data Analytics',
            'authors' => 'Dr. Chiamaka Okafor, Prof. Femi Adebayo',
            'year' => '2024',
            'link' => '#'
        ],
        [
            'title' => 'Smart Irrigation Systems in Benue State',
            'authors' => 'Dr. Ifeanyi Nwankwo, Dr. Amina Okoye',
            'year' => '2023',
            'link' => '#'
        ],
    ];
}

if (!$materials) {
    $materials = [
        [
            'title' => 'Thesis Formatting Guide (PG School)',
            'type' => 'PDF',
            'link' => '#',
            'shared_by' => 'PG Coordinator',
            'shared_at' => '2026-01-04'
        ],
        [
            'title' => 'Research Ethics Approval Form',
            'type' => 'DOCX',
            'link' => '#',
            'shared_by' => 'Dr. Tunde Adeyemi',
            'shared_at' => '2025-12-22'
        ],
    ];
}
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Resources</h1>
    <ul class="nav nav-tabs" id="resourcesTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="publications-tab" data-bs-toggle="tab" data-bs-target="#publications" type="button" role="tab" aria-controls="publications" aria-selected="true">Publications</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">Materials</button>
        </li>
    </ul>
    <div class="tab-content pt-3" id="resourcesTabContent">
        <div class="tab-pane fade show active" id="publications" role="tabpanel" aria-labelledby="publications-tab">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Recommended Publications</h5>
                            <div class="table-responsive">
                                <table class="table table-jostum table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Authors</th>
                                            <th>Year</th>
                                            <th>Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($publications as $publication): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($publication['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($publication['authors'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($publication['year'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><a href="<?php echo htmlspecialchars($publication['link'], ENT_QUOTES, 'UTF-8'); ?>">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Suggest a Publication</h5>
                            <form class="portal-form" data-refresh="resources" action="handlers/publication-add.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="publication-title">Title</label>
                                    <input class="form-control" id="publication-title" name="title" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="publication-authors">Authors</label>
                                    <input class="form-control" id="publication-authors" name="authors" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="publication-year">Year</label>
                                    <input class="form-control" id="publication-year" name="year" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="publication-link">Link</label>
                                    <input class="form-control" id="publication-link" name="link" type="text" placeholder="https://" required>
                                </div>
                                <button type="submit" class="btn btn-jostum">Add Publication</button>
                                <div class="portal-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Shared Materials</h5>
                            <div class="table-responsive">
                                <table class="table table-jostum table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Type</th>
                                            <th>Shared By</th>
                                            <th>Date</th>
                                            <th>Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($material['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($material['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($material['shared_by'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($material['shared_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><a href="<?php echo htmlspecialchars($material['link'], ENT_QUOTES, 'UTF-8'); ?>">Download</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Share a Material</h5>
                            <form class="portal-form" data-refresh="resources" action="handlers/material-add.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="material-title">Title</label>
                                    <input class="form-control" id="material-title" name="title" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="material-type">Type</label>
                                    <input class="form-control" id="material-type" name="type" type="text" placeholder="PDF" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="material-link">Link</label>
                                    <input class="form-control" id="material-link" name="link" type="text" placeholder="https://" required>
                                </div>
                                <button type="submit" class="btn btn-jostum">Share Material</button>
                                <div class="portal-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
