<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();
$user_id = api_require_user_id($db); // ✅ ล็อกอินตั้งแต่ต้น

// ✅ ถ้าไม่ส่ง action มา ให้ถือว่าเอา notifications ไปแสดง
$action = $_GET['action'] ?? 'get_notifications';

switch ($action) {
    case 'get_upcoming':
        getUpcomingClasses($db, $user_id);
        break;
    case 'set_notification':
        setNotification($db, $user_id);
        break;
    case 'get_notifications':
        getNotifications($db, $user_id);
        break;
    default:
        api_abort(400, "Invalid action");
}

function getUpcomingClasses($db, $user_id) {
    $days_ahead = isset($_GET['days']) ? (int)$_GET['days'] : 1;
    if ($days_ahead < 1) $days_ahead = 1;

    $query = "SELECT s.subject_name, s.subject_code, sc.day_of_week, sc.start_time,
              sc.end_time, sc.location
              FROM schedules sc
              JOIN subjects s ON sc.subject_id = s.id
              WHERE s.user_id = :user_id
              ORDER BY FIELD(sc.day_of_week, 'Monday', 'Tuesday', 'Wednesday',
                            'Thursday', 'Friday', 'Saturday', 'Sunday'), sc.start_time";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function setNotification($db, $user_id) {
    $data = json_decode(file_get_contents("php://input"), true) ?: [];

    $type = $data['type'] ?? null;
    $days_before = isset($data['days_before']) ? (int)$data['days_before'] : 1;
    $is_active = isset($data['is_active']) ? (int)(!!$data['is_active']) : 1;

    if (!$type) {
        api_abort(422, "type is required");
    }
    if ($days_before < 0) $days_before = 0;

    $query = "INSERT INTO notifications (user_id, notification_type, days_before, is_active)
              VALUES (:user_id, :type, :days_before, :is_active)";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":type", $type);
    $stmt->bindParam(":days_before", $days_before);
    $stmt->bindParam(":is_active", $is_active);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Notification set successfully"]);
    } else {
        api_abort(500, "Failed to set notification");
    }
}

function getNotifications($db, $user_id) {
    // ✅ เรียงล่าสุดก่อน (หน้า Overview จะเลือกอันแรกได้ถูก)
    $query = "SELECT *
              FROM notifications
              WHERE user_id = :user_id AND is_active = 1
              ORDER BY notify_at DESC, id DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
