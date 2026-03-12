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

if (!verifyCSRFToken($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid form submission. Please refresh and try again.']);
    exit;
}

$name    = trim($input['patient_name'] ?? '');
$email   = trim($input['email'] ?? '');
$phone   = trim($input['phone'] ?? '');
$deptId  = (int)($input['department_id'] ?? 0);
$docId   = (int)($input['doctor_id'] ?? 0);
$date    = trim($input['appointment_date'] ?? '');
$time    = trim($input['appointment_time'] ?? '');
$message = trim($input['message'] ?? '');

if (empty($name) || empty($email) || empty($phone) || empty($date) || empty($time)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$parsedDate = DateTime::createFromFormat('Y-m-d', $date);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment date format.']);
    exit;
}

$parsedTime = DateTime::createFromFormat('H:i', $time) ?: DateTime::createFromFormat('H:i:s', $time);
if (!$parsedTime) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment time format.']);
    exit;
}
$time = $parsedTime->format('H:i:s');

if ($parsedDate < new DateTime('today')) {
    echo json_encode(['success' => false, 'message' => 'Appointment date cannot be in the past.']);
    exit;
}

try {
    if ($docId) {
        $conflict = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = :doc AND appointment_date = :date AND appointment_time = :time AND status != 'cancelled'");
        $conflict->execute([':doc' => $docId, ':date' => $date, ':time' => $time]);
        if ((int)$conflict->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'This time slot is already booked. Please choose a different time.']);
            exit;
        }
    }

    $patientId = isset($_SESSION['patient_id']) ? (int)$_SESSION['patient_id'] : null;
    $stmt = $pdo->prepare("INSERT INTO appointments (patient_name, email, phone, department_id, doctor_id, appointment_date, appointment_time, message, patient_id) VALUES (:name, :email, :phone, :dept, :doc, :date, :time, :msg, :pid)");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':dept' => $deptId ?: null,
        ':doc' => $docId ?: null,
        ':date' => $date,
        ':time' => $time,
        ':msg' => $message,
        ':pid' => $patientId
    ]);

    $appointmentId = (int)$pdo->lastInsertId();
    $appointment = getAppointment($pdo, $appointmentId);
    if ($appointment) {
        sendAppointmentBookedNotification($pdo, $appointment);
    } else {
        error_log('appointment.php: could not load appointment after insert #' . $appointmentId);
    }

    echo json_encode(['success' => true, 'message' => 'Your appointment has been booked successfully! We will confirm within 24 hours.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
}
