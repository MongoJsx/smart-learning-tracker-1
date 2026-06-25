


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
date_default_timezone_set('Asia/Bangkok');
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$baseDir = $scriptName !== '' ? dirname($scriptName) : '';
$baseDir = str_replace('\\', '/', $baseDir);
$baseDir = rtrim($baseDir, '/');
if ($baseDir === '.' || $baseDir === '/') {
    $baseDir = '';
}
$adminLoginUrl = $baseDir . '/admin_login.php';
$rootUrl = $baseDir !== '' ? rtrim(dirname($baseDir), '/') : '';
$faviconUrl = ($rootUrl !== '' ? $rootUrl : '') . '/public/app/img/admin.png';

if (!isset($_SESSION['user_id'])) {
    header("Location: {$adminLoginUrl}");
    exit();
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access denied";
    exit();
}

function admin_db_connect()
{
    if (!function_exists('mysqli_connect')) {
        return null;
    }
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
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

function admin_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_db_query($conn, $sql)
{
    try {
        return mysqli_query($conn, $sql);
    } catch (Throwable $error) {
        return false;
    }
}

function admin_table_exists($conn, $table)
{
    if (!$conn || $table === '') {
        return false;
    }
    $escaped = mysqli_real_escape_string($conn, $table);
    $result = admin_db_query($conn, "SHOW TABLES LIKE '{$escaped}'");
    if (!$result) {
        return false;
    }
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

function admin_column_exists($conn, $table, $column)
{
    if (!$conn || $table === '' || $column === '') {
        return false;
    }
    $escapedTable = mysqli_real_escape_string($conn, $table);
    $escapedColumn = mysqli_real_escape_string($conn, $column);
    $result = admin_db_query($conn, "SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
    if (!$result) {
        return false;
    }
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    return $exists;
}

function admin_term_from_date($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return '';
    }
    $month = (int) date('n', $ts);
    if ($month >= 8 && $month <= 12) {
        return '1';
    }
    if ($month >= 1 && $month <= 5) {
        return '2';
    }
    if ($month >= 6 && $month <= 7) {
        return '3';
    }
    return '';
}

function admin_term_label($term)
{
    $term = (string) $term;
    if ($term === '1') {
        return 'ภาคต้น';
    }
    if ($term === '2') {
        return 'ภาคปลาย';
    }
    if ($term === '3') {
        return 'ฤดูร้อน';
    }
    return '';
}

function admin_academic_year_from_date($value)
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return '';
    }
    $month = (int) date('n', $ts);
    $year = (int) date('Y', $ts);
    $academicYear = ($month >= 8) ? $year : ($year - 1);
    return (string) ($academicYear + 543);
}

function admin_term_key_from_date($value)
{
    $term = admin_term_from_date($value);
    if ($term === '') {
        return '';
    }
    $academicYear = admin_academic_year_from_date($value);
    if ($academicYear === '') {
        return '';
    }
    return $academicYear . '-' . $term;
}

function admin_format_short_date($value)
{
    if ($value === null || $value === '') {
        return '-';
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return (string) $value;
    }
    return date('d/m/', $ts) . ((int) date('Y', $ts) + 543);
}

function admin_format_datetime_short($value)
{
    if ($value === null || $value === '') {
        return '-';
    }
    $ts = strtotime((string) $value);
    if ($ts === false) {
        return (string) $value;
    }
    return date('d/m/', $ts) . ((int) date('Y', $ts) + 543) . ' ' . date('H:i', $ts);
}

function admin_schedule_type_normalize($value)
{
    $type = strtolower(trim((string) $value));
    if ($type === 'exam' || $type === 'สอบ' || $type === 'test' || $type === 'quiz') {
        return 'exam';
    }
    if ($type === 'class' || $type === 'เรียน' || $type === 'study') {
        return 'class';
    }
    if ($type === 'other' || $type === 'กิจกรรม') {
        return 'other';
    }
    return $type;
}

function admin_schedule_type_label($value)
{
    $type = admin_schedule_type_normalize($value);
    if ($type === 'exam') {
        return 'สอบ';
    }
    if ($type === 'class') {
        return 'เรียน';
    }
    if ($type === 'other') {
        return 'กิจกรรม';
    }
    return (string) $value;
}

function admin_group_schedules($schedules)
{
    if (!is_array($schedules) || count($schedules) === 0) {
        return [];
    }
    $groups = [];
    foreach ($schedules as $schedule) {
        $termKey = '';
        if (isset($schedule['term_key']) && $schedule['term_key'] !== '') {
            $termKey = (string) $schedule['term_key'];
        } elseif (isset($schedule['term']) && $schedule['term'] !== '') {
            $termKey = (string) $schedule['term'];
        }
        $keyParts = [
            isset($schedule['user_id']) ? (string) $schedule['user_id'] : '',
            isset($schedule['subject_id']) ? (string) $schedule['subject_id'] : '',
            isset($schedule['day_of_week']) ? (string) $schedule['day_of_week'] : '',
            isset($schedule['start_time']) ? (string) $schedule['start_time'] : '',
            isset($schedule['end_time']) ? (string) $schedule['end_time'] : '',
            isset($schedule['room']) ? (string) $schedule['room'] : '',
            isset($schedule['schedule_type']) ? (string) $schedule['schedule_type'] : '',
            $termKey,
        ];
        $key = implode('|', $keyParts);
        if (!isset($groups[$key])) {
            $schedule['duplicate_count'] = 1;
            $groups[$key] = $schedule;
        } else {
            $groups[$key]['duplicate_count']++;
        }
    }
    return array_values($groups);
}

function admin_count_rows($conn, $table)
{
    if (!$conn || $table === '') {
        return 0;
    }
    $escaped = mysqli_real_escape_string($conn, $table);
    $result = admin_db_query($conn, "SELECT COUNT(*) AS total FROM `{$escaped}`");
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    return isset($row['total']) ? (int) $row['total'] : 0;
}

function admin_date_key($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d', $timestamp);
}

function admin_daily_label($dateKey)
{
    $timestamp = strtotime((string) $dateKey);
    if ($timestamp === false) {
        return (string) $dateKey;
    }
    $weekdays = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
    $weekday = $weekdays[(int) date('w', $timestamp)] ?? '';
    return $weekday . ' ' . date('d/m', $timestamp);
}

function admin_delta_label($todayValue, $yesterdayValue, $unit)
{
    $today = (int) $todayValue;
    $yesterday = (int) $yesterdayValue;
    $diff = $today - $yesterday;
    if ($diff === 0) {
        return ['class' => 'same', 'text' => 'เท่ากับเมื่อวาน'];
    }
    if ($diff > 0) {
        return ['class' => 'up', 'text' => '+' . $diff . ' ' . $unit . ' จากเมื่อวาน'];
    }
    return ['class' => 'down', 'text' => $diff . ' ' . $unit . ' จากเมื่อวาน'];
}

$conn = admin_db_connect();
$users = [];
$subjects = [];
$schedules = [];
$notifications = [];
$studyLogs = [];
$quizzes = [];
$usersById = [];
$subjectsById = [];
$subjectTermsById = [];
$semesterOptions = [];
$semesterById = [];
$currentAdminProfile = [
    'id' => isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '',
    'name' => isset($_SESSION['username']) ? (string) $_SESSION['username'] : 'ผู้ดูแล',
    'email' => isset($_SESSION['email']) ? (string) $_SESSION['email'] : '',
    'role' => isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'admin',
    'created_at' => '',
];
$hasSubjectSemesterColumn = false;
$userCountFromDb = 0;
$subjectCountFromDb = 0;
$scheduleCountFromDb = 0;
$notificationCountFromDb = 0;
$studyLogCountFromDb = 0;
$quizCountFromDb = 0;

if ($conn) {
    $userCountFromDb = admin_count_rows($conn, 'users');
    $subjectCountFromDb = admin_count_rows($conn, 'subjects');
    $scheduleCountFromDb = admin_count_rows($conn, 'schedules');
    $notificationCountFromDb = admin_count_rows($conn, 'learning_notifications');
    $studyLogCountFromDb = admin_count_rows($conn, 'study_logs');
    $quizCountFromDb = admin_count_rows($conn, 'quizzes');

    $semesterKeys = [];
    if (admin_table_exists($conn, 'semester')) {
        $result = admin_db_query($conn, 'SELECT semester_id, semester, academic_year FROM semester ORDER BY academic_year DESC, semester ASC');
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $term = isset($row['semester']) ? (string) $row['semester'] : '';
                $year = isset($row['academic_year']) ? (string) $row['academic_year'] : '';
                if ($term === '' || $year === '') {
                    continue;
                }
                $key = $year . '-' . $term;
                if (isset($semesterKeys[$key])) {
                    continue;
                }
                $semesterId = isset($row['semester_id']) ? (string) $row['semester_id'] : '';
                $termYearLabel = $term . '-' . $year;
                $semesterOptions[] = [
                    'id' => $semesterId,
                    'key' => $key,
                    'label' => $termYearLabel,
                    'term' => $term,
                    'year' => $year,
                ];
                if ($semesterId !== '') {
                    $semesterById[$semesterId] = [
                        'key' => $key,
                        'label' => $termYearLabel,
                    ];
                }
                $semesterKeys[$key] = true;
            }
            mysqli_free_result($result);
        }
    }

    $result = admin_db_query($conn, 'SELECT id, name, email, role, created_at FROM users ORDER BY id ASC');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        mysqli_free_result($result);
    }
    foreach ($users as $user) {
        $usersById[$user['id']] = $user['name'];
        if ((string) $user['id'] === $currentAdminProfile['id']) {
            $currentAdminProfile = array_merge($currentAdminProfile, $user);
        }
    }

    $hasSubjectSemesterColumn = admin_column_exists($conn, 'subjects', 'semester_id');
    $subjectSelectSql = 'SELECT id, user_id, name, description, color, target_hours, start_date, start_time, end_time, created_at, updated_at';
    if ($hasSubjectSemesterColumn) {
        $subjectSelectSql .= ', semester_id';
    }
    $subjectSelectSql .= ' FROM subjects ORDER BY id ASC';
    $result = admin_db_query($conn, $subjectSelectSql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $subjects[] = $row;
        }
        mysqli_free_result($result);
    }
    foreach ($subjects as $subject) {
        $subjectsById[$subject['id']] = $subject['name'];
        $semesterId = isset($subject['semester_id']) ? (string) $subject['semester_id'] : '';
        if ($hasSubjectSemesterColumn && $semesterId !== '' && isset($semesterById[$semesterId])) {
            $subjectTermsById[$subject['id']] = $semesterById[$semesterId]['key'];
        } else {
            $subjectTermsById[$subject['id']] = admin_term_key_from_date($subject['start_date']);
        }
    }

    $result = admin_db_query($conn, 'SELECT id, user_id, subject_id, day_of_week, start_time, end_time, room, schedule_type, created_at, updated_at FROM schedules ORDER BY id ASC');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $schedules[] = $row;
        }
        mysqli_free_result($result);
    }
    if (count($schedules) === 0 && admin_table_exists($conn, 'study_calendar_events')) {
        $result = admin_db_query($conn, 'SELECT id, user_id, subject_id, start_time, end_time, event_type, created_at, updated_at FROM study_calendar_events ORDER BY id ASC');
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $startTime = $row['start_time'];
                $endTime = $row['end_time'];
                $startTs = $startTime ? strtotime($startTime) : false;
                $endTs = $endTime ? strtotime($endTime) : false;
                $schedules[] = [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'],
                    'subject_id' => $row['subject_id'],
                    'day_of_week' => $startTs ? date('l', $startTs) : 'N/A',
                    'start_time' => $startTs ? date('H:i:s', $startTs) : null,
                    'end_time' => $endTs ? date('H:i:s', $endTs) : null,
                    'room' => null,
                    'schedule_type' => $row['event_type'],
                    'term_key' => admin_term_key_from_date($row['start_time']),
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }
            mysqli_free_result($result);
        }
    }

    $result = admin_db_query($conn, 'SELECT id, title, channel, status, notify_at FROM learning_notifications ORDER BY id ASC');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        mysqli_free_result($result);
    }

    $result = admin_db_query($conn, 'SELECT id, user_id, subject_id, title, log_date, duration_minutes FROM study_logs ORDER BY id ASC');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $studyLogs[] = $row;
        }
        mysqli_free_result($result);
    }
    $result = admin_db_query($conn, 'SELECT id, subject_id, title, ai_model, created_at FROM quizzes ORDER BY id ASC');
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $quizzes[] = $row;
        }
        mysqli_free_result($result);
    }
}

