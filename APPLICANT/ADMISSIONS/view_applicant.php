<?php
require_once __DIR__ . '/db.php';

try {
    $applicant_id = $_GET['id'] ?? 1; 

    // 1. Fetch Applicant & Education (Updated columns to match your 'DESCRIBE' output)
    $stmt = $pdo->prepare("
        SELECT a.*, h.* FROM applicants a 
        LEFT JOIN higher_education h ON a.id = h.applicant_id 
        WHERE a.id = ?");
    $stmt->execute([$applicant_id]);
    $profile = $stmt->fetch();

    if (!$profile) {
        die("Applicant not found.");
    }

    // 2. Fetch Sittings and their subjects
    // Using FETCH_GROUP requires the grouping column (sitting_number) to be the FIRST column in SELECT
    $stmt = $pdo->prepare("
        SELECT e.sitting_number, e.exam_type, e.exam_year, r.subject_name, r.grade 
        FROM olevel_exams e
        JOIN olevel_results r ON e.id = r.exam_id
        WHERE e.applicant_id = ?
        ORDER BY e.sitting_number ASC, r.subject_name ASC");
    $stmt->execute([$applicant_id]);
    
    // This groups results by sitting_number automatically
    $results = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_OBJ);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applicant Profile - <?= htmlspecialchars($profile['first_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 p-8 text-slate-900">
    <div class="max-w-6xl mx-auto">
        
        <header class="flex justify-between items-center mb-8 bg-white p-6 rounded-xl shadow-sm border border-slate-200">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">
                    <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>
                </h1>
                <p class="text-slate-500"><?= htmlspecialchars($profile['email']) ?></p>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-400 uppercase font-bold tracking-wider">Current CGPA</p>
                <span class="text-2xl font-black text-blue-600">
                    <?= number_format($profile['cgpa'], 2) ?>
                </span>
            </div>
        </header>

        <section class="bg-white p-6 rounded-xl shadow-sm mb-8 border border-slate-200">
            <h2 class="text-xl font-bold mb-4 border-b pb-2 text-slate-700">Higher Education</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <div><p class="text-xs text-gray-400 uppercase">Institution</p><p class="font-medium"><?= htmlspecialchars($profile['institution_name'] ?? 'N/A') ?></p></div>
                <div><p class="text-xs text-gray-400 uppercase">Course</p><p class="font-medium"><?= htmlspecialchars($profile['course_of_study'] ?? 'N/A') ?></p></div>
                <div><p class="text-xs text-gray-400 uppercase">Year</p><p class="font-medium"><?= htmlspecialchars($profile['grad_year'] ?? 'N/A') ?></p></div>
                <div><p class="text-xs text-gray-400 uppercase">Mode</p><p class="font-medium"><?= htmlspecialchars($profile['mode_study'] ?? 'N/A') ?></p></div>
            </div>
        </section>

        <h2 class="text-xl font-bold mb-4 text-slate-700">Academic Sittings</h2>
        <div class="grid md:grid-cols-2 gap-8">
            <?php if (empty($results)): ?>
                <p class="text-slate-500 italic">No O'Level records found for this applicant.</p>
            <?php else: ?>
                <?php foreach ($results as $sittingNum => $subjects): ?>
                <div class="bg-white p-6 rounded-xl shadow-sm border-t-4 <?= $sittingNum == 1 ? 'border-blue-500' : 'border-purple-500' ?> border-x border-b border-slate-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg text-slate-800">Sitting <?= $sittingNum ?></h3>
                        <div class="text-right">
                            <span class="block text-sm font-bold text-slate-600 uppercase"><?= htmlspecialchars($subjects[0]->exam_type) ?></span>
                            <span class="text-xs text-slate-400"><?= htmlspecialchars($subjects[0]->exam_year) ?></span>
                        </div>
                    </div>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-slate-400 text-xs uppercase tracking-tighter">
                                <th class="py-2">Subject</th>
                                <th class="py-2 text-right">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $s): ?>
                            <tr class="border-t border-slate-100 hover:bg-slate-50 transition-colors">
                                <td class="py-3 text-sm text-slate-700"><?= htmlspecialchars($s->subject_name) ?></td>
                                <td class="py-3 text-right font-mono font-bold text-blue-700"><?= htmlspecialchars($s->grade) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
