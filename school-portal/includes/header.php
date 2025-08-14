<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>School Portal</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <div class="brand">School Portal</div>
        <nav class="nav">
            <?php if ($user): ?>
                <a href="/dashboard.php">Dashboard</a>
                <a href="/messages.php">Messages</a>
                <a href="/students.php">Students</a>
                <a href="/report_view.php">Reports</a>
                <?php if (in_array($user['role'], ['subject_teacher', 'homeroom_teacher'], true)): ?>
                    <a href="/attendance_add.php">Attendance</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'subject_teacher'): ?>
                    <a href="/assessment_add.php">Assessments</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'record_officer'): ?>
                    <a href="/final_result_add.php">Final Results</a>
                    <a href="/result_card.php">Result Card</a>
                <?php endif; ?>
                <span class="spacer"></span>
                <span class="user"><?= h($user['full_name']) ?> (<?= h($user['role']) ?>)</span>
                <a class="btn" href="/logout.php">Log out</a>
            <?php else: ?>
                <a class="btn" href="/index.php">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">