if (count($semesterOptions) === 0) {
    $fallbackKeys = [];
    foreach ($subjects as $subject) {
        $key = admin_term_key_from_date($subject['start_date']);
        if ($key !== '') {
            $fallbackKeys[$key] = true;
        }
    }
    foreach ($studyLogs as $log) {
        $key = admin_term_key_from_date($log['log_date']);
        if ($key !== '') {
            $fallbackKeys[$key] = true;
        }
    }
    if (count($fallbackKeys) > 0) {
        $parsed = [];
        foreach (array_keys($fallbackKeys) as $key) {
            $parts = explode('-', $key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $year = $parts[0];
            $term = $parts[1];
            $parsed[] = [
                'key' => $key,
                'label' => $term . '-' . $year,
                'term' => $term,
                'year' => $year,
            ];
        }
        usort($parsed, function ($a, $b) {
            $yearA = (int) $a['year'];
            $yearB = (int) $b['year'];
            if ($yearA === $yearB) {
                $termA = (int) $a['term'];
                $termB = (int) $b['term'];
                if ($termA === $termB) {
                    return 0;
                }
                return ($termA < $termB) ? -1 : 1;
            }
            return ($yearA < $yearB) ? 1 : -1;
        });
        $semesterOptions = $parsed;
    }
}

$countUsers = $userCountFromDb > 0 ? $userCountFromDb : count($users);
$countSubjects = $subjectCountFromDb > 0 ? $subjectCountFromDb : count($subjects);
$countSchedules = $scheduleCountFromDb > 0 ? $scheduleCountFromDb : count($schedules);
$countNotifications = $notificationCountFromDb > 0 ? $notificationCountFromDb : count($notifications);
$countStudyLogs = $studyLogCountFromDb > 0 ? $studyLogCountFromDb : count($studyLogs);
$countQuizzes = $quizCountFromDb > 0 ? $quizCountFromDb : count($quizzes);

$adminUsersCount = 0;
$memberUsersCount = 0;
foreach ($users as $userRow) {
    $role = isset($userRow['role']) ? strtolower((string) $userRow['role']) : 'user';
    if ($role === 'admin') {
        $adminUsersCount++;
    } else {
        $memberUsersCount++;
    }
}

$subjectsWithTargetsCount = 0;
foreach ($subjects as $subjectRow) {
    $targetHours = isset($subjectRow['target_hours']) ? (float) $subjectRow['target_hours'] : 0;
    if ($targetHours > 0) {
        $subjectsWithTargetsCount++;
    }
}

$classSchedulesCount = 0;
$examSchedulesCount = 0;
foreach ($schedules as $scheduleRow) {
    $scheduleType = admin_schedule_type_normalize(isset($scheduleRow['schedule_type']) ? $scheduleRow['schedule_type'] : '');
    if ($scheduleType === 'exam') {
        $examSchedulesCount++;
    } else {
        $classSchedulesCount++;
    }
}

$totalStudyMinutes = 0;
$activeStudyUsers = [];
foreach ($studyLogs as $logRow) {
    $totalStudyMinutes += isset($logRow['duration_minutes']) ? max(0, (int) $logRow['duration_minutes']) : 0;
    $studyUserId = isset($logRow['user_id']) ? (string) $logRow['user_id'] : '';
    if ($studyUserId !== '') {
        $activeStudyUsers[$studyUserId] = true;
    }
}
$activeStudyUsersCount = count($activeStudyUsers);
$avgStudyMinutes = $countStudyLogs > 0 ? (int) round($totalStudyMinutes / $countStudyLogs) : 0;
$adminProfileInitial = mb_substr(trim((string) $currentAdminProfile['name']), 0, 1, 'UTF-8');
if ($adminProfileInitial === '') {
    $adminProfileInitial = 'ผ';
}
$adminJoinedLabel = isset($currentAdminProfile['created_at']) && trim((string) $currentAdminProfile['created_at']) !== ''
    ? date('d/m/Y H:i', strtotime((string) $currentAdminProfile['created_at']))
    : 'ไม่พบข้อมูล';

$latestActivityTimestamp = 0;
$latestActivityLabel = 'ยังไม่มีข้อมูลล่าสุด';
foreach ([$users, $subjects, $schedules, $studyLogs] as $recordGroup) {
    foreach ($recordGroup as $record) {
        foreach (['updated_at', 'created_at', 'log_date', 'start_date'] as $dateField) {
            if (!isset($record[$dateField]) || trim((string) $record[$dateField]) === '') {
                continue;
            }
            $timestamp = strtotime((string) $record[$dateField]);
            if ($timestamp !== false && $timestamp > $latestActivityTimestamp) {
                $latestActivityTimestamp = $timestamp;
            }
        }
    }
}
if ($latestActivityTimestamp > 0) {
    $latestActivityLabel = date('d/m/Y H:i', $latestActivityTimestamp);
}

$dailySeries = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-{$i} day"));
    $dailySeries[$dateKey] = [
        'date' => $dateKey,
        'logs' => 0,
        'minutes' => 0,
        'active_users' => 0,
        'notifications' => 0,
        'quizzes' => 0,
        'new_users' => 0,
    ];
}
$dailyStudyUsers = [];

foreach ($studyLogs as $log) {
    $dateKey = admin_date_key(isset($log['log_date']) ? $log['log_date'] : null);
    if ($dateKey === null || !isset($dailySeries[$dateKey])) {
        continue;
    }
    $dailySeries[$dateKey]['logs']++;
    $duration = isset($log['duration_minutes']) ? (int) $log['duration_minutes'] : 0;
    if ($duration > 0) {
        $dailySeries[$dateKey]['minutes'] += $duration;
    }
    $userId = isset($log['user_id']) ? (string) $log['user_id'] : '';
    if ($userId !== '') {
        if (!isset($dailyStudyUsers[$dateKey])) {
            $dailyStudyUsers[$dateKey] = [];
        }
        $dailyStudyUsers[$dateKey][$userId] = true;
    }
}

foreach ($dailyStudyUsers as $dateKey => $userSet) {
    if (!isset($dailySeries[$dateKey])) {
        continue;
    }
    $dailySeries[$dateKey]['active_users'] = count($userSet);
}

foreach ($notifications as $notification) {
    $dateKey = admin_date_key(isset($notification['notify_at']) ? $notification['notify_at'] : null);
    if ($dateKey !== null && isset($dailySeries[$dateKey])) {
        $dailySeries[$dateKey]['notifications']++;
    }
}

foreach ($quizzes as $quiz) {
    $dateKey = admin_date_key(isset($quiz['created_at']) ? $quiz['created_at'] : null);
    if ($dateKey !== null && isset($dailySeries[$dateKey])) {
        $dailySeries[$dateKey]['quizzes']++;
    }
}

foreach ($users as $user) {
    $dateKey = admin_date_key(isset($user['created_at']) ? $user['created_at'] : null);
    if ($dateKey !== null && isset($dailySeries[$dateKey])) {
        $dailySeries[$dateKey]['new_users']++;
    }
}

$todayKey = date('Y-m-d');
$yesterdayKey = date('Y-m-d', strtotime('-1 day'));
$todayDaily = isset($dailySeries[$todayKey]) ? $dailySeries[$todayKey] : [
    'logs' => 0, 'minutes' => 0, 'active_users' => 0, 'notifications' => 0, 'quizzes' => 0, 'new_users' => 0
];
$yesterdayDaily = isset($dailySeries[$yesterdayKey]) ? $dailySeries[$yesterdayKey] : [
    'logs' => 0, 'minutes' => 0, 'active_users' => 0, 'notifications' => 0, 'quizzes' => 0, 'new_users' => 0
];

$logsDelta = admin_delta_label($todayDaily['logs'], $yesterdayDaily['logs'], 'บันทึก');
$minutesDelta = admin_delta_label($todayDaily['minutes'], $yesterdayDaily['minutes'], 'นาที');
$activeDelta = admin_delta_label($todayDaily['active_users'], $yesterdayDaily['active_users'], 'คน');
$notifyDelta = admin_delta_label($todayDaily['notifications'], $yesterdayDaily['notifications'], 'แจ้งเตือน');

$countByDate = function ($items, $field, $dateKey) {
    $count = 0;
    foreach ($items as $item) {
        $itemDateKey = admin_date_key(isset($item[$field]) ? $item[$field] : null);
        if ($itemDateKey === $dateKey) {
            $count++;
        }
    }
    return $count;
};

$todayUsersCreated = $countByDate($users, 'created_at', $todayKey);
$yesterdayUsersCreated = $countByDate($users, 'created_at', $yesterdayKey);
$todaySubjectsCreated = $countByDate($subjects, 'created_at', $todayKey);
$yesterdaySubjectsCreated = $countByDate($subjects, 'created_at', $yesterdayKey);
$todaySchedulesCreated = $countByDate($schedules, 'created_at', $todayKey);
$yesterdaySchedulesCreated = $countByDate($schedules, 'created_at', $yesterdayKey);

$usersDelta = admin_delta_label($todayUsersCreated, $yesterdayUsersCreated, 'คน');
$subjectsDelta = admin_delta_label($todaySubjectsCreated, $yesterdaySubjectsCreated, 'วิชา');
$schedulesDelta = admin_delta_label($todaySchedulesCreated, $yesterdaySchedulesCreated, 'ตาราง');

$currentHour = (int) date('G');
$greetingText = 'สวัสดี';
if ($currentHour < 12) {
    $greetingText = 'สวัสดีตอนเช้า';
} elseif ($currentHour < 17) {
    $greetingText = 'สวัสดีตอนบ่าย';
} else {
    $greetingText = 'สวัสดีตอนเย็น';
}

$dailySeriesRows = array_values($dailySeries);
$dailyRowsForDisplay = array_reverse($dailySeriesRows);
$allLogDates = [];
foreach ($studyLogs as $logRow) {
    $logDateKey = admin_date_key(isset($logRow['log_date']) ? $logRow['log_date'] : null);
    if ($logDateKey !== null) {
        if (!isset($allLogDates[$logDateKey])) {
            $allLogDates[$logDateKey] = 0;
        }
        $allLogDates[$logDateKey]++;
    }
}
$allUserCreatedDates = [];
foreach ($users as $userRow) {
    $userDateKey = admin_date_key(isset($userRow['created_at']) ? $userRow['created_at'] : null);
    if ($userDateKey !== null) {
        $allUserCreatedDates[] = $userDateKey;
    }
}
sort($allUserCreatedDates);

$buildTrendWindow = function ($days) use ($allLogDates, $allUserCreatedDates) {
    $labels = [];
    $logs = [];
    $users = [];
    $dates = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $dateKey = date('Y-m-d', strtotime("-{$i} day"));
        $dates[] = $dateKey;
        $labels[] = date('d/m', strtotime($dateKey));
        $logs[] = isset($allLogDates[$dateKey]) ? (int) $allLogDates[$dateKey] : 0;
    }
    $runningUserTotal = 0;
    $userCursor = 0;
    $userDateCount = count($allUserCreatedDates);
    foreach ($dates as $dateKey) {
        while ($userCursor < $userDateCount && $allUserCreatedDates[$userCursor] <= $dateKey) {
            $runningUserTotal++;
            $userCursor++;
        }
        $users[] = $runningUserTotal;
    }
    return ['labels' => $labels, 'users' => $users, 'logs' => $logs];
};

$trendWeek = $buildTrendWindow(7);
$trendMonth = $buildTrendWindow(30);

$trendYearLabels = [];
$trendYearLogs = [];
$trendYearUsers = [];
$yearMonthKeys = [];
for ($i = 11; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime(date('Y-m-01') . " -{$i} month"));
    $yearMonthKeys[] = $monthKey;
    $trendYearLabels[] = date('m/Y', strtotime($monthKey . '-01'));
    $trendYearLogs[] = 0;
}
foreach ($allLogDates as $logDateKey => $logCount) {
    $monthKey = date('Y-m', strtotime($logDateKey));
    $monthIndex = array_search($monthKey, $yearMonthKeys, true);
    if ($monthIndex !== false) {
        $trendYearLogs[$monthIndex] += (int) $logCount;
    }
}
$runningUserTotal = 0;
$userCursor = 0;
$userDateCount = count($allUserCreatedDates);
foreach ($yearMonthKeys as $monthKey) {
    $monthEnd = date('Y-m-t', strtotime($monthKey . '-01'));
    while ($userCursor < $userDateCount && $allUserCreatedDates[$userCursor] <= $monthEnd) {
        $runningUserTotal++;
        $userCursor++;
    }
    $trendYearUsers[] = $runningUserTotal;
}
$trendChartData = [
    'week' => $trendWeek,
    'month' => $trendMonth,
    'year' => [
        'labels' => $trendYearLabels,
        'users' => $trendYearUsers,
        'logs' => $trendYearLogs,
    ],
];

