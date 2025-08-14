<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = get_pdo();
$user = current_user();

$counts = [
	'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
	'students' => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
	'classes' => (int)$pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
	'subjects' => (int)$pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
];

include __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= h($user['full_name']) ?></h1>
<div class="grid three">
	<div class="card"><h3>Users</h3><div><?= $counts['users'] ?></div></div>
	<div class="card"><h3>Students</h3><div><?= $counts['students'] ?></div></div>
	<div class="card"><h3>Classes</h3><div><?= $counts['classes'] ?></div></div>
</div>

<div class="card">
	<h2>Quick actions</h2>
	<div class="grid two">
		<div>
			<ul>
				<?php if (in_array($user['role'], ['subject_teacher','homeroom_teacher'], true)): ?>
					<li><a href="/attendance_add.php">Add attendance</a></li>
				<?php endif; ?>
				<?php if ($user['role'] === 'subject_teacher'): ?>
					<li><a href="/assessment_add.php">Add assessments</a></li>
				<?php endif; ?>
				<?php if ($user['role'] === 'record_officer'): ?>
					<li><a href="/final_result_add.php">Add final results</a></li>
					<li><a href="/result_card.php">Generate result card</a></li>
				<?php endif; ?>
				<li><a href="/messages.php">Messages</a></li>
				<li><a href="/report_view.php">Reports</a></li>
			</ul>
		</div>
		<div>
			<p>Use the navigation to manage students, attendance, assessments, results, and more.</p>
		</div>
	</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>