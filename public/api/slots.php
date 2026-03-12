<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$doctorId = (int)($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$doctorId || !$date) {
    echo json_encode(['success' => false, 'message' => 'doctor_id and date are required']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

$doctor = getDoctor($pdo, $doctorId);
if (!$doctor) {
    echo json_encode(['success' => false, 'message' => 'Doctor not found']);
    exit;
}

$slots = getAvailableSlots($pdo, $doctorId, $date);
$settings = getSettings($pdo);
$currency = $settings['currency_symbol'] ?? '₹';

echo json_encode([
    'success' => true,
    'doctor' => [
        'doctor_id' => $doctor['doctor_id'],
        'name' => $doctor['name'],
        'photo' => $doctor['photo'] ?? '',
        'specialization' => $doctor['specialization'] ?? '',
        'experience' => $doctor['experience'] ?? '',
        'qualification' => $doctor['qualification'] ?? '',
        'department_name' => $doctor['department_name'] ?? '',
        'consultation_fee' => (float)($doctor['consultation_fee'] ?? 500),
        'clinic_address' => $doctor['clinic_address'] ?? '',
        'consultation_types' => $doctor['consultation_types'] ?? 'both'
    ],
    'date' => $date,
    'sessions' => $slots,
    'currency' => $currency
]);
