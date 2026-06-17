<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();
$user_id = api_require_user_id($db); // ✅ ล็อกอินตั้งแต่ต้น

$action = $_GET['action'] ?? 'get_chart_data'; // ✅ default ให้ไม่ขึ้น Invalid action

switch ($action) {
    case 'log_session':
        logStudySession($db, $user_id);
        break;
    case 'get_daily_stats':
        getDailyStats($db, $user_id);
        break;
    case 'get_weekly_stats':
        getWeeklyStats($db, $user_id);
        break;
    case 'get_monthly_stats':
        getMonthlyStats($db, $user_id);
        break;
    case 'get_chart_data':
        getChartData($db, $user_id);
        break;
    default:
        api_abort(400, "Invalid action");
}

function logStudySession($db, $user_id) {
    $data = json_decode(file_get_contents("php://input"));
    $subject_id = isset($data->subject_id) ? $data->subject_id : null;

    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }

    // คำนวณระยะเวลา
    $start = new DateTime($data->start_time);
    $end = new DateTime($data->end_time);
    $diff = $end->diff($start);
    $duration = ($diff->h * 60) + $diff->i;

    $query = "INSERT INTO study_sessions
              (user_id, subject_id, session_date, start_time, end_time, duration_minutes, activity_type, note)
              VALUES (:user_id, :subject_id, :session_date, :start_time, :end_time, :duration, :activity_type, :note)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":subject_id", $subject_id);
    $stmt->bindParam(":session_date", $data->session_date);
    $stmt->bindParam(":start_time", $data->start_time);
    $stmt->bindParam(":end_time", $data->end_time);
    $stmt->bindParam(":duration", $duration);
    $stmt->bindParam(":activity_type", $data->activity_type);
    $stmt->bindParam(":note", $data->note);

    if ($stmt->execute()) {
        updateDailyStats($db, $user_id, $data->session_date);
        echo json_encode(["message" => "Study session logged successfully"]);
    } else {
        api_abort(500, "Failed to log session");
    }
}

function updateDailyStats($db, $user_id, $date) {
    $query = "INSERT INTO study_statistics (user_id, stat_date, total_hours, subject_count, summary_count)
              SELECT :user_id, :stat_date,
                     COALESCE(SUM(duration_minutes)/60, 0) as total_hours,
                     COUNT(DISTINCT subject_id) as subject_count,
                     0 as summary_count
              FROM study_sessions
              WHERE user_id = :user_id AND session_date = :stat_date
              ON DUPLICATE KEY UPDATE
                total_hours = VALUES(total_hours),
                subject_count = VALUES(subject_count)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":stat_date", $date);
    $stmt->execute();
}

function getDailyStats($db, $user_id) {
    $date = $_GET['date'] ?? date('Y-m-d');

    $query = "SELECT
              COALESCE(SUM(duration_minutes)/60, 0) as total_hours,
              COUNT(DISTINCT subject_id) as subject_count,
              COUNT(*) as session_count
              FROM study_sessions
              WHERE user_id = :user_id AND session_date = :date";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":date", $date);
    $stmt->execute();

    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function getWeeklyStats($db, $user_id) {
    $query = "SELECT
              session_date,
              COALESCE(SUM(duration_minutes)/60, 0) as total_hours,
              COUNT(DISTINCT subject_id) as subject_count
              FROM study_sessions
              WHERE user_id = :user_id
              AND session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY session_date
              ORDER BY session_date ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getMonthlyStats($db, $user_id) {
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');

    $query = "SELECT
              session_date,
              COALESCE(SUM(duration_minutes)/60, 0) as total_hours,
              COUNT(DISTINCT subject_id) as subject_count
              FROM study_sessions
              WHERE user_id = :user_id
              AND MONTH(session_date) = :month
              AND YEAR(session_date) = :year
              GROUP BY session_date
              ORDER BY session_date ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":month", $month);
    $stmt->bindParam(":year", $year);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getChartData($db, $user_id) {
    $period = $_GET['period'] ?? 'week'; // week, month, year

    $date_format = ($period === 'year') ? '%Y-%m' : '%Y-%m-%d';
    $interval = ($period === 'year') ? 365 : (($period === 'month') ? 30 : 7);
    $interval = (int)$interval; // ✅ cast เป็น int

    // ✅ ห้าม bind ใน INTERVAL -> ใส่เลขตรง ๆ
    $query = "SELECT
              DATE_FORMAT(ss.session_date, '{$date_format}') as period,
              COALESCE(SUM(ss.duration_minutes)/60, 0) as total_hours,
              COUNT(DISTINCT ss.subject_id) as subject_count,
              COALESCE(s.name, 'ไม่ระบุวิชา') as subject_name,
              COALESCE(SUM(ss.duration_minutes)/60, 0) as subject_hours
              FROM study_sessions ss
              LEFT JOIN subjects s ON ss.subject_id = s.id AND s.user_id = ss.user_id
              WHERE ss.user_id = :user_id
              AND ss.session_date >= DATE_SUB(CURDATE(), INTERVAL {$interval} DAY)
              GROUP BY period, ss.subject_id, subject_name
              ORDER BY period ASC, subject_hours DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chart_data = [
        'labels' => [],
        'datasets' => []
    ];

    $subjects = [];
    foreach ($results as $row) {
        if (!in_array($row['period'], $chart_data['labels'], true)) {
            $chart_data['labels'][] = $row['period'];
        }
        $sub = $row['subject_name'] ?? 'ไม่ระบุวิชา';
        if (!isset($subjects[$sub])) {
            $subjects[$sub] = [];
        }
        $subjects[$sub][$row['period']] = (float)($row['subject_hours'] ?? 0);
    }

    foreach ($subjects as $subject => $data) {
        $dataset = [
            'label' => $subject,
            'data' => []
        ];
        foreach ($chart_data['labels'] as $label) {
            $dataset['data'][] = isset($data[$label]) ? $data[$label] : 0;
        }
        $chart_data['datasets'][] = $dataset;
    }

    echo json_encode($chart_data);
}
?>
