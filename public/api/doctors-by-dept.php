<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

$deptId = (int)($_GET['department_id'] ?? 0);
$doctors = [];

if ($deptId) {
    $stmt = $pdo->prepare("SELECT doctor_id, name FROM doctors WHERE department_id = :dept AND status = 1 ORDER BY name");
    $stmt->execute([':dept' => $deptId]);
    $doctors = $stmt->fetchAll();
}

echo json_encode($doctors);
