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
$adminUrl = $baseDir . '/admin.php';
$rootUrl = $baseDir !== '' ? rtrim(dirname($baseDir), '/') : '';
$faviconUrl = ($rootUrl !== '' ? $rootUrl : '') . '/public/app/img/admin.png';
$adminBgUrl = ($rootUrl !== '' ? $rootUrl : '') . '/public/app/img/admin.png';

if (!function_exists('hash_equals')) {
    function hash_equals($known, $user)
    {
        if (!is_string($known) || !is_string($user)) {
            return false;
        }
        $knownLen = strlen($known);
        if ($knownLen !== strlen($user)) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < $knownLen; $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }
        return $result === 0;
    }
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: {$adminUrl}");
    exit();
}

function admin_base64url_decode($data)
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    $data = str_replace(array('-', '_'), array('+', '/'), $data);
    return base64_decode($data);
}

function admin_google_token_info($token)
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);
    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $response = @file_get_contents($url);
    }

    if (!$response) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

function admin_allow_insecure_google_token()
{
    $flag = read_env_value('GOOGLE_ALLOW_INSECURE_TOKEN');
    $flag = strtolower((string) $flag);
    return $flag === '1' || $flag === 'true' || $flag === 'yes';
}

function admin_decode_google_jwt_payload($token)
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    $payload = admin_base64url_decode($parts[1]);
    if (!$payload) {
        return null;
    }
    $data = json_decode($payload, true);
    return is_array($data) ? $data : null;
}

function admin_google_payload_is_valid($payload, $clientId)
{
    if (!is_array($payload)) {
        return false;
    }
    if (isset($payload['aud']) && $clientId !== null && $clientId !== '' && $payload['aud'] !== $clientId) {
        return false;
    }
    if (isset($payload['iss'])) {
        $allowed = array('accounts.google.com', 'https://accounts.google.com');
        if (!in_array($payload['iss'], $allowed, true)) {
            return false;
        }
    }
    return isset($payload['email']);
}

function read_env_value($key) {
    $paths = array(
        __DIR__ . DIRECTORY_SEPARATOR . '.env',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env'
    );
    $envPath = null;
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $envPath = $path;
            break;
        }
    }
    if ($envPath === null) {
        return null;
    }
    $parsed = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (!is_array($parsed)) {
        return null;
    }
    return isset($parsed[$key]) ? trim((string) $parsed[$key]) : null;
}

function admin_get_admin_emails()
{
    $raw = read_env_value('ADMIN_EMAILS');
    if ($raw === null || $raw === '') {
        return [];
    }
    $parts = preg_split('/[\s,]+/', (string) $raw);
    if (!is_array($parts)) {
        return [];
    }
    $emails = [];
    foreach ($parts as $part) {
        $value = trim((string) $part);
        if ($value !== '') {
            $emails[] = strtolower($value);
        }
    }
    return array_values(array_unique($emails));
}

function admin_dev_login_enabled()
{
    $flag = read_env_value('DEV_LOGIN_ENABLED');
    $flag = strtolower((string) $flag);
    return $flag === '1' || $flag === 'true' || $flag === 'yes';
}

function admin_allow_plain_password()
{
    $flag = read_env_value('ALLOW_PLAIN_PASSWORD');
    $flag = strtolower((string) $flag);
    return $flag === '1' || $flag === 'true' || $flag === 'yes';
}

function admin_current_url()
{
    $scheme = 'http';
    if (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
    ) {
        $scheme = 'https';
    }
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '127.0.0.1';
    $path = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '/admin_login.php';
    return $scheme . '://' . $host . $path;
}

function admin_google_redirect_uri()
{
    $current = admin_current_url();
    $uri = read_env_value('GOOGLE_ADMIN_REDIRECT_URI');
    if ($uri !== null && trim($uri) !== '') {
        return trim((string) $uri);
    }

    $host = (string) parse_url($current, PHP_URL_HOST);
    $isLocal = in_array($host, ['127.0.0.1', 'localhost'], true);
    if ($isLocal) {
        return $current;
    }

    $uri = read_env_value('GOOGLE_REDIRECT_URI');
    if ($uri !== null && trim($uri) !== '') {
        return trim((string) $uri);
    }
    return $current;
}

