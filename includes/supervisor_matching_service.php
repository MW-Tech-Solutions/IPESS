<?php
function normalize_keywords(string $value): array {
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
    $parts = array_filter(array_map('trim', explode(' ', $value)));
    return array_values(array_unique($parts));
}

function suggest_supervisors(PDO $pdo, int $department_id, string $topic, int $limit = 3): array {
    $stmt = $pdo->prepare("SELECT supervisor_id, full_name, specialization_keywords, current_students, max_capacity FROM supervisors WHERE department_id = ? AND status = 'Active'");
    $stmt->execute([$department_id]);
    $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $topic_keywords = normalize_keywords($topic);
    $scored = [];

    foreach ($supervisors as $sup) {
        $keywords = normalize_keywords($sup['specialization_keywords'] ?? '');
        $score = 0;
        foreach ($topic_keywords as $kw) {
            if (in_array($kw, $keywords, true)) {
                $score++;
            }
        }
        $capacity = max(1, (int) $sup['max_capacity']);
        $load_factor = 1 - min(1, ((int) $sup['current_students'] / $capacity));
        $score = $score + (int) round($load_factor * 2);

        $sup['match_score'] = $score;
        $scored[] = $sup;
    }

    usort($scored, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });

    return array_slice($scored, 0, $limit);
}
