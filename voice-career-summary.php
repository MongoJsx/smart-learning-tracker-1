<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปและแนะนำอาชีพจากเสียง</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Kanit', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #667eea;
            margin-bottom: 30px;
            text-align: center;
        }

        .voice-recorder {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .record-button {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 48px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .record-button:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        .record-button.recording {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .status-text {
            margin-top: 20px;
            font-size: 18px;
            color: #666;
        }

        .timer {
            font-size: 24px;
            color: #667eea;
            margin-top: 10px;
            font-weight: 600;
        }

        .audio-wave {
            margin-top: 20px;
            height: 60px;
            display: none;
        }

        .summary-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            display: none;
        }

        .summary-section h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 22px;
        }

        .transcript {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .career-recommendations {
            display: grid;
            gap: 15px;
        }

        .career-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #764ba2;
            transition: transform 0.3s ease;
        }

        .career-card:hover {
            transform: translateX(5px);
        }

        .career-card h3 {
            color: #764ba2;
            margin-bottom: 10px;
        }

        .career-card .match-score {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }

        .subject-tags {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .subject-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 14px;
        }

        .loading {
            text-align: center;
            padding: 20px;
            display: none;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .back-button {
            display: inline-block;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-button">← กลับสู่หน้าหลัก</a>
        
        <h1>🎤 สรุปและแนะนำอาชีพจากเสียง</h1>

        <div class="voice-recorder">
            <button id="recordButton" class="record-button">🎙️</button>
            <div class="status-text" id="statusText">กดปุ่มเพื่อเริ่มบันทึกเสียง</div>
            <div class="timer" id="timer">00:00</div>
            <canvas id="audioWave" class="audio-wave"></canvas>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 15px; color: #666;">กำลังประมวลผล...</p>
        </div>

        <div class="summary-section" id="summarySection">
            <h2>📝 สรุปจากเสียงที่บันทึก</h2>
            <div class="transcript" id="transcript">
                <p>กำลังรอข้อมูล...</p>
            </div>

            <h2>🎯 อาชีพที่แนะนำจากวิชาที่คุณถนัด</h2>
            <div class="career-recommendations" id="careerRecommendations">
                <!-- Career cards will be inserted here -->
            </div>
        </div>
    </div>

    <script src="voice-career-handler.js"></script>
</body>
</html>
