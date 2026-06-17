


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
                <span class="brand-badge">A</span>
                <span>ผู้ดูแลระบบ</span>
            </div>
            <div class="user-card">
                <div class="name">
                    <span class="greet">สวัสดี,</span>
                    <span class="username"><?php echo admin_h(isset($_SESSION['username']) ? $_SESSION['username'] : 'ผู้ดูแลระบบ'); ?></span>
                </div>
                <div class="role">
                    <span class="role-icon">🛡</span>
                    <span>สิทธิ์ผู้ดูแลระบบ</span>
                </div>
            </div>
            <nav class="nav">
                <a class="active" href="#" data-nav="dashboard">
                    <span class="icon blue">📊</span>
                    <span class="label">สถิติภาพรวม</span>
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
                    <span class="label">ตั้งค่า</span>
                </a>
                <a href="<?php echo admin_h(($baseDir !== '' ? $baseDir : '') . '/admin_logout.php'); ?>">
                    <span class="icon gray">🚪</span>
                    <span class="label">ออกจากระบบ</span>
                </a>
            </nav>
            <div class="sidebar-footer">ผู้ดูแลระบบ</div>
        </aside>

        <main class="main">
            <div class="topbar">
                <div class="left">
                    <button id="sidebarToggle" class="toggle-btn">เมนู</button>
                    <div style="font-weight: 700;">ผู้ดูแลระบบ</div>
                </div>
                <div class="right">
                    <div class="icon-pill datetime-pill" id="currentDateTime">กำลังโหลดวันเวลา...</div>
                    <div class="icon-pill">ผู้ดูแลระบบ</div>
                </div>
              </div>

            <section class="page-card">
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

                <div class="card-header" id="mainPanelHeader">
                    <div>
                        <div class="card-title" id="cardTitle">สถิติภาพรวม</div>
                    </div>
                </div>

                <div class="tab-panel active" data-panel="dashboard">
                    <div class="table-wrap">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-label" id="dashboardUsersLabel">ผู้ใช้ทั้งหมด</div>
                                <div class="stat-value" id="dashboardUsersValue"><?php echo (int) $countUsers; ?></div>
                                <div class="stat-sub" id="dashboardUsersSub">คนในระบบ</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">วิชาเรียน</div>
                                <div class="stat-value" id="dashboardSubjectsValue"><?php echo (int) $countSubjects; ?></div>
                                <div class="stat-sub" id="dashboardSubjectsSub">วิชาที่บันทึกไว้</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">ตารางเรียน</div>
                                <div class="stat-value" id="dashboardSchedulesValue"><?php echo (int) $countSchedules; ?></div>
                                <div class="stat-sub" id="dashboardSchedulesSub">รายการตารางทั้งหมด</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">บันทึกการเรียน</div>
                                <div class="stat-value" id="dashboardStudyLogsValue"><?php echo (int) $countStudyLogs; ?></div>
                                <div class="stat-sub" id="dashboardStudyLogsSub">รายการบันทึกทั้งหมด</div>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-title" id="dashboardChartTitle">ภาพรวมข้อมูล</div>
                            <div class="bar-chart" id="adminBarChart">
                                <div class="bar-item">
                                    <div class="bar-value" id="dashboardUsersBarValue"><?php echo (int) $countUsers; ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill" id="dashboardUsersBar" data-value="<?php echo (int) $countUsers; ?>"></div>
                                    </div>
                                    <div class="bar-label">ผู้ใช้งาน</div>
                                </div>
                                <div class="bar-item">
                                    <div class="bar-value" id="dashboardSubjectsBarValue"><?php echo (int) $countSubjects; ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill" id="dashboardSubjectsBar" data-value="<?php echo (int) $countSubjects; ?>"></div>
                                    </div>
                                    <div class="bar-label">วิชาเรียน</div>
                                </div>
                                <div class="bar-item">
                                    <div class="bar-value" id="dashboardSchedulesBarValue"><?php echo (int) $countSchedules; ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill" id="dashboardSchedulesBar" data-value="<?php echo (int) $countSchedules; ?>"></div>
                                    </div>
                                    <div class="bar-label">ตารางเรียน</div>
                                </div>
                                <div class="bar-item">
                                    <div class="bar-value" id="dashboardStudyLogsBarValue"><?php echo (int) $countStudyLogs; ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill" id="dashboardStudyLogsBar" data-value="<?php echo (int) $countStudyLogs; ?>"></div>
                                    </div>
                                    <div class="bar-label">บันทึกการเรียน</div>
                                </div>
                            </div>
                            <div class="bar-axis"></div>
                        </div>

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

                    </div>
                </div>

                <div class="tab-panel" data-panel="users">
                    <div class="table-wrap">
                        <table class="table" data-table="users">
                            <thead>
                                <tr>
                                    <th data-field="id" data-editable="0" data-create="0">รหัส</th>
                                    <th data-field="name" data-editable="1" data-create="1">ชื่อ</th>
                                    <th data-field="email" data-editable="1" data-create="1">อีเมล</th>
                                    <th data-field="role" data-editable="1" data-create="1">สิทธิ์</th>
                                    <th>ดูอย่างเดียว</th>
                                    <th>ดูข้อมูล</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) === 0): ?>
                                    <tr>
                                        <td colspan="6">ยังไม่มีข้อมูล</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr data-table="users" data-id="<?php echo admin_h($user['id']); ?>">
                                            <td data-field="id"><?php echo admin_h($user['id']); ?></td>
                                            <td data-field="name"><?php echo admin_h($user['name']); ?></td>
                                            <td data-field="email"><?php echo admin_h($user['email']); ?></td>
                                            <td data-field="role">
                                                <?php $role = isset($user['role']) ? (string) $user['role'] : ''; ?>
                                                <span class="badge <?php echo $role === 'admin' ? 'red' : 'green'; ?>"><?php echo admin_h($role); ?></span>
                                            </td>
                                                    <td>
                                                        <span class="tools">
                                                            <button type="button" class="tool gray">ดู</button>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="user-view">
                                                            <button type="button" class="user-view-btn subjects" data-user-view="subjects" title="วิชาเรียน" aria-label="วิชาเรียน">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                    <path fill="currentColor" d="M4 6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v12a1 1 0 0 1-1.6.8L13 16.5a2 2 0 0 0-2 0l-3.4 2.3A1 1 0 0 1 6 18V6z"/>
                                                                </svg>
                                                            </button>
                                                            <button type="button" class="user-view-btn schedules" data-user-view="schedules" title="ตารางเรียน" aria-label="ตารางเรียน">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                    <path fill="currentColor" d="M7 3a1 1 0 0 1 1 1v1h8V4a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1V4a1 1 0 0 1 1-1zm12 8H5v7h14v-7z"/>
                                                                </svg>
                                                            </button>
                                                            <button type="button" class="user-view-btn study-logs" data-user-view="study-logs" title="บันทึกการเรียน" aria-label="บันทึกการเรียน">
                                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                    <path fill="currentColor" d="M6 3h8l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm8 1v4h4"/>
                                                                    <path fill="currentColor" d="M8 12h8a1 1 0 1 0 0-2H8a1 1 0 0 0 0 2zm0 4h8a1 1 0 1 0 0-2H8a1 1 0 0 0 0 2z"/>
                                                                </svg>
                                                            </button>
                                                        </div>
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
                                    <th data-field="target_hours" data-editable="1" data-create="1">ชั่วโมงเป้าหมาย</th>
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
                        <table class="table" data-table="study_logs">
                            <thead>
                                <tr>
                                    <th data-field="id" data-editable="0" data-create="0">รหัส</th>
                                    <th data-field="user_id" data-editable="1" data-create="1">ผู้ใช้งาน</th>
                                    <th data-field="subject_id" data-editable="1" data-create="1">วิชา</th>
                                    <th data-field="title" data-editable="1" data-create="1">หัวข้อ</th>
                                    <th data-field="log_date" data-editable="1" data-create="1">วันที่</th>
                                    <th data-field="duration_minutes" data-editable="1" data-create="1">นาที</th>
                                    <th>ดูอย่างเดียว</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($studyLogs) === 0): ?>
                                    <tr>
                                        <td colspan="7">ยังไม่มีข้อมูล</td>
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
                                            <td data-field="duration_minutes"><?php echo admin_h($log['duration_minutes']); ?></td>
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
                                        <p class="settings-subtitle">ปรับแต่งค่าระบบและบันทึกลงฐานข้อมูลแบบเรียลไทม์</p>
                                    </div>
                                    <button class="settings-sync-btn" type="button" id="syncSettingsBtn">ซิงค์ข้อมูล</button>
                                </div>

                                <div class="settings-body">
                                    <div class="field">
                                        <label for="settingSystemName">ชื่อระบบหลัก</label>
                                        <input type="text" id="settingSystemName" placeholder="เช่น Smart Learning Tracker Admin">
                                    </div>
                                    <div class="field">
                                        <label for="settingContactEmail">อีเมลติดต่อระบบ</label>
                                        <input type="email" id="settingContactEmail" placeholder="example@email.com">
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
                                                <p>จำกัดการเข้าถึงเฉพาะผู้ดูแลเพื่อทำการปรับปรุงระบบ</p>
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
                                        <span class="label">ระยะเวลาทำงาน</span>
                                        <strong>99.98%</strong>
                                    </div>
                                    <div class="settings-summary-card">
                                        <span class="label">ความปลอดภัย</span>
                                        <strong>SSL v3</strong>
                                    </div>
                                    <div class="settings-summary-card">
                                        <span class="label">เวอร์ชัน API</span>
                                        <strong>4.2.0</strong>
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
        const toggleBtn = document.getElementById('sidebarToggle');
        toggleBtn.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
        });

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
        const mainPanelHeader = document.getElementById('mainPanelHeader');
        const mainPanelToolbar = document.getElementById('mainPanelToolbar');
        const userFilter = document.getElementById('userFilter');
        const termFilter = document.getElementById('termFilter');
        const trendRangeSelect = document.getElementById('trendRangeSelect');
        const selectOptions = {
            semester_id: <?php echo json_encode($semesterFieldOptions, JSON_UNESCAPED_UNICODE); ?>,
            role: [
                { value: 'admin', label: 'admin' },
                { value: 'user', label: 'user' }
            ],
            schedule_type: [
                { value: 'class', label: 'class' },
                { value: 'exam', label: 'exam' }
            ]
        };

        const navConfig = {
            dashboard: { title: 'สถิติภาพรวม', panel: 'dashboard', showTabs: false },
            users: { title: 'ผู้ใช้งาน', panel: 'users', showTabs: true },
            subjects: { title: 'วิชาเรียน', panel: 'subjects', showTabs: true },
            schedules: { title: 'ตารางเรียน', panel: 'schedules', showTabs: true },
            'study-logs': { title: 'บันทึกการเรียน', panel: 'study-logs', showTabs: true },
            settings: { title: 'ตั้งค่า', panel: 'settings', showTabs: false }
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
                cardTitle.textContent = selectedUserName !== '' ? `สถิติภาพรวมของ ${selectedUserName}` : 'สถิติภาพรวม';
            }

            renderBarChart();
        };

        const setActiveView = (key) => {
            const config = navConfig[key] || navConfig.users;
            if (cardTitle) cardTitle.textContent = config.title;
            const isSettingsPage = key === 'settings';
            if (mainPanelHeader) {
                mainPanelHeader.style.display = isSettingsPage ? 'none' : '';
            }
            if (mainPanelToolbar) {
                mainPanelToolbar.style.display = isSettingsPage ? 'none' : '';
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
                <g>${guides}</g>
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
        const addNewBtn = document.getElementById('addNewBtn');
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

        if (addNewBtn) {
            addNewBtn.addEventListener('click', () => {
                const activePanel = document.querySelector('.tab-panel.active');
                const table = activePanel ? activePanel.querySelector('table') : null;
                if (!table) return;
                buildAddForm(table);
                addModal.classList.add('active');
            });
        }

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
