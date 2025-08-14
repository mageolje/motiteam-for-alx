<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['subject_teacher']);
$pdo = get_pdo();
$user = current_user();

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$subjectsStmt = $pdo->prepare('SELECT id, name FROM subjects WHERE teacher_id = ? ORDER BY name');
$subjectsStmt->execute([$user['id']]);
$subjects = $subjectsStmt->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? 0);
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);
$title = $_GET['title'] ?? '';
$maxScore = (float)($_GET['max'] ?? 100);
$date = $_GET['date'] ?? today_date();

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
	$title = trim((string)($_POST['title'] ?? ''));
	$maxScore = (float)($_POST['max_score'] ?? 100);
	$date = $_POST['date'] ?? today_date();
	$scores = $_POST['score'] ?? [];
	if ($selectedClassId && $selectedSubjectId && $title !== '' && $maxScore > 0 && !empty($scores)) {
		$pdo->beginTransaction();
		try {
			$insert = $pdo->prepare('INSERT INTO assessments (student_id, subject_id, class_id, title, max_score, score, assessed_on, assessed_by) VALUES (?,?,?,?,?,?,?,?)');
			foreach ($scores as $studentId => $score) {
				$score = (float)$score;
				if ($score < 0) $score = 0; if ($score > $maxScore) $score = $maxScore;
				$insert->execute([(int)$studentId, $selectedSubjectId, $selectedClassId, $title, $maxScore, $score, $date, $user['id']]);
			}
			$pdo->commit();
			$message = 'Assessments saved.';
		} catch (Throwable $e) {
			$pdo->rollBack();
			$message = 'Error saving assessments.';
		}
	}
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
	<h1>Add assessments</h1>
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
			<label>Date</label>
			<input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
		</div>
		<div>
			<label>Title</label>
			<input type="text" name="title" value="<?= h($title) ?>" placeholder="e.g., Quiz 1">
		</div>
		<div>
			<label>Max score</label>
			<input type="number" name="max" value="<?= h((string)$maxScore) ?>" step="0.01" min="1" max="1000">
		</div>
	</form>
</div>

<?php if ($selectedClassId && $selectedSubjectId && $students): ?>
<form method="post" class="card">
	<?= csrf_field() ?>
	<input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
	<input type="hidden" name="subject_id" value="<?= $selectedSubjectId ?>">
	<input type="hidden" name="date" value="<?= h($date) ?>">
	<input type="hidden" name="title" value="<?= h($title) ?>">
	<input type="hidden" name="max_score" value="<?= h((string)$maxScore) ?>">
	<table class="table">
		<thead><tr><th>Student #</th><th>Name</th><th>Score</th></tr></thead>
		<tbody>
			<?php foreach ($students as $s): ?>
			<tr>
				<td><?= h($s['student_number']) ?></td>
				<td><?= h($s['full_name']) ?></td>
				<td><input type="number" name="score[<?= $s['id'] ?>]" value="0" step="0.01" min="0" max="<?= h((string)$maxScore) ?>" style="max-width:120px"></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div style="margin-top:12px"><button type="submit">Save assessments</button></div>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>