<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'get_schedule':
        getSchedule($db);
        break;
    case 'add_note':
        addNote($db);
        break;
    case 'get_notes':
        getNotes($db);
        break;
    case 'delete_note':
        deleteNote($db);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
}

function getSchedule($db) {
    $user_id = api_require_user_id($db);
    $month = isset($_GET['month']) ? $_GET['month'] : date('m');
    $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
    
    $query = "SELECT s.subject_name, s.subject_code, sc.day_of_week, 
              sc.start_time, sc.end_time, sc.location
              FROM schedules sc
              JOIN subjects s ON sc.subject_id = s.id
              WHERE s.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function addNote($db) {
    $data = json_decode(file_get_contents("php://input"));
    $user_id = api_require_user_id($db);
    
    $query = "INSERT INTO calendar_notes (user_id, note_date, note_time, title, description) 
              VALUES (:user_id, :note_date, :note_time, :title, :description)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":note_date", $data->note_date);
    $stmt->bindParam(":note_time", $data->note_time);
    $stmt->bindParam(":title", $data->title);
    $stmt->bindParam(":description", $data->description);
    
    if($stmt->execute()) {
        echo json_encode(["message" => "Note added successfully", "id" => $db->lastInsertId()]);
    } else {
        echo json_encode(["message" => "Failed to add note"]);
    }
}

function getNotes($db) {
    $user_id = api_require_user_id($db);
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    $query = "SELECT * FROM calendar_notes WHERE user_id = :user_id AND note_date = :date 
              ORDER BY note_time";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":date", $date);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function deleteNote($db) {
    $note_id = isset($_GET['id']) ? $_GET['id'] : 0;
    
    $user_id = api_require_user_id($db);
    
    $query = "DELETE FROM calendar_notes WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $note_id);
    $stmt->bindParam(":user_id", $user_id);
    
    if($stmt->execute()) {
        if ($stmt->rowCount() === 0) {
            api_abort(404, "Note not found");
        }
        echo json_encode(["message" => "Note deleted successfully"]);
    } else {
        echo json_encode(["message" => "Failed to delete note"]);
    }
}
?>
