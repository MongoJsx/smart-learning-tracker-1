<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'record_mood':
        recordMood($db);
        break;
    case 'get_mood_history':
        getMoodHistory($db);
        break;
    case 'get_mood_stats':
        getMoodStats($db);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
}

function recordMood($db) {
    $data = json_decode(file_get_contents("php://input"));
    $user_id = api_require_user_id($db);
    $subject_id = isset($data->subject_id) ? $data->subject_id : null;
    $lesson_summary_id = isset($data->lesson_summary_id) ? $data->lesson_summary_id : null;
    $mood = isset($data->mood) ? $data->mood : null;
    $note = isset($data->note) ? $data->note : null;

    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }
    if ($lesson_summary_id) {
        api_require_ownership($db, 'lesson_summaries', $lesson_summary_id, $user_id);
    }
    
    $query = "INSERT INTO mood_tracking (user_id, subject_id, lesson_summary_id, mood, note) 
              VALUES (:user_id, :subject_id, :lesson_summary_id, :mood, :note)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":subject_id", $subject_id);
    $stmt->bindParam(":lesson_summary_id", $lesson_summary_id);
    $stmt->bindParam(":mood", $mood);
    $stmt->bindParam(":note", $note);
    
    if($stmt->execute()) {
        echo json_encode(["message" => "Mood recorded successfully"]);
    } else {
        echo json_encode(["message" => "Failed to record mood"]);
    }
}

function getMoodHistory($db) {
    $user_id = api_require_user_id($db);
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    if ($days <= 0) { $days = 30; }
    if ($days > 365) { $days = 365; } // กันยิงหนัก

    $query = "SELECT mt.*, s.subject_name, DATE(mt.recorded_at) as date
              FROM mood_tracking mt
              LEFT JOIN subjects s ON mt.subject_id = s.id
              WHERE mt.user_id = :user_id
              AND mt.recorded_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
              ORDER BY mt.recorded_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}


function getMoodStats($db) {
    $user_id = api_require_user_id($db);
    
    $query = "SELECT mood, COUNT(*) as count 
              FROM mood_tracking 
              WHERE user_id = :user_id 
              GROUP BY mood";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
