<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::requireLogin();
$user = Auth::user();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Maternal Health Advisor</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body onload="MHApp.startAlarmChecks()">
  <div class="header">
    <div class="nav">
      <div><strong>Maternal Health Advisor</strong></div>
      <a href="/index.php">Dashboard</a>
      <a href="/appointments.php">Appointments</a>
      <a href="/chat.php">Chat</a>
      <a href="/upload.php">Uploads</a>
      <a href="/account.php">Account</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="/admin/users.php">Manage Users</a>
      <?php endif; ?>
      <span style="margin-left:auto">Hello, <?=htmlspecialchars($user['name'])?> (<?=htmlspecialchars($user['role'])?>)</span>
      <a href="/logout.php">Logout</a>
    </div>
  </div>
  <div class="container">
    <div class="card">
      <h2>Quick actions</h2>
      <div class="button-row">
        <?php if ($user['role'] === 'mother'): ?>
          <form method="post" action="/controllers/emergency.php" onsubmit="return confirm('Trigger emergency alert?')">
            <input type="hidden" name="action" value="trigger" />
            <button>Emergency</button>
          </form>
          <a class="btn" href="/alarms.php">Set alarm</a>
        <?php endif; ?>
        <a class="btn" href="/appointments.php">Manage appointments</a>
      </div>
    </div>

    <div class="card">
      <h2>Recent emergencies</h2>
      <div id="emergencies">
        <?php require __DIR__ . '/../controllers/partials/emergencies_list.php'; ?>
      </div>
    </div>
  </div>
  <script src="/assets/app.js"></script>
</body>
</html>