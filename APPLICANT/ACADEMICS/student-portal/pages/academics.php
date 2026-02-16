<?php
$upload_base = __DIR__ . '/../uploads/academics';
$requests_file = $upload_base . '/transcript-requests.json';
$transcript_requests = [];

if (file_exists($requests_file)) {
    $transcript_requests = json_decode(file_get_contents($requests_file), true) ?? [];
}

if (!$transcript_requests) {
    $transcript_requests = [
        [
            'type' => 'Unofficial Transcript',
            'status' => 'Ready',
            'requested_at' => '2026-01-05',
            'reference' => 'TRX-2041'
        ],
        [
            'type' => 'Official Transcript',
            'status' => 'Processing',
            'requested_at' => '2025-12-18',
            'reference' => 'TRX-1984'
        ],
    ];
}

$courses = [
    ['code' => 'CS-601', 'title' => 'Advanced Data Systems', 'instructor' => 'Dr. Chiamaka Okafor', 'credits' => 3],
    ['code' => 'DS-703', 'title' => 'Applied Analytics', 'instructor' => 'Dr. Tunde Adeyemi', 'credits' => 3],
    ['code' => 'RS-801', 'title' => 'Research Methodology', 'instructor' => 'Dr. Amina Okoye', 'credits' => 2],
];

$grades = [
    ['code' => 'CS-601', 'title' => 'Advanced Data Systems', 'score' => 84, 'grade' => 'A', 'remark' => 'Excellent'],
    ['code' => 'DS-703', 'title' => 'Applied Analytics', 'score' => 76, 'grade' => 'B+', 'remark' => 'Very Good'],
    ['code' => 'RS-801', 'title' => 'Research Methodology', 'score' => 69, 'grade' => 'B', 'remark' => 'Good'],
];

$exam_schedule = [
    ['code' => 'CS-601', 'title' => 'Advanced Data Systems', 'date' => '2026-02-03', 'time' => '09:00 - 11:00', 'venue' => 'PG Block Hall A'],
    ['code' => 'DS-703', 'title' => 'Applied Analytics', 'date' => '2026-02-06', 'time' => '13:00 - 15:00', 'venue' => 'ICT Lab 2'],
    ['code' => 'RS-801', 'title' => 'Research Methodology', 'date' => '2026-02-10', 'time' => '10:00 - 12:00', 'venue' => 'Senate Auditorium'],
];

$timetable = [
    ['code' => 'RS-801', 'title' => 'Research Methodology', 'time' => 'Mon 10:00 - 12:00', 'lecturer' => 'Dr. Amina Okoye'],
    ['code' => 'CS-601', 'title' => 'Advanced Data Systems', 'time' => 'Wed 14:00 - 16:00', 'lecturer' => 'Dr. Chiamaka Okafor'],
    ['code' => 'DS-703', 'title' => 'Applied Analytics', 'time' => 'Fri 09:00 - 11:00', 'lecturer' => 'Dr. Tunde Adeyemi'],
];
?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Academics</h1>
    <ul class="nav nav-tabs" id="academicsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses" type="button" role="tab" aria-controls="courses" aria-selected="true">Courses</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="grades-tab" data-bs-toggle="tab" data-bs-target="#grades" type="button" role="tab" aria-controls="grades" aria-selected="false">Grades</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="transcripts-tab" data-bs-toggle="tab" data-bs-target="#transcripts" type="button" role="tab" aria-controls="transcripts" aria-selected="false">Transcripts</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab" aria-controls="schedule" aria-selected="false">Exam Schedule</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="timetable-tab" data-bs-toggle="tab" data-bs-target="#timetable" type="button" role="tab" aria-controls="timetable" aria-selected="false">Timetable</button>
        </li>
    </ul>
    <div class="tab-content pt-3" id="academicsTabContent">
        <div class="tab-pane fade show active" id="courses" role="tabpanel" aria-labelledby="courses-tab">
            <div class="card card-jostum">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
                        <h5 class="card-title mb-0">Enrolled Courses - 2024/2025 Session</h5>
                        <span class="badge badge-jostum">Semester 1</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-jostum table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Instructor</th>
                                    <th>Credits</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($course['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($course['instructor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo (int) $course['credits']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="grades" role="tabpanel" aria-labelledby="grades-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Semester Results</h5>
                            <div class="table-responsive">
                                <table class="table table-jostum table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                            <th>Remark</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($grade['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($grade['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo (int) $grade['score']; ?></td>
                                                <td><?php echo htmlspecialchars($grade['grade'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($grade['remark'], ENT_QUOTES, 'UTF-8'); ?></td>
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
                            <h5 class="card-title">Grade Summary</h5>
                            <div class="jostum-stat mb-3">
                                <div class="label">GPA</div>
                                <div class="value">4.21 / 5.00</div>
                            </div>
                            <div class="jostum-stat mb-3">
                                <div class="label">Credits Earned</div>
                                <div class="value">8</div>
                            </div>
                            <div class="jostum-stat">
                                <div class="label">Standing</div>
                                <div class="value">Good Standing</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="transcripts" role="tabpanel" aria-labelledby="transcripts-tab">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Request Transcript</h5>
                            <form class="academics-form" action="handlers/transcript-request.php" method="post">
                                <div class="mb-3">
                                    <label class="form-label" for="request-type">Transcript Type</label>
                                    <select class="form-select" id="request-type" name="type" required>
                                        <option value="Unofficial Transcript">Unofficial Transcript</option>
                                        <option value="Official Transcript">Official Transcript</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="request-reason">Reason</label>
                                    <input class="form-control" id="request-reason" name="reason" type="text" placeholder="Scholarship application" required>
                                </div>
                                <button type="submit" class="btn btn-jostum">Submit Request</button>
                                <div class="academics-feedback mt-2 text-muted small"></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card card-jostum">
                        <div class="card-body">
                            <h5 class="card-title">Recent Requests</h5>
                            <div class="table-responsive">
                                <table class="table table-jostum table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Reference</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Requested</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transcript_requests as $request): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($request['reference'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($request['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($request['requested_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="#" class="btn btn-jostum mt-2">Download Latest Transcript</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
            <div class="card card-jostum">
                <div class="card-body">
                    <h5 class="card-title">Exam Schedule</h5>
                    <div class="table-responsive">
                        <table class="table table-jostum table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Venue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exam_schedule as $exam): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($exam['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($exam['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($exam['venue'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="timetable" role="tabpanel" aria-labelledby="timetable-tab">
            <div class="card card-jostum">
                <div class="card-body">
                    <h5 class="card-title">Weekly Timetable</h5>
                    <div class="table-responsive">
                        <table class="table table-jostum table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Time</th>
                                    <th>Lecturer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($timetable as $slot): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($slot['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($slot['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($slot['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($slot['lecturer'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
