<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .feature-card { cursor: pointer; transition: transform 0.2s; }
        .feature-card:hover { transform: scale(1.05); }
        .mood-emoji { font-size: 2rem; cursor: pointer; }
        .mood-emoji:hover { transform: scale(1.2); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Study System</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">สวัสดี, <?php echo $_SESSION['username']; ?></span>
                <a class="btn btn-outline-light" href="logout.php">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- การแจ้งเตือน -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='notifications.php'">
                    <div class="card-body text-center">
                        <h3>🔔</h3>
                        <h5>การแจ้งเตือน</h5>
                        <p>ตั้งค่าการแจ้งเตือนตารางเรียน</p>
                    </div>
                </div>
            </div>

            <!-- ปฏิทินการเรียน -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='calendar.php'">
                    <div class="card-body text-center">
                        <h3>📅</h3>
                        <h5>ปฏิทินการเรียน</h5>
                        <p>ดูตารางเรียนและจดบันทึก</p>
                    </div>
                </div>
            </div>

            <!-- สรุปบทเรียน -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='summary.php'">
                    <div class="card-body text-center">
                        <h3>📝</h3>
                        <h5>สรุปบทเรียน</h5>
                        <p>สรุปเนื้อหาด้วย AI</p>
                    </div>
                </div>
            </div>

            <!-- บันทึกอารมณ์ -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='mood.php'">
                    <div class="card-body text-center">
                        <h3>😊</h3>
                        <h5>บันทึกอารมณ์</h5>
                        <p>จับอารมณ์การเรียนรู้</p>
                    </div>
                </div>
            </div>

            <!-- เป้าหมายการเรียน -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='goals.php'">
                    <div class="card-body text-center">
                        <h3>🎯</h3>
                        <h5>เป้าหมายการเรียน</h5>
                        <p>ตั้งเป้าหมายรายสัปดาห์</p>
                    </div>
                </div>
            </div>

            <!-- สรุปจากเสียง -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='audio.php'">
                    <div class="card-body text-center">
                        <h3>🎤</h3>
                        <h5>สรุปจากเสียง</h5>
                        <p>แปลงเสียงเป็นข้อความ</p>
                    </div>
                </div>
            </div>

            <!-- แนะนำอาชีพ -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='career.php'">
                    <div class="card-body text-center">
                        <h3>💼</h3>
                        <h5>แนะนำอาชีพ</h5>
                        <p>วิเคราะห์แนวทางอาชีพ</p>
                    </div>
                </div>
            </div>

            <!-- กราฟชั่วโมงเรียน -->
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card feature-card" onclick="location.href='stats.php'">
                    <div class="card-body text-center">
                        <h3>📊</h3>
                        <h5>สถิติการเรียน</h5>
                        <p>ดูกราฟชั่วโมงการเรียน</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- สถิติด่วน -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>สถิติสัปดาห์นี้</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // โหลดข้อมูลสถิติ
        fetch(`api/study_stats.php?action=get_weekly_stats&user_id=<?php echo $_SESSION['user_id']; ?>`)
            .then(response => response.json())
            .then(data => {
                const ctx = document.getElementById('weeklyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(d => d.session_date),
                        datasets: [{
                            label: 'ชั่วโมงเรียน',
                            data: data.map(d => d.total_hours),
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
            });
    </script>
</body>
</html>
