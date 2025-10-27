<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'create') {
        $stmt = $pdo->prepare('INSERT INTO alarms (user_id, title, alarm_time) VALUES (?,?,?)');
        $stmt->execute([$user['id'], $_POST['title'] ?? 'Alarm', $_POST['alarm_time'] ?? '']);
        header('Location: /alarms.php'); exit;
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $stmt = $pdo->prepare('UPDATE alarms SET enabled = NOT enabled WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$_POST['id'], $user['id']]);
        header('Location: /alarms.php'); exit;
    }
}
$alarms = $pdo->prepare('SELECT * FROM alarms WHERE user_id = ? ORDER BY alarm_time DESC');
$alarms->execute([$user['id']]);
$alarms = $alarms->fetchAll();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Alarms</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body onload="MHApp.startAlarmChecks()">
  <div class="header">
    <div class="nav">
      <a href="/index.php" style="color:white">Back</a>
      <strong>Alarms</strong>
    </div>
  </div>
  <div class="container">
    <div class="card">
      <h2>Set alarm</h2>
      <form method="post">
        <input type="hidden" name="action" value="create" />
        <label>Title</label>
        <input type="text" name="title" required />
        <label>Time</label>
        <input type="datetime-local" name="alarm_time" required />
        <button>Save</button>
      </form>
    </div>
    <div class="card">
      <h2>Your alarms</h2>
      <table class="table">
        <thead><tr><th>Title</th><th>Time</th><th>Enabled</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($alarms as $a): ?>
            <tr>
              <td><?=htmlspecialchars($a['title'])?></td>
              <td><?=htmlspecialchars($a['alarm_time'])?></td>
              <td><?= $a['enabled'] ? 'Yes' : 'No' ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="id" value="<?=$a['id']?>" />
                  <button>Toggle</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <script src="/assets/app.js"></script>
</body>
</html>