function admin_google_exchange_code($code, $clientId, $clientSecret, $redirectUri)
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $postFields = http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

$error = '';
$email = '';
$googleClientId = read_env_value('GOOGLE_CLIENT_ID');
if ($googleClientId === null || $googleClientId === '') {
    $googleClientId = read_env_value('VITE_GOOGLE_CLIENT_ID');
}
$googleClientSecret = read_env_value('GOOGLE_CLIENT_SECRET');
$googleRedirectUri = admin_google_redirect_uri();
$devLoginEnabled = admin_dev_login_enabled();
$allowPlainPassword = admin_allow_plain_password();

if (isset($_GET['google_oauth']) && (string) $_GET['google_oauth'] === '1') {
    if ($googleClientId === null || $googleClientId === '') {
        $error = 'ยังไม่ได้ตั้งค่า GOOGLE_CLIENT_ID ใน .env';
    } elseif ($googleClientSecret === null || $googleClientSecret === '') {
        $error = 'ยังไม่ได้ตั้งค่า GOOGLE_CLIENT_SECRET ใน .env';
    } else {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $googleClientId,
            'redirect_uri' => $googleRedirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);
        header('Location: ' . $authUrl);
        exit();
    }
}

function admin_db_connect()
{
    if (!function_exists('mysqli_connect')) {
        return null;
    }

    $defaults = [
        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'db_651998018',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
    ];

    $envConfig = $defaults;
    $paths = array(
        __DIR__ . DIRECTORY_SEPARATOR . '.env',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env'
    );
    foreach ($paths as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
        if ($parsed !== false) {
            $envConfig['host'] = isset($parsed['DB_HOST']) ? $parsed['DB_HOST'] : $defaults['host'];
            $envConfig['port'] = isset($parsed['DB_PORT']) ? $parsed['DB_PORT'] : $defaults['port'];
            $envConfig['database'] = isset($parsed['DB_DATABASE']) ? $parsed['DB_DATABASE'] : $defaults['database'];
            $envConfig['username'] = isset($parsed['DB_USERNAME']) ? $parsed['DB_USERNAME'] : $defaults['username'];
            $envConfig['password'] = isset($parsed['DB_PASSWORD']) ? $parsed['DB_PASSWORD'] : $defaults['password'];
            $envConfig['charset'] = isset($parsed['DB_CHARSET']) ? $parsed['DB_CHARSET'] : $defaults['charset'];
        }
        break;
    }

    $conn = @mysqli_connect(
        $envConfig['host'],
        $envConfig['username'],
        $envConfig['password'],
        $envConfig['database'],
        (int) $envConfig['port']
    );

    if (!$conn) {
        return null;
    }

    mysqli_set_charset($conn, $envConfig['charset']);

    return $conn;
}

function admin_bind_params($stmt, $types, $params)
{
    $refs = [];
    $refs[] = $stmt;
    $refs[] = &$types;
    foreach ($params as $index => $value) {
        $refs[] = &$params[$index];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);
}

