<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = get_pdo();

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$selectedClassId = (int)($_GET['class_id'] ?? 0);
$term = $_GET['term'] ?? 'Term 1';
$year = (int)($_GET['year'] ?? (int)date('Y'));
$students = [];

if ($selectedClassId) {
	$students = $pdo->prepare('SELECT s.id, s.student_number, s.full_name FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.class_id = ? ORDER BY s.full_name');
	$students->execute([$selectedClassId]);
	$students = $students->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
	<h1>Result card</h1>
	<form method="get" class="grid three">
		<div>
			<label>Class</label>
			<select name="class_id" onchange="this.form.submit()" required>
				<option value="">Select class</option>
				<?php foreach ($classes as $c): ?>
					<option value="<?= $c['id'] ?>" <?= $selectedClassId===$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label>Term</label>
			<select name="term" onchange="this.form.submit()">
				<?php foreach (['Term 1','Term 2','Term 3'] as $t): ?>
					<option <?= $term===$t?'selected':'' ?>><?= h($t) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label>Year</label>
			<input type="number" name="year" value="<?= h((string)$year) ?>" onchange="this.form.submit()">
		</div>
	</form>
</div>

<?php if ($selectedClassId && $students): ?>
<?php foreach ($students as $student): ?>
<div class="card">
	<h3><?= h($student['full_name']) ?> (<?= h($student['student_number']) ?>)</h3>
	<table class="table">
		<thead><tr><th>Subject</th><th>Final %</th><th>Grade</th></tr></thead>
		<tbody>
		<?php
			$stmt = $pdo->prepare('SELECT sub.name, fr.final_percentage, fr.grade FROM final_results fr JOIN subjects sub ON sub.id = fr.subject_id WHERE fr.student_id = ? AND fr.class_id = ? AND fr.term = ? AND fr.year = ? ORDER BY sub.name');
			$stmt->execute([$student['id'], $selectedClassId, $term, $year]);
			$rows = $stmt->fetchAll();
			foreach ($rows as $r):
		?>
		<tr>
			<td><?= h($r['name']) ?></td>
			<td><?= h((string)$r['final_percentage']) ?></td>
			<td><?= h($r['grade']) ?></td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>