<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit();
}

if (!isset($_FILES['audio'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์เสียง']);
    exit();
}

$userId = $_SESSION['user_id'];

// จำลองการแปลงเสียงเป็นข้อความ (ในการใช้งานจริงควรใช้ API เช่น Google Speech-to-Text)
$transcript = simulateVoiceToText($_FILES['audio']);

// ดึงข้อมูลวิชาที่ถนัดของผู้ใช้
$stmt = $conn->prepare("
    SELECT subject_name, proficiency_level, interest_level 
    FROM user_subjects 
    WHERE user_id = ? 
    ORDER BY (proficiency_level + interest_level) DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$strongSubjects = [];
while ($row = $result->fetch_assoc()) {
    if ($row['proficiency_level'] >= 3 || $row['interest_level'] >= 3) {
        $strongSubjects[] = $row['subject_name'];
    }
}

// แนะนำอาชีพตามวิชาที่ถนัด
$careers = recommendCareers($strongSubjects, $transcript);

echo json_encode([
    'success' => true,
    'data' => [
        'transcript' => $transcript,
        'careers' => $careers
    ]
]);

function simulateVoiceToText($audioFile) {
    // จำลองการแปลงเสียง - ในการใช้งานจริงต้องใช้ Speech-to-Text API
    $sampleTexts = [
        "ผมสนใจงานที่เกี่ยวข้องกับคอมพิวเตอร์และเทคโนโลยี ชอบแก้ปัญหาและเขียนโปรแกรม",
        "อยากทำงานที่ต้องใช้ความคิดสร้างสรรค์ ชอบวาดรูปและออกแบบ",
        "สนใจด้านธุรกิจและการเงิน ชอบวิเคราะห์ข้อมูลและวางแผน",
        "ชอบช่วยเหลือผู้อื่นและทำงานเกี่ยวกับสุขภาพ"
    ];
    
    return $sampleTexts[array_rand($sampleTexts)];
}

function recommendCareers($subjects, $transcript) {
    $careerDatabase = [
        [
            'title' => 'วิศวกรซอฟต์แวร์',
            'description' => 'พัฒนาและออกแบบระบบซอฟต์แวร์ แอปพลิเคชัน และเว็บไซต์',
            'subjects' => ['คณิตศาสตร์', 'วิทยาศาสตร์', 'คอมพิวเตอร์'],
            'keywords' => ['โปรแกรม', 'คอมพิวเตอร์', 'เทคโนโลยี', 'เขียน']
        ],
        [
            'title' => 'นักวิเคราะห์ข้อมูล',
            'description' => 'วิเคราะห์และตีความข้อมูลเพื่อช่วยในการตัดสินใจทางธุรกิจ',
            'subjects' => ['คณิตศาสตร์', 'สถิติ', 'คอมพิวเตอร์'],
            'keywords' => ['วิเคราะห์', 'ข้อมูล', 'สถิติ', 'ธุรกิจ']
        ],
        [
            'title' => 'นักออกแบบกราฟิก',
            'description' => 'สร้างสรรค์งานออกแบบภาพและสื่อดิจิทัล',
            'subjects' => ['ศิลปะ', 'คอมพิวเตอร์', 'ภาษาอังกฤษ'],
            'keywords' => ['ออกแบบ', 'สร้างสรรค์', 'วาดรูป', 'ศิลป']
        ],
        [
            'title' => 'แพทย์/พยาบาล',
            'description' => 'ดูแลและรักษาผู้ป่วย ให้บริการทางการแพทย์',
            'subjects' => ['วิทยาศาสตร์', 'ชีววิทยา', 'เคมี'],
            'keywords' => ['สุขภาพ', 'ช่วยเหลือ', 'รักษา', 'ดูแล']
        ],
        [
            'title' => 'นักวิจัย',
            'description' => 'ทำการวิจัยและพัฒนาองค์ความรู้ใหม่ๆ',
            'subjects' => ['วิทยาศาสตร์', 'คณิตศาสตร์', 'ภาษาอังกฤษ'],
            'keywords' => ['วิจัย', 'ทดลอง', 'วิทยาศาสตร์', 'ค้นคว้า']
        ],
        [
            'title' => 'นักการตลาดดิจิทัล',
            'description' => 'วางแผนและดำเนินการตลาดผ่านช่องทางออนไลน์',
            'subjects' => ['ภาษาอังกฤษ', 'คอมพิวเตอร์', 'สังคมศึกษา'],
            'keywords' => ['ตลาด', 'ธุรกิจ', 'โซเชียล', 'ขาย']
        ]
    ];

    $recommendations = [];
    
    foreach ($careerDatabase as $career) {
        $matchScore = 0;
        
        // คะแนนจากวิชาที่ถนัด
        $subjectMatch = count(array_intersect($subjects, $career['subjects']));
        $matchScore += ($subjectMatch / max(count($career['subjects']), 1)) * 60;
        
        // คะแนนจาก transcript
        $transcriptLower = mb_strtolower($transcript);
        foreach ($career['keywords'] as $keyword) {
            if (strpos($transcriptLower, $keyword) !== false) {
                $matchScore += 10;
            }
        }
        
        $matchScore = min($matchScore, 100);
        
        if ($matchScore >= 30) {
            $recommendations[] = [
                'title' => $career['title'],
                'description' => $career['description'],
                'subjects' => $career['subjects'],
                'match_score' => round($matchScore)
            ];
        }
    }
    
    // เรียงตามคะแนนความเหมาะสม
    usort($recommendations, function($a, $b) {
        return $b['match_score'] - $a['match_score'];
    });
    
    return array_slice($recommendations, 0, 5);
}
?>
