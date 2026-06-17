<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'create_goal':
        createGoal($db);
        break;
    case 'get_goals':
        getGoals($db);
        break;
    case 'update_progress':
        updateProgress($db);
        break;
    case 'complete_goal':
        completeGoal($db);
        break;
    case 'get_upcoming_deadlines':
        getUpcomingDeadlines($db);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
}

function createGoal($db) {
    $data = json_decode(file_get_contents("php://input"));
    $user_id = api_require_user_id($db);
    
    $query = "INSERT INTO learning_goals (user_id, goal_title, goal_description, target_date) 
              VALUES (:user_id, :title, :description, :target_date)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":title", $data->title);
    $stmt->bindParam(":description", $data->description);
    $stmt->bindParam(":target_date", $data->target_date);
    
    if($stmt->execute()) {
        echo json_encode([
            "message" => "Goal created successfully",
            "id" => $db->lastInsertId()
        ]);
    } else {
        echo json_encode(["message" => "Failed to create goal"]);
    }
}

function getGoals($db) {
    $user_id = api_require_user_id($db);
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $query = "SELECT lg.*, 
              (SELECT AVG(progress_percentage) FROM goal_progress WHERE goal_id = lg.id) as avg_progress
              FROM learning_goals lg
              WHERE lg.user_id = :user_id";
    
    if($status == 'active') {
        $query .= " AND lg.is_completed = 0";
    } elseif($status == 'completed') {
        $query .= " AND lg.is_completed = 1";
    }
    
    $query .= " ORDER BY lg.target_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function updateProgress($db) {
    $data = json_decode(file_get_contents("php://input"));
    $user_id = api_require_user_id($db);
    $goal_id = isset($data->goal_id) ? $data->goal_id : null;
    $subject_id = isset($data->subject_id) ? $data->subject_id : null;
    $note = isset($data->note) ? $data->note : null;
    $percentage = isset($data->percentage) ? $data->percentage : null;

    if (!$goal_id) {
        api_abort(422, "goal_id is required");
    }

    api_require_ownership($db, 'learning_goals', $goal_id, $user_id);
    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }
    
    $query = "INSERT INTO goal_progress (goal_id, subject_id, progress_note, progress_percentage) 
              VALUES (:goal_id, :subject_id, :note, :percentage)
              ON DUPLICATE KEY UPDATE progress_percentage = :percentage, progress_note = :note";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":goal_id", $goal_id);
    $stmt->bindParam(":subject_id", $subject_id);
    $stmt->bindParam(":note", $note);
    $stmt->bindParam(":percentage", $percentage);
    
    if($stmt->execute()) {
        echo json_encode(["message" => "Progress updated successfully"]);
    } else {
        echo json_encode(["message" => "Failed to update progress"]);
    }
}

function completeGoal($db) {
    $user_id = api_require_user_id($db);

    $data = json_decode(file_get_contents("php://input"));
    $goal_id = 0;

    if (isset($_POST['goal_id'])) {
        $goal_id = (int)$_POST['goal_id'];
    } elseif (isset($data->goal_id)) {
        $goal_id = (int)$data->goal_id;
    }

    if ($goal_id <= 0) {
        api_abort(422, "goal_id is required");
    }

    $query = "UPDATE learning_goals
              SET is_completed = 1, completed_at = NOW()
              WHERE id = :goal_id AND user_id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":goal_id", $goal_id, PDO::PARAM_INT);
    $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);

    if($stmt->execute()) {
        if ($stmt->rowCount() === 0) {
            api_abort(404, "Goal not found");
        }
        echo json_encode(["message" => "Goal completed successfully"]);
    } else {
        echo json_encode(["message" => "Failed to complete goal"]);
    }
}


function getUpcomingDeadlines($db) {
    $user_id = api_require_user_id($db);
    $days = isset($_GET['days']) ? $_GET['days'] : 7;
    
    $query = "SELECT * FROM learning_goals 
              WHERE user_id = :user_id 
              AND is_completed = 0
              AND target_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
              ORDER BY target_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":days", $days);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
