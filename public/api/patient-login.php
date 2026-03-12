<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$csrfToken = $input['csrf_token'] ?? '';
if (!verifyCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$action = $input['action'] ?? 'login';

if ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter both email and password.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT patient_id, name, email, password FROM patients WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $patient = $stmt->fetch();

    if ($patient && password_verify($password, $patient['password'])) {
        $_SESSION['patient_id'] = $patient['patient_id'];
        $_SESSION['patient_name'] = $patient['name'];
        $_SESSION['patient_email'] = $patient['email'];

        $pdo->prepare("UPDATE patients SET last_login = CURRENT_TIMESTAMP WHERE patient_id = :id")
            ->execute([':id' => $patient['patient_id']]);

        echo json_encode(['success' => true, 'message' => 'Login successful! Redirecting...', 'redirect' => '/public/patient-dashboard.php']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    }
    exit;
}

if ($action === 'register') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $existing = $pdo->prepare("SELECT patient_id FROM patients WHERE email = :email");
    $existing->execute([':email' => $email]);
    if ($existing->fetch()) {
        echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO patients (name, email, phone, password) VALUES (:name, :email, :phone, :password) RETURNING patient_id");
    $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':password' => $hash]);
    $newPatient = $stmt->fetch();

    $_SESSION['patient_id'] = $newPatient['patient_id'];
    $_SESSION['patient_name'] = $name;
    $_SESSION['patient_email'] = $email;

    echo json_encode(['success' => true, 'message' => 'Account created successfully! Redirecting...', 'redirect' => '/public/patient-dashboard.php']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
