<?php
require __DIR__ . '/../config.php';
require_role(['mother']);
$pdo = get_pdo();
$user = current_user();

// Handle actions
$notice = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_appointment') {
            $physician_id = (int)($_POST['physician_id'] ?? 0);
            $time = trim((string)($_POST['appointment_time'] ?? ''));
            if ($physician_id && $time) {
                $stmt = $pdo->prepare('INSERT INTO appointments (mother_id, physician_id, appointment_time, status) VALUES (?,?,?,"pending")');
                $stmt->execute([$user['id'], $physician_id, $time]);
                $notice = 'Appointment request sent.';
            } else { $error = 'Select physician and time.'; }
        } elseif ($action === 'create_emergency') {
            $desc = trim((string)($_POST['description'] ?? ''));
            $stmt = $pdo->prepare('INSERT INTO emergencies (mother_id, description) VALUES (?,?)');
            $stmt->execute([$user['id'], $desc]);
            $notice = 'Emergency submitted. A physician will be notified.';
        } elseif ($action === 'set_alarm') {
            $title = trim((string)($_POST['title'] ?? ''));
            $time = trim((string)($_POST['alarm_time'] ?? ''));
            if ($title !== '' && $time !== '') {
                $stmt = $pdo->prepare('INSERT INTO alarms (user_id, title, alarm_time) VALUES (?,?,?)');
                $stmt->execute([$user['id'], $title, $time]);
                $notice = 'Alarm added.';
            } else { $error = 'Title and date/time are required.'; }
        } elseif ($action === 'upload_health') {
            $title = trim((string)($_POST['title'] ?? ''));
            $content = trim((string)($_POST['content'] ?? ''));
            if ($title && $content) {
                $stmt = $pdo->prepare('INSERT INTO health_records (mother_id, uploaded_by, title, content) VALUES (?,?,?,?)');
                $stmt->execute([$user['id'], $user['id'], $title, $content]);
                $notice = 'Information uploaded.';
            } else { $error = 'Title and content are required.'; }
        } elseif ($action === 'send_message') {
            $to_id = (int)($_POST['to_id'] ?? 0);
            $content = trim((string)($_POST['content'] ?? ''));
            if ($to_id && $content !== '') {
                $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content) VALUES (?,?,?)');
                $stmt->execute([$user['id'], $to_id, $content]);
                $notice = 'Message sent.';
            } else { $error = 'Select recipient and enter a message.'; }
        }
    } catch (Throwable $e) {
        $error = 'Action failed.';
    }
}

// Load data
$physicians = $pdo->query("SELECT id, name FROM users WHERE role = 'physician' AND is_active = 1 ORDER BY name")->fetchAll();
$appointments = $pdo->prepare('SELECT a.*, u.name AS physician_name FROM appointments a JOIN users u ON a.physician_id=u.id WHERE a.mother_id=? ORDER BY a.appointment_time DESC');
$appointments->execute([$user['id']]);
$appointments = $appointments->fetchAll();

$alarms = $pdo->prepare('SELECT * FROM alarms WHERE user_id=? AND is_active=1 ORDER BY alarm_time ASC');
$alarms->execute([$user['id']]);
$alarms = $alarms->fetchAll();

// Health records for this mother
$recordsStmt = $pdo->prepare('SELECT hr.*, u.name AS uploaded_by_name FROM health_records hr LEFT JOIN users u ON hr.uploaded_by=u.id WHERE hr.mother_id=? ORDER BY hr.created_at DESC');
$recordsStmt->execute([$user['id']]);
$records = $recordsStmt->fetchAll();

