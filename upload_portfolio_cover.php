<?php
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'db_651998018');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
    ]);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No image uploaded',
    ]);
    exit;
}

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'portfolio-cover' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload directory is not writable',
    ]);
    exit;
}

$file = $_FILES['image'];
$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if ($extension === '' || !in_array($extension, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file extension',
    ]);
    exit;
}

$fileName = uniqid('cover_', true) . '.' . $extension;
$targetPath = $uploadDir . $fileName;
$relativePath = 'uploads/portfolio-cover/' . $fileName;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectSegment = basename(__DIR__);
$publicUrl = $scheme . '://' . $host . '/' . $projectSegment . '/' . $relativePath;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload failed',
    ]);
    exit;
}

if (isset($_POST['portfolio_id']) && $_POST['portfolio_id'] !== '') {
    $portfolioId = (int) $_POST['portfolio_id'];
    $stmt = $conn->prepare('UPDATE portfolios SET cover_image = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $publicUrl, $portfolioId);
        $stmt->execute();
        $stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'image' => $publicUrl,
]);