$latestUsersPreview = array_slice(array_reverse($users), 0, 5);
$latestSubjectsPreview = array_slice(array_reverse($subjects), 0, 5);
$recentActivities = [];

foreach ($latestUsersPreview as $userPreview) {
    $recentActivities[] = [
        'icon_class' => 'violet',
        'icon' => '👤',
        'title' => 'เพิ่มผู้ใช้งานใหม่',
        'meta' => isset($userPreview['name']) ? (string) $userPreview['name'] : 'ผู้ใช้งานใหม่',
        'time' => isset($userPreview['created_at']) ? (string) $userPreview['created_at'] : '',
    ];
}

foreach (array_slice(array_reverse($subjects), 0, 4) as $subjectPreview) {
    $recentActivities[] = [
        'icon_class' => 'blue',
        'icon' => '📘',
        'title' => 'สร้างวิชาเรียน',
        'meta' => isset($subjectPreview['name']) ? (string) $subjectPreview['name'] : 'วิชาเรียน',
        'time' => isset($subjectPreview['created_at']) ? (string) $subjectPreview['created_at'] : '',
    ];
}

foreach (array_slice(array_reverse($studyLogs), 0, 4) as $logPreview) {
    $activitySubjectId = isset($logPreview['subject_id']) ? (string) $logPreview['subject_id'] : '';
    $activitySubjectName = $activitySubjectId !== '' && isset($subjectsById[$activitySubjectId]) ? $subjectsById[$activitySubjectId] : 'บันทึกการเรียน';
    $recentActivities[] = [
        'icon_class' => 'orange',
        'icon' => '📝',
        'title' => 'บันทึกการเรียน',
        'meta' => $activitySubjectName,
        'time' => isset($logPreview['log_date']) ? (string) $logPreview['log_date'] : '',
    ];
}

usort($recentActivities, function ($a, $b) {
    $timeA = isset($a['time']) ? strtotime((string) $a['time']) : false;
    $timeB = isset($b['time']) ? strtotime((string) $b['time']) : false;
    $timeA = $timeA === false ? 0 : $timeA;
    $timeB = $timeB === false ? 0 : $timeB;
    return $timeB <=> $timeA;
});

$recentActivities = array_slice($recentActivities, 0, 5);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบการเรียน - ผู้ดูแลระบบ</title>
    <link rel="icon" type="image/png" href="<?php echo admin_h($faviconUrl); ?>">
    <link rel="shortcut icon" href="<?php echo admin_h($faviconUrl); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo admin_h(($baseDir !== '' ? $baseDir : '') . '/slty.css'); ?>">
