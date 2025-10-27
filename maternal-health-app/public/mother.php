<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireRole(['mother']);
$user = Auth::user();
$pdo = Database::getConnection();

// Upcoming appointments for the mother
$appts = $pdo->prepare("SELECT a.*, p.name AS physician_name FROM appointments a JOIN users p ON a.physician_id=p.id WHERE a.mother_id = ? AND a.scheduled_at >= NOW() ORDER BY a.scheduled_at ASC LIMIT 5");
$appts->execute([$user['id']]);
$appts = $appts->fetchAll();

// Recent uploads (her own)
$uploads = $pdo->prepare('SELECT * FROM uploads WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$uploads->execute([$user['id']]);
$uploads = $uploads->fetchAll();

// Alarms
$alarms = $pdo->prepare('SELECT * FROM alarms WHERE user_id = ? ORDER BY alarm_time ASC LIMIT 5');
$alarms->execute([$user['id']]);
$alarms = $alarms->fetchAll();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Mother Portal</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body onload="MHApp.startAlarmChecks()">
  <div class="header">
    <div class="nav">
      <a href="/index.php" style="color:white">Dashboard</a>
      <strong>Mother Portal</strong>
      <span style="margin-left:auto">Hello, <?=htmlspecialchars($user['name'])?></span>
      <a href="/logout.php">Logout</a>
    </div>
  </div>
  <div class="container">
    <div class="card">
      <h2>Quick actions</h2>
      <div class="button-row">
        <a class="btn" href="/appointments.php">View/Set appointment</a>
        <a class="btn" href="/alarms.php">Set alarm</a>
        <a class="btn" href="/chat.php">Open chat</a>
        <a class="btn" href="/upload.php">Upload information</a>
        <a class="btn" href="/account.php">Edit account</a>
        <form method="post" action="/controllers/emergency.php" onsubmit="return confirm('Trigger emergency alert?')">
          <input type="hidden" name="action" value="trigger" />
          <button>Emergency</button>
        </form>
      </div>
    </div>

    <div class="card">
      <h2>Upcoming appointments</h2>
      <table class="table">
        <thead><tr><th>When</th><th>With</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($appts as $a): ?>
            <tr>
              <td><?=htmlspecialchars($a['scheduled_at'])?></td>
              <td><?=htmlspecialchars($a['physician_name'])?></td>
              <td><?=htmlspecialchars($a['status'])?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Your alarms</h2>
      <ul>
        <?php foreach ($alarms as $al): ?>
          <li><?=htmlspecialchars($al['title'])?> — <?=htmlspecialchars($al['alarm_time'])?> (<?= $al['enabled'] ? 'enabled' : 'disabled' ?>)</li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h2>Your recent uploads</h2>
      <ul>
        <?php foreach ($uploads as $u): ?>
          <li><a href="<?=htmlspecialchars($u['file_path'])?>" target="_blank"><?=htmlspecialchars($u['file_name'])?></a> — <?=htmlspecialchars($u['created_at'])?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <script src="/assets/app.js"></script>
</body>
</html>