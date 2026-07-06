<?php

require_once __DIR__ . '/app/bootstrap.php';

function getStudentDetails(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAlerts(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM alerts WHERE student_id = ? ORDER BY created_at DESC');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function getAcademics(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT * FROM academic_records WHERE student_id = ?');
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

function getResearch(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare('SELECT * FROM research_progress WHERE student_id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getFinance(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare('SELECT * FROM finances WHERE student_id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getResources(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM resources LIMIT 5')->fetchAll();
}

function h(?string $string): string
{
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}