</head>
<body>
    <div class="layout" id="adminLayout">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-badge brand-cluster" aria-hidden="true">
                    <i class="dot dot-a"></i>
                    <i class="dot dot-b"></i>
                    <i class="dot dot-c"></i>
                    <i class="dot dot-d"></i>
                    <i class="dot dot-e"></i>
                </span>
                <span class="brand-text">
                    <strong>Smart Learning</strong>
                    <small>Tracker</small>
                </span>
            </div>
            <div class="user-card">
                <div class="name">
                    <span class="greet">สวัสดี,</span>
                    <span class="username"><?php echo admin_h(isset($_SESSION['username']) ? $_SESSION['username'] : 'ผู้ดูแล'); ?></span>
                </div>
                <div class="role">
                    <span class="role-icon">🛡</span>
                    <span>ผู้ดูแลระบบหลัก</span>
                </div>
            </div>
            <nav class="nav">
                <a class="active" href="#" data-nav="dashboard">
                    <span class="icon blue">📊</span>
                    <span class="label">ภาพรวมระบบ</span>
                </a>
                <a href="#" data-nav="users">
                    <span class="icon blue">👥</span>
                    <span class="label">ผู้ใช้งาน</span>
                </a>
                <a href="#" data-nav="subjects">
                    <span class="icon cyan">📚</span>
                    <span class="label">วิชาเรียน</span>
                </a>
                <a href="#" data-nav="schedules">
                    <span class="icon cyan">🗓️</span>
                    <span class="label">ตารางเรียน</span>
                </a>
                <a href="#" data-nav="study-logs">
                    <span class="icon purple">🧾</span>
                    <span class="label">บันทึกการเรียน</span>
                </a>
                <a href="#" data-nav="settings">
                    <span class="icon gray">⚙️</span>
                    <span class="label">ตั้งค่าระบบ</span>
                </a>

                <a href="<?php echo admin_h(($baseDir !== '' ? $baseDir : '') . '/admin_logout.php'); ?>">
                    <span class="icon gray">🚪</span>
                    <span class="label">ออกจากระบบ</span>
                </a>
            </nav>
            <div class="sidebar-footer">ระบบติดตามการเรียนรู้</div>
        </aside>

        <main class="main">
     
            <section class="page-card">
                <div class="card-header" id="mainPanelHeader">
                    <div class="panel-context">
                        <div class="panel-kicker" id="panelKicker">ศูนย์ควบคุมผู้ดูแล</div>
                        <div class="card-title" id="cardTitle">ภาพรวมระบบ</div>
                        <div class="panel-subtitle" id="cardSubtitle">ภาพรวมข้อมูลจริงของผู้ใช้ วิชา ตารางเรียน และบันทึกการเรียน</div>
                    </div>
                </div>

                <div class="panel-toolbar" id="mainPanelToolbar">
                    <select id="userFilter" style="min-width:190px;">
                        <option value="">ทุกผู้ใช้งาน</option>
                        <?php foreach ($users as $userOption): ?>
                            <option value="<?php echo admin_h($userOption['id']); ?>">
                                <?php echo admin_h($userOption['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="termFilter" style="min-width:160px;">
                        <option value="">ทุกเทอม</option>
                        <?php if (count($semesterOptions) > 0): ?>
                            <?php foreach ($semesterOptions as $semesterOption): ?>
                                <option value="<?php echo admin_h($semesterOption['key']); ?>">
                                    <?php echo admin_h($semesterOption['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="1">ภาคต้น</option>
                            <option value="2">ภาคปลาย</option>
                            <option value="3">ฤดูร้อน</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="tab-panel active" data-panel="dashboard">
                    <div class="table-wrap">
                        <div class="stats-grid" id="adminBarChart">
                            <div class="stat-card">
                                <div class="stat-icon violet">👥</div>
                                <div class="stat-label" id="dashboardUsersLabel">ผู้ใช้ทั้งหมด</div>
                                <div class="stat-value" id="dashboardUsersValue"><?php echo (int) $countUsers; ?></div>
                                <div class="stat-sub" id="dashboardUsersSub">คนในระบบ</div>
                                <div class="stat-trend <?php echo admin_h($usersDelta['class']); ?>"><?php echo admin_h($usersDelta['text']); ?></div>
                                <div class="stat-mini-chart">
                                    <div class="bar-item compact">
                                        <div class="bar-value" id="dashboardUsersBarValue"><?php echo (int) $countUsers; ?></div>
                                        <div class="bar-track">
                                            <div class="bar-fill" id="dashboardUsersBar" data-value="<?php echo (int) $countUsers; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon blue">🎓</div>
                                <div class="stat-label">วิชาเรียน</div>
                                <div class="stat-value" id="dashboardSubjectsValue"><?php echo (int) $countSubjects; ?></div>
                                <div class="stat-sub" id="dashboardSubjectsSub">วิชาที่บันทึกไว้</div>
                                <div class="stat-trend <?php echo admin_h($subjectsDelta['class']); ?>"><?php echo admin_h($subjectsDelta['text']); ?></div>
                                <div class="stat-mini-chart">
                                    <div class="bar-item compact">
                                        <div class="bar-value" id="dashboardSubjectsBarValue"><?php echo (int) $countSubjects; ?></div>
                                        <div class="bar-track">
                                            <div class="bar-fill" id="dashboardSubjectsBar" data-value="<?php echo (int) $countSubjects; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon green">🗓</div>
                                <div class="stat-label">ตารางเรียน</div>
                                <div class="stat-value" id="dashboardSchedulesValue"><?php echo (int) $countSchedules; ?></div>
                                <div class="stat-sub" id="dashboardSchedulesSub">รายการตารางทั้งหมด</div>
                                <div class="stat-trend <?php echo admin_h($schedulesDelta['class']); ?>"><?php echo admin_h($schedulesDelta['text']); ?></div>
                                <div class="stat-mini-chart">
                                    <div class="bar-item compact">
                                        <div class="bar-value" id="dashboardSchedulesBarValue"><?php echo (int) $countSchedules; ?></div>
                                        <div class="bar-track">
                                            <div class="bar-fill" id="dashboardSchedulesBar" data-value="<?php echo (int) $countSchedules; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon orange">🧾</div>
                                <div class="stat-label">บันทึกการเรียน</div>
                                <div class="stat-value" id="dashboardStudyLogsValue"><?php echo (int) $countStudyLogs; ?></div>
                                <div class="stat-sub" id="dashboardStudyLogsSub">รายการบันทึกทั้งหมด</div>
                                <div class="stat-trend <?php echo admin_h($logsDelta['class']); ?>"><?php echo admin_h($logsDelta['text']); ?></div>
                                <div class="stat-mini-chart">
                                    <div class="bar-item compact">
                                        <div class="bar-value" id="dashboardStudyLogsBarValue"><?php echo (int) $countStudyLogs; ?></div>
                                        <div class="bar-track">
                                            <div class="bar-fill" id="dashboardStudyLogsBar" data-value="<?php echo (int) $countStudyLogs; ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-insights-grid">
                            <div class="chart-card trend-card">
                                <div class="trend-head">
                                    <div class="trend-title-wrap">
                                        <span class="trend-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M3 12h4l2-5 4 10 2-5h6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <div class="chart-title">แนวโน้มการใช้งาน (7 วันย้อนหลัง)</div>
                                    </div>
                                    <div class="trend-legend" aria-hidden="true">
                                        <span class="trend-legend-item"><i class="dot users"></i>ผู้ใช้งาน</span>
                                        <span class="trend-legend-item"><i class="dot logs"></i>บันทึก</span>
                                    </div>
                                    <select id="trendRangeSelect" class="trend-range-select" aria-label="ช่วงเวลาแนวโน้ม">
                                        <option value="week">สัปดาห์</option>
                                        <option value="month">เดือน</option>
                                        <option value="year">ปี</option>
                                    </select>
                                </div>
                                <div class="trend-chart" id="adminTrendChart" data-trend="<?php echo admin_h(json_encode($trendChartData, JSON_UNESCAPED_UNICODE)); ?>">
                                    <svg viewBox="0 0 920 320" preserveAspectRatio="none" role="img" aria-label="กราฟแนวโน้มผู้ใช้งานและบันทึกการเรียนย้อนหลัง 7 วัน"></svg>
                                </div>
                            </div>

                            <div class="dashboard-side-stack">
                                <div class="chart-card activity-card">
                                    <div class="activity-head">
                                        <div class="chart-title">กิจกรรมล่าสุด</div>
                                        <button type="button" class="mini-link-btn">ดูทั้งหมด</button>
                                    </div>
                                    <div class="activity-list">
                                        <?php if (count($recentActivities) === 0): ?>
                                            <div class="activity-empty">ยังไม่มีกิจกรรมล่าสุด</div>
                                        <?php else: ?>
                                            <?php foreach ($recentActivities as $activity): ?>
                                                <div class="activity-item">
                                                    <span class="activity-icon <?php echo admin_h($activity['icon_class']); ?>"><?php echo admin_h($activity['icon']); ?></span>
                                                    <span class="activity-copy">
                                                        <strong><?php echo admin_h($activity['title']); ?></strong>
                                                        <small><?php echo admin_h($activity['meta']); ?></small>
                                                    </span>
                                                    <span class="activity-time"><?php echo admin_h(admin_format_datetime_short($activity['time'])); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-bottom-grid">
                            <div class="daily-table-card users-latest-card">
                                <div class="daily-head">
                                    <span class="daily-head-icon">👤</span>
                                    <div>
                                        <h3 class="daily-table-title">ผู้ใช้งานล่าสุด <span class="title-sparkle">✦</span></h3>
                                        <div class="daily-head-sub">สรุปรายชื่อผู้ใช้งานที่เพิ่มเข้ามาล่าสุด</div>
                                    </div>
                                    <button type="button" class="mini-link-btn">ดูทั้งหมด <span aria-hidden="true">›</span></button>
                                </div>
                                <div class="daily-table-wrap">
                                    <table class="daily-table compact-table">
                                        <thead>
                                            <tr>
                                                <th>👤 ชื่อผู้ใช้งาน</th>
                                                <th>✉️ อีเมล</th>
                                                <th>🛡️ บทบาท</th>
                                                <th>📅 วันที่เพิ่ม</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($latestUsersPreview) === 0): ?>
                                                <tr>
                                                    <td colspan="5">ยังไม่มีข้อมูล</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($latestUsersPreview as $userPreview): ?>
                                                    <?php $previewRole = isset($userPreview['role']) ? strtolower((string) $userPreview['role']) : 'user'; ?>
                                                    <tr>
                                                        <td>
                                                            <div class="user-cell compact">
                                                                <span class="user-avatar"><?php echo admin_h(function_exists('mb_substr') ? mb_substr((string) $userPreview['name'], 0, 1, 'UTF-8') : substr((string) $userPreview['name'], 0, 1)); ?></span>
                                                                <span class="user-name"><?php echo admin_h($userPreview['name']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><?php echo admin_h($userPreview['email']); ?></td>
                                                        <td><span class="badge role-badge <?php echo $previewRole === 'admin' ? 'red' : 'green'; ?>"><?php echo admin_h($previewRole === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป'); ?></span></td>
                                                        <td><?php echo admin_h(admin_format_short_date(isset($userPreview['created_at']) ? $userPreview['created_at'] : '')); ?></td>
                                                        <td class="row-more-cell"><button type="button" class="row-more-btn" aria-label="เพิ่มเติม">⋮</button></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer-action">
                                    <button type="button" class="footer-outline-btn">→ ผู้ใช้ทั้งหมด</button>
                                </div>
                            </div>

                            <div class="daily-table-card subject-latest-card">
                                <div class="daily-head">
                                    <span class="daily-head-icon subject">📚</span>
                                    <div>
                                        <h3 class="daily-table-title">วิชาเรียนที่เพิ่มล่าสุด <span class="title-sparkle">✦</span></h3>
                                        <div class="daily-head-sub">สรุปรายวิชาล่าสุดที่ถูกสร้างในระบบ</div>
                                    </div>
                                    <button type="button" class="mini-link-btn">ดูทั้งหมด <span aria-hidden="true">›</span></button>
                                </div>
                                <div class="daily-table-wrap">
                                    <table class="daily-table compact-table">
                                        <thead>
                                            <tr>
                                                <th>📘 วิชา</th>
                                                <th>👤 ผู้ใช้งาน</th>
                                                <th>🕘 เวลา</th>
                                                <th>📅 วันที่เริ่ม</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($latestSubjectsPreview) === 0): ?>
                                                <tr>
                                                    <td colspan="4">ยังไม่มีข้อมูล</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($latestSubjectsPreview as $subjectPreview): ?>
                                                    <?php $subjectOwnerId = isset($subjectPreview['user_id']) ? (string) $subjectPreview['user_id'] : ''; ?>
                                                    <tr>
                                                        <td>
                                                            <div class="subject-name-cell">
                                                                <span class="subject-icon-badge">📘</span>
                                                                <span><?php echo admin_h(isset($subjectPreview['name']) ? $subjectPreview['name'] : '-'); ?></span>
                                                            </div>
                                                        </td>
                                                        <td><?php echo admin_h($subjectOwnerId !== '' && isset($usersById[$subjectOwnerId]) ? $usersById[$subjectOwnerId] : $subjectOwnerId); ?></td>
                                                        <td><?php echo admin_h((isset($subjectPreview['start_time']) ? $subjectPreview['start_time'] : '-') . ' - ' . (isset($subjectPreview['end_time']) ? $subjectPreview['end_time'] : '-')); ?></td>
                                                        <td><?php echo admin_h(admin_format_short_date(isset($subjectPreview['start_date']) ? $subjectPreview['start_date'] : '')); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-panel" data-panel="users">
                    <div class="table-wrap">
                        <div class="section-heading">
                            <div>
                                <div class="section-kicker">จัดการผู้ใช้งาน</div>
                                <h3>จัดการบัญชีผู้ใช้จากข้อมูลจริง</h3>
                              
                            </div>
                            <div class="section-metrics">
                                <div class="mini-metric">
                                    <span class="mini-label">ผู้ใช้ทั้งหมด</span>
                                    <strong><?php echo (int) $countUsers; ?></strong>
                                </div>
                                <div class="mini-metric">
                                    <span class="mini-label">ผู้ดูแล</span>
                                    <strong><?php echo (int) $adminUsersCount; ?></strong>
                                </div>
                                <div class="mini-metric">
                                    <span class="mini-label">สมาชิก</span>
                                    <strong><?php echo (int) $memberUsersCount; ?></strong>
                                </div>
                            </div>
                        </div>
                        <table class="table user-table" data-table="users">
                            <thead>
                                <tr>
                                    <th data-field="name" data-editable="1" data-create="1">ชื่อผู้ใช้งาน</th>
                                    <th data-field="email" data-editable="1" data-create="1">อีเมล</th>
                                    <th data-field="role" data-editable="1" data-create="1">บทบาท</th>
                                    <th>สถานะ</th>
                                    <th data-field="created_at" data-editable="0" data-create="0">วันที่เพิ่ม</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) === 0): ?>
                                    <tr>
                                        <td colspan="6">ยังไม่มีข้อมูล</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <?php $role = isset($user['role']) ? strtolower((string) $user['role']) : 'user'; ?>
                                        <tr data-table="users" data-id="<?php echo admin_h($user['id']); ?>">
                                            <td data-field="name">
                                                <div class="user-cell">
                                                    <span class="user-avatar"><?php echo admin_h(function_exists('mb_substr') ? mb_substr((string) $user['name'], 0, 1, 'UTF-8') : substr((string) $user['name'], 0, 1)); ?></span>
                                                    <span class="user-name"><?php echo admin_h($user['name']); ?></span>
                                                </div>
                                            </td>
                                            <td data-field="email"><?php echo admin_h($user['email']); ?></td>
                                            <td data-field="role">
                                                <span class="badge role-badge <?php echo $role === 'admin' ? 'red' : 'green'; ?>"><?php echo admin_h($role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป'); ?></span>
                                            </td>
                                            <td><span class="status-pill on">ใช้งาน</span></td>
                                            <td data-field="created_at"><?php echo admin_h(admin_format_short_date(isset($user['created_at']) ? $user['created_at'] : '')); ?></td>
                                            <td>
                                                <span class="tools user-actions">
                                                    <button type="button" class="tool blue icon-only" aria-label="แก้ไข" title="แก้ไข">✏️</button>
                                                    <button type="button" class="tool gray icon-only" aria-label="ดูรายละเอียด" title="ดูรายละเอียด">👁</button>
                                                    <button type="button" class="tool red icon-only" aria-label="ลบ" title="ลบ">🗑</button>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pager">
                        <div class="page-btn">‹</div>
                        <div class="page-current">1</div>
                        <div class="page-btn">›</div>
                    </div>
                </div>

                <div class="tab-panel" data-panel="subjects">
                    <div class="table-wrap">
                        <div class="section-heading">
                            <div>
                                <div class="section-kicker">คลังวิชาเรียน</div>
                                <h3>ภาพรวมวิชาเรียนทั้งหมด</h3>
                              
                            </div>
                            <div class="section-metrics">
                                <div class="mini-metric">
                                    <span class="mini-label">วิชาทั้งหมด</span>
                                    <strong><?php echo (int) $countSubjects; ?></strong>
                                </div>
                                <div class="mini-metric">
                                    <span class="mini-label">มีเป้าหมาย</span>
                                    <strong><?php echo (int) $subjectsWithTargetsCount; ?></strong>
                                </div>
                                <div class="mini-metric">
                                    <span class="mini-label">ภาคเรียน</span>
                                    <strong><?php echo (int) count($semesterOptions); ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php $subjectColspan = $hasSubjectSemesterColumn ? 11 : 10; ?>
                        <table class="table" data-table="subjects">
                            <thead>
                                <tr>
                                    <th data-field="id" data-editable="0" data-create="0">รหัส</th>
                                    <th data-field="user_id" data-editable="1" data-create="1">ผู้ใช้งาน</th>
                                    <?php if ($hasSubjectSemesterColumn): ?>
                                        <th data-field="semester_id" data-editable="1" data-create="1">ภาคเรียน</th>
                                    <?php endif; ?>
                                    <th data-field="name" data-editable="1" data-create="1">วิชา</th>
                                    <th data-field="description" data-editable="1" data-create="1">คำอธิบาย</th>
                                    <th data-field="color" data-editable="1" data-create="1">สี</th>
                           
                                    <th data-field="start_date" data-editable="1" data-create="1">วันที่เริ่ม</th>
                                    <th data-field="start_time" data-editable="1" data-create="1">เวลาเริ่ม</th>
                                    <th data-field="end_time" data-editable="1" data-create="1">เวลาสิ้นสุด</th>
                                    <th>ดูอย่างเดียว</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($subjects) === 0): ?>
                                    <tr>
                                        <td colspan="<?php echo admin_h($subjectColspan); ?>">ยังไม่มีข้อมูล</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($subjects as $subject): ?>
                                        <?php
                                            $subjectUserId = $subject['user_id'];
                                            $subjectUserLabel = isset($usersById[$subjectUserId]) ? $usersById[$subjectUserId] : $subjectUserId;
                                            $subjectSemesterId = isset($subject['semester_id']) ? (string) $subject['semester_id'] : '';
                                            $subjectSemesterLabel = $subjectSemesterId !== '' && isset($semesterById[$subjectSemesterId]) ? $semesterById[$subjectSemesterId]['label'] : '';
                                        ?>
                                        <?php $subjectTermKey = admin_term_key_from_date($subject['start_date']); ?>
                                        <?php if ($hasSubjectSemesterColumn && $subjectSemesterId !== '' && isset($semesterById[$subjectSemesterId])) { $subjectTermKey = $semesterById[$subjectSemesterId]['key']; } ?>
                                        <tr data-table="subjects" data-id="<?php echo admin_h($subject['id']); ?>" data-term="<?php echo admin_h($subjectTermKey); ?>">
                                            <td data-field="id"><?php echo admin_h($subject['id']); ?></td>
                                            <td data-field="user_id" data-value="<?php echo admin_h($subjectUserId); ?>">
                                                <?php echo admin_h($subjectUserLabel); ?>
                                            </td>
                                            <?php if ($hasSubjectSemesterColumn): ?>
                                                <td data-field="semester_id" data-value="<?php echo admin_h($subjectSemesterId); ?>">
                                                    <?php echo admin_h($subjectSemesterLabel !== '' ? $subjectSemesterLabel : $subjectSemesterId); ?>
                                                </td>
                                            <?php endif; ?>
                                            <td data-field="name"><?php echo admin_h($subject['name']); ?></td>
                                            <td data-field="description"><?php echo admin_h($subject['description']); ?></td>
                                            <td data-field="color">
                                                <?php $color = isset($subject['color']) ? (string) $subject['color'] : ''; ?>
                                                <span class="badge" style="background:<?php echo admin_h($color !== '' ? $color : '#64748b'); ?>;"><?php echo admin_h($color); ?></span>
                                            </td>
                                            <td data-field="target_hours"><?php echo admin_h($subject['target_hours']); ?></td>
                                            <td data-field="start_date"><?php echo admin_h($subject['start_date']); ?></td>
                                            <td data-field="start_time"><?php echo admin_h($subject['start_time']); ?></td>
                                            <td data-field="end_time"><?php echo admin_h($subject['end_time']); ?></td>
                                            <td>
                                                <span class="tools">
                                                    <button type="button" class="tool gray">ดู</button>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pager">
                        <div class="page-btn">‹</div>
                        <div class="page-current">1</div>
                        <div class="page-btn">›</div>
                    </div>
                </div>

                <div class="tab-panel" data-panel="schedules">
                    <div class="table-wrap">
                        <div class="section-heading">
                            <div>
                                <div class="section-kicker">ควบคุมตารางเรียน</div>
                                <h3>ตารางเรียนและกิจกรรมทั้งหมด</h3>
                              
                            </div>
                            <div class="section-metrics">
                                <div class="mini-metric">
                                    <span class="mini-label">ตารางทั้งหมด</span>
                                    <strong><?php echo (int) $countSchedules; ?></strong>
                                </div>
                                <div class="mini-metric">
                                    <span class="mini-label">คาบเรียน</span>
                                    <strong><?php echo (int) $classSchedulesCount; ?></strong>
                                </div>
                                <div class="mini-metric">
                                    <span class="mini-label">สอบ/พิเศษ</span>
                                    <strong><?php echo (int) $examSchedulesCount; ?></strong>
                                </div>
                            </div>
                        </div>
                        <table class="table" data-table="schedules">
                            <thead>
                                <tr>
                                    <th data-field="id" data-editable="0" data-create="0">รหัส</th>
                                    <th data-field="user_id" data-editable="1" data-create="1">ผู้ใช้งาน</th>
                                    <th data-field="subject_id" data-editable="1" data-create="1">วิชา</th>
                                    <th data-field="day_of_week" data-editable="1" data-create="1">วัน</th>
                                    <th data-field="start_time" data-editable="1" data-create="1">เวลาเริ่ม</th>
                                    <th data-field="end_time" data-editable="1" data-create="1">เวลาสิ้นสุด</th>
                                    <th data-field="room" data-editable="1" data-create="1">ห้อง</th>
                                    <th data-field="schedule_type" data-editable="1" data-create="1">ประเภท</th>
                                    <th>จำนวน</th>
                                    <th>ดูอย่างเดียว</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($schedules) === 0): ?>
                                    <tr>
                                        <td colspan="10">ยังไม่มีข้อมูล</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <?php
                                            $scheduleUserId = $schedule['user_id'];
                                            $scheduleSubjectId = $schedule['subject_id'];
                                            $scheduleUserLabel = isset($usersById[$scheduleUserId]) ? $usersById[$scheduleUserId] : $scheduleUserId;
                                            $scheduleSubjectLabel = isset($subjectsById[$scheduleSubjectId]) ? $subjectsById[$scheduleSubjectId] : $scheduleSubjectId;
                                            $scheduleTermKey = '';
                                            if (isset($schedule['term_key']) && $schedule['term_key'] !== '') {
                                                $scheduleTermKey = $schedule['term_key'];
                                            } elseif (isset($schedule['term']) && $schedule['term'] !== '') {
                                                $scheduleTermKey = $schedule['term'];
                                            } elseif (isset($subjectTermsById[$scheduleSubjectId])) {
                                                $scheduleTermKey = $subjectTermsById[$scheduleSubjectId];
                                            }
                                        ?>
                                        <tr data-table="schedules" data-id="<?php echo admin_h($schedule['id']); ?>" data-term="<?php echo admin_h($scheduleTermKey); ?>">
                                            <td data-field="id"><?php echo admin_h($schedule['id']); ?></td>
                                            <td data-field="user_id" data-value="<?php echo admin_h($scheduleUserId); ?>">
                                                <?php echo admin_h($scheduleUserLabel); ?>
                                            </td>
                                            <td data-field="subject_id" data-value="<?php echo admin_h($scheduleSubjectId); ?>">
                                                <?php echo admin_h($scheduleSubjectLabel); ?>
                                            </td>
                                            <td data-field="day_of_week"><?php echo admin_h($schedule['day_of_week']); ?></td>
                                            <td data-field="start_time"><?php echo admin_h($schedule['start_time']); ?></td>
                                            <td data-field="end_time"><?php echo admin_h($schedule['end_time']); ?></td>
                                            <td data-field="room"><?php echo admin_h($schedule['room']); ?></td>
                                            <td data-field="schedule_type">
                                                <?php $type = isset($schedule['schedule_type']) ? (string) $schedule['schedule_type'] : ''; ?>
                                                <?php $normalizedType = admin_schedule_type_normalize($type); ?>
                                                <span class="badge <?php echo $normalizedType === 'exam' ? 'amber' : 'green'; ?>"><?php echo admin_h(admin_schedule_type_label($type)); ?></span>
                                            </td>
                                            <td>
                                                <?php $dupCount = isset($schedule['duplicate_count']) ? (int) $schedule['duplicate_count'] : 1; ?>
                                                <?php echo $dupCount > 1 ? 'x' . $dupCount : $dupCount; ?>
                                            </td>
                                            <td>
                                                <span class="tools">
                                                    <button type="button" class="tool gray">ดู</button>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pager">
                        <div class="page-btn">‹</div>
                        <div class="page-current">1</div>
                        <div class="page-btn">›</div>
                    </div>
                </div>

                <div class="tab-panel" data-panel="study-logs">
                    <div class="table-wrap">
                        <div class="section-heading">
                            <div>
                                <div class="section-kicker">กิจกรรมการเรียน</div>
                                <h3>บันทึกการเรียนจากผู้ใช้จริง</h3>
                             
                            </div>
                            <div class="section-metrics">
                                <div class="mini-metric">
                                    <span class="mini-label">บันทึกทั้งหมด</span>
                                    <strong><?php echo (int) $countStudyLogs; ?></strong>
                                </div>
                            </div>
                        </div>
                        <table class="table" data-table="study_logs">
                            <thead>
                                <tr>
                                    <th data-field="id" data-editable="0" data-create="0">รหัส</th>
                                    <th data-field="user_id" data-editable="1" data-create="1">ผู้ใช้งาน</th>
                                    <th data-field="subject_id" data-editable="1" data-create="1">วิชา</th>
                                    <th data-field="title" data-editable="1" data-create="1">หัวข้อ</th>
                                    <th data-field="log_date" data-editable="1" data-create="1">วันที่</th>
                                    <th>ดูอย่างเดียว</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($studyLogs) === 0): ?>
                                    <tr>
                                        <td colspan="6">ยังไม่มีข้อมูล</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($studyLogs as $log): ?>
                                        <?php
                                            $logUserId = $log['user_id'];
                                            $logSubjectId = $log['subject_id'];
                                            $logUserLabel = isset($usersById[$logUserId]) ? $usersById[$logUserId] : $logUserId;
                                            $logSubjectLabel = isset($subjectsById[$logSubjectId]) ? $subjectsById[$logSubjectId] : $logSubjectId;
                                            $logTermKey = admin_term_key_from_date($log['log_date']);
                                        ?>
                                        <tr data-table="study_logs" data-id="<?php echo admin_h($log['id']); ?>" data-term="<?php echo admin_h($logTermKey); ?>">
                                            <td data-field="id"><?php echo admin_h($log['id']); ?></td>
                                            <td data-field="user_id" data-value="<?php echo admin_h($logUserId); ?>">
                                                <?php echo admin_h($logUserLabel); ?>
                                            </td>
                                            <td data-field="subject_id" data-value="<?php echo admin_h($logSubjectId); ?>">
                                                <?php echo admin_h($logSubjectLabel); ?>
                                            </td>
                                            <td data-field="title"><?php echo admin_h($log['title']); ?></td>
                                            <td data-field="log_date"><?php echo admin_h($log['log_date']); ?></td>
                                            <td>
                                                <span class="tools">
                                                    <button type="button" class="tool gray">ดู</button>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pager">
                        <div class="page-btn">‹</div>
                        <div class="page-current">1</div>
                        <div class="page-btn">›</div>
                    </div>
                </div>

                <div class="tab-panel" data-panel="settings">
                    <div class="table-wrap">
                        <div class="settings-shell">
                            <div class="settings-card">
                                <div class="settings-topbar">
                                    <div>
                                        <h3 class="settings-title">ตั้งค่าระบบผู้ดูแล</h3>
                                    
                                    </div>
                                    <button class="settings-sync-btn" type="button" id="syncSettingsBtn">ซิงค์ข้อมูล</button>
                                </div>

                                <div class="settings-body">
                                    <div class="field">
                                        <label for="settingSystemName">ชื่อระบบหลัก</label>
                                        <input type="text" id="settingSystemName" placeholder="เช่น ระบบติดตามการเรียนรู้สำหรับผู้ดูแล">
                                    </div>
                                    <div class="field">
                                        <label for="settingContactEmail">อีเมลติดต่อระบบ</label>
                                        <input type="email" id="settingContactEmail" placeholder="เช่น admin@school.ac.th">
                                    </div>
                                    <div class="field wide">
                                        <label for="settingWelcomeMessage">ข้อความต้อนรับผู้ดูแลระบบ</label>
                                        <textarea id="settingWelcomeMessage" rows="4" placeholder="เช่น ยินดีต้อนรับเข้าสู่ระบบจัดการ"></textarea>
                                    </div>
                                    <div class="field wide settings-maintenance">
                                        <div class="settings-maintenance-meta">
                                            <span class="settings-maintenance-icon">⚡</span>
                                            <div>
                                                <h4>โหมดซ่อมบำรุง</h4>
                                            
                                            </div>
                                        </div>
                                        <button class="settings-switch" type="button" id="settingMaintenanceSwitch" aria-pressed="false" title="สลับโหมดซ่อมบำรุง">
                                            <span class="settings-switch-dot"></span>
                                        </button>
                                        <select id="settingMaintenanceMode" class="settings-hidden-select" aria-hidden="true">
                                            <option value="0">ปิด</option>
                                            <option value="1">เปิด</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="settings-actions">
                                    <button class="btn primary" type="button" id="saveSettingsBtn">บันทึกการตั้งค่า</button>
                                    <button class="settings-reset-btn" type="button" id="resetSettingsBtn">โหลดค่าล่าสุด</button>
                                    <span id="settingsStatus" class="settings-status">พร้อมใช้งาน</span>
                                </div>

                                <div class="settings-summary-grid">
                                    <div class="settings-summary-card">
                                        <span class="label">ผู้ใช้งานที่มีการเรียน</span>
                                        <strong><?php echo (int) $activeStudyUsersCount; ?> คน</strong>
                                    </div>
                                    <div class="settings-summary-card">
                                        <span class="label">แบบฝึกหัดอัจฉริยะ</span>
                                        <strong><?php echo (int) $countQuizzes; ?> รายการ</strong>
                                    </div>
                                    <div class="settings-summary-card">
                                        <span class="label">กิจกรรมล่าสุด</span>
                                        <strong><?php echo admin_h($latestActivityLabel); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-panel" data-panel="profile">
                    <div class="table-wrap">
                        <div class="profile-shell">
                            <div class="profile-card profile-summary-card">
                                <div class="profile-hero">
                                    <div class="profile-avatar"><?php echo admin_h($adminProfileInitial); ?></div>
                                    <div class="profile-identity">
                                        <div class="section-kicker">โปรไฟล์ผู้ดูแล</div>
                                        <h3><?php echo admin_h(isset($currentAdminProfile['name']) ? $currentAdminProfile['name'] : 'ผู้ดูแล'); ?></h3>
                                        <p>ข้อมูลบัญชีผู้ดูแลระบบที่กำลังเข้าสู่ระบบอยู่ในขณะนี้</p>
                                    </div>
                                </div>
                                <div class="profile-stat-grid">
                                    <div class="mini-metric">
                                        <span class="mini-label">สิทธิ์</span>
                                        <strong><?php echo admin_h(((string) ($currentAdminProfile['role'] ?? 'admin')) === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานทั่วไป'); ?></strong>
                                    </div>
                                    <div class="mini-metric">
                                        <span class="mini-label">เข้าร่วมเมื่อ</span>
                                        <strong><?php echo admin_h($adminJoinedLabel); ?></strong>
                                    </div>
                                    <div class="mini-metric">
                                        <span class="mini-label">กิจกรรมล่าสุด</span>
                                        <strong><?php echo admin_h($latestActivityLabel); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-grid">
                                <div class="profile-card">
                                    <div class="profile-card-head">
                                        <h4>ข้อมูลบัญชี</h4>
                                        <span>ข้อมูลจริงจากระบบ</span>
                                    </div>
                                    <div class="profile-field-list">
                                        <div class="profile-field">
                                            <label>รหัสผู้ใช้</label>
                                            <div><?php echo admin_h(isset($currentAdminProfile['id']) ? $currentAdminProfile['id'] : '-'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <label>ชื่อผู้ใช้</label>
                                            <div><?php echo admin_h(isset($currentAdminProfile['name']) ? $currentAdminProfile['name'] : 'ผู้ดูแล'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <label>อีเมล</label>
                                            <div><?php echo admin_h(isset($currentAdminProfile['email']) && trim((string) $currentAdminProfile['email']) !== '' ? $currentAdminProfile['email'] : 'ยังไม่มีข้อมูล'); ?></div>
                                        </div>
                                        <div class="profile-field">
                                            <label>ประเภทบัญชี</label>
                                            <div><?php echo admin_h(((string) ($currentAdminProfile['role'] ?? 'admin')) === 'admin' ? 'ผู้ดูแลระบบหลัก' : 'ผู้ใช้งานทั่วไป'); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="profile-card">
                                    <div class="profile-card-head">
                                        <h4>สรุปการดูแลระบบ</h4>
                                        <span>ภาพรวมที่เกี่ยวข้องกับการจัดการ</span>
                                    </div>
                                    <div class="profile-stat-grid compact">
                                        <div class="mini-metric">
                                            <span class="mini-label">ผู้ใช้ในระบบ</span>
                                            <strong><?php echo (int) $countUsers; ?></strong>
                                        </div>
                                        <div class="mini-metric">
                                            <span class="mini-label">วิชาทั้งหมด</span>
                                            <strong><?php echo (int) $countSubjects; ?></strong>
                                        </div>
                                        <div class="mini-metric">
                                            <span class="mini-label">ตารางเรียน</span>
                                            <strong><?php echo (int) $countSchedules; ?></strong>
                                        </div>
                                        <div class="mini-metric">
                                            <span class="mini-label">บันทึกการเรียน</span>
                                            <strong><?php echo (int) $countStudyLogs; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="modal-backdrop" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-heading">
                    <div class="modal-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="modal-title">แก้ไขข้อมูล</div>
                        <div class="modal-subtitle">ปรับรายละเอียดรายการที่เลือก</div>
                    </div>
                </div>
                <button class="modal-close" type="button" id="closeModal" aria-label="ปิด">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form class="modal-body" id="editForm"></form>
            <div class="modal-footer">
                <button class="btn secondary" type="button" id="cancelEdit">ยกเลิก</button>
                <button class="btn primary" type="button" id="saveEdit">บันทึก</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="viewModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-heading">
                    <div class="modal-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                            <path d="M14 2v6h6" />
                            <path d="M9 13h6" />
                            <path d="M9 17h6" />
                            <path d="M9 9h1" />
                        </svg>
                    </div>
                    <div>
                        <div class="modal-title">รายละเอียดข้อมูล</div>
                        <div class="modal-subtitle">รหัสอ้างอิง: <span id="viewReference">-</span></div>
                    </div>
                </div>
                <button class="modal-close" type="button" id="closeViewModal" aria-label="ปิด">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body" id="viewBody"></div>
            <div class="modal-footer">
                <button class="btn secondary" type="button" id="closeView">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="addModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-heading">
                    <div class="modal-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14" />
                            <path d="M5 12h14" />
                        </svg>
                    </div>
                    <div>
                        <div class="modal-title">เพิ่มรายการใหม่</div>
                        <div class="modal-subtitle">กรอกข้อมูลสำหรับรายการใหม่</div>
                    </div>
                </div>
                <button class="modal-close" type="button" id="closeAddModal" aria-label="ปิด">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form class="modal-body" id="addForm"></form>
            <div class="modal-footer">
                <button class="btn secondary" type="button" id="cancelAdd">ยกเลิก</button>
                <button class="btn primary" type="button" id="saveAdd">บันทึก</button>
            </div>
        </div>
    </div>

    <?php
    $semesterFieldOptions = [];
    if ($hasSubjectSemesterColumn) {
        foreach ($semesterOptions as $semesterOption) {
            $optionValue = isset($semesterOption['id']) ? (string) $semesterOption['id'] : '';
            if ($optionValue === '') {
                continue;
            }
            $semesterFieldOptions[] = [
                'value' => $optionValue,
                'label' => isset($semesterOption['label']) ? (string) $semesterOption['label'] : $optionValue,
            ];
        }
    }
    ?>
    <script>
        const currentDateTime = document.getElementById('currentDateTime');
        const dateFormatter = new Intl.DateTimeFormat('th-TH', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            timeZone: 'Asia/Bangkok'
        });
        const timeFormatter = new Intl.DateTimeFormat('th-TH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Bangkok'
        });
        const renderDateTime = () => {
            const now = new Date();
            const formatted = `${dateFormatter.format(now)} เวลา ${timeFormatter.format(now)} น.`;
            if (currentDateTime) {
                currentDateTime.textContent = formatted;
            }
        };
        renderDateTime();
        window.setInterval(renderDateTime, 1000);

        const panels = document.querySelectorAll('.tab-panel');
        const navLinks = document.querySelectorAll('.nav a[data-nav]');
        const cardTitle = document.getElementById('cardTitle');
        const cardSubtitle = document.getElementById('cardSubtitle');
        const panelKicker = document.getElementById('panelKicker');
        const mainPanelHeader = document.getElementById('mainPanelHeader');
        const mainPanelToolbar = document.getElementById('mainPanelToolbar');
        const userFilter = document.getElementById('userFilter');
        const termFilter = document.getElementById('termFilter');
        const trendRangeSelect = document.getElementById('trendRangeSelect');
        const selectOptions = {
            semester_id: <?php echo json_encode($semesterFieldOptions, JSON_UNESCAPED_UNICODE); ?>,
            role: [
                { value: 'admin', label: 'ผู้ดูแลระบบ' },
                { value: 'user', label: 'ผู้ใช้งานทั่วไป' }
            ],
            schedule_type: [
                { value: 'class', label: 'คาบเรียน' },
                { value: 'exam', label: 'สอบหรือกิจกรรมพิเศษ' }
            ]
        };

        const navConfig = {
            dashboard: {
                title: 'ภาพรวมระบบ',
              
                kicker: 'ศูนย์ควบคุมผู้ดูแล',
                panel: 'dashboard',
                showToolbar: true
            },
            users: {
                title: 'ผู้ใช้งาน',
            
                kicker: 'จัดการผู้ใช้งาน',
                panel: 'users',
                showToolbar: true
            },
            subjects: {
                title: 'วิชาเรียน',
               
                kicker: 'คลังวิชาเรียน',
                panel: 'subjects',
                showToolbar: true
            },
            schedules: {
                title: 'ตารางเรียน',
              
                kicker: 'ควบคุมตารางเรียน',
                panel: 'schedules',
                showToolbar: true
            },
            'study-logs': {
                title: 'บันทึกการเรียน',
              
                kicker: 'กิจกรรมการเรียน',
                panel: 'study-logs',
                showToolbar: true
            },
            settings: {
                title: 'ตั้งค่าระบบ',
            
                kicker: 'การตั้งค่าระบบ',
                panel: 'settings',
                showToolbar: false
            }
        };

        const dashboardUsersLabel = document.getElementById('dashboardUsersLabel');
        const dashboardUsersValue = document.getElementById('dashboardUsersValue');
        const dashboardUsersSub = document.getElementById('dashboardUsersSub');
        const dashboardSubjectsValue = document.getElementById('dashboardSubjectsValue');
        const dashboardSubjectsSub = document.getElementById('dashboardSubjectsSub');
        const dashboardSchedulesValue = document.getElementById('dashboardSchedulesValue');
        const dashboardSchedulesSub = document.getElementById('dashboardSchedulesSub');
        const dashboardStudyLogsValue = document.getElementById('dashboardStudyLogsValue');
        const dashboardStudyLogsSub = document.getElementById('dashboardStudyLogsSub');
        const dashboardChartTitle = document.getElementById('dashboardChartTitle');
        const dashboardUsersBarValue = document.getElementById('dashboardUsersBarValue');
        const dashboardSubjectsBarValue = document.getElementById('dashboardSubjectsBarValue');
        const dashboardSchedulesBarValue = document.getElementById('dashboardSchedulesBarValue');
        const dashboardStudyLogsBarValue = document.getElementById('dashboardStudyLogsBarValue');
        const dashboardUsersBar = document.getElementById('dashboardUsersBar');
        const dashboardSubjectsBar = document.getElementById('dashboardSubjectsBar');
        const dashboardSchedulesBar = document.getElementById('dashboardSchedulesBar');
        const dashboardStudyLogsBar = document.getElementById('dashboardStudyLogsBar');

        const getSelectedUserName = () => {
            if (!userFilter) return '';
            const selectedOption = userFilter.options[userFilter.selectedIndex];
            if (!selectedOption) return '';
            return (selectedOption.textContent || '').trim();
        };

        const rowMatchesFilters = (row, selectedUser, selectedTerm) => {
            if (!row || !row.dataset || !row.dataset.id) {
                return false;
            }
            let matchesUser = true;
            if (selectedUser !== '') {
                const userCell = row.querySelector('td[data-field="user_id"]');
                const idCell = row.querySelector('td[data-field="id"]');
                const cellValue = userCell ? (userCell.dataset.value || userCell.textContent.trim()) : (idCell ? idCell.textContent.trim() : '');
                matchesUser = cellValue === selectedUser;
            }
            let matchesTerm = true;
            if (selectedTerm !== '') {
                const rowTerm = typeof row.dataset.term !== 'undefined' ? row.dataset.term : null;
                if (rowTerm !== null) {
                    if (selectedTerm.includes('-')) {
                        matchesTerm = rowTerm === selectedTerm;
                    } else {
                        matchesTerm = rowTerm === selectedTerm || rowTerm.endsWith(`-${selectedTerm}`);
                    }
                }
            }
            return matchesUser && matchesTerm;
        };

        const countMatchingRows = (panelKey) => {
            const panel = document.querySelector(`.tab-panel[data-panel="${panelKey}"]`);
            if (!panel) return 0;
            const rows = panel.querySelectorAll('tbody tr[data-id]');
            const selectedUser = userFilter ? userFilter.value : '';
            const selectedTerm = termFilter ? termFilter.value : '';
            let count = 0;
            rows.forEach((row) => {
                if (rowMatchesFilters(row, selectedUser, selectedTerm)) {
                    count += 1;
                }
            });
            return count;
        };

        const refreshDashboardOverview = () => {
            const selectedUser = userFilter ? userFilter.value : '';
            const selectedTerm = termFilter ? termFilter.value : '';
            const selectedUserName = selectedUser !== '' ? getSelectedUserName() : '';

            const usersCount = selectedUser !== '' ? 1 : <?php echo (int) $countUsers; ?>;
            const subjectsCount = countMatchingRows('subjects');
            const schedulesCount = countMatchingRows('schedules');
            const studyLogsCount = countMatchingRows('study-logs');

            if (dashboardUsersLabel) {
                dashboardUsersLabel.textContent = selectedUser !== '' ? 'ผู้ใช้งานที่เลือก' : 'ผู้ใช้ทั้งหมด';
            }
            if (dashboardUsersValue) dashboardUsersValue.textContent = String(usersCount);
            if (dashboardSubjectsValue) dashboardSubjectsValue.textContent = String(subjectsCount);
            if (dashboardSchedulesValue) dashboardSchedulesValue.textContent = String(schedulesCount);
            if (dashboardStudyLogsValue) dashboardStudyLogsValue.textContent = String(studyLogsCount);

            if (dashboardUsersSub) {
                dashboardUsersSub.textContent = selectedUserName !== '' ? selectedUserName : 'คนในระบบ';
            }
            if (dashboardSubjectsSub) {
                dashboardSubjectsSub.textContent = selectedUser !== '' || selectedTerm !== '' ? 'วิชาตามตัวกรอง' : 'วิชาที่บันทึกไว้';
            }
            if (dashboardSchedulesSub) {
                dashboardSchedulesSub.textContent = selectedUser !== '' || selectedTerm !== '' ? 'ตารางตามตัวกรอง' : 'รายการตารางทั้งหมด';
            }
            if (dashboardStudyLogsSub) {
                dashboardStudyLogsSub.textContent = selectedUser !== '' || selectedTerm !== '' ? 'บันทึกตามตัวกรอง' : 'รายการบันทึกทั้งหมด';
            }

            if (dashboardChartTitle) {
                dashboardChartTitle.textContent = selectedUserName !== '' ? `ภาพรวมข้อมูลของ ${selectedUserName}` : 'ภาพรวมข้อมูล';
            }
            if (dashboardUsersBarValue) dashboardUsersBarValue.textContent = String(usersCount);
            if (dashboardSubjectsBarValue) dashboardSubjectsBarValue.textContent = String(subjectsCount);
            if (dashboardSchedulesBarValue) dashboardSchedulesBarValue.textContent = String(schedulesCount);
            if (dashboardStudyLogsBarValue) dashboardStudyLogsBarValue.textContent = String(studyLogsCount);
            if (dashboardUsersBar) dashboardUsersBar.dataset.value = String(usersCount);
            if (dashboardSubjectsBar) dashboardSubjectsBar.dataset.value = String(subjectsCount);
            if (dashboardSchedulesBar) dashboardSchedulesBar.dataset.value = String(schedulesCount);
            if (dashboardStudyLogsBar) dashboardStudyLogsBar.dataset.value = String(studyLogsCount);

            if (cardTitle && document.querySelector('.tab-panel[data-panel="dashboard"]')?.classList.contains('active')) {
                cardTitle.textContent = selectedUserName !== '' ? `ภาพรวมระบบ: ${selectedUserName}` : 'ภาพรวมระบบ';
            }

            renderBarChart();
        };

        const setActiveView = (key) => {
            const config = navConfig[key] || navConfig.users;
            if (cardTitle) cardTitle.textContent = config.title;
            if (cardSubtitle) cardSubtitle.textContent = config.subtitle || '';
            if (panelKicker) panelKicker.textContent = config.kicker || 'ศูนย์ควบคุมผู้ดูแล';
            if (mainPanelHeader) {
                mainPanelHeader.style.display = '';
            }
            if (mainPanelToolbar) {
                mainPanelToolbar.style.display = config.showToolbar === false ? 'none' : '';
            }

            panels.forEach((panel) => {
                const panelKey = panel.getAttribute('data-panel');
                panel.classList.toggle('active', panelKey === config.panel);
            });

            navLinks.forEach((link) => {
                const navKey = link.getAttribute('data-nav');
                link.classList.toggle('active', navKey === key);
            });
            filterActiveTable();
            refreshDashboardOverview();
            if (key === 'settings') {
                loadSettings();
            }
        };

        const filterActiveTable = () => {
            const activePanel = document.querySelector('.tab-panel.active');
            if (!activePanel) {
                refreshDashboardOverview();
                return;
            }
            const table = activePanel.querySelector('table');
            if (!table) {
                refreshDashboardOverview();
                return;
            }
            const selectedUser = userFilter ? userFilter.value : '';
            const selectedTerm = termFilter ? termFilter.value : '';
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((row) => {
                const isDataRow = !!row.dataset.id;
                if (!isDataRow) {
                    row.style.display = selectedUser === '' && selectedTerm === '' ? '' : 'none';
                    return;
                }
                row.style.display = rowMatchesFilters(row, selectedUser, selectedTerm) ? '' : 'none';
            });
            refreshDashboardOverview();
        };

        const setUserFilterValue = (value) => {
            if (userFilter) {
                userFilter.value = value;
            }
            filterActiveTable();
        };

        const renderBarChart = () => {
            const chart = document.getElementById('adminBarChart');
            if (!chart) return;
            const bars = chart.querySelectorAll('.bar-fill');
            let maxValue = 0;
            bars.forEach((bar) => {
                const value = Number(bar.dataset.value || 0);
                if (value > maxValue) maxValue = value;
            });
            if (maxValue <= 0) maxValue = 1;
            bars.forEach((bar, index) => {
                const value = Number(bar.dataset.value || 0);
                const percent = value <= 0 ? 6 : Math.max(10, Math.round((value / maxValue) * 100));
                bar.style.height = `${percent}%`;
                bar.style.animationDelay = `${index * 80}ms`;
            });
        };

        const renderTrendChart = (rangeKey = 'week') => {
            const chart = document.getElementById('adminTrendChart');
            if (!chart) return;
            const svg = chart.querySelector('svg');
            if (!svg) return;
            const raw = chart.dataset.trend || '{}';
            let trends = { week: { labels: [], users: [], logs: [] } };
            try {
                trends = JSON.parse(raw);
            } catch (error) {
                trends = { week: { labels: [], users: [], logs: [] } };
            }
            const trend = trends[rangeKey] || trends.week || { labels: [], users: [], logs: [] };
            const labels = Array.isArray(trend.labels) ? trend.labels : [];
            const users = Array.isArray(trend.users) ? trend.users : [];
            const logs = Array.isArray(trend.logs) ? trend.logs : [];
            const count = Math.max(labels.length, users.length, logs.length);
            if (count === 0) {
                svg.innerHTML = '';
                return;
            }

            const width = 920;
            const height = 320;
            const pad = { top: 20, right: 26, bottom: 52, left: 50 };
            const chartWidth = width - pad.left - pad.right;
            const chartHeight = height - pad.top - pad.bottom;
            const maxRaw = Math.max(...users, ...logs, 1);
            const maxValue = Math.ceil(maxRaw / 5) * 5;
            const minValue = 0;
            const steps = 5;
            const pointsUsers = [];
            const pointsLogs = [];

            const xFor = (index) => {
                if (count <= 1) return pad.left + chartWidth / 2;
                return pad.left + (chartWidth * index) / (count - 1);
            };
            const yFor = (value) => {
                const ratio = (value - minValue) / (maxValue - minValue || 1);
                return pad.top + chartHeight - ratio * chartHeight;
            };
            const pathFrom = (points) => points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0]},${p[1]}`).join(' ');
            const areaFrom = (points) => {
                if (points.length === 0) return '';
                const baseY = pad.top + chartHeight;
                return `${pathFrom(points)} L${points[points.length - 1][0]},${baseY} L${points[0][0]},${baseY} Z`;
            };
            for (let i = 0; i < count; i++) {
                const x = xFor(i);
                pointsUsers.push([x, yFor(Number(users[i] || 0))]);
                pointsLogs.push([x, yFor(Number(logs[i] || 0))]);
            }

            let guides = '';
            for (let i = 0; i <= steps; i++) {
                const v = minValue + ((maxValue - minValue) / steps) * i;
                const y = yFor(v);
                guides += `<line x1="${pad.left}" y1="${y}" x2="${width - pad.right}" y2="${y}" class="trend-grid"/>`;
                guides += `<text x="${pad.left - 10}" y="${y + 4}" text-anchor="end" class="trend-y-label">${Math.round(v)}</text>`;
            }

            let xLabels = '';
            for (let i = 0; i < count; i++) {
                const x = xFor(i);
                const label = labels[i] || '';
                xLabels += `<text x="${x}" y="${height - 20}" text-anchor="middle" class="trend-x-label">${label}</text>`;
            }

            let hoverZones = '';
            for (let i = 0; i < count; i++) {
                const left = i === 0 ? pad.left : (xFor(i - 1) + xFor(i)) / 2;
                const right = i === count - 1 ? (width - pad.right) : (xFor(i) + xFor(i + 1)) / 2;
                hoverZones += `<rect x="${left}" y="${pad.top}" width="${Math.max(1, right - left)}" height="${chartHeight}" class="trend-hover-zone" data-index="${i}"></rect>`;
            }

            const userDots = pointsUsers.map((p) => `<circle cx="${p[0]}" cy="${p[1]}" r="4.5" class="trend-dot users"/>`).join('');
            const logDots = pointsLogs.map((p) => `<circle cx="${p[0]}" cy="${p[1]}" r="4.5" class="trend-dot logs"/>`).join('');
            svg.innerHTML = `
                <defs>
                    <linearGradient id="trendUsersFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.18"/>
                        <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.03"/>
                    </linearGradient>
                    <linearGradient id="trendLogsFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.18"/>
                        <stop offset="100%" stop-color="#10b981" stop-opacity="0.03"/>
                    </linearGradient>
                </defs>
                <g>${guides}</g>
                <path d="${areaFrom(pointsUsers)}" class="trend-area users"/>
                <path d="${areaFrom(pointsLogs)}" class="trend-area logs"/>
                <path d="${pathFrom(pointsUsers)}" class="trend-line users"/>
                <path d="${pathFrom(pointsLogs)}" class="trend-line logs"/>
                ${userDots}
                ${logDots}
                <g>${xLabels}</g>
                <g>${hoverZones}</g>
            `;

            let tooltip = chart.querySelector('.trend-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'trend-tooltip';
                chart.appendChild(tooltip);
            }
            const showTooltip = (event, index) => {
                const idx = Number(index);
                const userValue = Number(users[idx] || 0);
                const logValue = Number(logs[idx] || 0);
                const label = labels[idx] || '';
                tooltip.innerHTML = `${label}<br>ผู้ใช้งาน: ${userValue}<br>บันทึก: ${logValue}`;
                tooltip.classList.add('show');
                const rect = chart.getBoundingClientRect();
                tooltip.style.left = `${event.clientX - rect.left + 14}px`;
                tooltip.style.top = `${event.clientY - rect.top - 12}px`;
            };
            const hideTooltip = () => {
                tooltip.classList.remove('show');
            };
            svg.querySelectorAll('.trend-hover-zone').forEach((zone) => {
                zone.addEventListener('mouseenter', (event) => showTooltip(event, zone.dataset.index));
                zone.addEventListener('mousemove', (event) => showTooltip(event, zone.dataset.index));
                zone.addEventListener('mouseleave', hideTooltip);
            });
            chart.addEventListener('mouseleave', hideTooltip);
        };

        navLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const key = link.getAttribute('data-nav');
                if (key) {
                    setActiveView(key);
                }
            });
        });

        if (userFilter) {
            userFilter.addEventListener('change', () => {
                setUserFilterValue(userFilter.value);
            });
        }

        if (termFilter) {
            termFilter.addEventListener('change', () => {
                filterActiveTable();
            });
        }

        setActiveView('dashboard');
        renderBarChart();
        renderTrendChart(trendRangeSelect ? trendRangeSelect.value : 'week');
        setUserFilterValue(userFilter ? userFilter.value : '');

        if (trendRangeSelect) {
            trendRangeSelect.addEventListener('change', () => {
                renderTrendChart(trendRangeSelect.value || 'week');
            });
        }

        const apiUrl = 'admin_api.php';
        const callApi = async (params) => {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params)
            });
            let data = null;
            try {
                data = await response.json();
            } catch (error) {
                data = null;
            }
            if (!response.ok || !data || !data.ok) {
                const message = data && data.error ? data.error : 'เกิดข้อผิดพลาดในการเชื่อมต่อ';
                throw new Error(message);
            }
            return data;
        };

        const setSettingsStatus = (message, isError = false, state = '') => {
            if (!settingsStatus) return;
            settingsStatus.textContent = message;
            settingsStatus.classList.remove('loading', 'success', 'error');
            if (isError) {
                settingsStatus.classList.add('error');
            } else if (state === 'loading') {
                settingsStatus.classList.add('loading');
            } else if (state === 'success') {
                settingsStatus.classList.add('success');
            }
        };

        const applySettingsToForm = (settings) => {
            if (settingSystemName) settingSystemName.value = String(settings.system_name ?? '');
            if (settingWelcomeMessage) settingWelcomeMessage.value = String(settings.admin_welcome_message ?? '');
            if (settingContactEmail) settingContactEmail.value = String(settings.contact_email ?? '');
            if (settingMaintenanceMode) settingMaintenanceMode.value = String(settings.maintenance_mode ?? '0');
            syncMaintenanceSwitchUi();
        };

        const syncMaintenanceSwitchUi = () => {
            if (!settingMaintenanceSwitch || !settingMaintenanceMode) return;
            const active = String(settingMaintenanceMode.value) === '1';
            settingMaintenanceSwitch.classList.toggle('active', active);
            settingMaintenanceSwitch.setAttribute('aria-pressed', active ? 'true' : 'false');
        };

        const loadSettings = async (force = false) => {
            if (settingsLoaded && !force) return;
            if (!saveSettingsBtn) return;
            try {
                saveSettingsBtn.disabled = true;
                setSettingsStatus('กำลังโหลดการตั้งค่า...', false, 'loading');
                const data = await callApi({ action: 'settings_get' });
                applySettingsToForm(data.settings || {});
                settingsLoaded = true;
                setSettingsStatus('โหลดการตั้งค่าแล้ว', false, 'success');
            } catch (error) {
                setSettingsStatus(error.message || 'โหลดการตั้งค่าไม่สำเร็จ', true);
            } finally {
                saveSettingsBtn.disabled = false;
            }
        };

        const editModal = document.getElementById('editModal');
        const editForm = document.getElementById('editForm');
        const closeModal = document.getElementById('closeModal');
        const cancelEdit = document.getElementById('cancelEdit');
        const saveEdit = document.getElementById('saveEdit');
        const viewModal = document.getElementById('viewModal');
        const viewBody = document.getElementById('viewBody');
        const viewReference = document.getElementById('viewReference');
        const closeViewModal = document.getElementById('closeViewModal');
        const closeView = document.getElementById('closeView');
        const addModal = document.getElementById('addModal');
        const addForm = document.getElementById('addForm');
        const closeAddModal = document.getElementById('closeAddModal');
        const cancelAdd = document.getElementById('cancelAdd');
        const saveAdd = document.getElementById('saveAdd');
        const saveSettingsBtn = document.getElementById('saveSettingsBtn');
        const syncSettingsBtn = document.getElementById('syncSettingsBtn');
        const resetSettingsBtn = document.getElementById('resetSettingsBtn');
        const settingsStatus = document.getElementById('settingsStatus');
        const settingSystemName = document.getElementById('settingSystemName');
        const settingWelcomeMessage = document.getElementById('settingWelcomeMessage');
        const settingContactEmail = document.getElementById('settingContactEmail');
        const settingMaintenanceMode = document.getElementById('settingMaintenanceMode');
        const settingMaintenanceSwitch = document.getElementById('settingMaintenanceSwitch');
        let settingsLoaded = false;
        let activeRow = null;

        const closeEditModal = () => {
            editModal.classList.remove('active');
            editForm.innerHTML = '';
            activeRow = null;
        };

        [closeModal, cancelEdit].forEach((btn) => {
            btn.addEventListener('click', closeEditModal);
        });

        editModal.addEventListener('click', (event) => {
            if (event.target === editModal) {
                closeEditModal();
            }
        });

        const closeViewDetails = () => {
            viewModal.classList.remove('active');
            viewBody.innerHTML = '';
            viewReference.textContent = '-';
        };

        [closeViewModal, closeView].forEach((btn) => {
            btn.addEventListener('click', closeViewDetails);
        });

        viewModal.addEventListener('click', (event) => {
            if (event.target === viewModal) {
                closeViewDetails();
            }
        });

        const closeAddNew = () => {
            addModal.classList.remove('active');
            addForm.innerHTML = '';
        };

        [closeAddModal, cancelAdd].forEach((btn) => {
            btn.addEventListener('click', closeAddNew);
        });

        addModal.addEventListener('click', (event) => {
            if (event.target === addModal) {
                closeAddNew();
            }
        });

        const getTableMeta = (table) => {
            return Array.from(table.querySelectorAll('thead th')).map((th) => ({
                label: th.textContent.trim(),
                field: th.dataset.field || '',
                editable: th.dataset.editable === '1',
                creatable: th.dataset.create === '1'
            }));
        };

        const getCellValue = (row, field) => {
            if (!field) return '';
            const cell = row.querySelector(`td[data-field="${field}"]`);
            if (!cell) return '';
            if (cell.dataset && typeof cell.dataset.value !== 'undefined') {
                return cell.dataset.value;
            }
            return cell.textContent.trim();
        };

        const getCellDisplay = (row, field) => {
            if (!field) return '';
            const cell = row.querySelector(`td[data-field="${field}"]`);
            return cell ? cell.textContent.trim() : '';
        };

        const buildEditForm = (row) => {
            const table = row.closest('table');
            const meta = getTableMeta(table);
            const tableName = table && table.dataset ? table.dataset.table : '';
            editForm.innerHTML = '';

            meta.forEach((col) => {
                if (!col.field || !col.editable) {
                    return;
                }
                const value = getCellValue(row, col.field);
                const field = document.createElement('div');
                field.className = 'field';

                const fieldLabel = document.createElement('label');
                fieldLabel.textContent = col.label;

                field.appendChild(fieldLabel);
                field.appendChild(createFieldControl(col.field, value, tableName));
                editForm.appendChild(field);
            });
        };

        const buildAddForm = (table) => {
            const meta = getTableMeta(table);
            const tableName = table && table.dataset ? table.dataset.table : '';
            addForm.innerHTML = '';

            meta.forEach((col) => {
                if (!col.field || !col.creatable) {
                    return;
                }
                const field = document.createElement('div');
                field.className = 'field';

                const fieldLabel = document.createElement('label');
                fieldLabel.textContent = col.label;

                field.appendChild(fieldLabel);
                field.appendChild(createFieldControl(col.field, '', tableName));
                addForm.appendChild(field);
            });
        };

        const createFieldControl = (fieldName, value, tableName) => {
            const options = selectOptions[fieldName];
            const shouldUseSelect = Array.isArray(options) && (options.length > 0 || (tableName === 'subjects' && fieldName === 'semester_id'));
            if (shouldUseSelect) {
                const select = document.createElement('select');
                select.dataset.field = fieldName;

                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = '-- เลือก --';
                select.appendChild(defaultOption);

                options.forEach((option) => {
                    const optionEl = document.createElement('option');
                    optionEl.value = String(option.value ?? '');
                    optionEl.textContent = String(option.label ?? optionEl.value);
                    select.appendChild(optionEl);
                });

                select.value = String(value ?? '');

                if (tableName === 'subjects' && fieldName === 'semester_id') {
                    select.required = true;
                }

                return select;
            }

            const input = document.createElement('input');
            input.type = 'text';
            input.value = String(value ?? '');
            input.dataset.field = fieldName;
            return input;
        };

        const buildViewDetails = (row) => {
            const table = row.closest('table');
            const meta = getTableMeta(table);
            viewBody.innerHTML = '';
            const referenceValue = getCellDisplay(row, 'id') || row.dataset.id || '-';
            viewReference.textContent = referenceValue;

            meta.forEach((col) => {
                if (!col.field) {
                    return;
                }
                const value = getCellDisplay(row, col.field);
                const field = document.createElement('div');
                const isWideField = ['user_id', 'name', 'subject_id', 'title', 'description', 'email'].includes(col.field);
                const isLongField = ['description', 'content', 'note', 'notes'].includes(col.field) || String(value).length > 70;
                field.className = `field readonly${isWideField || isLongField ? ' wide' : ''}`;

                const fieldLabel = document.createElement('label');
                fieldLabel.textContent = col.label;

                let valueEl;
                if (col.field === 'color' && /^#[0-9a-f]{3,8}$/i.test(String(value).trim())) {
                    valueEl = document.createElement('div');
                    valueEl.className = 'readonly-value color-value';

                    const swatch = document.createElement('span');
                    swatch.className = 'color-swatch';
                    swatch.style.backgroundColor = value;

                    const colorText = document.createElement('span');
                    colorText.textContent = value || '-';

                    valueEl.appendChild(swatch);
                    valueEl.appendChild(colorText);
                } else if (isLongField) {
                    valueEl = document.createElement('textarea');
                    valueEl.value = value;
                    valueEl.readOnly = true;
                    valueEl.tabIndex = -1;
                    valueEl.rows = 3;
                } else {
                    valueEl = document.createElement('input');
                    valueEl.type = 'text';
                    valueEl.value = value;
                    valueEl.readOnly = true;
                    valueEl.tabIndex = -1;
                }

                field.appendChild(fieldLabel);
                field.appendChild(valueEl);
                viewBody.appendChild(field);
            });
        };

        document.querySelectorAll('.tool.blue').forEach((tool) => {
            tool.style.cursor = 'pointer';
            tool.title = 'แก้ไข';
            tool.addEventListener('click', () => {
                const row = tool.closest('tr');
                if (!row) return;
                activeRow = row;
                buildEditForm(row);
                editModal.classList.add('active');
            });
        });

        document.querySelectorAll('.tool.gray').forEach((tool) => {
            tool.style.cursor = 'pointer';
            tool.title = 'ดูรายละเอียด';
            tool.addEventListener('click', () => {
                const row = tool.closest('tr');
                if (!row) return;
                buildViewDetails(row);
                viewModal.classList.add('active');
            });
        });

        document.querySelectorAll('.tool.red').forEach((tool) => {
            tool.style.cursor = 'pointer';
            tool.title = 'ลบข้อมูล';
            tool.addEventListener('click', async () => {
                const row = tool.closest('tr');
                if (!row) return;
                const table = row.dataset.table;
                const id = row.dataset.id;
                if (!table || !id) return;
                const ok = confirm('คุณต้องการลบรายการนี้หรือไม่?');
                if (!ok) return;
                try {
                    await callApi({ action: 'delete', table: table, id: id });
                    row.remove();
                } catch (error) {
                    alert(error.message);
                }
            });
        });

        document.querySelectorAll('tr[data-table="users"]').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('.tools')) {
                    return;
                }
                const userId = row.dataset.id;
                if (!userId) return;
                setUserFilterValue(userId);
                setActiveView('dashboard');
            });
        });

        document.querySelectorAll('[data-user-view]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                const target = button.dataset.userView;
                const row = button.closest('tr[data-table="users"]');
                const userId = row ? row.dataset.id : '';
                if (!userId || !target) return;
                setUserFilterValue(userId);
                setActiveView(target);
            });
        });

        const shouldSkipEmptyField = (field, value) => {
            if (value !== '') return false;
            return field === 'created_at' || field === 'updated_at';
        };

        saveAdd.addEventListener('click', async () => {
            const activePanel = document.querySelector('.tab-panel.active');
            const table = activePanel ? activePanel.querySelector('table') : null;
            if (!table) return;
            const tableName = table.dataset.table;
            if (!tableName) return;
            const inputs = addForm.querySelectorAll('[data-field]');
            const fields = {};
            inputs.forEach((input) => {
                const field = input.dataset.field;
                if (field) {
                    const rawValue = typeof input.value === 'string' ? input.value : '';
                    const value = rawValue.trim();
                    if (shouldSkipEmptyField(field, value)) {
                        return;
                    }
                    fields[field] = value;
                }
            });
            if (tableName === 'subjects' && Array.isArray(selectOptions.semester_id) && selectOptions.semester_id.length > 0 && !fields.semester_id) {
                alert('กรุณาเลือกภาคเรียน');
                return;
            }
            try {
                await callApi({ action: 'create', table: tableName, fields: JSON.stringify(fields) });
                closeAddNew();
                window.location.reload();
            } catch (error) {
                alert(error.message);
            }
        });

        saveEdit.addEventListener('click', async () => {
            if (!activeRow) return;
            const table = activeRow.dataset.table;
            const id = activeRow.dataset.id;
            if (!table || !id) return;
            const inputs = editForm.querySelectorAll('[data-field]');
            const fields = {};
            inputs.forEach((input) => {
                const field = input.dataset.field;
                if (field) {
                    const rawValue = typeof input.value === 'string' ? input.value : '';
                    const value = rawValue.trim();
                    if (shouldSkipEmptyField(field, value)) {
                        return;
                    }
                    fields[field] = value;
                }
            });
            if (table === 'subjects' && Array.isArray(selectOptions.semester_id) && selectOptions.semester_id.length > 0 && !fields.semester_id) {
                alert('กรุณาเลือกภาคเรียน');
                return;
            }
            try {
                await callApi({ action: 'update', table: table, id: id, fields: JSON.stringify(fields) });
                closeEditModal();
                window.location.reload();
            } catch (error) {
                alert(error.message);
            }
        });

        if (saveSettingsBtn) {
            saveSettingsBtn.addEventListener('click', async () => {
                const fields = {
                    system_name: settingSystemName ? settingSystemName.value.trim() : '',
                    admin_welcome_message: settingWelcomeMessage ? settingWelcomeMessage.value.trim() : '',
                    contact_email: settingContactEmail ? settingContactEmail.value.trim() : '',
                    maintenance_mode: settingMaintenanceMode ? settingMaintenanceMode.value : '0'
                };
                try {
                    saveSettingsBtn.disabled = true;
                    setSettingsStatus('กำลังบันทึก...', false, 'loading');
                    await callApi({ action: 'settings_save', fields: JSON.stringify(fields) });
                    settingsLoaded = true;
                    setSettingsStatus('บันทึกการตั้งค่าเรียบร้อย', false, 'success');
                } catch (error) {
                    setSettingsStatus(error.message || 'บันทึกการตั้งค่าไม่สำเร็จ', true);
                } finally {
                    saveSettingsBtn.disabled = false;
                }
            });
        }

        if (settingMaintenanceSwitch && settingMaintenanceMode) {
            settingMaintenanceSwitch.addEventListener('click', () => {
                const current = String(settingMaintenanceMode.value) === '1';
                settingMaintenanceMode.value = current ? '0' : '1';
                syncMaintenanceSwitchUi();
            });
            settingMaintenanceMode.addEventListener('change', syncMaintenanceSwitchUi);
            syncMaintenanceSwitchUi();
        }

        if (syncSettingsBtn) {
            syncSettingsBtn.addEventListener('click', async () => {
                settingsLoaded = false;
                await loadSettings(true);
            });
        }

        if (resetSettingsBtn) {
            resetSettingsBtn.addEventListener('click', async () => {
                settingsLoaded = false;
                await loadSettings(true);
            });
        }

    </script>
</body>
</html>
