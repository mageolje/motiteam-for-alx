<?php
declare(strict_types=1);

// Basic configuration for database connection
// Update these to match your MySQL setup
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'school_portal';
const DB_USER = 'root';
const DB_PASS = '';

// Error reporting (adjust as needed)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session early for all pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns a singleton PDO connection using utf8mb4 and exceptions.
 */
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

/**
 * Simple role list for navigation and checks.
 */
function all_roles(): array {
    return ['record_officer', 'subject_teacher', 'homeroom_teacher', 'admin'];
}