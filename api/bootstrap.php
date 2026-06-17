<?php

header('Content-Type: application/json; charset=UTF-8');

function api_abort($status, $message)
{
    http_response_code($status);
    echo json_encode([
        'message' => $message,
    ]);
    exit();
}

function api_start_session()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

api_start_session();

function api_get_authorization_header()
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim($_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!empty($_SERVER['Authorization'])) {
        return trim($_SERVER['Authorization']);
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return trim($value);
                }
            }
        }
    }

    return null;
}

function api_bearer_token()
{
    $header = api_get_authorization_header();
    if (!$header) {
        return null;
    }

    if (preg_match('/Bearer\\s+(\\S+)/i', $header, $matches)) {
        return $matches[1];
    }

    return null;
}

function api_find_user_id_from_token($db, $token)
{
    if (!$token) {
        return null;
    }

    $tokenId = null;
    $plainToken = $token;

    if (strpos($token, '|') !== false) {
        list($maybeId, $maybeToken) = explode('|', $token, 2);
        if (ctype_digit($maybeId)) {
            $tokenId = (int) $maybeId;
            $plainToken = $maybeToken;
        }
    }

    $hashed = hash('sha256', $plainToken);

    if ($tokenId) {
        $stmt = $db->prepare("SELECT tokenable_id, expires_at FROM personal_access_tokens WHERE id = :id AND token = :token AND tokenable_type = 'App\\\\Models\\\\User' LIMIT 1");
        $stmt->bindParam(':id', $tokenId, PDO::PARAM_INT);
    } else {
        $stmt = $db->prepare("SELECT tokenable_id, expires_at FROM personal_access_tokens WHERE token = :token AND tokenable_type = 'App\\\\Models\\\\User' LIMIT 1");
    }

    $stmt->bindParam(':token', $hashed);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
        return null;
    }

    return (int) $row['tokenable_id'];
}

function api_require_user_id($db = null)
{
    api_start_session();

    // ✅ ถ้ามี Bearer token ให้เชื่อ token เป็นหลักเสมอ (กัน session ของคนอื่นค้าง)
    $token = api_bearer_token();
    if ($token) {
        $db = $db ?: api_db();
        $tokenUserId = api_find_user_id_from_token($db, $token);

        if ($tokenUserId) {
            // ✅ sync session ให้ตรงกับ token ทุกครั้ง
            $_SESSION['user_id'] = (int) $tokenUserId;
            return (int) $tokenUserId;
        }

        api_abort(401, 'Unauthorized');
    }

    // ✅ ไม่มี token ค่อย fallback ไป session (กรณีเก่าที่ login แบบ session)
    if (!empty($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }

    api_abort(401, 'Unauthorized');
}


function api_require_ownership($db, $table, $id, $userId, $idField = 'id', $userField = 'user_id')
{
    $stmt = $db->prepare("SELECT {$userField} FROM {$table} WHERE {$idField} = :id LIMIT 1");
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        api_abort(404, 'Not found');
    }

    if ((int) $row[$userField] !== (int) $userId) {
        api_abort(403, 'Unauthorized');
    }
}

function api_env()
{
    static $env = null;
    if ($env !== null) {
        return $env;
    }

    $env = array();
    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (file_exists($envPath)) {
        $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            $env = $parsed;
        }
    }

    return $env;
}

function api_env_value($key, $default = null)
{
    $env = api_env();
    return array_key_exists($key, $env) ? $env[$key] : $default;
}

function api_db()
{
    static $db = null;
    if ($db) {
        return $db;
    }

    $host = api_env_value('DB_HOST', '127.0.0.1');
    $port = api_env_value('DB_PORT', '3306');
    $database = api_env_value('DB_DATABASE', 'smart_learning_tracker_1');
    $username = api_env_value('DB_USERNAME', 'root');
    $password = api_env_value('DB_PASSWORD', '');
    $charset = api_env_value('DB_CHARSET', 'utf8');

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );

    try {
        $db = new PDO($dsn, $username, $password, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            'message' => 'Database connection failed',
            'error' => $e->getMessage(),
        ));
        exit();
    }

    return $db;
}
