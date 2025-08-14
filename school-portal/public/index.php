<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
	redirect('/dashboard.php');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$username = trim((string)($_POST['username'] ?? ''));
	$password = (string)($_POST['password'] ?? '');
	if ($username !== '' && $password !== '') {
		if (login($username, $password)) {
			redirect('/dashboard.php');
		}
		$message = 'Invalid credentials';
	} else {
		$message = 'Please enter username and password';
	}
}

include __DIR__ . '/../includes/header.php';
?>
<div class="grid two">
	<div class="card">
		<h1>Log in</h1>
		<?php if ($message): ?><div class="alert error"><?= h($message) ?></div><?php endif; ?>
		<form method="post">
			<?= csrf_field() ?>
			<label>Username</label>
			<input type="text" name="username" required>
			<label>Password</label>
			<input type="password" name="password" required>
			<div style="margin-top:12px; display:flex; gap:10px; align-items:center;">
				<button type="submit">Log in</button>
			</div>
		</form>
	</div>
	<div class="card">
		<h2>Demo Accounts</h2>
		<ul>
			<li><strong>Record officer</strong>: officer1 / password</li>
			<li><strong>Subject teacher</strong>: teacher1 / password</li>
			<li><strong>Homeroom teacher</strong>: homeroom1 / password</li>
		</ul>
	</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>