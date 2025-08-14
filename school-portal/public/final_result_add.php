<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['record_officer']);
$pdo = get_pdo();
$user = current_user();

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$subjects = $pdo->query('SELECT id, name FROM subjects ORDER BY name')->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? 0);
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);
$term = $_GET['term'] ?? 'Term 1';
$year = (int)($_GET['year'] ?? (int)date('Y'));

$students = [];
if ($selectedClassId) {
	$stmt = $pdo->prepare('SELECT s.id, s.student_number, s.full_name FROM enrollments e JOIN students s ON s.id = e.student_id WHERE e.class_id = ? ORDER BY s.full_name');
	$stmt->execute([$selectedClassId]);
	$students = $stmt->fetchAll();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$selectedClassId = (int)($_POST['class_id'] ?? 0);
	$selectedSubjectId = (int)($_POST['subject_id'] ?? 0);
	$term = $_POST['term'] ?? 'Term 1';
	$year = (int)($_POST['year'] ?? (int)date('Y'));
	$percentages = $_POST['percentage'] ?? [];
	if ($selectedClassId && $selectedSubjectId && $term !== '' && $year > 2000 && !empty($percentages)) {
		$pdo->beginTransaction();
		try {
			$insert = $pdo->prepare('INSERT INTO final_results (student_id, subject_id, class_id, final_percentage, grade, term, year, entered_by)
				VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE final_percentage=VALUES(final_percentage), grade=VALUES(grade), entered_by=VALUES(entered_by)');
			foreach ($percentages as $studentId => $pct) {
				$pct = (float)$pct; if ($pct < 0) $pct = 0; if ($pct > 100) $pct = 100;
				$grade = grade_from_percentage($pct);
				$insert->execute([(int)$studentId, $selectedSubjectId, $selectedClassId, $pct, $grade, $term, $year, $user['id']]);
			}
			$pdo->commit();
			$message = 'Final results saved.';
		} catch (Throwable $e) {
			$pdo->rollBack();
			$message = 'Error saving final results.';
		}
	}
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
	<h1>Add final results</h1>
	<?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
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
			<label>Subject</label>
			<select name="subject_id" onchange="this.form.submit()" required>
				<option value="">Select subject</option>
				<?php foreach ($subjects as $s): ?>
					<option value="<?= $s['id'] ?>" <?= $selectedSubjectId===$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
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

<?php if ($selectedClassId && $selectedSubjectId && $students): ?>
<form method="post" class="card">
	<?= csrf_field() ?>
	<input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
	<input type="hidden" name="subject_id" value="<?= $selectedSubjectId ?>">
	<input type="hidden" name="term" value="<?= h($term) ?>">
	<input type="hidden" name="year" value="<?= h((string)$year) ?>">
	<table class="table">
		<thead><tr><th>Student #</th><th>Name</th><th>Final %</th><th>Grade</th></tr></thead>
		<tbody>
			<?php foreach ($students as $s): ?>
			<tr>
				<td><?= h($s['student_number']) ?></td>
				<td><?= h($s['full_name']) ?></td>
				<td><input type="number" name="percentage[<?= $s['id'] ?>]" value="0" step="0.01" min="0" max="100" style="max-width:120px"></td>
				<td><em>auto</em></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div style="margin-top:12px"><button type="submit">Save final results</button></div>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>