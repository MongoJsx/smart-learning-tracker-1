<?php
if (function_exists('session_status') && session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = __DIR__ . DIRECTORY_SEPARATOR . '.sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        @session_save_path($sessionPath);
    }
}
session_start();

function admin_session_login_allowed_origins()
{
    $origins = [];
    $rawList = getenv('FRONTEND_ORIGINS');
    if (is_string($rawList) && trim($rawList) !== '') {
        foreach (explode(',', $rawList) as $item) {
            $value = trim($item);
            if ($value !== '') {
                $origins[] = $value;
            }
        }
    }
    $single = getenv('FRONTEND_ORIGIN');
    if (is_string($single) && trim($single) !== '') {
        $origins[] = trim($single);
    }
    $origins[] = 'http://localhost:5173';
    $origins[] = 'http://127.0.0.1:5173';

    return array_values(array_unique($origins));
}

function admin_session_login_apply_cors()
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim((string) $_SERVER['HTTP_ORIGIN']) : '';
    if ($origin === '') {
        return;
    }
    $allowed = admin_session_login_allowed_origins();
    if (!in_array($origin, $allowed, true)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
}

admin_session_login_apply_cors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit();
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
$token = '';
if (is_array($payload) && isset($payload['token'])) {
    $token = trim((string) $payload['token']);
}
if ($token === '' && isset($_POST['token'])) {
    $token = trim((string) $_POST['token']);
}

if ($token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing token']);
    exit();
}

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$baseDir = $scriptName !== '' ? dirname($scriptName) : '';
$baseDir = str_replace('\\', '/', $baseDir);
$baseDir = rtrim($baseDir, '/');
if ($baseDir === '.' || $baseDir === '/') {
    $baseDir = '';
}
$rootUrl = $baseDir !== '' ? rtrim(dirname($baseDir), '/') : '';
$adminUrl = ($baseDir !== '' ? $baseDir : '') . '/admin.php';
$apiPath = ($rootUrl !== '' ? $rootUrl : '') . '/public/index.php/api/auth/me';

$scheme = 'http';
if (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
) {
    $scheme = 'https';
}
$host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
$apiUrl = $scheme . '://' . $host . $apiPath;

$responseBody = null;
$statusCode = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ]);
    $responseBody = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "Accept: application/json\r\nAuthorization: Bearer {$token}\r\n",
        ],
    ]);
    $responseBody = @file_get_contents($apiUrl, false, $context);
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $statusCode = (int) $m[1];
        }
    }
}

if (!is_string($responseBody) || $responseBody === '' || $statusCode < 200 || $statusCode >= 300) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token ไม่ถูกต้องหรือหมดอายุ']);
    exit();
}

$user = json_decode($responseBody, true);
if (!is_array($user) || !isset($user['id']) || !isset($user['role'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'ไม่สามารถตรวจสอบผู้ใช้ได้']);
    exit();
}

$role = strtolower((string) $user['role']);
if ($role !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ']);
    exit();
}

$_SESSION['user_id'] = (string) $user['id'];
$_SESSION['username'] = isset($user['name']) ? (string) $user['name'] : 'Admin';
$_SESSION['email'] = isset($user['email']) ? (string) $user['email'] : '';
$_SESSION['role'] = 'admin';

echo json_encode([
    'ok' => true,
    'redirect' => $adminUrl,
]);
