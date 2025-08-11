<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::startSession();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Login - Maternal Health Advisor</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header"><div class="nav"><strong>Maternal Health Advisor</strong></div></div>
  <div class="container">
    <div class="card" style="max-width:480px;margin:40px auto;">
      <h2>Login</h2>
      <?php if ($error): ?><div class="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <label>Email</label>
        <input type="email" name="email" required />
        <label>Password</label>
        <input type="password" name="password" required />
        <div class="button-row">
          <button type="submit">Login</button>
          <a class="btn" href="/register.php">Create account</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>