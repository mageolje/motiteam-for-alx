<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect_to_role_dashboard();
}

$error = null; $success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role = (string)($_POST['role'] ?? '');

    if ($name === '' || $email === '' || $password === '' || !in_array($role, ['mother','physician'], true)) {
        $error = 'All fields are required.';
    } else {
        $pdo = get_pdo();
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
            $success = 'Account created. You can now log in.';
        } catch (PDOException $e) {
            if ((int)$e->errorInfo[1] === 1062) {
                $error = 'Email already in use.';
            } else {
                $error = 'Failed to create account.';
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - Maternal Health Advisor</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container">
      <h1>Create account</h1>
      <nav class="nav"><a href="/login.php">Login</a></nav>
    </div>
  </header>
  <main class="container" style="max-width:640px;">
    <div class="card">
      <h2>Sign up</h2>
      <?php if ($error): ?><p style="color:#b91c1c;"><?= h($error) ?></p><?php endif; ?>
      <?php if ($success): ?><p style="color:#065f46;"><?= h($success) ?></p><?php endif; ?>
      <form method="post">
        <label>Full name</label>
        <input type="text" name="name" required />
        <label>Email</label>
        <input type="email" name="email" required />
        <label>Password</label>
        <input type="password" name="password" required />
        <label>Role</label>
        <select name="role" required>
          <option value="">Choose role</option>
          <option value="mother">Mother</option>
          <option value="physician">Physician</option>
        </select>
        <div class="mt-3"><button type="submit">Create account</button></div>
      </form>
    </div>
  </main>
</body>
</html>