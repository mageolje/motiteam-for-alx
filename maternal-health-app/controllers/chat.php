<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = (int)($_POST['to_user_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');
    if ($to && $content !== '') {
        $stmt = $pdo->prepare('INSERT INTO messages (from_user_id, to_user_id, content) VALUES (?,?,?)');
        $stmt->execute([$user['id'], $to, $content]);
        Response::json(['ok' => true]);
    }
    Response::json(['ok' => false], 400);
}

$partnerId = (int)($_GET['partner_id'] ?? 0);
$stmt = $pdo->prepare('SELECT m.*, u1.name as from_name, u2.name as to_name FROM messages m JOIN users u1 ON m.from_user_id=u1.id JOIN users u2 ON m.to_user_id=u2.id WHERE (from_user_id=? AND to_user_id=?) OR (from_user_id=? AND to_user_id=?) ORDER BY m.created_at ASC');
$stmt->execute([$user['id'], $partnerId, $partnerId, $user['id']]);
$messages = $stmt->fetchAll();
Response::json(['messages' => $messages]);