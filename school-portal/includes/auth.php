<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('/index.php');
    }
}

/**
 * @param string|array $roles
 */
function require_role(string|array $roles): void {
    require_login();
    $user = current_user();
    $allowed = (array)$roles;
    if (!$user || !in_array($user['role'], $allowed, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function login(string $username, string $password): bool {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, full_name, role, password_hash FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}