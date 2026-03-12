<?php
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function getSettings(PDO $pdo): array {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :key");
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetch();
    return $result ? ($result['setting_value'] ?? $default) : $default;
}

function updateSetting(PDO $pdo, string $key, string $value): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value";
    } else {
        $sql = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
    }
    $pdo->prepare($sql)->execute([':key' => $key, ':value' => $value]);
}

function getDepartments(PDO $pdo, bool $activeOnly = true): array {
    $sql = "SELECT * FROM departments";
    if ($activeOnly) $sql .= " WHERE status = 1";
    $sql .= " ORDER BY sort_order, name";
    return $pdo->query($sql)->fetchAll();
}

function getDepartment(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function getDepartmentBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function getDoctors(PDO $pdo, ?int $departmentId = null, bool $activeOnly = true): array {
    $sql = "SELECT d.*, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id";
    $conditions = [];
    $params = [];
    if ($activeOnly) {
        $conditions[] = "d.status = 1";
    }
    if ($departmentId) {
        $conditions[] = "d.department_id = :dept_id";
        $params[':dept_id'] = $departmentId;
    }
    if ($conditions) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY d.sort_order, d.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDoctor(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT d.*, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.doctor_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function getDoctorBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT d.*, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.slug = :slug AND d.status = 1");
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function getDoctorReviews(PDO $pdo, int $doctorId, int $limit = 10): array {
    $stmt = $pdo->prepare("SELECT * FROM doctor_reviews WHERE doctor_id = :id ORDER BY created_at DESC LIMIT :lim");
    $stmt->bindValue(':id', $doctorId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRelatedDoctors(PDO $pdo, int $departmentId, int $excludeId, int $limit = 4): array {
    $stmt = $pdo->prepare("SELECT d.*, dep.name as department_name FROM doctors d LEFT JOIN departments dep ON d.department_id = dep.department_id WHERE d.department_id = :dept AND d.doctor_id != :exclude AND d.status = 1 ORDER BY d.sort_order, d.name LIMIT :lim");
    $stmt->bindValue(':dept', $departmentId, PDO::PARAM_INT);
    $stmt->bindValue(':exclude', $excludeId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function generateDoctorSlug(PDO $pdo, string $name, ?int $excludeId = null): string {
    $cleanName = preg_replace('/^dr\.?\s+/i', '', trim($name));
    $base = 'dr-' . slugify($cleanName);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT doctor_id FROM doctors WHERE slug = :slug";
        $params = [':slug' => $slug];
        if ($excludeId) {
            $sql .= " AND doctor_id != :exclude";
            $params[':exclude'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function getAppointments(PDO $pdo, ?string $status = null, ?string $search = null, ?int $doctorId = null): array {
    $sql = "SELECT a.*, d.name as doctor_name, dep.name as department_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.doctor_id LEFT JOIN departments dep ON a.department_id = dep.department_id";
    $conditions = [];
    $params = [];
    if ($doctorId) {
        $conditions[] = "a.doctor_id = :doctor_id";
        $params[':doctor_id'] = $doctorId;
    }
    if ($status) {
        $conditions[] = "a.status = :status";
        $params[':status'] = $status;
    }
    if ($search) {
        $conditions[] = "(a.patient_name LIKE :search OR a.email LIKE :search2 OR a.phone LIKE :search3)";
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }
    if ($conditions) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY a.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPosts(PDO $pdo, bool $publishedOnly = false, int $limit = 0): array {
    $sql = "SELECT * FROM posts";
    if ($publishedOnly) $sql .= " WHERE status = 'published'";
    $sql .= " ORDER BY created_at DESC";
    $params = [];
    if ($limit > 0) {
        $sql .= " LIMIT :lim";
        $params[':lim'] = $limit;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPost(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE post_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function getPostBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = :slug AND status = 'published'");
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function getTestimonials(PDO $pdo, bool $activeOnly = true): array {
    $sql = "SELECT * FROM testimonials";
    if ($activeOnly) $sql .= " WHERE status = 1";
    $sql .= " ORDER BY created_at DESC";
    return $pdo->query($sql)->fetchAll();
}

function getCount(PDO $pdo, string $table): int {
    $allowed = ['doctors', 'departments', 'appointments', 'posts', 'testimonials', 'hero_slides', 'menus', 'pages'];
    if (!in_array($table, $allowed)) return 0;
    return (int)$pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
}

function getMenus(PDO $pdo, bool $activeOnly = true): array {
    $sql = "SELECT * FROM menus";
    if ($activeOnly) $sql .= " WHERE status = 1";
    $sql .= " ORDER BY menu_order ASC, id ASC";
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getPage(PDO $pdo, string $slug): ?array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE page_slug = :slug");
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function getHomeSection(PDO $pdo, string $key): array {
    try {
        $stmt = $pdo->prepare("SELECT section_data FROM home_sections WHERE section_key = :key");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row ? json_decode($row['section_data'], true) : [];
    } catch (Exception $e) {
        return [];
    }
}

function saveHomeSection(PDO $pdo, string $key, array $data): bool {
    try {
        $json = json_encode($data);
        $upd = $pdo->prepare("UPDATE home_sections SET section_data = :data, updated_at = CURRENT_TIMESTAMP WHERE section_key = :key");
        $upd->execute([':key' => $key, ':data' => $json]);
        if ($upd->rowCount() === 0) {
            $ins = $pdo->prepare("INSERT INTO home_sections (section_key, section_data, updated_at) VALUES (:key, :data, CURRENT_TIMESTAMP)");
            $ins->execute([':key' => $key, ':data' => $json]);
        }
        return true;
    } catch (Exception $e) {
        error_log('saveHomeSection [' . $key . ']: ' . $e->getMessage());
        return false;
    }
}

function getHeroSlides(PDO $pdo, bool $activeOnly = false): array {
    $sql = "SELECT * FROM hero_slides";
    if ($activeOnly) $sql .= " WHERE status = 1";
    $sql .= " ORDER BY sort_order ASC, id ASC";
    return $pdo->query($sql)->fetchAll();
}

function getHeroSlide(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM hero_slides WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function uploadImage(array $file, string $dir = 'uploads'): ?string {
    $result = uploadImageDetailed($file, $dir);
    return (substr($result, 0, 1) === '/') ? $result : null;
}

/**
 * Upload an image and return the web path on success, or a human-readable error string on failure.
 */
function uploadImageDetailed(array $file, string $dir = 'uploads'): string {
    $uploadDir = __DIR__ . '/../assets/' . $dir . '/';

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0755, true)) {
            $err = 'Cannot create upload directory: ' . $uploadDir . ' — check server permissions.';
            error_log('[JMedi Upload] ' . $err);
            return $err;
        }
    }
    if (!is_writable($uploadDir)) {
        $err = 'Upload directory not writable: ' . $uploadDir;
        error_log('[JMedi Upload] ' . $err);
        return $err;
    }

    $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp','image/x-icon','image/vnd.microsoft.icon'];
    $allowedExts  = ['jpg','jpeg','png','gif','webp','ico'];

    $phpErrMap = [
        UPLOAD_ERR_INI_SIZE   => 'File too large — exceeds PHP upload_max_filesize (' . ini_get('upload_max_filesize') . '). Ask host to increase it or use a smaller image.',
        UPLOAD_ERR_FORM_SIZE  => 'File too large — exceeds the HTML form MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was sent.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server has no temporary folder configured.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
        $err = $phpErrMap[$file['error']] ?? 'Unknown PHP upload error code: ' . $file['error'];
        error_log('[JMedi Upload] ' . $err . ' | file: ' . ($file['name'] ?? '?'));
        return $err;
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $err = 'No valid uploaded file found (tmp_name missing or not a real upload).';
        error_log('[JMedi Upload] ' . $err);
        return $err;
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realMime, $allowedMimes)) {
        $err = 'Invalid file type detected: ' . $realMime . '. Allowed: JPG, PNG, GIF, WEBP, ICO.';
        error_log('[JMedi Upload] ' . $err);
        return $err;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        $err = 'File too large (' . round($file['size'] / 1024 / 1024, 1) . ' MB). Max 10 MB.';
        error_log('[JMedi Upload] ' . $err);
        return $err;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        $ext = explode('/', $realMime)[1] ?? 'jpg';
        $ext = str_replace(['jpeg','x-icon','vnd.microsoft.icon'], ['jpg','ico','ico'], $ext);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log('[JMedi Upload] SUCCESS: ' . $filepath);
        return '/assets/' . $dir . '/' . $filename;
    }

    $err = 'move_uploaded_file() failed. Target: ' . $filepath . ' — check directory write permissions.';
    error_log('[JMedi Upload] ' . $err);
    return $err;
}

function getDoctorSchedules(PDO $pdo, int $doctorId): array {
    $stmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :id ORDER BY day_of_week, session_label");
    $stmt->execute([':id' => $doctorId]);
    return $stmt->fetchAll();
}

function getDoctorSchedulesByDay(PDO $pdo, int $doctorId): array {
    $schedules = getDoctorSchedules($pdo, $doctorId);
    $byDay = [];
    foreach ($schedules as $s) {
        $byDay[$s['day_of_week']][] = $s;
    }
    return $byDay;
}

function getAvailableSlots(PDO $pdo, int $doctorId, string $date): array {
    $dayOfWeek = (int)date('w', strtotime($date));

    $stmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = :id AND day_of_week = :dow AND is_active = 1 ORDER BY start_time");
    $stmt->execute([':id' => $doctorId, ':dow' => $dayOfWeek]);
    $sessions = $stmt->fetchAll();

    if (empty($sessions)) return [];

    $bookedStmt = $pdo->prepare("SELECT appointment_time FROM appointments WHERE doctor_id = :id AND appointment_date = :date AND status != 'cancelled'");
    $bookedStmt->execute([':id' => $doctorId, ':date' => $date]);
    $booked = array_column($bookedStmt->fetchAll(), 'appointment_time');

    $result = [];
    foreach ($sessions as $session) {
        $slots = [];
        $start = strtotime($session['start_time']);
        $end = strtotime($session['end_time']);
        $dur = (int)$session['slot_duration_minutes'] * 60;

        while ($start < $end) {
            $timeStr = date('H:i:s', $start);
            $display = date('h:i A', $start);
            $isBooked = in_array($timeStr, $booked) || in_array(date('H:i', $start) . ':00', $booked);
            if (!$isBooked) {
                $slots[] = ['time' => $timeStr, 'display' => $display];
            }
            $start += $dur;
        }

        if (!empty($slots)) {
            $result[] = [
                'session' => $session['session_label'],
                'slots' => $slots,
                'count' => count($slots)
            ];
        }
    }
    return $result;
}

function saveDoctorSchedule(PDO $pdo, int $doctorId, int $dayOfWeek, string $sessionLabel, array $data): bool {
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $sql = "INSERT INTO doctor_schedules (doctor_id, day_of_week, session_label, start_time, end_time, slot_duration_minutes, is_active) VALUES (:did, :dow, :label, :start, :end, :dur, :active) ON CONFLICT (doctor_id, day_of_week, session_label) DO UPDATE SET start_time = EXCLUDED.start_time, end_time = EXCLUDED.end_time, slot_duration_minutes = EXCLUDED.slot_duration_minutes, is_active = EXCLUDED.is_active";
        } else {
            $sql = "INSERT INTO doctor_schedules (doctor_id, day_of_week, session_label, start_time, end_time, slot_duration_minutes, is_active) VALUES (:did, :dow, :label, :start, :end, :dur, :active) ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), slot_duration_minutes = VALUES(slot_duration_minutes), is_active = VALUES(is_active)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':did' => $doctorId, ':dow' => $dayOfWeek, ':label' => $sessionLabel,
            ':start' => $data['start_time'], ':end' => $data['end_time'],
            ':dur' => $data['slot_duration'], ':active' => $data['is_active']
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function deleteDoctorSchedule(PDO $pdo, int $id): bool {
    $stmt = $pdo->prepare("DELETE FROM doctor_schedules WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

function getAppointment(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT a.*, d.name as doctor_name, d.photo as doctor_photo, d.phone as doctor_phone, d.email as doctor_email, d.specialization as doctor_specialization, d.consultation_fee, dep.name as department_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.doctor_id LEFT JOIN departments dep ON a.department_id = dep.department_id WHERE a.appointment_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function updateAppointment(PDO $pdo, int $id, array $data): bool {
    $fields = [];
    $params = [':id' => $id];
    $allowed = ['status', 'admin_notes', 'appointment_date', 'appointment_time', 'doctor_id', 'department_id', 'consultation_type'];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = :$field";
            $params[":$field"] = $data[$field];
        }
    }
    if (empty($fields)) return false;
    $sql = "UPDATE appointments SET " . implode(', ', $fields) . " WHERE appointment_id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function buildWhatsAppDeepLink(string $phone, string $message): string {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if ($cleanPhone === '') {
        return '';
    }
    return 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($message);
}

function getNotificationSenderConfig(PDO $pdo): array {
    $siteName = getSetting($pdo, 'site_name', 'JMedi');
    $fallbackEmail = trim(getSetting($pdo, 'email', ''));
    $fromEmail = trim(getSetting($pdo, 'mail_from_email', $fallbackEmail));
    $fromName = trim(getSetting($pdo, 'mail_from_name', $siteName));

    return [
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'site_name' => $siteName,
    ];
}

function buildNotificationMailHeaders(PDO $pdo, ?string $overrideReplyTo = null, bool $isHtml = false): string {
    $sender = getNotificationSenderConfig($pdo);
    $fromEmail = $sender['from_email'];
    $fromName = $sender['from_name'];
    $replyTo = trim((string)($overrideReplyTo ?? $fromEmail));

    $headers = [];
    if ($fromEmail !== '') {
        $safeName = str_replace(["\r", "\n"], '', $fromName);
        $headers[] = 'From: ' . ($safeName !== '' ? $safeName . ' <' . $fromEmail . '>' : $fromEmail);
        $headers[] = 'Reply-To: ' . ($replyTo !== '' ? $replyTo : $fromEmail);
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $isHtml ? 'Content-Type: text/html; charset=UTF-8' : 'Content-Type: text/plain; charset=UTF-8';

    return implode("\r\n", $headers);
}

function smtpReadLine($socket): string {
    $line = '';
    while (!feof($socket)) {
        $chunk = fgets($socket, 515);
        if ($chunk === false) {
            break;
        }
        $line .= $chunk;
        if (isset($chunk[3]) && $chunk[3] === ' ') {
            break;
        }
    }
    return trim($line);
}

function smtpExpect($socket, array $codes, string $context): bool {
    $response = smtpReadLine($socket);
    if ($response === '') {
        error_log("SMTP {$context}: empty response");
        return false;
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        error_log("SMTP {$context} failed: {$response}");
        return false;
    }
    return true;
}

function smtpWrite($socket, string $command): bool {
    return fwrite($socket, $command . "\r\n") !== false;
}

function sendViaSmtp(PDO $pdo, string $toEmail, string $subject, string $body, string $headers = ''): bool {
    $host = trim(getSetting($pdo, 'smtp_host', ''));
    $port = (int)getSetting($pdo, 'smtp_port', '587');
    $encryption = strtolower(trim(getSetting($pdo, 'smtp_encryption', 'tls')));
    $username = trim(getSetting($pdo, 'smtp_username', ''));
    $password = (string)getSetting($pdo, 'smtp_password', '');

    if ($host === '' || !$port || $username === '' || $password === '') {
        error_log('sendViaSmtp: Missing SMTP configuration values.');
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("sendViaSmtp: Connection failed ({$errno}) {$errstr}");
        return false;
    }

    $sender = getNotificationSenderConfig($pdo);
    $fromEmail = $sender['from_email'] !== '' ? $sender['from_email'] : $username;
    $fromName = $sender['from_name'] !== '' ? $sender['from_name'] : 'JMedi';

    $ok = smtpExpect($socket, [220], 'connect')
        && smtpWrite($socket, 'EHLO localhost')
        && smtpExpect($socket, [250], 'EHLO');

    if ($ok && $encryption === 'tls') {
        $ok = smtpWrite($socket, 'STARTTLS')
            && smtpExpect($socket, [220], 'STARTTLS')
            && @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            && smtpWrite($socket, 'EHLO localhost')
            && smtpExpect($socket, [250], 'EHLO after TLS');
        if (!$ok) {
            error_log('sendViaSmtp: STARTTLS failed.');
        }
    }

    if ($ok) {
        $ok = smtpWrite($socket, 'AUTH LOGIN')
            && smtpExpect($socket, [334], 'AUTH LOGIN')
            && smtpWrite($socket, base64_encode($username))
            && smtpExpect($socket, [334], 'SMTP username')
            && smtpWrite($socket, base64_encode($password))
            && smtpExpect($socket, [235], 'SMTP password');
    }

    if ($ok) {
        $ok = smtpWrite($socket, 'MAIL FROM:<' . $fromEmail . '>')
            && smtpExpect($socket, [250], 'MAIL FROM')
            && smtpWrite($socket, 'RCPT TO:<' . $toEmail . '>')
            && smtpExpect($socket, [250, 251], 'RCPT TO')
            && smtpWrite($socket, 'DATA')
            && smtpExpect($socket, [354], 'DATA');
    }

    if ($ok) {
        $msg = "From: {$fromName} <{$fromEmail}>\r\n";
        $msg .= "To: <{$toEmail}>\r\n";
        $msg .= "Subject: {$subject}\r\n";
        if ($headers !== '') {
            $msg .= $headers . "\r\n";
        }
        $msg .= "\r\n" . $body . "\r\n.";
        $ok = smtpWrite($socket, $msg) && smtpExpect($socket, [250], 'message body');
    }

    smtpWrite($socket, 'QUIT');
    fclose($socket);

    return $ok;
}

function sendNotificationEmail(PDO $pdo, string $toEmail, string $subject, string $body, string $headers = ''): bool {
    $transport = strtolower(trim(getSetting($pdo, 'mail_transport', 'php_mail')));

    if ($transport === 'smtp') {
        return sendViaSmtp($pdo, $toEmail, $subject, $body, $headers);
    }

    $sender = getNotificationSenderConfig($pdo);
    if ($sender['from_email'] !== '') {
        return @mail($toEmail, $subject, $body, $headers, '-f ' . $sender['from_email']);
    }
    return @mail($toEmail, $subject, $body, $headers);
}

function sendAppointmentBookedNotification(PDO $pdo, array $appointment): array {
    $payload = ['sent' => false, 'email' => null, 'whatsapp_link' => ''];

    if (getSetting($pdo, 'notify_on_booking', '1') !== '1') {
        return $payload;
    }

    $toEmail = trim(getSetting($pdo, 'appointment_email', getSetting($pdo, 'email', '')));
    if ($toEmail === '') {
        return $payload;
    }

    $sender = getNotificationSenderConfig($pdo);
    $siteName = $sender['site_name'];
    $subject = 'New Appointment Booking - ' . $siteName;
    $body = "A new appointment has been booked.\n\n";
    $body .= 'Patient: ' . ($appointment['patient_name'] ?? '-') . "\n";
    $body .= 'Email: ' . ($appointment['email'] ?? '-') . "\n";
    $body .= 'Phone: ' . ($appointment['phone'] ?? '-') . "\n";
    $body .= 'Department: ' . ($appointment['department_name'] ?? '-') . "\n";
    $body .= 'Doctor: ' . ($appointment['doctor_name'] ?? '-') . "\n";
    $body .= 'Date: ' . ($appointment['appointment_date'] ?? '-') . "\n";
    $body .= 'Time: ' . ($appointment['appointment_time'] ?? '-') . "\n";
    if (!empty($appointment['message'])) {
        $body .= "Message: {$appointment['message']}\n";
    }

    $headers = buildNotificationMailHeaders($pdo);
    $payload['email'] = ['to' => $toEmail, 'subject' => $subject, 'body' => $body, 'headers' => $headers];
    $payload['whatsapp_link'] = buildWhatsAppDeepLink((string)($appointment['phone'] ?? ''), "Hello {$appointment['patient_name']}, we received your appointment request at {$siteName}.");

    try {
        $payload['sent'] = sendNotificationEmail($pdo, $toEmail, $subject, $body, $headers);
        if (!$payload['sent']) {
            error_log('sendAppointmentBookedNotification: email send failed for appointment #' . ($appointment['appointment_id'] ?? 'new'));
        }
    } catch (Throwable $e) {
        error_log('sendAppointmentBookedNotification exception: ' . $e->getMessage());
    }

    return $payload;
}

function sendAppointmentStatusNotification(PDO $pdo, array $appointment, string $oldStatus, string $newStatus): array {
    $payload = ['sent' => false, 'email' => null, 'whatsapp_link' => ''];

    if ($oldStatus === $newStatus || getSetting($pdo, 'notify_on_status_change', '1') !== '1') {
        return $payload;
    }

    $toEmail = trim((string)($appointment['email'] ?? ''));
    if ($toEmail === '') {
        return $payload;
    }

    $sender = getNotificationSenderConfig($pdo);
    $siteName = $sender['site_name'];
    $statusLabel = ucfirst($newStatus);
    $subject = "Appointment {$statusLabel} - {$siteName}";
    $body = "Dear " . ($appointment['patient_name'] ?? 'Patient') . ",\n\n";
    $body .= "Your appointment status has been updated to: {$statusLabel}.\n\n";
    $body .= 'Doctor: ' . ($appointment['doctor_name'] ?? 'Our Doctor') . "\n";
    $body .= 'Date: ' . ($appointment['appointment_date'] ?? '-') . "\n";
    $body .= 'Time: ' . ($appointment['appointment_time'] ?? '-') . "\n";
    $body .= "\nThank you,\n{$siteName}";

    $waMsg = "Hello {$appointment['patient_name']}, your appointment status is now {$statusLabel} at {$siteName}.";
    $templateKey = $newStatus === 'confirmed' ? 'appointment_confirmed' : ($newStatus === 'cancelled' ? 'appointment_cancelled' : '');
    $templateVars = [
        'patient_name' => $appointment['patient_name'] ?? 'Patient',
        'doctor_name' => $appointment['doctor_name'] ?? 'Our Doctor',
        'appointment_date' => $appointment['appointment_date'] ?? '-',
        'appointment_time' => $appointment['appointment_time'] ?? '-',
        'clinic_name' => $siteName,
    ];
    $payload['email'] = ['to' => $toEmail, 'subject' => $subject, 'body' => $body];
    $payload['whatsapp_link'] = buildWhatsAppDeepLink((string)($appointment['phone'] ?? ''), $waMsg);

    try {
        $payload['sent'] = $templateKey !== ''
            ? sendTemplateEmail($pdo, $templateKey, $toEmail, $templateVars, $subject, $body)
            : sendNotificationEmail($pdo, $toEmail, $subject, $body, buildNotificationMailHeaders($pdo));
        if (!$payload['sent']) {
            error_log('sendAppointmentStatusNotification: email send failed for appointment #' . ($appointment['appointment_id'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('sendAppointmentStatusNotification exception: ' . $e->getMessage());
    }

    return $payload;
}

function getEmailTemplates(PDO $pdo): array {
    try {
        return $pdo->query("SELECT * FROM email_templates ORDER BY template_name ASC")->fetchAll();
    } catch (Throwable $e) {
        error_log('getEmailTemplates: ' . $e->getMessage());
        return [];
    }
}

function getEmailTemplate(PDO $pdo, string $templateName): ?array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_name = :name LIMIT 1");
        $stmt->execute([':name' => $templateName]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('getEmailTemplate: ' . $e->getMessage());
        return null;
    }
}

function renderTemplateVariables(string $content, array $variables): string {
    $replace = [];
    foreach ($variables as $key => $value) {
        $replace['{{' . $key . '}}'] = (string)$value;
    }
    return strtr($content, $replace);
}

function getDefaultAppointmentConfirmedTemplateHtml(): string {
    return <<<'HTML'
<!doctype html>
<html>
  <body style="margin:0;padding:0;background-color:#f2f5f8;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f2f5f8;margin:0;padding:0;">
      <tr>
        <td align="center" style="padding:20px 10px;">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
            <tr>
              <td style="padding:14px 20px;background-color:#0f6f90;color:#ffffff;font-size:18px;font-weight:bold;">JMedi Smart Medical Platform</td>
            </tr>
            <tr>
              <td>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                  <tr>
                    <td width="60%" valign="top" style="width:60%;padding:26px 22px;background-color:#eef4f7;">
                      <div style="font-size:30px;line-height:34px;color:#0f7288;font-weight:800;letter-spacing:0.5px;margin-bottom:14px;">Appointment Confirmed</div>
                      <div style="font-size:28px;line-height:36px;color:#1f2d3d;margin-bottom:14px;">Dear {{patient_name}},</div>
                      <div style="font-size:22px;line-height:34px;color:#1f2d3d;margin-bottom:18px;">Your appointment with <strong>{{doctor_name}}</strong> has been confirmed.</div>

                      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#e9eef3;border-radius:8px;">
                        <tr>
                          <td style="padding:15px;border-radius:8px;">
                            <div style="font-size:34px;line-height:40px;color:#1f2d3d;"><strong>Date:</strong> {{appointment_date}}</div>
                            <div style="font-size:34px;line-height:40px;color:#1f2d3d;margin-top:6px;"><strong>Time:</strong> {{appointment_time}}</div>
                          </td>
                        </tr>
                      </table>

                      <div style="font-size:22px;line-height:34px;color:#1f2d3d;margin-top:18px;">Thank you,<br>{{clinic_name}}</div>
                    </td>
                    <td width="40%" valign="middle" align="center" style="width:40%;padding:26px 14px;background-color:#1fa2a6;">
                      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:220px;background-color:#ffffff;border-radius:14px;">
                        <tr>
                          <td align="center" style="padding:20px 12px 10px 12px;">
                            <img src="{{clinic_logo}}" alt="{{clinic_name}} logo" width="120" style="display:block;border:0;outline:none;text-decoration:none;max-width:120px;height:auto;margin:0 auto 10px auto;">
                            <div style="font-size:44px;line-height:48px;color:#0f4e75;font-weight:800;">JMedi</div>
                            <div style="font-size:16px;line-height:22px;color:#0f7288;">Smart Medical Platform</div>
                          </td>
                        </tr>
                        <tr>
                          <td align="center" style="padding:12px;background-color:#18828a;color:#ffffff;font-size:16px;line-height:20px;border-bottom-left-radius:14px;border-bottom-right-radius:14px;">Powered by JNVWeb</td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td align="center" style="padding:12px 16px;background-color:#f0f3f6;color:#667085;font-size:12px;line-height:18px;">&copy; {{year}} JMedi – Smart Medical Platform</td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;
}

function getEmailTemplateDefaults(): array {
    return [
        'appointment_confirmed' => [
            'subject' => 'Appointment Confirmed - {{clinic_name}}',
            'body' => getDefaultAppointmentConfirmedTemplateHtml(),
            'variables' => 'patient_name,doctor_name,appointment_date,appointment_time,clinic_name,clinic_logo,year',
        ],
        'appointment_cancelled' => [
            'subject' => 'Appointment Cancelled - {{clinic_name}}',
            'body' => <<<'HTML'
<div style="font-size:22px;line-height:30px;color:#b42318;font-weight:700;margin-bottom:8px;">Appointment Cancelled</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:12px;">Dear {{patient_name}},</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:14px;">Your appointment with <strong>{{doctor_name}}</strong> was cancelled. We apologize for the inconvenience.</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#fff4f2;border:1px solid #f3d4ce;border-radius:10px;margin:10px 0 14px 0;">
  <tr>
    <td style="padding:12px 14px;">
      <div style="font-size:14px;line-height:20px;color:#5f6c7b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Original slot</div>
      <div style="font-size:18px;line-height:26px;color:#1f2933;"><strong>Date:</strong> {{appointment_date}}</div>
      <div style="font-size:18px;line-height:26px;color:#1f2933;margin-top:6px;"><strong>Time:</strong> {{appointment_time}}</div>
    </td>
  </tr>
</table>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:10px;">If you would like to reschedule, reply to this email or call our front desk and we will help you find the next available time.</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:14px;">Thank you,<br>{{clinic_name}}</div>
HTML,
            'variables' => 'patient_name,doctor_name,appointment_date,appointment_time,clinic_name',
        ],
        'doctor_approved' => [
            'subject' => 'Doctor Profile Approved - {{clinic_name}}',
            'body' => <<<'HTML'
<div style="font-size:22px;line-height:30px;color:#0f6f90;font-weight:700;margin-bottom:8px;">Profile Approved</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:12px;">Dear {{doctor_name}},</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:14px;">Your doctor profile is approved and now visible to patients on {{clinic_name}}.</div>
<div style="background:#f0f4ff;border:1px solid #d7e3ff;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
  <div style="font-size:14px;line-height:20px;color:#4b5563;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Next steps</div>
  <div style="font-size:16px;line-height:24px;color:#1f2933;">- Keep your availability updated so patients can book instantly.</div>
  <div style="font-size:16px;line-height:24px;color:#1f2933;">- Review your profile details and ensure contact information is correct.</div>
  <div style="font-size:16px;line-height:24px;color:#1f2933;">- Respond promptly to new appointment requests and confirmations.</div>
</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:10px;">We are excited to have you onboard.</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:14px;">Regards,<br>{{clinic_name}}</div>
HTML,
            'variables' => 'doctor_name,clinic_name',
        ],
        'patient_registration' => [
            'subject' => 'Welcome to {{clinic_name}}',
            'body' => <<<'HTML'
<div style="font-size:22px;line-height:30px;color:#0f6f90;font-weight:700;margin-bottom:8px;">Welcome to {{clinic_name}}</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:12px;">Dear {{patient_name}},</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:14px;">Thank you for creating your account with {{clinic_name}}. You can now book appointments and manage your visits from one place.</div>
<div style="background:#f4f9f4;border:1px solid #d7ead7;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
  <div style="font-size:14px;line-height:20px;color:#4b5563;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">What you can do</div>
  <div style="font-size:16px;line-height:24px;color:#1f2933;">- Schedule or reschedule appointments online.</div>
  <div style="font-size:16px;line-height:24px;color:#1f2933;">- Receive email updates about your visits.</div>
  <div style="font-size:16px;line-height:24px;color:#1f2933;">- Keep your details up to date for faster check-in.</div>
</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:10px;">We look forward to seeing you soon.</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:14px;">Regards,<br>{{clinic_name}}</div>
HTML,
            'variables' => 'patient_name,clinic_name',
        ],
        'payment_success' => [
            'subject' => 'Payment Successful - {{clinic_name}}',
            'body' => <<<'HTML'
<div style="font-size:22px;line-height:30px;color:#0f6f90;font-weight:700;margin-bottom:8px;">Payment Received</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:12px;">Dear {{patient_name}},</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:14px;">We have successfully recorded your payment.</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f2f7ff;border:1px solid #d7e3ff;border-radius:10px;margin:10px 0 14px 0;">
  <tr>
    <td style="padding:12px 14px;">
      <div style="font-size:14px;line-height:20px;color:#4b5563;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Receipt</div>
      <div style="font-size:20px;line-height:28px;color:#0f6f90;font-weight:700;">Amount: {{amount}}</div>
    </td>
  </tr>
</table>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:10px;">This receipt has been saved to your records. If you need any assistance, reply to this email and our team will help.</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:14px;">Thank you,<br>{{clinic_name}}</div>
HTML,
            'variables' => 'patient_name,amount,clinic_name',
        ],
        'password_reset' => [
            'subject' => 'Password Reset Request - {{clinic_name}}',
            'body' => <<<'HTML'
<div style="font-size:22px;line-height:30px;color:#0f6f90;font-weight:700;margin-bottom:8px;">Reset Your Password</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:12px;">Hello {{patient_name}},</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:14px;">We received a request to reset the password for your {{clinic_name}} account.</div>
<div style="text-align:center;margin:14px 0 16px 0;">
  <a href="{{reset_link}}" style="background:#0f6f90;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:8px;font-size:16px;font-weight:600;display:inline-block;">Reset Password</a>
</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-bottom:10px;">If the button above does not work, copy and paste this link into your browser:</div>
<div style="font-size:14px;line-height:20px;color:#0f6f90;background:#f1f5f9;border:1px solid #d8e2ef;border-radius:8px;padding:10px 12px;word-break:break-all;">{{reset_link}}</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:14px;">If you did not request this change, you can ignore this email and your password will stay the same.</div>
<div style="font-size:16px;line-height:24px;color:#1f2933;margin-top:14px;">Regards,<br>{{clinic_name}}</div>
HTML,
            'variables' => 'patient_name,reset_link,clinic_name',
        ],
    ];
}

function getDefaultEmailTemplate(string $templateName): ?array {
    $defaults = getEmailTemplateDefaults();
    return $defaults[$templateName] ?? null;
}

function buildEmailLayout(string $htmlBody, string $clinicName): string {
    $safeClinic = e($clinicName);
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f7fb;"><tr><td align="center" style="padding:20px 10px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e7edf4;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#0d6efd;color:#ffffff;padding:16px 20px;font-size:20px;font-weight:700;">' . $safeClinic . '</td></tr>'
        . '<tr><td style="padding:22px;color:#263238;line-height:1.6;">' . $htmlBody . '</td></tr>'
        . '<tr><td style="padding:14px 20px;color:#789;font-size:12px;border-top:1px solid #edf2f7;">&copy; ' . date('Y') . ' ' . $safeClinic . ' · JMedi Smart Medical Platform</td></tr>'
        . '</table></td></tr></table></body></html>';
}


function sendTemplateEmail(PDO $pdo, string $templateName, string $toEmail, array $variables, string $fallbackSubject, string $fallbackTextBody): bool {
    $template = getEmailTemplate($pdo, $templateName);
    $siteName = getSetting($pdo, 'site_name', 'JMedi');
    $defaultLogo = getSetting($pdo, 'frontend_logo', '');

    $variables = array_merge([
        'clinic_name' => $siteName,
        'year' => date('Y'),
        'clinic_logo' => $defaultLogo,
    ], $variables);

    if ($template && (int)($template['status'] ?? 1) === 1) {
        $subject = renderTemplateVariables((string)$template['subject'], $variables);
        $rawBody = renderTemplateVariables((string)$template['body'], $variables);
        $html = ($templateName === 'appointment_confirmed' && stripos($rawBody, '<table') !== false)
            ? $rawBody
            : buildEmailLayout($rawBody, (string)($variables['clinic_name'] ?? $siteName));
        $headers = buildNotificationMailHeaders($pdo, null, true);
        return sendNotificationEmail($pdo, $toEmail, $subject, $html, $headers);
    }

    $subject = renderTemplateVariables($fallbackSubject, $variables);
    $body = renderTemplateVariables($fallbackTextBody, $variables);
    $headers = buildNotificationMailHeaders($pdo);
    return sendNotificationEmail($pdo, $toEmail, $subject, $body, $headers);
}

function sendDoctorApprovedNotification(PDO $pdo, array $doctor): bool {
    $to = trim((string)($doctor['email'] ?? ''));
    if ($to === '') return false;
    return sendTemplateEmail(
        $pdo,
        'doctor_approved',
        $to,
        [
            'doctor_name' => $doctor['name'] ?? 'Doctor',
            'clinic_name' => getSetting($pdo, 'site_name', 'JMedi'),
        ],
        'Doctor Profile Approved',
        "Dear {{doctor_name}},

Your doctor profile has been approved.

Thank you,
{{clinic_name}}"
    );
}

function sendPatientRegistrationNotification(PDO $pdo, array $patient): bool {
    $to = trim((string)($patient['email'] ?? ''));
    if ($to === '') return false;
    return sendTemplateEmail(
        $pdo,
        'patient_registration',
        $to,
        [
            'patient_name' => $patient['name'] ?? 'Patient',
            'clinic_name' => getSetting($pdo, 'site_name', 'JMedi'),
        ],
        'Welcome to {{clinic_name}}',
        "Dear {{patient_name}},

Your registration is successful.

Regards,
{{clinic_name}}"
    );
}

function sendPaymentSuccessNotification(PDO $pdo, array $payload): bool {
    $to = trim((string)($payload['email'] ?? ''));
    if ($to === '') return false;
    return sendTemplateEmail(
        $pdo,
        'payment_success',
        $to,
        [
            'patient_name' => $payload['patient_name'] ?? 'Patient',
            'clinic_name' => getSetting($pdo, 'site_name', 'JMedi'),
            'amount' => $payload['amount'] ?? '',
        ],
        'Payment Successful - {{clinic_name}}',
        "Dear {{patient_name}},

Your payment was successful.

Regards,
{{clinic_name}}"
    );
}

function sendPasswordResetNotification(PDO $pdo, array $payload): bool {
    $to = trim((string)($payload['email'] ?? ''));
    if ($to === '') return false;
    return sendTemplateEmail(
        $pdo,
        'password_reset',
        $to,
        [
            'patient_name' => $payload['patient_name'] ?? 'User',
            'reset_link' => $payload['reset_link'] ?? '#',
            'clinic_name' => getSetting($pdo, 'site_name', 'JMedi'),
        ],
        'Password Reset Request - {{clinic_name}}',
        "Hello {{patient_name}},

Reset your password using this link: {{reset_link}}

Regards,
{{clinic_name}}"
    );
}

function getAppointmentStats(PDO $pdo): array {
    $today      = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');

    $stmtToday = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date = :d");
    $stmtToday->execute([':d' => $today]);

    $stmtMonth = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN :start AND :end");
    $stmtMonth->execute([':start' => $monthStart, ':end' => $monthEnd]);

    $stmtStatus = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM appointments GROUP BY status");
    $stmtStatus->execute();
    $statusCounts = [];
    foreach ($stmtStatus->fetchAll() as $row) {
        $statusCounts[$row['status']] = (int)$row['cnt'];
    }

    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM appointments");

    return [
        'today'      => (int)$stmtToday->fetchColumn(),
        'pending'    => $statusCounts['pending']    ?? 0,
        'confirmed'  => $statusCounts['confirmed']  ?? 0,
        'completed'  => $statusCounts['completed']  ?? 0,
        'cancelled'  => $statusCounts['cancelled']  ?? 0,
        'this_month' => (int)$stmtMonth->fetchColumn(),
        'total'      => (int)$stmtTotal->fetchColumn(),
    ];
}

function formatDate(string $date): string {
    return date('M d, Y', strtotime($date));
}

function truncateText(string $text, int $length = 150): string {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}
