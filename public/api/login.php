<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$csrfToken = $input['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter both username and password']);
    exit;
}

if (loginAdmin($pdo, $username, $password)) {
    echo json_encode([
        'success' => true,
        'message' => 'Login successful! Redirecting...',
        'redirect' => '/admin/'
    ]);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}
