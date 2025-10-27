<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireRole(['physician']);
$user = Auth::user();
$pdo = Database::getConnection();

// Today's and upcoming appointments for this physician
$appts = $pdo->prepare("SELECT a.*, m.name AS mother_name FROM appointments a JOIN users m ON a.mother_id=m.id WHERE a.physician_id = ? AND a.scheduled_at >= NOW() ORDER BY a.scheduled_at ASC LIMIT 10");
$appts->execute([$user['id']]);
$appts = $appts->fetchAll();

// Open emergencies
$emergencies = $pdo->query("SELECT e.*, u.name as mother_name FROM emergencies e JOIN users u ON e.mother_id=u.id WHERE e.resolved = 0 ORDER BY e.created_at DESC LIMIT 10")->fetchAll();

// Mothers list for quick chat
$mothers = $pdo->query("SELECT id, name FROM users WHERE role='mother' AND is_active=1 ORDER BY name")->fetchAll();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Physician Portal</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header">
    <div class="nav">
      <a href="/index.php" style="color:white">Dashboard</a>
      <strong>Physician Portal</strong>
      <span style="margin-left:auto">Dr. <?=htmlspecialchars($user['name'])?></span>
      <a href="/logout.php">Logout</a>
    </div>
  </div>
  <div class="container">
    <div class="card">
      <h2>Quick actions</h2>
      <div class="button-row">
        <a class="btn" href="/appointments.php">Manage appointments</a>
        <a class="btn" href="/chat.php">Open chat</a>
        <a class="btn" href="/account.php">Edit account</a>
      </div>
    </div>

    <div class="card">
      <h2>Open emergencies</h2>
      <table class="table">
        <thead><tr><th>Mother</th><th>Message</th><th>When</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($emergencies as $e): ?>
            <tr>
              <td><?=htmlspecialchars($e['mother_name'])?></td>
              <td><?=htmlspecialchars($e['message'])?></td>
              <td><?=htmlspecialchars($e['created_at'])?></td>
              <td>
                <form method="post" action="/controllers/emergency.php" style="display:inline">
                  <input type="hidden" name="action" value="resolve" />
                  <input type="hidden" name="id" value="<?=$e['id']?>" />
                  <button>Mark resolved</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Upcoming appointments</h2>
      <table class="table">
        <thead><tr><th>When</th><th>Mother</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach ($appts as $a): ?>
            <tr>
              <td><?=htmlspecialchars($a['scheduled_at'])?></td>
              <td><?=htmlspecialchars($a['mother_name'])?></td>
              <td><?=htmlspecialchars($a['status'])?></td>
              <td><?=htmlspecialchars($a['notes'])?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Quick chat with a mother</h2>
      <form method="get" action="/chat.php" style="display:flex; gap:8px; align-items:center">
        <select name="partner_id">
          <?php foreach ($mothers as $m): ?>
            <option value="<?=$m['id']?>"><?=$m['name']?></option>
          <?php endforeach; ?>
        </select>
        <button>Open chat</button>
      </form>
    </div>
  </div>
  <script src="/assets/app.js"></script>
</body>
</html>