$with = isset($_GET['with']) ? (int)$_GET['with'] : (count($physicians) ? (int)$physicians[0]['id'] : 0);
$messages = [];
if ($with) {
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC');
    $stmt->execute([$user['id'], $with, $with, $user['id']]);
    $messages = $stmt->fetchAll();
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mother Dashboard</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<header class="header">
  <div class="container">
    <h1>Mother Dashboard</h1>
    <nav class="nav">
      <span class="text-muted">Signed in as <?= h($user['name']) ?></span>
      <a href="/logout.php" class="button">Logout</a>
    </nav>
  </div>
</header>
<main class="container">
  <?php if ($notice): ?><p style="color:#065f46;" class="mt-3"><?= h($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p style="color:#b91c1c;" class="mt-3"><?= h($error) ?></p><?php endif; ?>
  <div class="grid">
    <section class="card col-8">
      <h2>Appointments</h2>
      <form method="post" class="grid">
        <input type="hidden" name="action" value="create_appointment" />
        <div class="col-6">
          <label>Physician</label>
          <select name="physician_id" required>
            <option value="">Select physician</option>
            <?php foreach ($physicians as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label>Date & time</label>
          <input type="datetime-local" name="appointment_time" required />
        </div>
        <div class="col-12"><button type="submit">Request appointment</button></div>
      </form>
      <table class="table mt-3">
        <thead><tr><th>When</th><th>Physician</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr>
              <td><?= h($a['appointment_time']) ?></td>
              <td><?= h($a['physician_name']) ?></td>
              <td><span class="badge <?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
              <td><?= h($a['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="card col-4">
      <h2>Alarms</h2>
      <form method="post">
        <input type="hidden" name="action" value="set_alarm" />
        <label>Title</label>
        <input type="text" name="title" required />
        <label>Date & time</label>
        <input type="datetime-local" name="alarm_time" required />
        <div class="mt-2"><button type="submit">Add alarm</button></div>
      </form>
      <ul class="mt-3">
        <?php foreach ($alarms as $al): ?>
          <li class="text-muted"><?= h($al['alarm_time']) ?> - <?= h($al['title']) ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="card col-6">
      <h2>Emergency</h2>
      <form method="post">
        <input type="hidden" name="action" value="create_emergency" />
        <label>Describe the emergency</label>
        <textarea name="description" rows="3" placeholder="Optional"></textarea>
        <div class="mt-2"><button type="submit" style="background:#ef4444;">Send Emergency Alert</button></div>
      </form>
    </section>

    <section class="card col-6">
      <h2>Upload information</h2>
      <form method="post">
        <input type="hidden" name="action" value="upload_health" />
        <label>Title</label>
        <input type="text" name="title" required />
        <label>Content</label>
        <textarea name="content" rows="4" required></textarea>
        <div class="mt-2"><button type="submit">Upload</button></div>
      </form>
    </section>

    <section class="card col-6">
      <h2>Retrieve information</h2>
      <table class="table">
        <thead><tr><th>When</th><th>Title</th><th>From</th></tr></thead>
        <tbody>
          <?php foreach ($records as $r): ?>
            <tr>
              <td><?= h($r['created_at']) ?></td>
              <td><?= h($r['title']) ?></td>
              <td><?= h($r['uploaded_by_name'] ? $r['uploaded_by_name'] : 'You') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="card col-12">
      <h2>Chat with physician</h2>
      <form method="get" class="flex">
        <label>Conversation with</label>
        <select name="with" onchange="this.form.submit()">
          <?php foreach ($physicians as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= $with===(int)$p['id']?'selected':'' ?>><?= h($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="card mt-3" style="max-height:240px; overflow:auto;">
        <?php foreach ($messages as $m): ?>
          <div class="mt-2"><strong><?= $m['sender_id']===$user['id'] ? 'You' : 'Physician' ?>:</strong> <?= h($m['content']) ?> <span class="text-muted" style="font-size:12px;">(<?= h($m['created_at']) ?>)</span></div>
        <?php endforeach; ?>
      </div>
      <?php if ($with): ?>
      <form method="post" class="mt-3">
        <input type="hidden" name="action" value="send_message" />
        <input type="hidden" name="to_id" value="<?= (int)$with ?>" />
        <div class="flex"><input type="text" name="content" placeholder="Type a message" required /> <button type="submit">Send</button></div>
      </form>
      <?php endif; ?>
    </section>
  </div>
</main>
</body>
</html>