<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['subject_teacher','homeroom_teacher']);
$pdo = get_pdo();
$user = current_user();

$classes = $pdo->query('SELECT id, name FROM classes ORDER BY name')->fetchAll();
$subjects = $pdo->prepare('SELECT id, name FROM subjects WHERE teacher_id = ? ORDER BY name');
$subjects->execute([$user['id']]);
$subjectOptions = $subjects->fetchAll();

$selectedClassId = (int)($_GET['class_id'] ?? 0);
$date = $_GET['date'] ?? today_date();
$selectedSubjectId = (int)($_GET['subject_id'] ?? 0);

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
	$selectedSubjectId = isset($_POST['subject_id']) && $_POST['subject_id'] !== '' ? (int)$_POST['subject_id'] : null;
	$date = $_POST['date'] ?? today_date();
	$statuses = $_POST['status'] ?? [];
	if ($selectedClassId && $date && !empty($statuses)) {
		$pdo->beginTransaction();
		try {
			$insert = $pdo->prepare('INSERT INTO attendance (student_id, subject_id, class_id, date, status, marked_by) VALUES (?,?,?,?,?,?)
				ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)');
			foreach ($statuses as $studentId => $status) {
				if (!in_array($status, ['present','absent','late','excused'], true)) continue;
				$insert->execute([(int)$studentId, $selectedSubjectId, $selectedClassId, $date, $status, $user['id']]);
			}
			$pdo->commit();
			$message = 'Attendance saved.';
		} catch (Throwable $e) {
			$pdo->rollBack();
			$message = 'Error saving attendance.';
		}
	}
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
	<h1>Add attendance</h1>
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
			<label>Date</label>
			<input type="date" name="date" value="<?= h($date) ?>" onchange="this.form.submit()">
		</div>
		<div>
			<label>Subject (optional)</label>
			<select name="subject_id" onchange="this.form.submit()">
				<option value="">-</option>
				<?php foreach ($subjectOptions as $s): ?>
					<option value="<?= $s['id'] ?>" <?= $selectedSubjectId===$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</form>
</div>

<?php if ($selectedClassId && $students): ?>
<form method="post" class="card">
	<?= csrf_field() ?>
	<input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
	<input type="hidden" name="date" value="<?= h($date) ?>">
	<input type="hidden" name="subject_id" value="<?= h((string)$selectedSubjectId) ?>">
	<table class="table">
		<thead>
			<tr>
				<th>Student #</th><th>Name</th><th>Status</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($students as $s): ?>
			<tr>
				<td><?= h($s['student_number']) ?></td>
				<td><?= h($s['full_name']) ?></td>
				<td>
					<select name="status[<?= $s['id'] ?>]">
						<option value="present">Present</option>
						<option value="absent">Absent</option>
						<option value="late">Late</option>
						<option value="excused">Excused</option>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<div style="margin-top:12px"><button type="submit">Save attendance</button></div>
</form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>