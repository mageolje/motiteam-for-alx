<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = get_pdo();

$attendanceStats = $pdo->query("SELECT status, COUNT(*) AS cnt FROM attendance GROUP BY status")->fetchAll();
$avgScores = $pdo->query("SELECT sub.name AS subject, ROUND(AVG(final_percentage),2) AS avg_pct FROM final_results fr JOIN subjects sub ON sub.id = fr.subject_id GROUP BY sub.id ORDER BY sub.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid two">
	<div class="card">
		<h1>Attendance breakdown</h1>
		<table class="table">
			<thead><tr><th>Status</th><th>Count</th></tr></thead>
			<tbody>
			<?php foreach ($attendanceStats as $row): ?>
				<tr><td><?= h($row['status']) ?></td><td><?= h((string)$row['cnt']) ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="card">
		<h1>Average final % by subject</h1>
		<table class="table">
			<thead><tr><th>Subject</th><th>Average %</th></tr></thead>
			<tbody>
			<?php foreach ($avgScores as $row): ?>
				<tr><td><?= h($row['subject']) ?></td><td><?= h((string)$row['avg_pct']) ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>