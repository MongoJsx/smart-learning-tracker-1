<?php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/bootstrap.php';

$db = api_db();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch($action) {
    case 'analyze_and_recommend':
        analyzeAndRecommend($db);
        break;
    case 'get_recommendations':
        getRecommendations($db);
        break;
    case 'get_subject_performance':
        getSubjectPerformance($db);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
}

function analyzeAndRecommend(PDO $db) {
    $user_id = api_require_user_id($db);
    
    // วิเคราะห์วิชาที่ถนัด
    $query = "SELECT s.id, s.subject_name, s.subject_code,
              COUNT(DISTINCT ls.id) as summary_count,
              COALESCE(SUM(ss.duration_minutes)/60, 0) as study_hours,
              AVG(CASE mt.mood 
                  WHEN 'happy' THEN 5 
                  WHEN 'excited' THEN 5
                  WHEN 'neutral' THEN 3 
                  WHEN 'tired' THEN 2
                  WHEN 'stressed' THEN 1 
                  ELSE 3 END) as avg_mood_score
              FROM subjects s
              LEFT JOIN lesson_summaries ls ON s.id = ls.subject_id
              LEFT JOIN study_sessions ss ON s.id = ss.subject_id
              LEFT JOIN mood_tracking mt ON s.id = mt.subject_id
              WHERE s.user_id = :user_id
              GROUP BY s.id
              HAVING summary_count > 0 OR study_hours > 0
              ORDER BY (summary_count * 0.4 + study_hours * 0.3 + COALESCE(avg_mood_score, 3) * 0.3) DESC
              LIMIT 5";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $top_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Career analysis query failed: ' . $e->getMessage());
        $top_subjects = [];
    }
    
    // สร้างคำแนะนำอาชีพ
    $recommendations = generateCareerRecommendations($top_subjects);
    
    // บันทึกลงฐานข้อมูล
    if (careerTableSupportsSimpleColumns($db)) {
        foreach($recommendations as $rec) {
            $query2 = "INSERT INTO career_recommendations 
                      (user_id, career_name, recommended_skills, based_on_subjects, confidence_score)
                      VALUES (:user_id, :career_name, :skills, :subjects, :score)";
            
            $stmt2 = $db->prepare($query2);
            $stmt2->bindParam(":user_id", $user_id);
            $stmt2->bindParam(":career_name", $rec['career']);
            $stmt2->bindParam(":skills", $rec['skills']);
            $stmt2->bindParam(":subjects", $rec['subjects']);
            $stmt2->bindParam(":score", $rec['score']);
            $stmt2->execute();
        }
    }
    
    echo json_encode([
        "top_subjects" => $top_subjects,
        "recommendations" => $recommendations
    ]);
}

function generateCareerRecommendations($subjects) {
    // ลบตัวอย่างอาชีพแบบ hardcode ออกทั้งหมด
    // ควรให้ฝั่ง AI service เป็นผู้วิเคราะห์จริงเท่านั้น
    return [];
}

function getRecommendations(PDO $db) {
    $user_id = api_require_user_id($db);
    
    if (!careerTableSupportsSimpleColumns($db)) {
        echo json_encode([]);
        return;
    }
    
    $query = "SELECT * FROM career_recommendations 
              WHERE user_id = :user_id 
              ORDER BY confidence_score DESC, id DESC
              LIMIT 5";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log('Subject performance query failed: ' . $e->getMessage());
        echo json_encode([]);
    }
}

function getSubjectPerformance(PDO $db) {
    $user_id = api_require_user_id($db);
    
    $query = "SELECT * FROM subject_performance 
              WHERE user_id = :user_id 
              ORDER BY (summary_count * 0.4 + study_hours * 0.6) DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function careerTableSupportsSimpleColumns(PDO $db) {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    
    try {
        $columns = ['career_name', 'recommended_skills', 'based_on_subjects', 'confidence_score'];
        foreach ($columns as $column) {
            $stmt = $db->prepare("SHOW COLUMNS FROM career_recommendations LIKE :column");
            $stmt->bindParam(":column", $column);
            $stmt->execute();
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $cache = false;
                return false;
            }
        }
        $cache = true;
        return true;
    } catch (Exception $e) {
        $cache = false;
        return false;
    }
}

?>
