<?php
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Database.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

if ($user['role'] === 'mother') {
    $stmt = $pdo->prepare('SELECT e.*, u.name as mother_name FROM emergencies e JOIN users u ON e.mother_id=u.id WHERE mother_id = ? ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$user['id']]);
} else {
    $stmt = $pdo->query('SELECT e.*, u.name as mother_name FROM emergencies e JOIN users u ON e.mother_id=u.id ORDER BY created_at DESC LIMIT 20');
}
$items = $stmt->fetchAll();
?>
<table class="table">
  <thead><tr><th>Mother</th><th>Message</th><th>When</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <td><?=htmlspecialchars($it['mother_name'])?></td>
      <td><?=htmlspecialchars($it['message'])?></td>
      <td><?=htmlspecialchars($it['created_at'])?></td>
      <td><?= $it['resolved'] ? 'Resolved' : 'Open' ?></td>
      <td>
        <?php if (!$it['resolved'] && in_array($user['role'], ['physician','admin'], true)): ?>
          <form method="post" action="/controllers/emergency.php">
            <input type="hidden" name="action" value="resolve" />
            <input type="hidden" name="id" value="<?=$it['id']?>" />
            <button>Resolve</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>