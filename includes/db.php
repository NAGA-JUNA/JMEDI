<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

$db_url = getenv('DATABASE_URL');

if ($db_url) {
    $parsed = parse_url($db_url);
    $scheme = $parsed['scheme'] ?? 'pgsql';
    $host   = $parsed['host'] ?? 'localhost';
    $port   = $parsed['port'] ?? (substr($scheme, 0, 5) === 'mysql' ? 3306 : 5432);
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $user   = $parsed['user'] ?? '';
    $pass   = $parsed['pass'] ?? '';

    if (substr($scheme, 0, 8) === 'postgres') {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    }
} else {
    /* cPanel production — MySQL */
    $host   = 'localhost';
    $port   = 3306;
    $dbname = 'svaobtfy_jmedi';
    $user   = 'svaobtfy_jmedi';
    $pass   = 'sa1T4HXr@7602626264';
    $dsn    = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
}

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
