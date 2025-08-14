<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$pdo = get_pdo();
$user = current_user();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$receiverId = (int)($_POST['receiver_id'] ?? 0);
	$subject = trim((string)($_POST['subject'] ?? ''));
	$body = trim((string)($_POST['body'] ?? ''));
	if ($receiverId && $subject !== '' && $body !== '') {
		$stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?,?,?,?)');
		$stmt->execute([$user['id'], $receiverId, $subject, $body]);
		$message = 'Message sent.';
	}
}

$users = $pdo->prepare('SELECT id, full_name, role FROM users WHERE id != ? ORDER BY role, full_name');
$users->execute([$user['id']]);
$usersList = $users->fetchAll();

$inbox = $pdo->prepare('SELECT m.id, m.subject, m.created_at, u.full_name AS from_name FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.receiver_id = ? ORDER BY m.created_at DESC LIMIT 20');
$inbox->execute([$user['id']]);
$inboxRows = $inbox->fetchAll();

$sent = $pdo->prepare('SELECT m.id, m.subject, m.created_at, u.full_name AS to_name FROM messages m JOIN users u ON u.id = m.receiver_id WHERE m.sender_id = ? ORDER BY m.created_at DESC LIMIT 20');
$sent->execute([$user['id']]);
$sentRows = $sent->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="grid two">
	<div class="card">
		<h1>Send message</h1>
		<?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
		<form method="post">
			<?= csrf_field() ?>
			<label>To</label>
			<select name="receiver_id" required>
				<option value="">Select user</option>
				<?php foreach ($usersList as $u): ?>
					<option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?> (<?= h($u['role']) ?>)</option>
				<?php endforeach; ?>
			</select>
			<label>Subject</label>
			<input type="text" name="subject" required>
			<label>Message</label>
			<textarea name="body" rows="5" required></textarea>
			<div style="margin-top:12px"><button type="submit">Send</button></div>
		</form>
	</div>
	<div class="card">
		<h2>Inbox</h2>
		<table class="table">
			<thead><tr><th>From</th><th>Subject</th><th>When</th></tr></thead>
			<tbody>
			<?php foreach ($inboxRows as $r): ?>
				<tr><td><?= h($r['from_name']) ?></td><td><?= h($r['subject']) ?></td><td><?= h($r['created_at']) ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2>Sent</h2>
		<table class="table">
			<thead><tr><th>To</th><th>Subject</th><th>When</th></tr></thead>
			<tbody>
			<?php foreach ($sentRows as $r): ?>
				<tr><td><?= h($r['to_name']) ?></td><td><?= h($r['subject']) ?></td><td><?= h($r['created_at']) ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>