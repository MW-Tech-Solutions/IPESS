<?php
$upload_base = __DIR__ . '/../uploads/progress';
$checklist_file = $upload_base . '/checklist.json';
$checklist_items = [];

if (file_exists($checklist_file)) {
    $checklist_items = json_decode(file_get_contents($checklist_file), true) ?? [];
}

if (!$checklist_items) {
    $checklist_items = [
        ['id' => 'chk-1', 'task' => 'Submit Chapter 1 revised draft', 'status' => 'Pending'],
        ['id' => 'chk-2', 'task' => 'Get supervisor approval for proposal', 'status' => 'In Progress'],
        ['id' => 'chk-3', 'task' => 'Register for PG seminar', 'status' => 'Completed'],
    ];
}

$credits_file = $upload_base . '/credits.json';
$credit_records = [];
if (file_exists($credits_file)) {
    $credit_records = json_decode(file_get_contents($credits_file), true) ?? [];
}
if (!$credit_records) {
    $credit_records = [
        ['semester' => 'Semester 1', 'code' => 'CS-601', 'title' => 'Advanced Data Systems', 'credits' => 3, 'status' => 'Passed'],
        ['semester' => 'Semester 1', 'code' => 'DS-703', 'title' => 'Applied Analytics', 'credits' => 3, 'status' => 'Passed'],
        ['semester' => 'Semester 1', 'code' => 'RS-801', 'title' => 'Research Methodology', 'credits' => 2, 'status' => 'Passed'],
    ];
}

$total_credits = 0;
foreach ($credit_records as $record) {
    $total_credits += (int) $record['credits'];
}

$roadmap = [
    ['stage' => 'Year 1: Coursework & Comprehensive Exams', 'status' => 'Completed', 'badge' => 'success'],
    ['stage' => 'Year 2: Thesis Proposal & Defense', 'status' => 'In Progress', 'badge' => 'primary'],
    ['stage' => 'Year 3: Fieldwork & Data Collection', 'status' => 'Pending', 'badge' => 'secondary'],
    ['stage' => 'Year 4: Thesis Writing & Publications', 'status' => 'Pending', 'badge' => 'secondary'],
];
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Progress Tracker</h1>
     <ul class="nav nav-tabs" id="progressTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="roadmap-tab" data-bs-toggle="tab" data-bs-target="#roadmap" type="button" role="tab" aria-controls="roadmap" aria-selected="true">Degree Roadmap</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="compilation-tab" data-bs-toggle="tab" data-bs-target="#compilation" type="button" role="tab" aria-controls="compilation" aria-selected="false">Credit Compilation</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="checklist-tab" data-bs-toggle="tab" data-bs-target="#checklist" type="button" role="tab" aria-controls="checklist" aria-selected="false">Checklist</button>
        </li>
    </ul>
    
    <div class="tab-content pt-3" id="progressTabContent">
        <div class="tab-pane fade show active" id="roadmap" role="tabpanel" aria-labelledby="roadmap-tab">
            <div class="card card-jostum">
                <div class="card-body">
                    <h5 class="card-title">PhD in Computer Science Roadmap</h5>
                    <p class="text-muted">This is a typical timeline. Your personal progress may vary.</p>
                    <ul class="list-group">
                        <?php foreach ($roadmap as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($item['stage'], ENT_QUOTES, 'UTF-8'); ?>
                                <span class="badge bg-<?php echo htmlspecialchars($item['badge'], ENT_QUOTES, 'UTF-8'); ?> rounded-pill"><?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="compilation" role="tabpanel" aria-labelledby="compilation-tab">
             <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Credit Compilation</h5>
                            <div class="table-responsive">
                                <table class="table table-jostum table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Semester</th>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th>Credits</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($credit_records as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['semester'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($record['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($record['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int) $record['credits']; ?></td>
                                                <td><?php echo htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card card-jostum h-100">
                        <div class="card-body">
                            <h5 class="card-title">Credits Summary</h5>
                            <div class="jostum-stat mb-3">
                                <div class="label">Total Credits</div>
                                <div class="value"><?php echo (int) $total_credits; ?></div>
                            </div>
                            <form class="portal-form" data-refresh="progress" action="handlers/credit-add.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="credit-semester">Semester</label>
                                    <input class="form-control" id="credit-semester" name="semester" type="text" placeholder="Semester 2" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="credit-code">Course Code</label>
                                    <input class="form-control" id="credit-code" name="code" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="credit-title">Course Title</label>
                                    <input class="form-control" id="credit-title" name="title" type="text" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="credit-units">Credits</label>
                                    <input class="form-control" id="credit-units" name="credits" type="number" min="1" max="6" required>
                                </div>
                                <button type="submit" class="btn btn-jostum">Add Credit</button>
                                <div class="portal-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tab-pane fade" id="checklist" role="tabpanel" aria-labelledby="checklist-tab">
             <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Submission Checklist</h5>
                            <ul class="list-group">
                                <?php foreach ($checklist_items as $item): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?php echo htmlspecialchars($item['task'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <form class="portal-form" data-refresh="progress" action="handlers/checklist-toggle.php" method="post">
                                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-jostum">
                                                <?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Add Checklist Item</h5>
                            <form class="portal-form" data-refresh="progress" action="handlers/checklist-add.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="checklist-task">Task</label>
                                    <input class="form-control" id="checklist-task" name="task" type="text" required>
                                </div>
                                <button type="submit" class="btn btn-jostum">Add Task</button>
                                <div class="portal-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
