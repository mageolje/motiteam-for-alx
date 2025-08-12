<?php
// api.php - JSON API for SPA interactions
require __DIR__ . '/config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$pdo = get_pdo();

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function require_json_login(): array {
    $user = current_user();
    if (!$user) {
        respond(['status' => 'error', 'message' => 'Not authenticated'], 401);
    }
    return $user;
}

function require_json_role(array $roles): array {
    $user = require_json_login();
    if (!in_array($user['role'], $roles, true)) {
        respond(['status' => 'error', 'message' => 'Forbidden'], 403);
    }
    return $user;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = ($method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? ''));

try {
    switch ($action) {
        case 'login': {
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $emailOrUsername = trim((string)($_POST['username'] ?? $_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $roleOpt = trim((string)($_POST['role'] ?? ''));
            if ($emailOrUsername === '' || $password === '') {
                respond(['status' => 'error', 'message' => 'Email/username and password are required'], 400);
            }
            // Treat provided username as email for this app; SPA may send username
            $email = $emailOrUsername;
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if ($row && (int)$row['is_active'] === 1 && password_verify($password, $row['password_hash'])) {
                if ($roleOpt !== '' && $roleOpt !== $row['role']) {
                    respond(['status' => 'error', 'message' => 'Role mismatch for this account'], 400);
                }
                $_SESSION['user_id'] = (int)$row['id'];
                $initials = strtoupper(substr((string)$row['name'], 0, 2));
                respond(['status' => 'success', 'user' => [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'role' => (string)$row['role'],
                    'avatar' => $initials,
                ]]);
            }
            respond(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }
        case 'logout': {
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            logout();
            respond(['status' => 'success']);
        }
        case 'get_data': {
            $user = require_json_login();

            $data = [
                'appointments' => [],
                'information' => [],
                'alarms' => [],
                'chatMessages' => [],
                'users' => [],
                'emergencyContacts' => [
                    ['id' => 1, 'name' => 'Ambo Referral Hospital', 'phone' => '+251112345678'],
                    ['id' => 2, 'name' => 'Emergency Hotline', 'phone' => '911'],
                ],
            ];

            // Appointments depending on role
            if ($user['role'] === 'mother') {
                $stmt = $pdo->prepare('SELECT a.id, a.appointment_time, a.status, a.notes, p.name AS physician_name FROM appointments a JOIN users p ON p.id=a.physician_id WHERE a.mother_id=? ORDER BY a.appointment_time DESC');
                $stmt->execute([$user['id']]);
            } elseif ($user['role'] === 'physician') {
                $stmt = $pdo->prepare('SELECT a.id, a.appointment_time, a.status, a.notes, m.name AS mother_name FROM appointments a JOIN users m ON m.id=a.mother_id WHERE a.physician_id=? ORDER BY a.appointment_time DESC');
                $stmt->execute([$user['id']]);
            } else {
                $stmt = $pdo->query('SELECT a.id, a.appointment_time, a.status, a.notes FROM appointments a ORDER BY a.appointment_time DESC');
            }
            foreach ($stmt as $row) {
                $dt = new DateTime($row['appointment_time']);
                $title = $row['notes'] !== null && $row['notes'] !== '' ? (string)$row['notes'] : 'Appointment';
                $item = [
                    'id' => (int)$row['id'],
                    'title' => $title,
                    'date' => $dt->format('Y-m-d'),
                    'time' => $dt->format('H:i:s'),
                    'doctor' => isset($row['physician_name']) ? (string)$row['physician_name'] : (isset($row['mother_name']) ? (string)$row['mother_name'] : ''),
                    'status' => ucfirst((string)$row['status']) === 'Pending' ? 'Upcoming' : ucfirst((string)$row['status']),
                ];
                $data['appointments'][] = $item;
            }

            // Information (health records). For SPA, flatten to title/category/content/created_at
            $stmt = $pdo->query('SELECT hr.id, hr.title, hr.content, hr.created_at, u.name AS uploader FROM health_records hr LEFT JOIN users u ON u.id=hr.uploaded_by ORDER BY hr.created_at DESC LIMIT 50');
            foreach ($stmt as $row) {
                $data['information'][] = [
                    'id' => (int)$row['id'],
                    'title' => (string)$row['title'],
                    'category' => 'General',
                    'content' => (string)$row['content'],
                    'created_at' => (string)$row['created_at'],
                    'views' => 0,
                    'uploader' => (string)($row['uploader'] ?? ''),
                ];
            }

            // Alarms for user
            $stmt = $pdo->prepare('SELECT id, title, alarm_time, is_active FROM alarms WHERE user_id=? ORDER BY alarm_time ASC');
            $stmt->execute([$user['id']]);
            foreach ($stmt as $row) {
                $time = (new DateTime($row['alarm_time']))->format('H:i:s');
                $data['alarms'][] = [
                    'id' => (int)$row['id'],
                    'user_id' => (int)$user['id'],
                    'name' => (string)$row['title'],
                    'time' => $time,
                    'active' => (bool)$row['is_active'],
                ];
            }

            // Users for admin
            if ($user['role'] === 'admin') {
                $stmt = $pdo->query('SELECT id, name, email, role, is_active FROM users ORDER BY created_at DESC');
                foreach ($stmt as $row) {
                    $data['users'][] = [
                        'id' => (int)$row['id'],
                        'name' => (string)$row['name'],
                        'email' => (string)$row['email'],
                        'role' => (string)$row['role'],
                        'status' => ((int)$row['is_active'] === 1 ? 'active' : 'inactive'),
                        'last_active' => null,
                    ];
                }
            }

            // Chat messages: provide last few between user and first counterpart if any
            // Default counterpart: if mother -> a physician user; if physician -> a mother user
            $counterpartStmt = null;
            if ($user['role'] === 'mother') {
                $counterpartStmt = $pdo->query("SELECT id, name FROM users WHERE role='physician' AND is_active=1 ORDER BY id ASC LIMIT 1");
            } elseif ($user['role'] === 'physician') {
                $counterpartStmt = $pdo->query("SELECT id, name FROM users WHERE role='mother' AND is_active=1 ORDER BY id ASC LIMIT 1");
            }
            $other = $counterpartStmt ? $counterpartStmt->fetch() : null;
            if ($other) {
                $stmt = $pdo->prepare('SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC LIMIT 50');
                $stmt->execute([$user['id'], (int)$other['id'], (int)$other['id'], $user['id']]);
                foreach ($stmt as $m) {
                    $data['chatMessages'][] = [
                        'id' => (int)$m['id'],
                        'sender' => $m['sender_id'] === $user['id'] ? 'You' : (string)$other['name'],
                        'message' => (string)$m['content'],
                        'time' => (string)$m['created_at'],
                        'sent' => $m['sender_id'] === $user['id'],
                    ];
                }
            }

            respond(['status' => 'success', 'data' => $data]);
        }
        case 'create_appointment': {
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $user = require_json_role(['mother', 'physician']);
            $appointmentTime = trim((string)($_POST['appointment_time'] ?? ''));
            if ($appointmentTime === '') {
                respond(['status' => 'error', 'message' => 'appointment_time is required (YYYY-MM-DD HH:MM:SS)'], 400);
            }
            if ($user['role'] === 'mother') {
                $physicianId = (int)($_POST['physician_id'] ?? 0);
                if ($physicianId <= 0) respond(['status' => 'error', 'message' => 'physician_id is required'], 400);
                $stmt = $pdo->prepare('INSERT INTO appointments (mother_id, physician_id, appointment_time, status) VALUES (?,?,?,"pending")');
                $stmt->execute([$user['id'], $physicianId, $appointmentTime]);
            } else { // physician creating for a mother
                $motherId = (int)($_POST['mother_id'] ?? 0);
                if ($motherId <= 0) respond(['status' => 'error', 'message' => 'mother_id is required'], 400);
                $stmt = $pdo->prepare('INSERT INTO appointments (mother_id, physician_id, appointment_time, status) VALUES (?,?,?,"approved")');
                $stmt->execute([$motherId, $user['id'], $appointmentTime]);
            }
            respond(['status' => 'success']);
        }
        case 'update_appointment': {
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $user = require_json_role(['mother', 'physician']);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) respond(['status' => 'error', 'message' => 'id is required'], 400);
            // Only allow owner side to cancel or physician to update status/notes
            $status = isset($_POST['status']) ? (string)$_POST['status'] : null;
            $notes = isset($_POST['notes']) ? (string)$_POST['notes'] : null;
            if ($user['role'] === 'mother') {
                // mother can only cancel own appointment
                $stmt = $pdo->prepare('UPDATE appointments SET status="cancelled" WHERE id=? AND mother_id=?');
                $stmt->execute([$id, $user['id']]);
            } else {
                // physician can update status and notes for their appointment
                if ($status === null && $notes === null) respond(['status' => 'error', 'message' => 'Nothing to update'], 400);
                $fields = [];$params=[];
                if ($status !== null) { $fields[] = 'status=?'; $params[] = $status; }
                if ($notes !== null) { $fields[] = 'notes=?'; $params[] = $notes; }
                $params[] = $id; $params[] = $user['id'];
                $sql = 'UPDATE appointments SET ' . implode(', ', $fields) . ' WHERE id=? AND physician_id=?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            respond(['status' => 'success']);
        }
        case 'send_message': {
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $user = require_json_role(['mother', 'physician', 'admin']);
            $toId = (int)($_POST['to_id'] ?? $_POST['receiver_id'] ?? 0);
            $content = trim((string)($_POST['content'] ?? $_POST['message'] ?? ''));
            if ($toId <= 0 || $content === '') respond(['status' => 'error', 'message' => 'to_id and content are required'], 400);
            $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content) VALUES (?,?,?)');
            $stmt->execute([$user['id'], $toId, $content]);
            respond(['status' => 'success']);
        }
        case 'list_messages': {
            $user = require_json_role(['mother', 'physician', 'admin']);
            $withId = (int)($_GET['with_id'] ?? $_POST['with_id'] ?? 0);
            if ($withId <= 0) respond(['status' => 'error', 'message' => 'with_id is required'], 400);
            $stmt = $pdo->prepare('SELECT id, sender_id, receiver_id, content, created_at FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC');
            $stmt->execute([$user['id'], $withId, $withId, $user['id']]);
            $rows = $stmt->fetchAll();
            respond(['status' => 'success', 'messages' => $rows]);
        }
        case 'admin_list_users': {
            require_json_role(['admin']);
            $rows = $pdo->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
            respond(['status' => 'success', 'users' => $rows]);
        }
        case 'admin_create_user': {
            require_json_role(['admin']);
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? 'mother');
            if ($name === '' || $email === '' || $password === '' || !in_array($role, ['mother','physician','admin'], true)) {
                respond(['status' => 'error', 'message' => 'Invalid input'], 400);
            }
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
            respond(['status' => 'success', 'id' => (int)$pdo->lastInsertId()]);
        }
        case 'admin_update_user': {
            require_json_role(['admin']);
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) respond(['status' => 'error', 'message' => 'id is required'], 400);
            $name = isset($_POST['name']) ? trim((string)$_POST['name']) : null;
            $email = isset($_POST['email']) ? trim((string)$_POST['email']) : null;
            $role = isset($_POST['role']) ? (string)$_POST['role'] : null;
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : null;
            $fields = [];$params = [];
            if ($name !== null) { $fields[]='name=?'; $params[]=$name; }
            if ($email !== null) { $fields[]='email=?'; $params[]=$email; }
            if ($role !== null && in_array($role, ['mother','physician','admin'], true)) { $fields[]='role=?'; $params[]=$role; }
            if ($isActive !== null) { $fields[]='is_active=?'; $params[]=$isActive ? 1 : 0; }
            if (!$fields) respond(['status' => 'error', 'message' => 'Nothing to update'], 400);
            $params[] = $id;
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at=NOW() WHERE id=?';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            respond(['status' => 'success']);
        }
        case 'admin_delete_user': {
            require_json_role(['admin']);
            if ($method !== 'POST') respond(['status' => 'error', 'message' => 'Invalid method'], 405);
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) respond(['status' => 'error', 'message' => 'id is required'], 400);
            $stmt = $pdo->prepare('DELETE FROM users WHERE id=?');
            $stmt->execute([$id]);
            respond(['status' => 'success']);
        }
        default:
            respond(['status' => 'error', 'message' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    respond(['status' => 'error', 'message' => 'Server error'], 500);
}