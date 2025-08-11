<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect_to_role_dashboard();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password_hash']) && (int)$row['is_active'] === 1) {
            $_SESSION['user_id'] = (int)$row['id'];
            redirect_to_role_dashboard();
        } else {
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - Maternal Health Advisor</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container">
      <h1>Maternal Health Advisor</h1>
      <nav class="nav"><a href="/register.php">Create account</a></nav>
    </div>
  </header>
  <main class="container" style="max-width:560px;">
    <div class="card">
      <h2>Login</h2>
      <?php if ($error): ?>
        <p class="text-muted" style="color:#b91c1c;"><?= h($error) ?></p>
      <?php endif; ?>
      <form method="post">
        <label>Email</label>
        <input type="email" name="email" required />
        <label>Password</label>
        <input type="password" name="password" required />
        <div class="mt-3"><button type="submit">Sign in</button></div>
      </form>
      <p class="mt-3 text-muted">Demo accounts: mother@example.com / physician@example.com with password <strong>Password@123</strong>.</p>
    </div>
  </main>
</body>
</html>