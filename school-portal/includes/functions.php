<?php
declare(strict_types=1);

/** HTML escape */
function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** CSRF token helpers */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)$sent)) {
            http_response_code(400);
            exit('Invalid CSRF token.');
        }
    }
}

/** Redirect helper */
function redirect(string $path): never {
    header('Location: ' . $path);
    exit;
}

/** Date helper */
function today_date(): string {
    return (new DateTime('now'))->format('Y-m-d');
}

/** Grade mapping helper */
function grade_from_percentage(float $percentage): string {
    if ($percentage >= 85) return 'A';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 55) return 'C';
    if ($percentage >= 40) return 'D';
    return 'E';
}