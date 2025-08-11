<?php
require __DIR__ . '/../config.php';
require_role(['physician']);
$pdo = get_pdo();
$user = current_user();

$notice = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_appt') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $notes = trim((string)($_POST['notes'] ?? ''));
            $stmt = $pdo->prepare('UPDATE appointments SET status=?, notes=? WHERE id=? AND physician_id=?');
            $stmt->execute([$status, $notes, $id, $user['id']]);
            $notice = 'Appointment updated.';
        } elseif ($action === 'create_appt') {
            $mother_id = (int)($_POST['mother_id'] ?? 0);
            $time = trim((string)($_POST['appointment_time'] ?? ''));
            if ($mother_id && $time) {
                $stmt = $pdo->prepare('INSERT INTO appointments (mother_id, physician_id, appointment_time, status) VALUES (?,?,?,"approved")');
                $stmt->execute([$mother_id, $user['id'], $time]);
                $notice = 'Appointment created.';
            } else { $error = 'Select mother and time.'; }
        } elseif ($action === 'upload_health') {
            $mother_id = (int)($_POST['mother_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $content = trim((string)($_POST['content'] ?? ''));
            if ($mother_id && $title && $content) {
                $stmt = $pdo->prepare('INSERT INTO health_records (mother_id, uploaded_by, title, content) VALUES (?,?,?,?)');
                $stmt->execute([$mother_id, $user['id'], $title, $content]);
                $notice = 'Record shared with mother.';
            } else { $error = 'All fields are required.'; }
        } elseif ($action === 'send_message') {
            $to_id = (int)($_POST['to_id'] ?? 0);
            $content = trim((string)($_POST['content'] ?? ''));
            if ($to_id && $content !== '') {
                $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content) VALUES (?,?,?)');
                $stmt->execute([$user['id'], $to_id, $content]);
                $notice = 'Message sent.';
            } else { $error = 'Select recipient and enter a message.'; }
        } elseif ($action === 'ack_emergency') {
            $id = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? 'acknowledged';
            $stmt = $pdo->prepare('UPDATE emergencies SET status=? WHERE id=?');
            $stmt->execute([$status, $id]);
            $notice = 'Emergency updated.';
        }
    } catch (Throwable $e) {
        $error = 'Action failed.';
    }
}

$appointments = $pdo->prepare('SELECT a.*, m.name AS mother_name FROM appointments a JOIN users m ON a.mother_id=m.id WHERE a.physician_id=? ORDER BY a.appointment_time DESC');
$appointments->execute([$user['id']]);
$appointments = $appointments->fetchAll();

$mothers = $pdo->query("SELECT id, name FROM users WHERE role='mother' AND is_active=1 ORDER BY name")->fetchAll();

$emergencies = $pdo->query("SELECT e.*, u.name AS mother_name FROM emergencies e JOIN users u ON e.mother_id=u.id WHERE e.status IN ('open','acknowledged') ORDER BY e.created_at DESC")->fetchAll();

$with = isset($_GET['with']) ? (int)$_GET['with'] : (count($mothers) ? (int)$mothers[0]['id'] : 0);
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
  <title>Physician Dashboard</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
<header class="header">
  <div class="container">
    <h1>Physician Dashboard</h1>
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
      <h2>Manage appointments</h2>
      <form method="post" class="grid">
        <input type="hidden" name="action" value="create_appt" />
        <div class="col-6">
          <label>Mother</label>
          <select name="mother_id" required>
            <option value="">Select mother</option>
            <?php foreach ($mothers as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6">
          <label>Date & time</label>
          <input type="datetime-local" name="appointment_time" required />
        </div>
        <div class="col-12"><button type="submit">Create appointment</button></div>
      </form>
      <table class="table mt-3">
        <thead><tr><th>When</th><th>Mother</th><th>Status</th><th>Notes</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
            <tr>
              <td><?= h($a['appointment_time']) ?></td>
              <td><?= h($a['mother_name']) ?></td>
              <td><span class="badge <?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
              <td><?= h($a['notes'] ?? '') ?></td>
              <td>
                <form method="post" class="flex">
                  <input type="hidden" name="action" value="update_appt" />
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>" />
                  <select name="status">
                    <?php foreach (['pending','approved','completed','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $a['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="notes" placeholder="Notes" value="<?= h($a['notes'] ?? '') ?>" />
                  <button type="submit">Update</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="card col-4">
      <h2>Emergencies</h2>
      <?php foreach ($emergencies as $e): ?>
        <div class="card mt-2">
          <div><strong><?= h($e['mother_name']) ?></strong> — <span class="badge"><?= h($e['status']) ?></span></div>
          <div class="text-muted"><?= h($e['description'] ?? '') ?></div>
          <div class="mt-2">
            <form method="post" class="flex">
              <input type="hidden" name="action" value="ack_emergency" />
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>" />
              <select name="status">
                <?php foreach (['open','acknowledged','resolved'] as $s): ?>
                <option value="<?= $s ?>" <?= $e['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Save</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="card col-6">
      <h2>Share record with mother</h2>
      <form method="post">
        <input type="hidden" name="action" value="upload_health" />
        <label>Mother</label>
        <select name="mother_id" required>
          <option value="">Select mother</option>
          <?php foreach ($mothers as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Title</label>
        <input type="text" name="title" required />
        <label>Content</label>
        <textarea name="content" rows="4" required></textarea>
        <div class="mt-2"><button type="submit">Share</button></div>
      </form>
    </section>

    <section class="card col-6">
      <h2>Chat with mother</h2>
      <form method="get" class="flex">
        <label>Conversation with</label>
        <select name="with" onchange="this.form.submit()">
          <?php foreach ($mothers as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= $with===(int)$m['id']?'selected':'' ?>><?= h($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="card mt-3" style="max-height:240px; overflow:auto;">
        <?php foreach ($messages as $m): ?>
          <div class="mt-2"><strong><?= $m['sender_id']===$user['id'] ? 'You' : 'Mother' ?>:</strong> <?= h($m['content']) ?> <span class="text-muted" style="font-size:12px;">(<?= h($m['created_at']) ?>)</span></div>
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