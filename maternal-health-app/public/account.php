<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

$messages = [];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Provide valid name and email.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            try {
                $stmt->execute([$name, $email, $user['id']]);
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                $messages[] = 'Profile updated.';
            } catch (PDOException $e) {
                $errors[] = 'Email already in use.';
            }
        }
    } elseif ($action === 'change_password') {
        $p1 = $_POST['password'] ?? '';
        $p2 = $_POST['password2'] ?? '';
        if ($p1 !== $p2 || strlen($p1) < 6) {
            $errors[] = 'Passwords must match and be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($p1, PASSWORD_BCRYPT), $user['id']]);
            $messages[] = 'Password changed.';
        }
    } elseif ($action === 'deactivate') {
        $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$user['id']]);
        Auth::logout();
        header('Location: /login.php'); exit;
    }
}
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Account</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header">
    <div class="nav">
      <a href="/index.php" style="color:white">Back</a>
      <strong>Account</strong>
    </div>
  </div>
  <div class="container">
    <?php foreach ($messages as $m): ?><div class="alert"><?=htmlspecialchars($m)?></div><?php endforeach; ?>
    <?php foreach ($errors as $e): ?><div class="alert"><?=htmlspecialchars($e)?></div><?php endforeach; ?>
    <div class="card">
      <h2>Edit account</h2>
      <form method="post">
        <input type="hidden" name="action" value="update_profile" />
        <label>Name</label>
        <input type="text" name="name" value="<?=htmlspecialchars($user['name'])?>" required />
        <label>Email</label>
        <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>" required />
        <button>Save</button>
      </form>
    </div>
    <div class="card">
      <h2>Change password</h2>
      <form method="post">
        <input type="hidden" name="action" value="change_password" />
        <label>New password</label>
        <input type="password" name="password" required />
        <label>Confirm password</label>
        <input type="password" name="password2" required />
        <button>Update password</button>
      </form>
    </div>
    <div class="card">
      <h2>Deactivate account</h2>
      <form method="post" onsubmit="return confirm('Deactivate your account? You will be logged out.')">
        <input type="hidden" name="action" value="deactivate" />
        <button>Deactivate</button>
      </form>
    </div>
  </div>
</body>
</html>