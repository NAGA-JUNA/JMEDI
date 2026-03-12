<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['patient_id']);
unset($_SESSION['patient_name']);
unset($_SESSION['patient_email']);
header('Location: /public/patient-login.php');
exit;
