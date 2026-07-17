<?php

function portal_page_sections_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'portal_page_sections'
        LIMIT 1
    ");
    $stmt->execute();
    return $exists = (bool) $stmt->fetchColumn();
}

function portal_page_section_defaults(string $pageKey): array
{
    $defaults = [
        'main_landing' => [
            'topbar' => [
                'meta' => ['+234 704 366 7952', 'info@uam.edu.ng', 'Mon - Fri: 8:00 - 16:00hrs'],
                'social' => ['Facebook', 'Instagram', 'Telegram', 'Youtube'],
            ],
            'header' => [
                'logo' => 'APPLICANT/ADMISSIONS/images/jostum.jpeg',
                'name_lines' => ['Joseph Sarwuan Tarka University,', 'Makurdi'],
                'sub' => 'Education for Individual and Social Responsibility',
                'nav_items' => [
                    ['label' => 'Home', 'url' => '#', 'children' => []],
                    ['label' => 'About Us', 'url' => '#', 'children' => [
                        ['label' => 'History', 'url' => '#'],
                        ['label' => 'Vision', 'url' => '#'],
                        ['label' => 'Mission', 'url' => '#'],
                    ]],
                    ['label' => 'Leadership', 'url' => '#', 'children' => [
                        ['label' => 'Visitors', 'url' => '#'],
                        ['label' => 'Principal Officers', 'url' => '#'],
                        ['label' => 'Deans of Colleges', 'url' => '#'],
                        ['label' => 'Heads of Departments', 'url' => '#'],
                        ['label' => 'Directors', 'url' => '#'],
                    ]],
                    ['label' => 'Administration', 'url' => '#', 'children' => [
                        ['label' => 'Office of the VC', 'url' => '#'],
                        ['label' => 'Registry', 'url' => '#'],
                        ['label' => 'Bursary', 'url' => '#'],
                    ]],
                    ['label' => 'Admissions', 'url' => '#', 'children' => [
                        ['label' => 'Undergraduate Requirement', 'url' => '#'],
                        ['label' => 'Sandwich Requirement', 'url' => '#'],
                        ['label' => 'Post-graduate Requirement', 'url' => '#'],
                        ['label' => 'JUPEB Requirement', 'url' => '#'],
                        ['label' => 'Remedial Requirement', 'url' => '#'],
                        ['label' => 'Check Admission', 'url' => '#'],
                    ]],
                    ['label' => 'Academics', 'url' => '#', 'children' => [
                        ['label' => 'Colleges/Departments', 'url' => '#'],
                        ['label' => 'Post-graduate School', 'url' => '#'],
                        ['label' => 'Directorates', 'url' => '#'],
                        ['label' => 'Academic Calendar', 'url' => '#'],
                    ]],
                    ['label' => 'News & Events', 'url' => '#', 'children' => []],
                    ['label' => 'Contact Us', 'url' => '#', 'children' => []],
                ],
                'portal_links' => [
                    ['label' => 'APPLICANT', 'url' => 'APPLICANT/ADMISSIONS/index.php', 'variant' => 'primary'],
                ],
            ],
            'hero' => [
                'title' => 'Welcome to Joseph Sarwuan Tarka University, Makurdi',
                'text' => 'A vibrant academic community committed to excellence in teaching, research, and service. Discover programs that shape innovators and leaders for tomorrow.',
                'primary_label' => 'Apply Now',
                'primary_url' => 'APPLICANT/ADMISSIONS/login.php',
                'secondary_label' => '',
                'secondary_url' => '',
                'images' => [
                    'APPLICANT/ADMISSIONS/images/jostumgate.png',
                    'APPLICANT/ADMISSIONS/images/jostumhall.png',
                    'APPLICANT/ADMISSIONS/images/jostumstudents.png',
                ],
            ],
            'vc_message' => [
                'image' => 'APPLICANT/ADMISSIONS/images/vc.png',
                'image_alt' => 'JOSTUM Students',
                'title' => 'Message from the Vice Chancellor',
                'paragraphs' => [
                    'Dear students, staff, and visitors, welcome to Joseph Sarwuan Tarka University, Makurdi. We are proud of our culture of scholarship, innovation, and community impact.',
                    'Our institution offers a wide range of undergraduate and postgraduate programs supported by dedicated faculty and modern learning resources.',
                ],
                'signature_name' => 'Professor Isaac Nathaniel Itodo',
                'signature_role' => 'Vice Chancellor',
            ],
            'events' => [
                ['image' => 'APPLICANT/ADMISSIONS/images/jostumhall.png', 'image_alt' => 'IPESS', 'meta' => 'IPESS • October 3, 2025', 'title' => 'Stronger country systems: How Nigeria is building a skilled workforce for the future', 'description' => 'Highlights from the IPESS capacity development initiative and its impact.'],
                ['image' => 'APPLICANT/ADMISSIONS/images/jostumgate.png', 'image_alt' => 'IPESS Graduates', 'meta' => 'IPESS • September 21, 2025', 'title' => 'IPESS JOSTUM graduates 140 trainees in Minna and Lokoja', 'description' => 'Valedictory sessions celebrate new professionals trained across two states.'],
                ['image' => 'APPLICANT/ADMISSIONS/images/jostumstudents.png', 'image_alt' => 'Engineering Homecoming', 'meta' => 'College News • September 9, 2025', 'title' => 'Homecoming: Engineering family in JOSTUM receive Engr. Prof. Itodo', 'description' => 'A warm reception honoring leadership and service to the university.'],
                ['image' => 'APPLICANT/ADMISSIONS/images/jostumm.jpeg', 'image_alt' => 'Campus', 'meta' => 'IPESS • August 6, 2025', 'title' => 'SPESSE Project: IPESS – JOSTUM breaking new grounds in human capacity', 'description' => 'Strategic programs expand professional training opportunities.'],
            ],
            'stats' => [
                ['key' => 'colleges', 'label' => 'Total Colleges'],
                ['key' => 'departments', 'label' => 'Total Departments'],
                ['key' => 'staff', 'label' => 'Teaching Staff'],
                ['key' => 'awards', 'label' => 'Award Winning'],
            ],
            'cta' => [
                'title' => 'Are you ready to start your career journey with us?',
                'text' => 'Education for Individual and Social Responsibility.',
                'actions' => [
                    ['label' => 'Start Application', 'url' => 'APPLICANT/ADMISSIONS/index.php', 'variant' => 'primary'],
                    ['label' => 'Administrator Access', 'url' => 'ADMIN/index.php', 'variant' => 'secondary'],
                ],
            ],
            'footer' => [
                'columns' => [
                    ['title' => 'Contact Us', 'items' => ['+234 704 366 7952', 'info@uam.edu.ng', 'JOSTUM P.M.B. 2373', 'Gbajimba Road, Makurdi Nigeria']],
                    ['title' => 'Connect With Us', 'items' => ['Facebook', 'Instagram', 'Telegram', 'Youtube']],
                    ['title' => 'Academics', 'items' => ['Admissions', 'Colleges', 'Departments', 'Courses', 'Calendar', 'Library']],
                    ['title' => 'Useful Links', 'items' => ['ADMIN|ADMIN/index.php', 'APPLICANT|APPLICANT/ADMISSIONS/index.php', 'Directorates', 'Resources', 'Fees', 'FAQs']],
                ],
                'bottom_text' => '© Copyright 2025 – Joseph Sarwuan Tarka University, Makurdi. Powered by Directorate of ICT.',
            ],
        ],
        'admission_landing' => [
            'topbar' => [
                'meta' => ['+234 704 366 7952', 'admissions@uam.edu.ng', 'Mon - Fri: 8:00am - 4:00pm'],
                'right_text' => 'Prospective Students Portal',
            ],
            'navbar' => [
                'logo' => 'images/jostum.jpeg',
                'name_lines' => ['Joseph Sarwuan Tarka University,', 'Makurdi'],
                'sub' => 'Education for Individual and Social Responsibility',
                'nav_items' => [
                    ['label' => 'Programmes', 'url' => '#programmes'],
                    ['label' => 'Requirements', 'url' => '#requirements'],
                    ['label' => 'How to Apply', 'url' => '#process'],
                    ['label' => 'Deadlines', 'url' => '#deadlines'],
                    ['label' => 'FAQ', 'url' => '#faq'],
                ],
                'actions' => [
                    ['label' => 'Apply Now', 'url' => 'register.php', 'variant' => 'primary'],
                    ['label' => 'Main Site', 'url' => '/', 'variant' => 'outline'],
                ],
            ],
            'hero' => [
                'background_image' => 'images/jostumgate-opt.jpg',
                'title' => 'Begin Your Admission Journey',
                'text' => 'Explore undergraduate and postgraduate opportunities at Joseph Sarwuan Tarka University, Makurdi. Start your application and track your admission status with ease.',
                'primary_label' => 'Start Application',
                'primary_url' => 'register.php',
                'secondary_label' => 'View Admission Steps',
                'secondary_url' => '#process',
            ],
            'process' => [
                'title' => 'How to Apply',
                'subtitle' => 'A simple four-step process to get you started.',
                'steps' => [
                    ['number' => '1', 'title' => 'Create an Account', 'description' => 'Sign up with a valid email to receive verification.'],
                    ['number' => '2', 'title' => 'Fill Application', 'description' => 'Provide accurate personal and academic details.'],
                    ['number' => '3', 'title' => 'Upload Documents', 'description' => 'Attach your credentials and required documents.'],
                    ['number' => '4', 'title' => 'Submit & Track', 'description' => 'Submit your form and monitor your admission status.'],
                ],
            ],
            'cta' => [
                'title' => 'Ready to apply to JOSTUM?',
                'text' => 'Start your application today and take the next step in your academic journey.',
                'button_label' => 'Apply Now',
                'button_url' => 'register.php',
            ],
            'footer' => [
                'columns' => [
                    ['title' => 'Admissions Office', 'items' => ['Joseph Sarwuan Tarka University, Makurdi', 'Gbajimba Road, Makurdi, Benue State']],
                    ['title' => 'Contact', 'items' => ['+234 704 366 7952', 'admissions@uam.edu.ng']],
                    ['title' => 'Quick Links', 'items' => ['Admission Application|register.php', 'Main University Website|././']],
                ],
                'bottom_text' => 'Joseph Sarwuan Tarka University, Makurdi. All rights reserved.',
            ],
        ],
    ];

    return $defaults[$pageKey] ?? [];
}

function portal_page_get_content(?PDO $pdo, string $pageKey): array
{
    $content = portal_page_section_defaults($pageKey);
    if (!$pdo) {
        return $content;
    }

    try {
        if (!portal_page_sections_table_exists($pdo)) {
            return $content;
        }

        $stmt = $pdo->prepare("
            SELECT section_key, content_json
            FROM portal_page_sections
            WHERE page_key = ? AND is_active = 1
        ");
        $stmt->execute([$pageKey]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $decoded = json_decode((string) $row['content_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $content[$row['section_key']] = $decoded;
            }
        }
    } catch (Throwable $e) {
    }

    return $content;
}
