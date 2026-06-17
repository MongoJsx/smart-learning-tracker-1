<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทิน - SMART CLASSROOM</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .schedule-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .profile-sidebar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: #4CAF50;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
        }

        .profile-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .profile-class {
            color: #666;
            font-size: 14px;
        }

        .calendar-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .schedule-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">SMART CLASSROOM</div>
        <div class="nav-menu">
            <a href="overview.php">ภาพรวม</a>
            <a href="review.php">รีวิวเรียน</a>
            <a href="exercise.php">แบบฝึกหัด</a>
            <a href="schedule.php" class="active">ปฏิทิน</a>
            <a href="profile.php">โปรไฟล์</a>
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </nav>

    <div class="container">
        <div class="schedule-container">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-name">
                        สวัสดี <?php echo htmlspecialchars($user['student_id']); ?>
                    </div>
                    <div class="profile-class">
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    </div>
                </div>
            </div>

            <!-- Calendar Section -->
            <div class="calendar-section">
                <h2>ปฏิทินการเรียน</h2>
                <!-- ...existing code... -->
            </div>
        </div>
    </div>
</body>
</html>