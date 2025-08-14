<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = get_pdo();

$rows = $pdo->query('SELECT s.id, s.student_number, s.full_name, c.name AS class_name
	FROM students s
	LEFT JOIN enrollments e ON e.student_id = s.id
	LEFT JOIN classes c ON c.id = e.class_id
	ORDER BY s.full_name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
	<h1>Students</h1>
	<table class="table">
		<thead>
			<tr>
				<th>Student #</th>
				<th>Name</th>
				<th>Class</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $r): ?>
			<tr>
				<td><?= h($r['student_number']) ?></td>
				<td><?= h($r['full_name']) ?></td>
				<td><?= h($r['class_name'] ?? '-') ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>