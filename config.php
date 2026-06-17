<?php

$defaults = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'smart_learning_tracker_1',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
$envConfig = $defaults;

if (file_exists($envPath)) {
    $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($parsed !== false) {
        $envConfig['host'] = isset($parsed['DB_HOST']) ? $parsed['DB_HOST'] : $defaults['host'];
        $envConfig['port'] = isset($parsed['DB_PORT']) ? $parsed['DB_PORT'] : $defaults['port'];
        $envConfig['database'] = isset($parsed['DB_DATABASE']) ? $parsed['DB_DATABASE'] : $defaults['database'];
        $envConfig['username'] = isset($parsed['DB_USERNAME']) ? $parsed['DB_USERNAME'] : $defaults['username'];
        $envConfig['password'] = isset($parsed['DB_PASSWORD']) ? $parsed['DB_PASSWORD'] : $defaults['password'];
        $envConfig['charset'] = isset($parsed['DB_CHARSET']) ? $parsed['DB_CHARSET'] : $defaults['charset'];
    }
}

$conn = @mysqli_connect(
    $envConfig['host'],
    $envConfig['username'],
    $envConfig['password'],
    $envConfig['database'],
    (int) $envConfig['port']
);

if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error(),
    ]);
    exit();
}

mysqli_set_charset($conn, $envConfig['charset']);
