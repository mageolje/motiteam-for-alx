<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'trigger' && $user['role'] === 'mother') {
        $stmt = $pdo->prepare('INSERT INTO emergencies (mother_id, message) VALUES (?, ?)');
        $stmt->execute([$user['id'], 'Emergency triggered by mother']);
        header('Location: /index.php'); exit;
    }
    if ($action === 'resolve' && in_array($user['role'], ['physician','admin'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE emergencies SET resolved = 1, resolved_by = ? WHERE id = ?');
        $stmt->execute([$user['id'], $id]);
        header('Location: /index.php'); exit;
    }
}

Response::json(['ok' => false], 400);