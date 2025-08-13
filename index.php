<?php
// Start session as early as possible
session_start();

// Enable mysqli exceptions for cleaner error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Simple helper to emit JSON and exit
function json_response(array $payload, int $statusCode = 200): void {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// Create DB connection
try {
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPass = '';
    $dbName = 'maternal_health';

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    json_response(['status' => 'error', 'message' => 'Database connection failed'], 500);
}

// Bootstrap schema if needed
try {
    $schemaSql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role ENUM('mother', 'physician', 'admin') NOT NULL,
            email VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_active TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY unique_username (username),
            KEY idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS appointments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            date DATE NOT NULL,
            time TIME NOT NULL,
            doctor VARCHAR(100) NOT NULL,
            status ENUM('Upcoming', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Upcoming',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user (user_id),
            KEY idx_date (date),
            CONSTRAINT fk_appointments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS health_info (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
            content TEXT,
            created_at DATE,
            views INT UNSIGNED DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS medication_alarms (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            time TIME NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_alarm_user (user_id),
            CONSTRAINT fk_alarms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_id INT UNSIGNED NOT NULL,
            receiver_id INT UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            sent TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sender (sender_id),
            KEY idx_receiver (receiver_id),
            CONSTRAINT fk_chat_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_chat_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS emergency_contacts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            UNIQUE KEY unique_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        INSERT IGNORE INTO emergency_contacts (name, phone) VALUES
            ('Ambo Referral Hospital', '+251112345678'),
            ('Emergency Hotline', '911');
    ";

    $conn->multi_query($schemaSql);
    while ($conn->more_results() && $conn->next_result()) {
        if ($tmp = $conn->store_result()) {
            $tmp->free();
        }
    }

    // Seed users and sample data if empty
    $count = 0;
    $res = $conn->query("SELECT COUNT(*) AS c FROM users");
    if ($res) {
        $row = $res->fetch_assoc();
        $count = (int)$row['c'];
        $res->free();
    }

    if ($count === 0) {
        $seedUsers = [
            ['username' => 'mother', 'password' => password_hash('mother123', PASSWORD_DEFAULT), 'name' => 'Mother Abebe', 'role' => 'mother', 'email' => 'mother@example.com'],
            ['username' => 'doctor', 'password' => password_hash('doctor123', PASSWORD_DEFAULT), 'name' => 'Dr. Gurmessa Inkosa', 'role' => 'physician', 'email' => 'doctor@example.com'],
            ['username' => 'admin',  'password' => password_hash('admin123',  PASSWORD_DEFAULT), 'name' => 'Admin User', 'role' => 'admin', 'email' => 'admin@example.com'],
        ];
        $stmt = $conn->prepare("INSERT INTO users (username, password, name, role, email) VALUES (?, ?, ?, ?, ?)");
        foreach ($seedUsers as $u) {
            $stmt->bind_param('sssss', $u['username'], $u['password'], $u['name'], $u['role'], $u['email']);
            $stmt->execute();
        }
        $stmt->close();

        // Get mother's id
        $motherId = null;
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $motherUsername = 'mother';
        $stmt->bind_param('s', $motherUsername);
        $stmt->execute();
        $stmt->bind_result($motherId);
        $stmt->fetch();
        $stmt->close();

        if ($motherId) {
            // Seed appointments
            $appointments = [
                ['user_id' => $motherId, 'title' => 'Prenatal Checkup',       'date' => '2023-08-15', 'time' => '10:00:00', 'doctor' => 'Dr. Kebede', 'status' => 'Upcoming'],
                ['user_id' => $motherId, 'title' => 'Ultrasound Scan',        'date' => '2023-08-22', 'time' => '14:30:00', 'doctor' => 'Dr. Alem',   'status' => 'Upcoming'],
                ['user_id' => $motherId, 'title' => 'Nutrition Consultation', 'date' => '2023-08-05', 'time' => '11:15:00', 'doctor' => 'Dr. Tsehay', 'status' => 'Completed'],
            ];
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, title, date, time, doctor, status) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($appointments as $a) {
                $stmt->bind_param('isssss', $a['user_id'], $a['title'], $a['date'], $a['time'], $a['doctor'], $a['status']);
                $stmt->execute();
            }
            $stmt->close();

            // Seed alarms
            $alarms = [
                ['user_id' => $motherId, 'name' => 'Prenatal Vitamins', 'time' => '08:00:00', 'active' => 1],
                ['user_id' => $motherId, 'name' => 'Iron Supplement',   'time' => '14:00:00', 'active' => 1],
                ['user_id' => $motherId, 'name' => 'Calcium Tablet',    'time' => '21:00:00', 'active' => 0],
            ];
            $stmt = $conn->prepare("INSERT INTO medication_alarms (user_id, name, time, active) VALUES (?, ?, ?, ?)");
            foreach ($alarms as $al) {
                $stmt->bind_param('issi', $al['user_id'], $al['name'], $al['time'], $al['active']);
                $stmt->execute();
            }
            $stmt->close();
        }

        // Seed health info
        $healthInfos = [
            ['title' => 'Nutrition During Pregnancy', 'category' => 'Prenatal Care', 'content' => 'Important nutritional guidelines...', 'created_at' => '2023-08-10'],
            ['title' => 'Exercise Recommendations',    'category' => 'Physical Activity', 'content' => 'Safe exercises during pregnancy...', 'created_at' => '2023-08-05'],
            ['title' => 'Preparing for Delivery',      'category' => 'Delivery', 'content' => 'What to expect and how to prepare...', 'created_at' => '2023-07-28'],
            ['title' => 'Postpartum Care',             'category' => 'After Birth', 'content' => 'Caring for yourself after birth...', 'created_at' => '2023-07-20'],
        ];
        $stmt = $conn->prepare("INSERT INTO health_info (title, category, content, created_at) VALUES (?, ?, ?, ?)");
        foreach ($healthInfos as $hi) {
            $stmt->bind_param('ssss', $hi['title'], $hi['category'], $hi['content'], $hi['created_at']);
            $stmt->execute();
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // If bootstrapping fails, continue to allow HTML to render, but API calls will report the error when used
}

// Action routing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // LOGIN
        if ($action === 'login') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role     = trim((string)($_POST['role'] ?? ''));

            $stmt = $conn->prepare("SELECT id, username, password, name, role FROM users WHERE username = ? AND role = ? LIMIT 1");
            $stmt->bind_param('ss', $username, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'avatar' => strtoupper(substr($user['name'], 0, 2)),
                ];

                $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
                $stmt->bind_param('i', $_SESSION['user']['id']);
                $stmt->execute();
                $stmt->close();

                json_response(['status' => 'success', 'user' => $_SESSION['user']]);
            }

            json_response(['status' => 'error', 'message' => 'Invalid credentials'], 401);
        }

        // LOGOUT
        if ($action === 'logout') {
            session_destroy();
            json_response(['status' => 'success']);
        }

        // REQUIRE AUTH BELOW
        if (!isset($_SESSION['user'])) {
            json_response(['status' => 'error', 'message' => 'Not authenticated'], 401);
        }

        $currentUser = $_SESSION['user'];

        // GET DATA FOR DASHBOARD
        if ($action === 'get_data') {
            $data = [
                'appointments' => [],
                'information' => [],
                'alarms' => [],
                'chatMessages' => [
                    ['id' => 1, 'sender' => 'Dr. Gurmessa', 'message' => 'How are you feeling today?', 'time' => '2023-08-10 10:15', 'sent' => false],
                    ['id' => 2, 'sender' => 'You',          'message' => "I'm feeling better, but still some morning sickness", 'time' => '2023-08-10 10:20', 'sent' => true],
                    ['id' => 3, 'sender' => 'Dr. Bulto',    'message' => "That's normal. Try eating small, frequent meals and ginger tea.", 'time' => '2023-08-10 10:22', 'sent' => false],
                ],
                'users' => [],
                'emergencyContacts' => [],
            ];

            // Appointments for current user
            $stmt = $conn->prepare("SELECT id, user_id, title, date, time, doctor, status, created_at FROM appointments WHERE user_id = ? ORDER BY date DESC, time DESC");
            $stmt->bind_param('i', $currentUser['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data['appointments'][] = $row;
            }
            $stmt->close();

            // Health info
            $result = $conn->query("SELECT id, title, category, content, created_at, views FROM health_info ORDER BY created_at DESC");
            while ($row = $result->fetch_assoc()) {
                $data['information'][] = $row;
            }

            // Alarms for current user
            $stmt = $conn->prepare("SELECT id, user_id, name, time, active, created_at FROM medication_alarms WHERE user_id = ? ORDER BY time ASC");
            $stmt->bind_param('i', $currentUser['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Cast active into boolean for the frontend
                $row['active'] = (int)$row['active'] === 1;
                $data['alarms'][] = $row;
            }
            $stmt->close();

            // Users (admin only)
            if ($currentUser['role'] === 'admin') {
                $result = $conn->query("SELECT id, name, email, username, role, status, last_active FROM users ORDER BY created_at DESC");
                while ($row = $result->fetch_assoc()) {
                    $data['users'][] = $row;
                }
            }

            // Emergency contacts
            $result = $conn->query("SELECT id, name, phone FROM emergency_contacts");
            while ($row = $result->fetch_assoc()) {
                $data['emergencyContacts'][] = $row;
            }

            json_response(['status' => 'success', 'data' => $data]);
        }

        // CREATE USER (admin only)
        if ($action === 'create_user') {
            if ($currentUser['role'] !== 'admin') {
                json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
            }

            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $role = trim((string)($_POST['role'] ?? ''));
            $username = trim((string)($_POST['username'] ?? ''));
            $passwordRaw = (string)($_POST['password'] ?? '');

            if ($name === '' || $email === '' || $role === '' || $username === '' || $passwordRaw === '') {
                json_response(['status' => 'error', 'message' => 'All fields are required'], 400);
            }

            if (!in_array($role, ['mother', 'physician', 'admin'], true)) {
                json_response(['status' => 'error', 'message' => 'Invalid role'], 400);
            }

            // Check duplicate username
            $stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_row() !== null;
            $stmt->close();

            if ($exists) {
                json_response(['status' => 'error', 'message' => 'Username already exists'], 409);
            }

            $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, role, username, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $name, $email, $role, $username, $password);
            $stmt->execute();
            $stmt->close();

            json_response(['status' => 'success', 'message' => 'User created successfully']);
        }

        // UPDATE APPOINTMENT (owner or admin)
        if ($action === 'update_appointment') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $date = trim((string)($_POST['date'] ?? ''));
            $time = trim((string)($_POST['time'] ?? ''));
            $doctor = trim((string)($_POST['doctor'] ?? ''));

            if ($id <= 0 || $title === '' || $date === '' || $time === '' || $doctor === '') {
                json_response(['status' => 'error', 'message' => 'Invalid input'], 400);
            }

            if ($currentUser['role'] === 'admin') {
                $stmt = $conn->prepare("UPDATE appointments SET title = ?, date = ?, time = ?, doctor = ? WHERE id = ?");
                $stmt->bind_param('ssssi', $title, $date, $time, $doctor, $id);
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET title = ?, date = ?, time = ?, doctor = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ssssii', $title, $date, $time, $doctor, $id, $currentUser['id']);
            }

            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                json_response(['status' => 'success', 'message' => 'Appointment updated successfully']);
            }

            json_response(['status' => 'error', 'message' => 'Failed to update appointment'], 400);
        }

        // CANCEL APPOINTMENT (owner or admin)
        if ($action === 'cancel_appointment') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['status' => 'error', 'message' => 'Invalid appointment id'], 400);
            }

            if ($currentUser['role'] === 'admin') {
                $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
                $stmt->bind_param('i', $id);
            } else {
                $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $id, $currentUser['id']);
            }

            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                json_response(['status' => 'success', 'message' => 'Appointment cancelled successfully']);
            }

            json_response(['status' => 'error', 'message' => 'Failed to cancel appointment'], 400);
        }

        // DEACTIVATE USER (admin only)
        if ($action === 'deactivate_user') {
            if ($currentUser['role'] !== 'admin') {
                json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
            }

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                json_response(['status' => 'error', 'message' => 'Invalid user id'], 400);
            }

            $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected > 0) {
                json_response(['status' => 'success', 'message' => 'User deactivated successfully']);
            }

            json_response(['status' => 'error', 'message' => 'Failed to deactivate user'], 400);
        }

        // CREATE APPOINTMENT (owner)
        if ($action === 'create_appointment') {
            $title = trim((string)($_POST['title'] ?? ''));
            $date  = trim((string)($_POST['date'] ?? ''));
            $time  = trim((string)($_POST['time'] ?? ''));
            $doctor= trim((string)($_POST['doctor'] ?? ''));

            if ($title === '' || $date === '' || $time === '' || $doctor === '') {
                json_response(['status' => 'error', 'message' => 'All fields are required'], 400);
            }

            $stmt = $conn->prepare("INSERT INTO appointments (user_id, title, date, time, doctor, status) VALUES (?, ?, ?, ?, ?, 'Upcoming')");
            $stmt->bind_param('issss', $currentUser['id'], $title, $date, $time, $doctor);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            json_response(['status' => 'success', 'id' => $newId]);
        }

        // CREATE ALARM (owner)
        if ($action === 'create_alarm') {
            $name = trim((string)($_POST['name'] ?? ''));
            $time = trim((string)($_POST['time'] ?? ''));
            if ($name === '' || $time === '') {
                json_response(['status' => 'error', 'message' => 'All fields are required'], 400);
            }
            $active = 1;
            $stmt = $conn->prepare("INSERT INTO medication_alarms (user_id, name, time, active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('issi', $currentUser['id'], $name, $time, $active);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            json_response(['status' => 'success', 'id' => $newId]);
        }

        // CREATE HEALTH INFORMATION (physician or admin)
        if ($action === 'create_information') {
            if (!in_array($currentUser['role'], ['physician', 'admin'], true)) {
                json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
            }

            $title = trim((string)($_POST['title'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $content = trim((string)($_POST['content'] ?? ''));

            if ($title === '' || $category === '' || $content === '') {
                json_response(['status' => 'error', 'message' => 'All fields are required'], 400);
            }

            $createdDate = date('Y-m-d');
            $stmt = $conn->prepare("INSERT INTO health_info (title, category, content, created_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $title, $category, $content, $createdDate);
            $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            json_response(['status' => 'success', 'id' => $newId]);
        }

        // Unknown action
        json_response(['status' => 'error', 'message' => 'Unknown action'], 400);
    } catch (Throwable $e) {
        json_response(['status' => 'error', 'message' => 'Server error'], 500);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Maternal Health Advisor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        :root {
            --primary: #4a6fa5;
            --secondary: #6e9de4;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
            --purple: #6f42c1;
            --pink: #e83e8c;
            --teal: #20c997;
            --cyan: #17a2b8;
        }

        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: linear-gradient(135deg, #4a6fa5 0%, #6e9de4 80%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
            box-sizing: border-box;
        }

        .login-card {
            width: 600px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px 10px;
            text-align: center;
        }

        .login-body {
            background-color: white;
            padding: 20px;
        }

        .user-type-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .user-type-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .user-type-card.active {
            border-color: var(--primary);
            background-color: rgba(74, 111, 165, 0.05);
        }

        .user-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding-top: 20px;
            box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li { margin-bottom: 5px; }

        .sidebar-menu a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid white;
        }

        .sidebar-menu i { width: 25px; text-align: center; margin-right: 10px; }

        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .topbar {
            background-color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .page-title { margin: 0; font-weight: 600; color: var(--dark); }

        .user-info { display: flex; align-items: center; }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }

        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); margin-bottom: 20px; border: none; }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }

        .stat-card { text-align: center; padding: 20px; border-radius: 10px; color: white; margin-bottom: 20px; }
        .stat-card i { font-size: 2rem; margin-bottom: 15px; }
        .stat-card .number { font-size: 2rem; font-weight: bold; margin: 10px 0; }
        .stat-card .label { font-size: 0.9rem; opacity: 0.9; }

        .bg-admin { background: linear-gradient(135deg, var(--purple) 0%, #8a63d2 100%); }
        .bg-physician { background: linear-gradient(135deg, var(--info) 0%, #3ab0c3 100%); }
        .bg-mother { background: linear-gradient(135deg, var(--pink) 0%, #ed6ea9 100%); }
        .bg-success { background: linear-gradient(135deg, var(--success) 0%, #3ac47d 100%); }

        .dashboard-section { margin-bottom: 30px; }
        .dashboard-section-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e9ecef; }

        .appointment-card { border-left: 4px solid var(--info); }
        .emergency-card { border-left: 4px solid var(--danger); }
        .info-card { border-left: 4px solid var(--success); }

        .user-list li { padding: 10px 15px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
        .user-list li:last-child { border-bottom: none; }

        .badge-user { background-color: var(--secondary); color: white; }

        .table th { font-weight: 600; color: var(--dark); }

        .form-container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }

        .chat-container { background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); }
        .chat-header { background-color: var(--primary); color: white; padding: 15px 20px; font-weight: 600; }
        .chat-messages { height: 400px; overflow-y: auto; padding: 20px; background-color: #f8f9fa; }
        .message { margin-bottom: 15px; max-width: 80%; }
        .message-sent { margin-left: auto; text-align: right; }
        .message-received { margin-right: auto; }
        .message-content { padding: 10px 15px; border-radius: 18px; display: inline-block; }
        .message-sent .message-content { background-color: var(--primary); color: white; }
        .message-received .message-content { background-color: #e9ecef; color: var(--dark); }
        .message-time { font-size: 0.75rem; color: #6c757d; margin-top: 5px; }
        .chat-input { padding: 15px; background-color: white; border-top: 1px solid #e9ecef; }

        .emergency-btn { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .alarm-card { border-left: 4px solid var(--warning); }
        .alarm-item { padding: 15px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; }
        .alarm-item:last-child { border-bottom: none; }

        .info-item { padding: 15px; border-bottom: 1px solid #e9ecef; }
        .info-item:last-child { border-bottom: none; }
        .info-title { font-weight: 600; margin-bottom: 5px; color: var(--primary); }
        .info-date { font-size: 0.85rem; color: #6c757d; }

        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar .menu-text { display: none; }
            .sidebar-brand span { display: none; }
            .sidebar-menu a { text-align: center; padding: 15px 10px; }
            .sidebar-menu i { margin-right: 0; font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <!-- Login Page -->
    <div id="login-page" class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNTAgMjUwIj48cGF0aCBmaWxsPSIjNGE2ZmE1IiBkPSJNMTI1IDMwTDE4Ny4xNDYgNjUuODU0TDIzNS4zNTUgNDcuNzA3TDE5MC4yMTEgMTI1TDIzNS4zNTUgMjAyLjI5M0wxODcuMTQ2IDE4NC4xNDZMMTI1IDIyMEw2Mi44NTQgMTg0LjE0NkwxNC42NDUgMjAyLjI5M0w1OS43ODkgMTI1TDE0LjY0NSA0Ny43MDdMNjIuODU0IDY1Ljg1NEwxMjUgMzBaIi8+PHBhdGggZmlsbD0iIzZlOWRlNCIgZD0iTTEyNSA4NS4zNTRMMTYxLjE3NyAxMDcuNzQ0TDE4NS4zNTUgOTguMjM3TDE2MS44MDIgMTI1TDE4NS4zNTUgMTUxLjc2M0wxNjEuMTc3IDE0Mi4yNTZMMTI1IDE2NC42NDZMODguODIzIDE0Mi4yNTZMNjQuNjQ1IDE1MS43NjNMODguMTk4IDEyNUw2NC42NDUgOTguMjM3TDg4LjgyMyAxMDcuNzQ0TDEyNSA4NS4zNTRaIi8+PC9zdmc+" alt="Logo" width="80" height="80">
                <h3 class="mt-3">Maternal Health Advisor</h3>
                <p class="text-muted">Ambo University - School of Engineering and Informatics</p>
            </div>
            <div class="login-body">
                <div class="mb-4">
                    <h5>Login to Your Account</h5>
                    <p class="text-muted">Select your user type and enter your credentials</p>
                </div>

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="user-type-card active" data-type="mother">
                            <div class="user-icon">
                                <i class="fas fa-female"></i>
                            </div>
                            <h6>Mother</h6>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="user-type-card" data-type="physician">
                            <div class="user-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h6>Physician</h6>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="user-type-card" data-type="admin">
                            <div class="user-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <h6>Administrator</h6>
                        </div>
                    </div>
                </div>

                <form id="login-form">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" placeholder="Enter your username" />
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" placeholder="Enter your password" />
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" />
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                <div class="mt-3 text-center">
                    <a href="#" class="text-muted">Forgot password?</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Container (Initially hidden) -->
    <div id="dashboard-container" class="dashboard-container d-none">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <span>Maternal Health</span>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="#" class="active" data-page="dashboard">
                        <i class="fas fa-home"></i>
                        <span class="menu-text">Dashboard</span>
                    </a>
                </li>
                <li id="appointment-menu">
                    <a href="#" data-page="appointments">
                        <i class="fas fa-calendar-check"></i>
                        <span class="menu-text">Appointments</span>
                    </a>
                </li>
                <li id="info-menu">
                    <a href="#" data-page="information">
                        <i class="fas fa-info-circle"></i>
                        <span class="menu-text">Health Information</span>
                    </a>
                </li>
                <li id="chat-menu">
                    <a href="#" data-page="chat">
                        <i class="fas fa-comments"></i>
                        <span class="menu-text">Chat with Doctor</span>
                    </a>
                </li>
                <li id="alarm-menu">
                    <a href="#" data-page="alarms">
                        <i class="fas fa-bell"></i>
                        <span class="menu-text">Medication Alarms</span>
                    </a>
                </li>
                <li id="emergency-menu">
                    <a href="#" data-page="emergency">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span class="menu-text">Emergency</span>
                    </a>
                </li>
                <li id="users-menu" class="d-none">
                    <a href="#" data-page="users">
                        <i class="fas fa-users"></i>
                        <span class="menu-text">Manage Users</span>
                    </a>
                </li>
                <li id="upload-menu" class="d-none">
                    <a href="#" data-page="upload">
                        <i class="fas fa-upload"></i>
                        <span class="menu-text">Upload Information</span>
                    </a>
                </li>
                <li>
                    <a href="#" id="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="menu-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h3 class="page-title" id="page-title">Dashboard</h3>
                <div class="user-info">
                    <div class="avatar" id="user-avatar">MA</div>
                    <div>
                        <div id="user-name">Mother Abebe</div>
                        <div class="text-muted small" id="user-role">Mother</div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div id="dashboard-content"></div>
        </div>
    </div>

    <!-- MODALS -->
    <!-- New Appointment Modal -->
    <div class="modal fade" id="appointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="appointmentForm">
                        <div class="mb-3">
                            <label for="appointmentTitle" class="form-label">Appointment Title</label>
                            <input type="text" class="form-control" id="appointmentTitle" required />
                        </div>
                        <div class="mb-3">
                            <label for="appointmentDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="appointmentDate" required />
                        </div>
                        <div class="mb-3">
                            <label for="appointmentTime" class="form-label">Time</label>
                            <input type="time" class="form-control" id="appointmentTime" required />
                        </div>
                        <div class="mb-3">
                            <label for="appointmentDoctor" class="form-label">Doctor</label>
                            <select class="form-select" id="appointmentDoctor" required>
                                <option value="">Select Doctor</option>
                                <option value="Dr. Kebede Sori">Dr. Kebede Sori</option>
                                <option value="Dr. Alem Tsehay">Dr. Alem Tsehay</option>
                                <option value="Dr. Tsehay Wondimu">Dr. Tsehay Wondimu</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="appointmentNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="appointmentNotes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAppointmentBtn">Save Appointment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Information Modal -->
    <div class="modal fade" id="informationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Health Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="informationForm">
                        <div class="mb-3">
                            <label for="infoTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="infoTitle" required />
                        </div>
                        <div class="mb-3">
                            <label for="infoCategory" class="form-label">Category</label>
                            <select class="form-select" id="infoCategory" required>
                                <option value="">Select Category</option>
                                <option value="Prenatal Care">Prenatal Care</option>
                                <option value="Nutrition">Nutrition</option>
                                <option value="Exercise">Exercise</option>
                                <option value="Delivery">Delivery</option>
                                <option value="Postpartum">Postpartum</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="infoContent" class="form-label">Content</label>
                            <textarea class="form-control" id="infoContent" rows="5" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveInformationBtn">Save Information</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Alarm Modal -->
    <div class="modal fade" id="alarmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Medication Alarm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="alarmForm">
                        <div class="mb-3">
                            <label for="alarmName" class="form-label">Medication Name</label>
                            <input type="text" class="form-control" id="alarmName" required />
                        </div>
                        <div class="mb-3">
                            <label for="alarmTime" class="form-label">Time</label>
                            <input type="time" class="form-control" id="alarmTime" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Repeat</label>
                            <div class="d-flex flex-wrap">
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="mon" checked />
                                    <label class="form-check-label" for="mon">Mon</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="tue" checked />
                                    <label class="form-check-label" for="tue">Tue</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="wed" checked />
                                    <label class="form-check-label" for="wed">Wed</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="thu" checked />
                                    <label class="form-check-label" for="thu">Thu</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="fri" checked />
                                    <label class="form-check-label" for="fri">Fri</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="sat" />
                                    <label class="form-check-label" for="sat">Sat</label>
                                </div>
                                <div class="form-check me-3 mb-2">
                                    <input class="form-check-input" type="checkbox" id="sun" />
                                    <label class="form-check-label" for="sun">Sun</label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="alarmNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="alarmNotes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveAlarmBtn">Save Alarm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="userForm">
                        <div class="mb-3">
                            <label for="userName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="userName" required />
                        </div>
                        <div class="mb-3">
                            <label for="userEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="userEmail" required />
                        </div>
                        <div class="mb-3">
                            <label for="userRole" class="form-label">Role</label>
                            <select class="form-select" id="userRole" required>
                                <option value="">Select Role</option>
                                <option value="mother">Mother</option>
                                <option value="physician">Physician</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="userUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="userUsername" required />
                        </div>
                        <div class="mb-3">
                            <label for="userPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="userPassword" required />
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveUserBtn">Create User</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Current user state
        let currentUser = null;
        let appData = {
            appointments: [],
            information: [],
            alarms: [],
            chatMessages: [],
            users: [],
            emergencyContacts: []
        };

        // DOM Elements
        const loginPage = document.getElementById('login-page');
        const dashboardContainer = document.getElementById('dashboard-container');
        const dashboardContent = document.getElementById('dashboard-content');
        const pageTitle = document.getElementById('page-title');
        const userAvatar = document.getElementById('user-avatar');
        const userName = document.getElementById('user-name');
        const userRole = document.getElementById('user-role');
        const loginForm = document.getElementById('login-form');
        const userTypeCards = document.querySelectorAll('.user-type-card');
        const logoutBtn = document.getElementById('logout-btn');
        const menuItems = document.querySelectorAll('.sidebar-menu a');
        const usersMenu = document.getElementById('users-menu');
        const uploadMenu = document.getElementById('upload-menu');

        // Initialize Bootstrap Modals
        const appointmentModal = new bootstrap.Modal(document.getElementById('appointmentModal'));
        const informationModal = new bootstrap.Modal(document.getElementById('informationModal'));
        const alarmModal = new bootstrap.Modal(document.getElementById('alarmModal'));
        const userModal = new bootstrap.Modal(document.getElementById('userModal'));

        // Set active user type
        userTypeCards.forEach(card => {
            card.addEventListener('click', () => {
                userTypeCards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            });
        });

        // Login form submission
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const selectedType = document.querySelector('.user-type-card.active').dataset.type;

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'login', username, password, role: selectedType })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    currentUser = data.user;
                    userAvatar.textContent = currentUser.avatar;
                    userName.textContent = currentUser.name;
                    userRole.textContent = currentUser.role.charAt(0).toUpperCase() + currentUser.role.slice(1);

                    if (currentUser.role === 'admin') {
                        usersMenu.classList.remove('d-none');
                        uploadMenu.classList.add('d-none');
                    } else if (currentUser.role === 'physician') {
                        usersMenu.classList.add('d-none');
                        uploadMenu.classList.remove('d-none');
                    } else {
                        usersMenu.classList.add('d-none');
                        uploadMenu.classList.add('d-none');
                    }

                    loginPage.classList.add('d-none');
                    dashboardContainer.classList.remove('d-none');

                    await loadAppData();
                    loadDashboard();
                } else {
                    alert(data.message || 'Invalid credentials');
                }
            } catch (err) {
                console.error(err);
                alert('An error occurred during login');
            }
        });

        // Logout
        logoutBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'logout' })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    dashboardContainer.classList.add('d-none');
                    loginPage.classList.remove('d-none');
                    loginForm.reset();
                }
            } catch (err) { console.error(err); }
        });

        // Menu navigation
        menuItems.forEach(item => {
            if (item.id !== 'logout-btn') {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    menuItems.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                    const page = item.dataset.page;
                    pageTitle.textContent = getPageTitle(page);

                    if (page === 'dashboard')      loadDashboard();
                    else if (page === 'appointments') loadAppointments();
                    else if (page === 'information')  loadInformation();
                    else if (page === 'chat')         loadChat();
                    else if (page === 'alarms')       loadAlarms();
                    else if (page === 'emergency')    loadEmergency();
                    else if (page === 'users')        loadUsers();
                    else if (page === 'upload')       loadUpload();
                });
            }
        });

        function getPageTitle(page) {
            const titles = {
                dashboard: 'Dashboard',
                appointments: 'Appointments',
                information: 'Health Information',
                chat: 'Chat with Doctor',
                alarms: 'Medication Alarms',
                emergency: 'Emergency',
                users: 'Manage Users',
                upload: 'Upload Information'
            };
            return titles[page] || 'Dashboard';
        }

        async function loadAppData() {
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'get_data' })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    appData = data.data;
                } else {
                    console.error('Error loading data:', data.message);
                }
            } catch (err) { console.error(err); }
        }

        function loadDashboard() {
            let content = '';
            if (currentUser.role === 'mother') {
                const upcomingAppointments = appData.appointments.filter(a => a.status === 'Upcoming');
                content = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card bg-mother">
                                <i class="fas fa-calendar-check"></i>
                                <div class="number">${upcomingAppointments.length}</div>
                                <div class="label">Upcoming Appointments</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success">
                                <i class="fas fa-info-circle"></i>
                                <div class="number">${appData.information.length}</div>
                                <div class="label">Health Resources</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info">
                                <i class="fas fa-bell"></i>
                                <div class="number">${appData.alarms.filter(a => a.active).length}</div>
                                <div class="label">Active Alarms</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning">
                                <i class="fas fa-comments"></i>
                                <div class="number">3</div>
                                <div class="label">Unread Messages</div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-section">
                        <h5 class="dashboard-section-title">Upcoming Appointments</h5>
                        <div class="card appointment-card">
                            <div class="card-body">
                                ${upcomingAppointments.slice(0, 2).map(appointment => `
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6>${appointment.title}</h6>
                                            <div class="text-muted">${appointment.date} at ${appointment.time}</div>
                                            <div>With ${appointment.doctor}</div>
                                        </div>
                                        <span class="badge bg-info">${appointment.status}</span>
                                    </div>
                                `).join('')}
                                <div class="text-center mt-3">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-page="appointments">View All Appointments</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="dashboard-section">
                                <h5 class="dashboard-section-title">Latest Health Information</h5>
                                <div class="card info-card">
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            ${appData.information.slice(0, 3).map(info => `
                                                <li class="info-item">
                                                    <div class="info-title">${info.title}</div>
                                                    <div class="text-muted">${info.category}</div>
                                                    <div class="info-date">Posted on ${info.created_at}</div>
                                                </li>
                                            `).join('')}
                                        </ul>
                                        <div class="text-center mt-3">
                                            <a href="#" class="btn btn-sm btn-outline-success" data-page="information">View All Information</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="dashboard-section">
                                <h5 class="dashboard-section-title">Active Medication Alarms</h5>
                                <div class="card alarm-card">
                                    <div class="card-body">
                                        <ul class="list-unstyled">
                                            ${appData.alarms.filter(a => a.active).map(alarm => `
                                                <li class="alarm-item">
                                                    <div>
                                                        <h6>${alarm.name}</h6>
                                                        <div class="text-muted">Every day at ${alarm.time}</div>
                                                    </div>
                                                    <div>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" ${alarm.active ? 'checked' : ''} />
                                                        </div>
                                                        <div class="mt-2">
                                                            <button class="btn btn-sm btn-outline-warning" disabled>Edit</button>
                                                            <button class="btn btn-sm btn-outline-danger" disabled>Delete</button>
                                                        </div>
                                                    </div>
                                                </li>
                                            `).join('')}
                                        </ul>
                                        <div class="text-center mt-3">
                                            <a href="#" class="btn btn-sm btn-outline-warning" data-page="alarms">Manage Alarms</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (currentUser.role === 'physician') {
                content = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card bg-physician">
                                <i class="fas fa-calendar-check"></i>
                                <div class="number">12</div>
                                <div class="label">Appointments Today</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success">
                                <i class="fas fa-users"></i>
                                <div class="number">42</div>
                                <div class="label">Active Patients</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info">
                                <i class="fas fa-file-medical"></i>
                                <div class="number">${appData.information.length}</div>
                                <div class="label">Health Resources</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning">
                                <i class="fas fa-comments"></i>
                                <div class="number">5</div>
                                <div class="label">Unread Messages</div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-section">
                        <h5 class="dashboard-section-title">Today's Appointments</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Patient</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>09:00 AM</td>
                                                <td>Mother Abebe</td>
                                                <td>Prenatal Checkup</td>
                                                <td><span class="badge bg-success">Confirmed</span></td>
                                            </tr>
                                            <tr>
                                                <td>10:30 AM</td>
                                                <td>Mother Alem</td>
                                                <td>Ultrasound</td>
                                                <td><span class="badge bg-warning">Pending</span></td>
                                            </tr>
                                            <tr>
                                                <td>02:00 PM</td>
                                                <td>Mother Tsehay</td>
                                                <td>Nutrition Consultation</td>
                                                <td><span class="badge bg-success">Confirmed</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-section">
                        <h5 class="dashboard-section-title">Recent Messages</h5>
                        <div class="card">
                            <div class="card-body">
                                <ul class="list-unstyled user-list">
                                    <li>
                                        <div>
                                            <h6>Mother Abebe</h6>
                                            <div class="text-muted small">Morning sickness has improved but still...</div>
                                        </div>
                                        <div class="text-muted small">2 hours ago</div>
                                    </li>
                                    <li>
                                        <div>
                                            <h6>Mother Alem</h6>
                                            <div class="text-muted small">Can I schedule an appointment for next week?</div>
                                        </div>
                                        <div class="text-muted small">Yesterday</div>
                                    </li>
                                    <li>
                                        <div>
                                            <h6>Mother Tsehay</h6>
                                            <div class="text-muted small">Thank you for the nutrition advice!</div>
                                        </div>
                                        <div class="text-muted small">2 days ago</div>
                                    </li>
                                </ul>
                                <div class="text-center mt-3">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-page="chat">View All Messages</a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (currentUser.role === 'admin') {
                content = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card bg-admin">
                                <i class="fas fa-users"></i>
                                <div class="number">${appData.users.length}</div>
                                <div class="label">Total Users</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-physician">
                                <i class="fas fa-user-md"></i>
                                <div class="number">${appData.users.filter(u => u.role === 'physician').length}</div>
                                <div class="label">Physicians</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-mother">
                                <i class="fas fa-female"></i>
                                <div class="number">${appData.users.filter(u => u.role === 'mother').length}</div>
                                <div class="label">Mothers</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info">
                                <i class="fas fa-file-medical"></i>
                                <div class="number">${appData.information.length}</div>
                                <div class="label">Health Resources</div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-section">
                        <h5 class="dashboard-section-title">System Overview</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Recent Activity</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-user-plus text-success me-2"></i> New physician registered: Dr. Tsehay</li>
                                            <li class="mb-2"><i class="fas fa-file-upload text-info me-2"></i> New health resource uploaded</li>
                                            <li class="mb-2"><i class="fas fa-calendar-plus text-primary me-2"></i> 5 new appointments scheduled</li>
                                            <li class="mb-2"><i class="fas fa-comment-medical text-warning me-2"></i> 12 new chat messages</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>System Status</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-server text-success me-2"></i> Database: <span class="text-success">Operational</span></li>
                                            <li class="mb-2"><i class="fas fa-plug text-success me-2"></i> API Services: <span class="text-success">Operational</span></li>
                                            <li class="mb-2"><i class="fas fa-shield-alt text-success me-2"></i> Security: <span class="text-success">Active</span></li>
                                            <li class="mb-2"><i class="fas fa-sync-alt text-info me-2"></i> Last Backup: 2023-08-10 02:00 AM</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-section">
                        <h5 class="dashboard-section-title">Recent Users</h5>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Last Active</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${appData.users.slice(0, 3).map(user => `
                                                <tr>
                                                    <td>${user.name}</td>
                                                    <td>${user.email || 'N/A'}</td>
                                                    <td><span class="badge badge-user">${user.role}</span></td>
                                                    <td><span class="badge ${user.status === 'active' ? 'bg-success' : 'bg-danger'}">${user.status}</span></td>
                                                    <td>${user.last_active || 'N/A'}</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger deactivate-user" data-id="${user.id}"><i class="fas fa-user-slash"></i></button>
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="#" class="btn btn-sm btn-outline-primary" data-page="users">Manage All Users</a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            dashboardContent.innerHTML = content;
            attachPageLinks();
            document.querySelectorAll('.deactivate-user').forEach(btn => {
                btn.addEventListener('click', () => {
                    const userId = btn.dataset.id;
                    if (confirm('Are you sure you want to deactivate this user?')) {
                        deactivateUser(userId);
                    }
                });
            });
        }

        function loadAppointments() {
            const content = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Appointment Management</h4>
                    <button class="btn btn-primary" id="new-appointment-btn">
                        <i class="fas fa-plus me-2"></i> New Appointment
                    </button>
                </div>

                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item"><a class="nav-link active" href="#">Upcoming</a></li>
                            <li class="nav-item"><a class="nav-link" href="#">Completed</a></li>
                            <li class="nav-item"><a class="nav-link" href="#">Cancelled</a></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>${currentUser.role === 'mother' ? 'Doctor' : 'Patient'}</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${appData.appointments.map(appointment => `
                                        <tr>
                                            <td>
                                                <div>${appointment.date}</div>
                                                <div class="text-muted small">${appointment.time}</div>
                                            </td>
                                            <td>${currentUser.role === 'mother' ? appointment.doctor : 'Mother Abebe'}</td>
                                            <td>${appointment.title}</td>
                                            <td>
                                                <span class="badge ${appointment.status === 'Upcoming' ? 'bg-info' : appointment.status === 'Completed' ? 'bg-success' : 'bg-danger'}">${appointment.status}</span>
                                            </td>
                                            <td>
                                                ${appointment.status === 'Upcoming' ? `
                                                    <button class="btn btn-sm btn-outline-danger cancel-appointment" data-id="${appointment.id}">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            dashboardContent.innerHTML = content;
            attachPageLinks();

            // Cancel handler
            document.querySelectorAll('.cancel-appointment').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const appointmentId = btn.dataset.id;
                    if (confirm('Are you sure you want to cancel this appointment?')) {
                        await cancelAppointment(appointmentId);
                    }
                });
            });

            document.getElementById('new-appointment-btn').addEventListener('click', () => {
                document.getElementById('appointmentForm').reset();
                appointmentModal.show();
            });
        }

        function loadInformation() {
            const content = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Health Information</h4>
                    ${currentUser.role !== 'mother' ? `
                        <button class="btn btn-primary" id="new-info-btn">
                            <i class="fas fa-plus me-2"></i> New Information
                        </button>
                    ` : ''}
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search information..." />
                            <button class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select">
                            <option>All Categories</option>
                            <option>Prenatal Care</option>
                            <option>Nutrition</option>
                            <option>Exercise</option>
                            <option>Delivery</option>
                            <option>Postpartum</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select">
                            <option>Sort by Newest</option>
                            <option>Sort by Oldest</option>
                            <option>Sort by Title</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    ${appData.information.map(info => `
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5>${info.title}</h5>
                                        <span class="badge bg-info">${info.category}</span>
                                    </div>
                                    <p class="text-muted">${(info.content || '').substring(0, 100)}...</p>
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div class="text-muted small">Posted on ${info.created_at}</div>
                                        <div>
                                            <button class="btn btn-sm btn-outline-primary">Read More</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </nav>
            `;

            dashboardContent.innerHTML = content;
            attachPageLinks();

            const newInfoBtn = document.getElementById('new-info-btn');
            if (newInfoBtn) {
                newInfoBtn.addEventListener('click', () => {
                    document.getElementById('informationForm').reset();
                    informationModal.show();
                });
            }
        }

        function loadChat() {
            const content = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Chat with ${currentUser.role === 'mother' ? 'Doctor' : 'Patient'}</h4>
                    <div class="d-flex">
                        <div class="me-2">
                            <select class="form-select">
                                <option>Dr. Gurmessa Inkosa</option>
                                <option>Dr. Alem Tsehay</option>
                                ${currentUser.role !== 'mother' ? '<option>Mother Abebe</option>' : ''}
                            </select>
                        </div>
                        <button class="btn btn-outline-danger">
                            <i class="fas fa-phone-alt me-2"></i> Voice Call
                        </button>
                    </div>
                </div>

                <div class="chat-container">
                    <div class="chat-header">
                        Chat with Dr. Kebede Sori
                    </div>
                    <div class="chat-messages" id="chat-messages">
                        ${appData.chatMessages.map(msg => `
                            <div class="message ${msg.sent ? 'message-sent' : 'message-received'}">
                                <div class="message-content">${msg.message}</div>
                                <div class="message-time">${msg.time}</div>
                            </div>
                        `).join('')}
                    </div>
                    <div class="chat-input">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Type your message..." />
                            <button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                        </div>
                    </div>
                </div>
            `;

            dashboardContent.innerHTML = content;
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function loadAlarms() {
            const content = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Medication Alarms</h4>
                    <button class="btn btn-primary" id="new-alarm-btn">
                        <i class="fas fa-plus me-2"></i> New Alarm
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <ul class="list-unstyled">
                            ${appData.alarms.map(alarm => `
                                <li class="alarm-item">
                                    <div>
                                        <h6>${alarm.name}</h6>
                                        <div class="text-muted">Every day at ${alarm.time}</div>
                                    </div>
                                    <div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" ${alarm.active ? 'checked' : ''} />
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-warning" disabled>Edit</button>
                                            <button class="btn btn-sm btn-outline-danger" disabled>Delete</button>
                                        </div>
                                    </div>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
            `;

            dashboardContent.innerHTML = content;
            document.getElementById('new-alarm-btn').addEventListener('click', () => {
                document.getElementById('alarmForm').reset();
                alarmModal.show();
            });
        }

        function loadEmergency() {
            const content = `
                <div class="row">
                    <div class="col-md-8 mx-auto">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white"><h4 class="mb-0">Emergency Assistance</h4></div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                                    <h3>Need Immediate Help?</h3>
                                    <p class="text-muted">Use this emergency feature to notify your physician immediately</p>
                                </div>
                                <div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>Please only use this in case of real emergencies</div>
                                <div class="mb-4">
                                    <label class="form-label">Describe your emergency</label>
                                    <textarea class="form-control" rows="4" placeholder="Please describe your symptoms or emergency situation..."></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Attach photo (optional)</label>
                                    <input type="file" class="form-control" />
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Your current location</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Loading your location..." value="Ambo, Ethiopia" readonly />
                                        <button class="btn btn-outline-secondary" type="button"><i class="fas fa-location-arrow"></i> Update Location</button>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-danger btn-lg emergency-btn" type="button"><i class="fas fa-bell me-2"></i> Send Emergency Alert</button>
                                </div>
                                <div class="mt-4 text-center">
                                    <h5>Emergency Contacts</h5>
                                    <div class="row mt-3">
                                        ${appData.emergencyContacts.map(contact => `
                                            <div class="col-md-6 mb-3">
                                                <div class="card"><div class="card-body">
                                                    <h6>${contact.name}</h6>
                                                    <div class="text-muted">${contact.phone}</div>
                                                </div></div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            dashboardContent.innerHTML = content;
        }

        function loadUsers() {
            const content = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>User Management</h4>
                    <button class="btn btn-primary" id="new-user-btn">
                        <i class="fas fa-plus me-2"></i> New User
                    </button>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Active</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${appData.users.map(user => `
                                        <tr>
                                            <td>${user.name}</td>
                                            <td>${user.email || 'N/A'}</td>
                                            <td>${user.username || 'N/A'}</td>
                                            <td><span class="badge badge-user">${user.role}</span></td>
                                            <td><span class="badge ${user.status === 'active' ? 'bg-success' : 'bg-danger'}">${user.status}</span></td>
                                            <td>${user.last_active || 'N/A'}</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger deactivate-user" data-id="${user.id}"><i class="fas fa-user-slash"></i></button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            dashboardContent.innerHTML = content;

            document.querySelectorAll('.deactivate-user').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const userId = btn.dataset.id;
                    if (confirm('Are you sure you want to deactivate this user?')) {
                        await deactivateUser(userId);
                        await loadAppData();
                        loadUsers();
                    }
                });
            });

            document.getElementById('new-user-btn').addEventListener('click', () => {
                document.getElementById('userForm').reset();
                userModal.show();
            });
        }

        function loadUpload() {
            const content = `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>Upload Health Information</h4>
                </div>
                <div class="card">
                    <div class="card-body">
                        <form id="uploadInfoForm">
                            <div class="mb-3"><label class="form-label">Title</label><input type="text" class="form-control" id="uploadInfoTitle" placeholder="Enter information title" /></div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="uploadInfoCategory">
                                    <option value="">Select category</option>
                                    <option>Prenatal Care</option>
                                    <option>Nutrition</option>
                                    <option>Exercise</option>
                                    <option>Delivery</option>
                                    <option>Postpartum</option>
                                    <option>Family Planning</option>
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label">Content</label><textarea class="form-control" id="uploadInfoContent" rows="6" placeholder="Enter health information content..."></textarea></div>
                            <div class="mb-3"><label class="form-label">Attachments</label><input type="file" class="form-control" disabled /></div>
                            <div class="row mb-3">
                                <div class="col-md-6"><label class="form-label">Language</label><select class="form-select"><option>Afaan Oromo</option><option>Amharic</option><option>English</option></select></div>
                                <div class="col-md-6"><label class="form-label">Target Audience</label><select class="form-select"><option>All Mothers</option><option>First Trimester</option><option>Second Trimester</option><option>Third Trimester</option><option>Postpartum</option></select></div>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload Information</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">Recently Uploaded</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Date</th>
                                        <th>Views</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${appData.information.slice(0, 3).map(info => `
                                        <tr>
                                            <td>${info.title}</td>
                                            <td>${info.category}</td>
                                            <td>${info.created_at}</td>
                                            <td>${info.views}</td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" disabled>View</button>
                                                <button class="btn btn-sm btn-outline-warning" disabled>Edit</button>
                                                <button class="btn btn-sm btn-outline-danger" disabled>Delete</button>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            dashboardContent.innerHTML = content;

            document.getElementById('uploadInfoForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const title = document.getElementById('uploadInfoTitle').value.trim();
                const category = document.getElementById('uploadInfoCategory').value.trim();
                const content = document.getElementById('uploadInfoContent').value.trim();
                if (!title || !category || !content) {
                    alert('Please fill in all required fields');
                    return;
                }
                try {
                    const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'create_information', title, category, content })
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        alert('Information uploaded successfully!');
                        await loadAppData();
                        loadUpload();
                    } else {
                        alert(data.message || 'Failed to upload information');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Failed to upload information');
                }
            });
        }

        function attachPageLinks() {
            document.querySelectorAll('[data-page]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = link.dataset.page;
                    document.querySelectorAll('.sidebar-menu a').forEach(menuItem => {
                        if (menuItem.dataset.page === page) menuItem.click();
                    });
                });
            });
        }

        // API helpers
        async function cancelAppointment(appointmentId) {
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'cancel_appointment', id: appointmentId })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alert('Appointment cancelled successfully!');
                    await loadAppData();
                    loadAppointments();
                } else {
                    alert(data.message || 'Failed to cancel appointment');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to cancel appointment');
            }
        }

        async function deactivateUser(userId) {
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'deactivate_user', id: userId })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alert('User deactivated successfully!');
                } else {
                    alert(data.message || 'Failed to deactivate user');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to deactivate user');
            }
        }

        // Create flows
        document.getElementById('saveAppointmentBtn').addEventListener('click', async () => {
            const title = document.getElementById('appointmentTitle').value.trim();
            const date = document.getElementById('appointmentDate').value;
            const time = document.getElementById('appointmentTime').value;
            const doctor = document.getElementById('appointmentDoctor').value;

            if (!title || !date || !time || !doctor) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'create_appointment', title, date, time, doctor })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    appointmentModal.hide();
                    alert('Appointment created successfully!');
                    await loadAppData();
                    loadAppointments();
                } else {
                    alert(data.message || 'Failed to create appointment');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to create appointment');
            }
        });

        document.getElementById('saveInformationBtn').addEventListener('click', async () => {
            const title = document.getElementById('infoTitle').value.trim();
            const category = document.getElementById('infoCategory').value.trim();
            const content = document.getElementById('infoContent').value.trim();

            if (!title || !category || !content) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'create_information', title, category, content })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    informationModal.hide();
                    alert('Health information created successfully!');
                    await loadAppData();
                    loadInformation();
                } else {
                    alert(data.message || 'Failed to create health information');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to create health information');
            }
        });

        document.getElementById('saveAlarmBtn').addEventListener('click', async () => {
            const name = document.getElementById('alarmName').value.trim();
            const time = document.getElementById('alarmTime').value;

            if (!name || !time) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'create_alarm', name, time })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alarmModal.hide();
                    alert('Medication alarm created successfully!');
                    await loadAppData();
                    loadAlarms();
                } else {
                    alert(data.message || 'Failed to create alarm');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to create alarm');
            }
        });

        document.getElementById('saveUserBtn').addEventListener('click', async () => {
            const name = document.getElementById('userName').value.trim();
            const email = document.getElementById('userEmail').value.trim();
            const role = document.getElementById('userRole').value.trim();
            const username = document.getElementById('userUsername').value.trim();
            const password = document.getElementById('userPassword').value;

            if (!name || !email || !role || !username || !password) {
                alert('Please fill in all required fields');
                return;
            }

            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'create_user', name, email, role, username, password })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    userModal.hide();
                    alert('User created successfully!');
                    await loadAppData();
                    loadUsers();
                } else {
                    alert(data.message || 'Failed to create user');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to create user');
            }
        });

        // Initialize
        attachPageLinks();
    </script>
</body>
</html>