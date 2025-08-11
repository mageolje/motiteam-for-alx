<?php
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Response.php';
Auth::requireLogin();
$user = Auth::user();
$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'ack') {
        $id = (int)($_POST['id'] ?? 0);
        // For demo, delete or disable alarm after notification
        $stmt = $pdo->prepare('UPDATE alarms SET enabled = 0 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $user['id']]);
        Response::json(['ok' => true]);
    }
}

$stmt = $pdo->prepare('SELECT id, title, alarm_time, enabled FROM alarms WHERE user_id = ? AND enabled = 1 ORDER BY alarm_time ASC');
$stmt->execute([$user['id']]);
$alarms = $stmt->fetchAll();
Response::json(['alarms' => $alarms]);