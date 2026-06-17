<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'create_summary':
        createSummary($db);
        break;
    case 'get_summaries':
        getSummaries($db);
        break;
    case 'upload_file':
        uploadFile($db);
        break;
    case 'get_summary_with_source':
        getSummaryWithSource($db);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
}

function createSummary($db) {
    $data = json_decode(file_get_contents("php://input"));
    $user_id = api_require_user_id($db);
    $subject_id = isset($data->subject_id) ? $data->subject_id : null;
    $source_file_id = isset($data->source_file_id) ? $data->source_file_id : null;
    $original_text = isset($data->original_text) ? $data->original_text : '';

    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }
    if ($source_file_id) {
        api_require_ownership($db, 'source_files', $source_file_id, $user_id);
    }
    
    // สรุปอัตโนมัติด้วย AI (ในที่นี้เป็นตัวอย่างง่ายๆ)
    $summary = generateSummary($original_text);
    
    $query = "INSERT INTO lesson_summaries (user_id, subject_id, source_file_id, 
              summary_text, original_text) 
              VALUES (:user_id, :subject_id, :source_file_id, :summary_text, :original_text)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":subject_id", $subject_id);
    $stmt->bindParam(":source_file_id", $source_file_id);
    $stmt->bindParam(":summary_text", $summary);
    $stmt->bindParam(":original_text", $original_text);
    
    if($stmt->execute()) {
        $summary_id = $db->lastInsertId();
        echo json_encode([
            "message" => "Summary created successfully",
            "id" => $summary_id,
            "summary" => $summary
        ]);
    } else {
        echo json_encode(["message" => "Failed to create summary"]);
    }
}

function generateSummary($text) {
    // ตัวอย่างการสรุปอย่างง่าย (ควรใช้ AI API จริง เช่น OpenAI)
    $sentences = preg_split('/[.!?]/', $text);
    $important = array_slice($sentences, 0, min(5, count($sentences)));
    return implode('. ', $important) . '.';
}

function getSummaries($db) {
    $user_id = api_require_user_id($db);
    $subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : null;

    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }
    
    $query = "SELECT ls.*, s.subject_name, sf.file_name 
              FROM lesson_summaries ls
              LEFT JOIN subjects s ON ls.subject_id = s.id
              LEFT JOIN source_files sf ON ls.source_file_id = sf.id
              WHERE ls.user_id = :user_id";
    
    if($subject_id) {
        $query .= " AND ls.subject_id = :subject_id";
    }
    
    $query .= " ORDER BY ls.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    if($subject_id) {
        $stmt->bindParam(":subject_id", $subject_id);
    }
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function uploadFile($db) {
    $user_id = api_require_user_id($db);
    $subject_id = isset($_POST['subject_id']) ? $_POST['subject_id'] : null;

    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }
    
    if(isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $upload_dir = '../uploads/';
        $file_path = $upload_dir . time() . '_' . basename($file['name']);
        
        if(move_uploaded_file($file['tmp_name'], $file_path)) {
            $file_type = getFileType($file['type']);
            
            $query = "INSERT INTO source_files (user_id, subject_id, file_name, file_path, file_type) 
                      VALUES (:user_id, :subject_id, :file_name, :file_path, :file_type)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":subject_id", $subject_id);
            $stmt->bindParam(":file_name", $file['name']);
            $stmt->bindParam(":file_path", $file_path);
            $stmt->bindParam(":file_type", $file_type);
            
            if($stmt->execute()) {
                echo json_encode([
                    "message" => "File uploaded successfully",
                    "id" => $db->lastInsertId(),
                    "path" => $file_path
                ]);
            }
        }
    }
}

function getFileType($mime_type) {
    if(strpos($mime_type, 'audio') !== false) return 'audio';
    if(strpos($mime_type, 'pdf') !== false) return 'pdf';
    if(strpos($mime_type, 'image') !== false) return 'image';
    return 'text';
}

function getSummaryWithSource($db) {
    $summary_id = isset($_GET['id']) ? $_GET['id'] : 0;
    $user_id = api_require_user_id($db);
    
    $query = "SELECT ls.*, sf.file_path, sf.file_name, sf.file_type
              FROM lesson_summaries ls
              LEFT JOIN source_files sf ON ls.source_file_id = sf.id
              WHERE ls.id = :id AND ls.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $summary_id);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        api_abort(404, "Summary not found");
    }
    
    echo json_encode($row);
}
?>