function admin_column_exists($conn, $table, $column)
{
    if (!$conn || $table === '' || $column === '') {
        return false;
    }
    $tableEscaped = mysqli_real_escape_string($conn, $table);
    $columnEscaped = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'");
    if (!$result) {
        return false;
    }
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

if (
    (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
    || (isset($_GET['code']) && $_GET['code'] !== '')
) {
    $isDevLogin = $devLoginEnabled && isset($_POST['dev_login']) && (string) $_POST['dev_login'] === '1';
    $isLocalLogin = isset($_POST['local_login']) && (string) $_POST['local_login'] === '1';
    $googleCredential = isset($_POST['google_credential']) ? trim((string) $_POST['google_credential']) : '';
    $email = isset($_POST['email']) ? trim((string) $_POST['email']) : $email;
    $passwordInput = isset($_POST['password']) ? (string) $_POST['password'] : '';
    if (
        !$isLocalLogin
        && !$isDevLogin
        && $googleCredential === ''
        && isset($_SERVER['REQUEST_METHOD'])
        && $_SERVER['REQUEST_METHOD'] === 'POST'
        && $email !== ''
    ) {
        $isLocalLogin = true;
    }

    if ($googleCredential === '' && isset($_GET['code']) && $_GET['code'] !== '') {
        $state = isset($_GET['state']) ? (string) $_GET['state'] : '';
        $expectedState = isset($_SESSION['google_oauth_state']) ? (string) $_SESSION['google_oauth_state'] : '';
        unset($_SESSION['google_oauth_state']);

        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            $error = 'สถานะการยืนยันตัวตนไม่ถูกต้อง กรุณาลองใหม่';
        } elseif ($googleClientId === null || $googleClientId === '' || $googleClientSecret === null || $googleClientSecret === '') {
            $error = 'Google OAuth ยังตั้งค่าไม่ครบใน .env';
        } else {
            $tokenData = admin_google_exchange_code((string) $_GET['code'], $googleClientId, $googleClientSecret, $googleRedirectUri);
            if (is_array($tokenData) && isset($tokenData['id_token']) && trim((string) $tokenData['id_token']) !== '') {
                $googleCredential = trim((string) $tokenData['id_token']);
            } else {
                $error = 'ไม่สามารถแลกเปลี่ยนรหัส Google ได้';
            }
        }
    }

    if ($error !== '') {
        // Skip and show error on form.
    } elseif ($isLocalLogin) {
        if ($email === '') {
            $error = 'กรุณากรอกอีเมล';
        } else {
            $conn = admin_db_connect();
            if (!$conn) {
                $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
            } else {
                $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
                if (!$stmt) {
                    $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
                } else {
                    mysqli_stmt_bind_param($stmt, 's', $email);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $user = $result ? mysqli_fetch_assoc($result) : null;
                    mysqli_stmt_close($stmt);

                    if (!$user || !isset($user['id'])) {
                        $error = 'ไม่พบบัญชีผู้ใช้งาน';
                    } elseif (!isset($user['role']) || (string) $user['role'] !== 'admin') {
                        $error = 'บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ';
                    } else {
                        $storedPassword = isset($user['password']) ? (string) $user['password'] : '';
                        $passwordOk = false;

                        if ($storedPassword !== '') {
                            if (function_exists('password_verify') && password_verify($passwordInput, $storedPassword)) {
                                $passwordOk = true;
                            } elseif ($allowPlainPassword && hash_equals($storedPassword, $passwordInput)) {
                                $passwordOk = true;
                            }
                        } elseif ($devLoginEnabled) {
                            // In local/dev mode, allow admin login without password when DB password is empty.
                            $passwordOk = true;
                        }

                        if (!$passwordOk) {
                            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
                        } else {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = isset($user['name']) ? $user['name'] : 'Admin';
                            $_SESSION['role'] = 'admin';
                            header("Location: {$adminUrl}");
                            exit();
                        }
                    }
                }
            }
        }
    } elseif ($isDevLogin) {
        $conn = admin_db_connect();
        if (!$conn) {
            $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
        } else {
            $user = null;
            $stmt = mysqli_prepare($conn, 'SELECT id, name, email, role FROM users WHERE role = "admin" ORDER BY id ASC LIMIT 1');
            if ($stmt) {
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = $result ? mysqli_fetch_assoc($result) : null;
                mysqli_stmt_close($stmt);
            }

            if (!$user) {
                $adminEmails = admin_get_admin_emails();
                if (count($adminEmails) > 0) {
                    $fallbackEmail = (string) $adminEmails[0];
                    $fallbackName = 'Admin';
                    $insert = mysqli_prepare($conn, 'INSERT INTO users (name, email, role, created_at, updated_at) VALUES (?, ?, "admin", NOW(), NOW())');
                    if ($insert) {
                        mysqli_stmt_bind_param($insert, 'ss', $fallbackName, $fallbackEmail);
                        $ok = mysqli_stmt_execute($insert);
                        $newId = $ok ? mysqli_insert_id($conn) : null;
                        mysqli_stmt_close($insert);
                        if ($ok && $newId) {
                            $user = [
                                'id' => $newId,
                                'name' => $fallbackName,
                                'role' => 'admin',
                            ];
                        }
                    }
                }
            }

            if ($user && isset($user['id'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = isset($user['name']) ? $user['name'] : 'Admin';
                $_SESSION['role'] = 'admin';
                header("Location: {$adminUrl}");
                exit();
            }
            $error = 'ไม่พบบัญชี admin สำหรับโหมดพัฒนา';
        }
    } elseif ($googleCredential !== '') {
        if ($googleClientId === null || $googleClientId === '') {
            $error = 'ยังไม่ได้ตั้งค่า GOOGLE_CLIENT_ID ใน .env';
        } else {
            $info = admin_google_token_info($googleCredential);
            if ((!$info || !isset($info['email'])) && admin_allow_insecure_google_token()) {
                $payload = admin_decode_google_jwt_payload($googleCredential);
                if (admin_google_payload_is_valid($payload, $googleClientId)) {
                    $info = $payload;
                }
            }

            if (!$info || !isset($info['email'])) {
                $error = 'ไม่สามารถตรวจสอบบัญชี Google ได้';
            } elseif (isset($info['aud']) && $googleClientId && $info['aud'] !== $googleClientId) {
                $error = 'Google client ไม่ตรงกับที่ตั้งค่าไว้';
            } else {
                $email = $info['email'];
                $conn = admin_db_connect();
                if (!$conn) {
                    $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
                } else {
                    $usersHasRole = admin_column_exists($conn, 'users', 'role');
                    $usersHasUpdatedAt = admin_column_exists($conn, 'users', 'updated_at');
                    $usersHasCreatedAt = admin_column_exists($conn, 'users', 'created_at');
                    $usersHasProvider = admin_column_exists($conn, 'users', 'provider');
                    $usersHasProviderId = admin_column_exists($conn, 'users', 'provider_id');
                    $usersHasProfilePic = admin_column_exists($conn, 'users', 'profile_pic');

                    $selectSql = $usersHasRole
                        ? 'SELECT id, name, email, role FROM users WHERE email = ? LIMIT 1'
                        : 'SELECT id, name, email FROM users WHERE email = ? LIMIT 1';
                    $stmt = mysqli_prepare($conn, $selectSql);
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 's', $email);
                        mysqli_stmt_execute($stmt);
                        $result = mysqli_stmt_get_result($stmt);
                        $user = $result ? mysqli_fetch_assoc($result) : null;
                        mysqli_stmt_close($stmt);

                        $adminEmails = admin_get_admin_emails();
                        $isAdminEmail = in_array(strtolower($email), $adminEmails, true);

                        if (!$user) {
                            if (!$isAdminEmail) {
                                $error = 'บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ';
                            } else {
                                $displayName = isset($info['name']) ? (string) $info['name'] : $email;
                                $insertCols = ['name', 'email'];
                                $insertVals = [$displayName, $email];
                                if ($usersHasRole) {
                                    $insertCols[] = 'role';
                                    $insertVals[] = 'admin';
                                }
                                if ($usersHasProvider) {
                                    $insertCols[] = 'provider';
                                    $insertVals[] = 'google';
                                }
                                if ($usersHasProviderId) {
                                    $insertCols[] = 'provider_id';
                                    $insertVals[] = isset($info['sub']) ? (string) $info['sub'] : null;
                                }
                                if ($usersHasProfilePic) {
                                    $insertCols[] = 'profile_pic';
                                    $insertVals[] = isset($info['picture']) ? (string) $info['picture'] : null;
                                }
                                if ($usersHasCreatedAt) {
                                    $insertCols[] = 'created_at';
                                    $insertVals[] = date('Y-m-d H:i:s');
                                }
                                if ($usersHasUpdatedAt) {
                                    $insertCols[] = 'updated_at';
                                    $insertVals[] = date('Y-m-d H:i:s');
                                }

                                $insertSql = 'INSERT INTO users (`' . implode('`,`', $insertCols) . '`) VALUES (' . implode(',', array_fill(0, count($insertVals), '?')) . ')';
                                $insert = mysqli_prepare($conn, $insertSql);
                                if ($insert) {
                                    $types = str_repeat('s', count($insertVals));
                                    admin_bind_params($insert, $types, $insertVals);
                                    $ok = mysqli_stmt_execute($insert);
                                    $newId = $ok ? mysqli_insert_id($conn) : null;
                                    mysqli_stmt_close($insert);

                                    if ($ok && $newId) {
                                        $_SESSION['user_id'] = $newId;
                                        $_SESSION['username'] = $displayName;
                                        $_SESSION['role'] = 'admin';
                                        header("Location: {$adminUrl}");
                                        exit();
                                    } else {
                                        $error = 'ไม่สามารถสร้างบัญชีผู้ดูแลระบบได้';
                                    }
                                } else {
                                    $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
                                }
                            }
                        } elseif (($usersHasRole && isset($user['role']) && $user['role'] !== 'admin') || !$usersHasRole) {
                            if ($isAdminEmail) {
                                $setParts = [];
                                $setValues = [];
                                if ($usersHasRole) {
                                    $setParts[] = '`role` = ?';
                                    $setValues[] = 'admin';
                                }
                                if ($usersHasUpdatedAt) {
                                    $setParts[] = '`updated_at` = ?';
                                    $setValues[] = date('Y-m-d H:i:s');
                                }
                                if ($usersHasProvider) {
                                    $setParts[] = '`provider` = ?';
                                    $setValues[] = 'google';
                                }
                                if ($usersHasProviderId && isset($info['sub']) && $info['sub'] !== '') {
                                    $setParts[] = '`provider_id` = ?';
                                    $setValues[] = (string) $info['sub'];
                                }
                                if ($usersHasProfilePic && isset($info['picture']) && $info['picture'] !== '') {
                                    $setParts[] = '`profile_pic` = ?';
                                    $setValues[] = (string) $info['picture'];
                                }

                                if (count($setParts) === 0) {
                                    $_SESSION['user_id'] = $user['id'];
                                    $_SESSION['username'] = $user['name'];
                                    $_SESSION['role'] = 'admin';
                                    header("Location: {$adminUrl}");
                                    exit();
                                }

                                $updateSql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1';
                                $update = mysqli_prepare($conn, $updateSql);
                                if ($update) {
                                    $setValues[] = (string) $user['id'];
                                    $types = str_repeat('s', count($setValues));
                                    admin_bind_params($update, $types, $setValues);
                                    $ok = mysqli_stmt_execute($update);
                                    mysqli_stmt_close($update);
                                    if ($ok) {
                                        $_SESSION['user_id'] = $user['id'];
                                        $_SESSION['username'] = $user['name'];
                                        $_SESSION['role'] = 'admin';
                                        header("Location: {$adminUrl}");
                                        exit();
                                    } else {
                                        $error = 'ไม่สามารถอัปเดตสิทธิ์ผู้ใช้ได้';
                                    }
                                } else {
                                    $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
                                }
                            } else {
                                $error = 'บัญชีนี้ไม่มีสิทธิ์ผู้ดูแลระบบ';
                            }
                        } else {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['name'];
                            $_SESSION['role'] = 'admin';
                            header("Location: {$adminUrl}");
                            exit();
                        }
                    } else {
                        $error = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล';
                    }
                }
            }
        }
    } else {
        $error = 'กรุณาเข้าสู่ระบบ';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="slty.css">
</head>
<body class="admin-login-page" style="--admin-login-bg:url('<?php echo htmlspecialchars($adminBgUrl, ENT_QUOTES, 'UTF-8'); ?>');">
    <form class="card" method="post">
        <div class="title">เข้าสู่ระบบ</div>
        <div class="subtitle">ใช้บัญชี Google เพื่อเข้าสู่ระบบ Smart Learning Tracker (Admin)</div>

        <?php if ($error !== ''): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <input type="hidden" name="google_credential" id="google_credential" value="">
        <input type="hidden" name="dev_login" id="dev_login" value="0">
        <input type="hidden" name="local_login" id="local_login" value="0">

        <label for="email">อีเมลผู้ดูแล</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="651463052@crru.ac.th" autocomplete="username">

        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" placeholder="รหัสผ่าน" autocomplete="current-password">

        <button type="submit" id="localLoginBtn">เข้าสู่ระบบด้วยอีเมล</button>
        <?php if ($devLoginEnabled): ?>
            <div class="note">โหมดพัฒนาเปิดอยู่: หากบัญชี admin ไม่มีรหัสผ่านในฐานข้อมูล จะเข้าได้ด้วยอีเมล</div>
        <?php endif; ?>

        <div class="terms">
            <input type="checkbox" id="terms_accept" aria-label="ยอมรับเงื่อนไขการใช้งาน">
            <div>
                ฉันยอมรับ <a href="#" target="_blank" rel="noopener">เงื่อนไขการใช้งาน</a>
                และ <a href="#" target="_blank" rel="noopener">นโยบายความเป็นส่วนตัว</a>
            </div>
        </div>

        <div class="divider"><span>เข้าสู่ระบบด้วย Google</span></div>
        <div id="google-signin" class="google-button google-disabled"></div>
        <?php if ($googleClientId === null || $googleClientId === ''): ?>
            <div class="note">ตั้งค่า GOOGLE_CLIENT_ID ในไฟล์ .env เพื่อเปิดใช้งาน Google Sign-In</div>
        <?php endif; ?>
    </form>

    <?php if ($googleClientId !== null && $googleClientId !== ''): ?>
        <script>
            var localBtn = document.getElementById('localLoginBtn');
            if (localBtn) {
                localBtn.addEventListener('click', function () {
                    var localInput = document.getElementById('local_login');
                    if (localInput) {
                        localInput.value = '1';
                    }
                });
            }

            function initGoogleLogin() {
                var terms = document.getElementById('terms_accept');
                var googleContainer = document.getElementById('google-signin');
                if (terms && googleContainer) {
                    terms.addEventListener('change', function () {
                        if (terms.checked) {
                            googleContainer.classList.remove('google-disabled');
                        } else {
                            googleContainer.classList.add('google-disabled');
                        }
                    });
                }

                if (!window.google || !window.google.accounts || !window.google.accounts.id) {
                    return;
                }
                var clientId = '<?php echo htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>';
                if (!clientId) return;
                window.google.accounts.id.initialize({
                    client_id: clientId,
                    callback: function (response) {
                        if (!response || !response.credential) return;
                        var input = document.getElementById('google_credential');
                        if (!input) return;
                        input.value = response.credential;
                        input.form.submit();
                    }
                });
                var container = document.getElementById('google-signin');
                if (!container) return;
                window.google.accounts.id.renderButton(container, {
                    theme: 'outline',
                    size: 'large',
                    text: 'continue_with',
                    shape: 'pill',
                    width: 320
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.initGoogleLogin) window.initGoogleLogin();
                });
            } else if (window.initGoogleLogin) {
                window.initGoogleLogin();
            }
        </script>
        <script src="https://accounts.google.com/gsi/client" async defer onload="initGoogleLogin()"></script>
    <?php endif; ?>

</body>
</html>
