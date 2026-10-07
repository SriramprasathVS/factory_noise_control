<?php
/**
 * Simple Authentication Helper (auth.php)
 * 1 Admin and 2 User Accounts
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1 Admin and 2 User Accounts
const SYSTEM_USERS = [
    'admin' => [
        'username' => 'admin',
        'password' => 'admin123',
        'name'     => 'Plant Manager (Admin)',
        'role'     => 'admin',
        'badge'    => 'ADMIN'
    ],
    'user1' => [
        'username' => 'user1',
        'password' => 'user123',
        'name'     => 'Tech Alex (Acoustic Tech)',
        'role'     => 'user',
        'badge'    => 'USER 1'
    ],
    'user2' => [
        'username' => 'user2',
        'password' => 'user123',
        'name'     => 'Sarah (Safety Operator)',
        'role'     => 'user',
        'badge'    => 'USER 2'
    ]
];

function isLoggedIn(): bool {
    return !empty($_SESSION['user']);
}

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function isAdmin(): bool {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Administrator access required.']);
        exit;
    }
}
