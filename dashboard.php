<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Fetch student info
$stmt = $conn->prepare("SELECT * FROM student_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student_info = $result->fetch_assoc();

// Fetch marks
$marks_stmt = $conn->prepare("SELECT * FROM marks WHERE student_id = ?");
if ($student_info) {
    $marks_stmt->bind_param("i", $student_info['id']);
    $marks_stmt->execute();
    $marks_result = $marks_stmt->get_result();
    $marks = $marks_result->fetch_all(MYSQLI_ASSOC);
} else {
    $marks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f4c75;
            --highlight: #3282b8;
            --light: #bbe1fa;
            --white: #ffffff;
            --gold: #d4af37;
            --bg: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Work Sans', sans-serif;
            background: var(--bg);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 20px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--white);
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            color: var(--light);
            font-weight: 500;
        }

        .btn {
            padding: 10px 25px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            font-size: 14px;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .dashboard-header {
            margin-bottom: 40px;
        }

        .dashboard-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: var(--secondary);
            opacity: 0.7;
            font-size: 16px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn-primary {
            padding: 15px 30px;
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: var(--white);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(15, 76, 117, 0.2);
            border: none;
            cursor: pointer;
            font-size: 15px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(15, 76, 117, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--gold), #f4d03f);
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(15, 76, 117, 0.1);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary);
            font-weight: 600;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--secondary);
            font-weight: 500;
            opacity: 0.7;
            font-size: 14px;
        }

        .info-value {
            color: var(--primary);
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--secondary);
            opacity: 0.6;
        }

        .marks-table {
            width: 100%;
            margin-top: 20px;
        }

        .marks-table th {
            text-align: left;
            padding: 12px;
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
        }

        .marks-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--secondary);
        }

        .percentage {
            font-weight: 600;
            color: var(--highlight);
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header h1 {
                font-size: 32px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <div class="logo-text">🎓 Student Portal</div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="logout.php" class="btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
            <p>Manage your student profile and academic information</p>
            
            <div class="action-buttons">
                <a href="edit_profile.php" class="btn-primary">
                    <?php echo $student_info ? '✏️ Edit Profile' : '➕ Create Profile'; ?>
                </a>
                <?php if ($student_info): ?>
                    <a href="edit_marks.php" class="btn-primary btn-secondary">📊 Manage Marks</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($student_info): ?>
            <div class="cards-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">👤</div>
                        <h2>Personal Information</h2>
                    </div>
                    
                    <?php if ($student_info['photo']): ?>
                        <img src="<?php echo htmlspecialchars($student_info['photo']); ?>" alt="Student Photo" class="profile-photo">
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <span class="info-label">Roll Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($student_info['roll_number'] ?? 'Not Set'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Course</span>
                        <span class="info-value"><?php echo htmlspecialchars($student_info['course'] ?? 'Not Set'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Year</span>
                        <span class="info-value"><?php echo htmlspecialchars($student_info['year'] ?? 'Not Set'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department</span>
                        <span class="info-value"><?php echo htmlspecialchars($student_info['department'] ?? 'Not Set'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($student_info['phone'] ?? 'Not Set'); ?></span>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-icon">📊</div>
                        <h2>Academic Performance</h2>
                    </div>
                    
                    <?php if (count($marks) > 0): ?>
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Semester</th>
                                    <th>Marks</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marks as $mark): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mark['subject_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mark['semester']); ?></td>
                                        <td><?php echo $mark['marks_obtained'] . '/' . $mark['max_marks']; ?></td>
                                        <td class="percentage">
                                            <?php echo round(($mark['marks_obtained'] / $mark['max_marks']) * 100, 2); ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No marks added yet. Click "Manage Marks" to add your marks.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="cards-grid">
                <div class="card">
                    <div class="empty-state">
                        <h2>👋 Get Started</h2>
                        <p>Create your student profile to begin managing your academic information.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>