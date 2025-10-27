<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (is_uploaded_file($_FILES['file']['tmp_name'])) {
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['file']['name']);
        $dest = $uploadDir . '/' . $safeName;
        move_uploaded_file($_FILES['file']['tmp_name'], $dest);
        $stmt = $pdo->prepare('INSERT INTO uploads (user_id, file_name, file_path, description) VALUES (?,?,?,?)');
        $stmt->execute([$user['id'], $_FILES['file']['name'], '/uploads/' . $safeName, $_POST['description'] ?? null]);
        header('Location: /upload.php'); exit;
    }
}
$docs = $pdo->prepare('SELECT * FROM uploads WHERE user_id = ? ORDER BY created_at DESC');
$docs->execute([$user['id']]);
$docs = $docs->fetchAll();
?><!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Uploads</title>
  <link rel="stylesheet" href="/assets/styles.css" />
</head>
<body>
  <div class="header"><div class="nav"><a href="/index.php" style="color:white">Back</a><strong>Uploads</strong></div></div>
  <div class="container">
    <div class="card">
      <h2>Upload information</h2>
      <form method="post" enctype="multipart/form-data">
        <label>Description</label>
        <input type="text" name="description" />
        <label>File</label>
        <input type="file" name="file" required />
        <button>Upload</button>
      </form>
    </div>
    <div class="card">
      <h2>Your files</h2>
      <table class="table">
        <thead><tr><th>Name</th><th>Description</th><th>When</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($docs as $d): ?>
            <tr>
              <td><?=htmlspecialchars($d['file_name'])?></td>
              <td><?=htmlspecialchars($d['description'])?></td>
              <td><?=htmlspecialchars($d['created_at'])?></td>
              <td><a class="btn" href="<?=$d['file_path']?>" target="_blank">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>