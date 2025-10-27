<?php
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
Auth::requireRole(['admin']);
$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    } elseif (($_POST['action'] ?? '') === 'update_role') {
        $role = $_POST['role'] ?? 'mother';
        if (in_array($role, ['mother','physician','admin'], true)) {
            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
        }
    }
    header('Location: /admin/users.php'); exit;
}
$users = $pdo->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Manage Users</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header"><div class="nav"><a href="/index.php" style="color:white">Back</a><strong>Manage Users</strong></div></div>
  <div class="container">
    <div class="card">
      <h2>Users</h2>
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?=htmlspecialchars($u['name'])?></td>
              <td><?=htmlspecialchars($u['email'])?></td>
              <td>
                <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                  <input type="hidden" name="action" value="update_role" />
                  <input type="hidden" name="id" value="<?=$u['id']?>" />
                  <select name="role">
                    <?php foreach (['mother','physician','admin'] as $r): ?>
                      <option value="<?=$r?>" <?=$r===$u['role']?'selected':''?>><?=$r?></option>
                    <?php endforeach; ?>
                  </select>
                  <button>Save</button>
                </form>
              </td>
              <td><?= $u['is_active'] ? 'Active' : 'Inactive' ?></td>
              <td>
                <form method="post" style="display:inline-block">
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="id" value="<?=$u['id']?>" />
                  <button><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>