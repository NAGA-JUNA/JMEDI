<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Home
if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/public/index.php';
    return;
}

// Serve real static files (css, js, images, fonts)
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|webp|ico|svg|woff|woff2|ttf|eot|map)$/i', $uri)) {
    $filePath = __DIR__ . $uri;
    if (file_exists($filePath)) {
        return false;
    }
    http_response_code(404);
    return;
}

// Serve real PHP files and directories as-is (admin/*, public/*, includes/*, etc.)
$filePath = __DIR__ . $uri;
if (file_exists($filePath . '.php')) {
    require $filePath . '.php';
    return;
}
if (is_dir($filePath)) {
    $indexPath = rtrim($filePath, '/') . '/index.php';
    if (file_exists($indexPath)) {
        require $indexPath;
        return;
    }
}
if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
    require $filePath;
    return;
}

// Doctor profile: /doctor/{slug}
if (preg_match('#^/doctor/([a-z0-9\-]+)$#i', $uri, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/public/doctor-profile.php';
    return;
}

// Clean URL routing: /doctors → public/doctors.php, /blog → public/blog.php, etc.
$segment = ltrim($uri, '/');
if (!str_contains($segment, '/')) {
    $publicFile = __DIR__ . '/public/' . $segment . '.php';
    if (file_exists($publicFile)) {
        require $publicFile;
        return;
    }
}

// Fallback — delegate to public/index.php (which handles its own 404)
$fallback = __DIR__ . '/public/index.php';
if (file_exists($fallback)) {
    require $fallback;
    return;
}

http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="d-flex align-items-center justify-content-center" style="min-height:100vh;"><div class="text-center"><h1 class="display-1 text-primary">404</h1><p class="lead">Page not found</p><a href="/" class="btn btn-primary">Go Home</a></div></body></html>';
