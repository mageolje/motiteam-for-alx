<?php

require_once __DIR__ . '/Database.php';

class Auth
{
    public static function startSession(): void
    {
        $configPath = __DIR__ . '/../config/config.php';
        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/../config/config.sample.php';
        }
        $config = require $configPath;
        if (session_status() === PHP_SESSION_NONE) {
            session_name($config['app']['session_name']);
            session_start();
        }
    }

    public static function login(string $email, string $password): bool
    {
        self::startSession();
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function register(string $name, string $email, string $password, string $role = 'mother'): array
    {
        $pdo = Database::getConnection();
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email'; }
        if (strlen($password) < 6) { $errors[] = 'Password too short'; }
        if (!in_array($role, ['mother','physician','admin'], true)) { $errors[] = 'Invalid role'; }
        if ($errors) { return ['ok' => false, 'errors' => $errors]; }
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
            return ['ok' => true];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'errors' => ['Email already exists']];
            }
            return ['ok' => false, 'errors' => ['Registration failed']];
        }
    }

    public static function requireLogin(): void
    {
        self::startSession();
        if (!isset($_SESSION['user'])) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $user = self::user();
        if (!$user || !in_array($user['role'], $roles, true)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}