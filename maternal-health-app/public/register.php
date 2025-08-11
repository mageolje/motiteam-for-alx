<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::startSession();
$done = false; $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'mother';
    $res = Auth::register($name, $email, $password, $role);
    if ($res['ok']) { $done = true; }
    else { $errors = $res['errors']; }
}
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Register - Maternal Health Advisor</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header"><div class="nav"><a href="/login.php" style="color:white">Back</a></div></div>
  <div class="container">
    <div class="card" style="max-width:560px;margin:40px auto;">
      <h2>Create account</h2>
      <?php if ($done): ?>
        <div class="alert">Account created. You can now <a href="/login.php">login</a>.</div>
      <?php else: ?>
        <?php foreach ($errors as $e): ?><div class="alert"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
        <form method="post">
          <label>Name</label>
          <input type="text" name="name" required />
          <label>Email</label>
          <input type="email" name="email" required />
          <label>Password</label>
          <input type="password" name="password" required />
          <label>Role</label>
          <select name="role">
            <option value="mother">Mother</option>
            <option value="physician">Physician</option>
          </select>
          <button type="submit">Create</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>