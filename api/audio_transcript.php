<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/bootstrap.php';

$db = api_db();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'upload_audio':
        uploadAudio($db);
        break;
    case 'transcribe':
        transcribeAudio($db);
        break;
    case 'get_transcripts':
        getTranscripts($db);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
}

function uploadAudio($db) {
    $user_id = api_require_user_id($db);
    $subject_id = isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : null;

    if ($subject_id) {
        api_require_ownership($db, 'subjects', $subject_id, $user_id);
    }
    
    if(isset($_FILES['audio'])) {
        $file = $_FILES['audio'];
        $upload_dir = '../uploads/audio/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_path = $upload_dir . time() . '_' . basename($file['name']);
        
        if(move_uploaded_file($file['tmp_name'], $file_path)) {
            $query = "INSERT INTO source_files (user_id, subject_id, file_name, file_path, file_type) 
                      VALUES (:user_id, :subject_id, :file_name, :file_path, 'audio')";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":subject_id", $subject_id);
            $stmt->bindParam(":file_name", $file['name']);
            $stmt->bindParam(":file_path", $file_path);
            
            if($stmt->execute()) {
                $file_id = $db->lastInsertId();
                
                // เริ่มการถอดเสียง (ในที่นี้เป็นตัวอย่าง ควรใช้ API จริง เช่น Google Speech-to-Text)
                $transcript = processAudioFile($file_path);
                $summary = generateSummary($transcript);
                
                $query2 = "INSERT INTO audio_transcripts (user_id, subject_id, audio_file_id, 
                          transcript_text, summary_text) 
                          VALUES (:user_id, :subject_id, :file_id, :transcript, :summary)";
                
                $stmt2 = $db->prepare($query2);
                $stmt2->bindParam(":user_id", $user_id);
                $stmt2->bindParam(":subject_id", $subject_id);
                $stmt2->bindParam(":file_id", $file_id);
                $stmt2->bindParam(":transcript", $transcript);
                $stmt2->bindParam(":summary", $summary);
                $stmt2->execute();
                
                echo json_encode([
                    "message" => "Audio uploaded and processed",
                    "file_id" => $file_id,
                    "transcript" => $transcript,
                    "summary" => $summary
                ]);
            }
        }
    }
}

function processAudioFile($file_path) {
    // ตัวอย่าง - ควรใช้ API จริง เช่น Google Speech-to-Text, OpenAI Whisper
    return "This is a sample transcript from the audio file. In real implementation, use speech-to-text API.";
}

function generateSummary($text) {
    // ตัวอย่างง่ายๆ - ควรใช้ AI API จริง
    $sentences = preg_split('/[.!?]/', $text);
    $important = array_slice($sentences, 0, min(3, count($sentences)));
    return implode('. ', $important) . '.';
}

function transcribeAudio($db) {
    $user_id = api_require_user_id($db);
    $file_id = isset($_POST['file_id']) ? (int) $_POST['file_id'] : 0;

    if ($file_id <= 0) {
        api_abort(422, 'Invalid file_id');
    }
    
    $query = "SELECT file_path FROM source_files WHERE id = :file_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":file_id", $file_id);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($file) {
        $transcript = processAudioFile($file['file_path']);
        echo json_encode(["transcript" => $transcript]);
    } else {
        api_abort(404, 'Audio file not found');
    }
}

function getTranscripts($db) {
    $user_id = api_require_user_id($db);
    
    $query = "SELECT at.*, s.subject_name, sf.file_name 
              FROM audio_transcripts at
              LEFT JOIN subjects s ON at.subject_id = s.id AND s.user_id = :user_id
              LEFT JOIN source_files sf ON at.audio_file_id = sf.id
              WHERE at.user_id = :user_id
              ORDER BY at.processed_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}
?>
