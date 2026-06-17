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
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$baseDir = $scriptName !== '' ? dirname($scriptName) : '';
$baseDir = str_replace('\\', '/', $baseDir);
$baseDir = rtrim($baseDir, '/');
if ($baseDir === '.' || $baseDir === '/') {
    $baseDir = '';
}
$rootUrl = $baseDir !== '' ? rtrim(dirname($baseDir), '/') : '';
$frontendBase = getenv('FRONTEND_URL') ?: '';
if (!is_string($frontendBase)) {
    $frontendBase = '';
}
$frontendBase = rtrim(trim($frontendBase), '/');

if ($frontendBase === '') {
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $isLocalhost = stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false;
    if ($isLocalhost) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $frontendBase = $scheme . '://localhost:5173';
    }
}

$userLoginUrl = $frontendBase !== ''
    ? $frontendBase . '/auth/login'
    : (($rootUrl !== '' ? $rootUrl : '') . '/auth/login');
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header("Location: {$userLoginUrl}");
exit();
