<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();
$partners = $pdo->query("SELECT id, name, role FROM users WHERE id <> " . (int)$user['id'] . " AND is_active=1 ORDER BY name")->fetchAll();
$partnerId = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : (count($partners) ? (int)$partners[0]['id'] : 0);
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Chat</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body onload="MHApp.startChatPolling(<?=$partnerId?>)">
  <div class="header">
    <div class="nav">
      <a href="/index.php" style="color:white">Back</a>
      <strong>Chat</strong>
    </div>
  </div>
  <div class="container">
    <div class="card" style="display:flex; gap:16px;">
      <div style="width:260px;">
        <h3>Contacts</h3>
        <ul>
          <?php foreach ($partners as $p): ?>
            <li><a href="/chat.php?partner_id=<?=$p['id']?>"><?=htmlspecialchars($p['name'])?> (<?=htmlspecialchars($p['role'])?>)</a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div style="flex:1; display:flex; flex-direction:column;">
        <div id="chat-box" style="flex:1; border:1px solid #e6e6ef; border-radius:6px; padding:10px; background:#fff; height:360px; overflow:auto;"></div>
        <form method="post" action="/controllers/chat.php" style="margin-top:8px; display:flex; gap:6px;">
          <input type="hidden" name="to_user_id" value="<?=$partnerId?>" />
          <input type="text" name="content" placeholder="Type a message" required />
          <button>Send</button>
        </form>
      </div>
    </div>
  </div>
  <script src="/assets/app.js"></script>
</body>
</html>