<?php

function admissions_content_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName]);

    return $cache[$tableName] = (bool) $stmt->fetchColumn();
}

function admissions_content_defaults(): array
{
    return [
        'programmes' => [
            ['title' => 'JUPEB', 'description' => 'Joint Universities Preliminary Examinations Board programme for direct-entry progression.', 'sort_order' => 1],
            ['title' => 'IPESS', 'description' => 'Institute and professional studies pathways for specialized academic and professional development.', 'sort_order' => 2],
            ['title' => 'PGD', 'description' => 'Postgraduate diploma programmes for academic transition and professional growth.', 'sort_order' => 3],
            ['title' => 'Master\'s Programmes', 'description' => 'Advanced coursework and research-driven master\'s programmes across disciplines.', 'sort_order' => 4],
            ['title' => 'Under Graduate', 'description' => 'Undergraduate degree programmes for candidates seeking first-degree admission across available disciplines.', 'sort_order' => 5],
        ],
        'requirements' => [
            ['title' => 'Academic Qualification', 'description' => 'Applicants must possess the minimum academic qualifications required for the chosen programme.', 'sort_order' => 1],
            ['title' => 'Supporting Documents', 'description' => 'Applicants should provide relevant credentials, transcripts, and any supporting evidence required by the school.', 'sort_order' => 2],
            ['title' => 'Valid Contact Details', 'description' => 'A working email address and phone number must be provided for communication and status updates.', 'sort_order' => 3],
            ['title' => 'Programme-Specific Conditions', 'description' => 'Some programmes may require extra screening, references, or professional qualifications.', 'sort_order' => 4],
        ],
        'important_dates' => [
            ['title' => 'Applications Open', 'event_date' => '2026-01-20', 'sort_order' => 1],
            ['title' => 'Early Submission Deadline', 'event_date' => '2026-03-15', 'sort_order' => 2],
            ['title' => 'Final Submission Deadline', 'event_date' => '2026-05-30', 'sort_order' => 3],
            ['title' => 'Admission Decisions', 'event_date' => '2026-06-20', 'sort_order' => 4],
        ],
        'faqs' => [
            ['question' => 'Can I edit my application after submission?', 'answer' => 'Yes, you can log in and update your application before the final deadline where the portal keeps that window open.', 'sort_order' => 1],
            ['question' => 'Where do I pay my application fee?', 'answer' => 'Payment instructions are provided on the application portal after registration.', 'sort_order' => 2],
            ['question' => 'How do I contact the admissions office?', 'answer' => 'Use the contact email and phone numbers displayed on the admissions page.', 'sort_order' => 3],
        ],
        'notice' => [
            'title' => 'Important Notice',
            'message' => 'Admissions updates, deadlines, and urgent notices will appear here once published by the portal administration team.',
            'button_label' => '',
            'button_url' => '',
        ],
    ];
}

function admissions_get_public_content(?PDO $pdo): array
{
    $defaults = admissions_content_defaults();
    $content = [
        'programmes' => $defaults['programmes'],
        'requirements' => $defaults['requirements'],
        'important_dates' => $defaults['important_dates'],
        'faqs' => $defaults['faqs'],
        'notice' => null,
    ];

    if (!$pdo) {
        $content['notice'] = $defaults['notice'];
        return $content;
    }

    try {
        if (admissions_content_table_exists($pdo, 'admissions_programmes')) {
            $rows = $pdo->query("
                SELECT title, description, sort_order
                FROM admissions_programmes
                WHERE is_active = 1
                ORDER BY sort_order ASC, programme_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $content['programmes'] = $rows;
            }
        }

        if (admissions_content_table_exists($pdo, 'admissions_requirements')) {
            $rows = $pdo->query("
                SELECT title, description, sort_order
                FROM admissions_requirements
                WHERE is_active = 1
                ORDER BY sort_order ASC, requirement_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $content['requirements'] = $rows;
            }
        }

        if (admissions_content_table_exists($pdo, 'admissions_important_dates')) {
            $rows = $pdo->query("
                SELECT title, event_date, sort_order
                FROM admissions_important_dates
                WHERE is_active = 1
                ORDER BY sort_order ASC, event_date ASC, date_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $content['important_dates'] = $rows;
            }
        }

        if (admissions_content_table_exists($pdo, 'admissions_faqs')) {
            $rows = $pdo->query("
                SELECT question, answer, sort_order
                FROM admissions_faqs
                WHERE is_active = 1
                ORDER BY sort_order ASC, faq_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $content['faqs'] = $rows;
            }
        }

        if (admissions_content_table_exists($pdo, 'admissions_notices')) {
            $stmt = $pdo->query("
                SELECT title, message, button_label, button_url
                FROM admissions_notices
                WHERE is_active = 1
                  AND (starts_at IS NULL OR starts_at <= NOW())
                  AND (ends_at IS NULL OR ends_at >= NOW())
                ORDER BY sort_order ASC, notice_id DESC
                LIMIT 1
            ");
            $notice = $stmt->fetch(PDO::FETCH_ASSOC);
            $content['notice'] = $notice ?: $defaults['notice'];
        } else {
            $content['notice'] = $defaults['notice'];
        }
    } catch (Throwable $e) {
        $content['notice'] = $defaults['notice'];
    }

    return $content;
}
