<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

// Handle create/update from POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($user['role'] === 'mother' || $user['role'] === 'physician') && ($_POST['action'] ?? '') === 'create') {
        $mother_id = $user['role'] === 'mother' ? $user['id'] : (int)($_POST['mother_id'] ?? 0);
        $physician_id = $user['role'] === 'physician' ? $user['id'] : (int)($_POST['physician_id'] ?? 0);
        $time = $_POST['scheduled_at'] ?? '';
        $stmt = $pdo->prepare('INSERT INTO appointments (mother_id, physician_id, scheduled_at, status, notes) VALUES (?,?,?,?,?)');
        $stmt->execute([$mother_id, $physician_id, $time, 'scheduled', $_POST['notes'] ?? null]);
        header('Location: /appointments.php'); exit;
    }
    if (($_POST['action'] ?? '') === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'scheduled';
        $notes = $_POST['notes'] ?? null;
        $stmt = $pdo->prepare('UPDATE appointments SET status = ?, notes = ? WHERE id = ?');
        $stmt->execute([$status, $notes, $id]);
        header('Location: /appointments.php'); exit;
    }
}

// Load appointments for the user
if ($user['role'] === 'mother') {
    $stmt = $pdo->prepare('SELECT a.*, m.name as mother_name, p.name as physician_name FROM appointments a JOIN users m ON a.mother_id=m.id JOIN users p ON a.physician_id=p.id WHERE mother_id = ? ORDER BY scheduled_at DESC');
    $stmt->execute([$user['id']]);
} elseif ($user['role'] === 'physician') {
    $stmt = $pdo->prepare('SELECT a.*, m.name as mother_name, p.name as physician_name FROM appointments a JOIN users m ON a.mother_id=m.id JOIN users p ON a.physician_id=p.id WHERE physician_id = ? ORDER BY scheduled_at DESC');
    $stmt->execute([$user['id']]);
} else { // admin sees all
    $stmt = $pdo->query('SELECT a.*, m.name as mother_name, p.name as physician_name FROM appointments a JOIN users m ON a.mother_id=m.id JOIN users p ON a.physician_id=p.id ORDER BY scheduled_at DESC');
}
$appointments = $stmt->fetchAll();

$physicians = $pdo->query("SELECT id, name FROM users WHERE role='physician' AND is_active=1 ORDER BY name")->fetchAll();
$mothers = $pdo->query("SELECT id, name FROM users WHERE role='mother' AND is_active=1 ORDER BY name")->fetchAll();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Appointments</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header">
    <div class="nav">
      <a href="/index.php" style="color:white">Back</a>
      <strong>Appointments</strong>
    </div>
  </div>
  <div class="container">
    <div class="card">
      <h2>Create appointment</h2>
      <form method="post">
        <input type="hidden" name="action" value="create" />
        <?php if ($user['role'] !== 'mother'): ?>
          <label>Mother</label>
          <select name="mother_id" required>
            <option value="">Select</option>
            <?php foreach ($mothers as $m): ?>
              <option value="<?=$m['id']?>"><?=$m['name']?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <?php if ($user['role'] !== 'physician'): ?>
          <label>Physician</label>
          <select name="physician_id" required>
            <option value="">Select</option>
            <?php foreach ($physicians as $p): ?>
              <option value="<?=$p['id']?>"><?=$p['name']?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        <label>Scheduled at</label>
        <input type="datetime-local" name="scheduled_at" required />
        <label>Notes</label>
        <textarea name="notes" rows="2"></textarea>
        <button type="submit">Set appointment</button>
      </form>
    </div>

    <div class="card">
      <h2>Your appointments</h2>
      <table class="table">
        <thead><tr><th>When</th><th>Mother</th><th>Physician</th><th>Status</th><th>Notes</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr>
              <td><?=htmlspecialchars($a['scheduled_at'])?></td>
              <td><?=htmlspecialchars($a['mother_name'])?></td>
              <td><?=htmlspecialchars($a['physician_name'])?></td>
              <td><span class="badge status-<?=htmlspecialchars($a['status'])?>"><?=htmlspecialchars($a['status'])?></span></td>
              <td><?=htmlspecialchars($a['notes'])?></td>
              <td>
                <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                  <input type="hidden" name="action" value="update" />
                  <input type="hidden" name="id" value="<?=$a['id']?>" />
                  <select name="status">
                    <?php foreach (['scheduled','completed','canceled','pending'] as $s): ?>
                      <option value="<?=$s?>" <?=$s===$a['status']?'selected':''?>><?=$s?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="notes" value="<?=htmlspecialchars($a['notes'])?>" />
                  <button>Save</button>
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