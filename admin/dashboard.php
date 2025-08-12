<?php
require __DIR__ . '/../config.php';
require_role(['admin']);
$pdo = get_pdo();
$user = current_user();

$notice = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_user') {
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'mother');
            if ($name && $email && $password && in_array($role, ['mother','physician','admin'], true)) {
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
                $notice = 'User created.';
            } else { $error = 'All fields are required.'; }
        } elseif ($action === 'update_user') {
            $id = (int)($_POST['id'] ?? 0);
            $role = (string)($_POST['role'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            if ($id > 0 && in_array($role, ['mother','physician','admin'], true)) {
                $stmt = $pdo->prepare('UPDATE users SET role=?, is_active=? WHERE id=?');
                $stmt->execute([$role, $is_active, $id]);
                $notice = 'User updated.';
            } else { $error = 'Invalid input.'; }
        } elseif ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
                $stmt->execute([$id]);
                $notice = 'User deleted.';
            } else { $error = 'Invalid user id.'; }
        }
    } catch (Throwable $e) {
        $error = 'Action failed.';
    }
}

$users = $pdo->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - Manage Users</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<header class="header">
  <div class="container">
    <h1>Admin Dashboard</h1>
    <nav class="nav">
      <span class="text-muted">Signed in as <?= h($user['name']) ?></span>
      <a href="/logout.php" class="button">Logout</a>
    </nav>
  </div>
</header>
<main class="container">
  <?php if ($notice): ?><p style="color:#065f46;" class="mt-3"><?= h($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p style="color:#b91c1c;" class="mt-3"><?= h($error) ?></p><?php endif; ?>

  <section class="card">
    <h2>Create user</h2>
    <form method="post" class="grid">
      <input type="hidden" name="action" value="create_user" />
      <div class="col-4"><label>Name</label><input type="text" name="name" required /></div>
      <div class="col-4"><label>Email</label><input type="email" name="email" required /></div>
      <div class="col-4"><label>Password</label><input type="password" name="password" required /></div>
      <div class="col-4">
        <label>Role</label>
        <select name="role" required>
          <option value="mother">Mother</option>
          <option value="physician">Physician</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="col-12"><button type="submit">Create</button></div>
    </form>
  </section>

  <section class="card mt-3">
    <h2>Users</h2>
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= h($u['name']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><?= h($u['role']) ?></td>
            <td><?= (int)$u['is_active']===1 ? 'active' : 'inactive' ?></td>
            <td><?= h($u['created_at']) ?></td>
            <td>
              <form method="post" class="flex">
                <input type="hidden" name="action" value="update_user" />
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
                <select name="role">
                  <?php foreach (['mother','physician','admin'] as $r): ?>
                  <option value="<?= $r ?>" <?= $u['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                  <?php endforeach; ?>
                </select>
                <label style="margin-left:8px;">
                  <input type="checkbox" name="is_active" <?= (int)$u['is_active']===1?'checked':'' ?> /> Active
                </label>
                <button type="submit" style="margin-left:8px;">Save</button>
              </form>
              <form method="post" onsubmit="return confirm('Delete this user?');">
                <input type="hidden" name="action" value="delete_user" />
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>" />
                <button type="submit" style="background:#b91c1c; margin-top:6px;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>