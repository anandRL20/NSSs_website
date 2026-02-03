<?php
require_once '../config.php';

// Check if user is admin and whitelisted
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin'] || !$_SESSION['is_whitelisted']) {
    header('Location: ../login.php');
    exit();
}

$conn = getDBConnection();

// Get filter parameters
$selected_batch = isset($_GET['batch']) ? (int)$_GET['batch'] : null;

// Get all users for whitelist management
$users_query = "SELECT id, username, email, full_name, is_admin, is_whitelisted, created_at FROM users ORDER BY created_at DESC";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Get all students with their info, with optional batch filter
$students_query = "SELECT u.id, u.username, u.full_name, u.email, 
                   s.roll_number, s.course, s.year, s.department, s.phone, s.photo, s.batch_year
                   FROM users u 
                   LEFT JOIN student_info s ON u.id = s.user_id 
                   WHERE u.is_admin = 0";

if ($selected_batch) {
    $students_query .= " AND s.batch_year = " . $selected_batch;
}

$students_query .= " ORDER BY s.batch_year DESC, s.roll_number";
$students_result = $conn->query($students_query);
$students = $students_result->fetch_all(MYSQLI_ASSOC);

// Get available batch years
$batch_query = "SELECT DISTINCT batch_year FROM student_info WHERE batch_year IS NOT NULL ORDER BY batch_year DESC";
$batch_result = $conn->query($batch_query);
$available_batches = $batch_result->fetch_all(MYSQLI_ASSOC);

// Count students per batch
$batch_counts = [];
foreach ($available_batches as $batch) {
    $year = $batch['batch_year'];
    $count_query = "SELECT COUNT(*) as count FROM student_info WHERE batch_year = $year";
    $count_result = $conn->query($count_query);
    $count = $count_result->fetch_assoc();
    $batch_counts[$year] = $count['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Management</title>
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
            --success: #27ae60;
            --error: #e74c3c;
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
            background: linear-gradient(135deg, var(--gold), #f4d03f);
            padding: 20px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-badge {
            background: var(--primary);
            color: var(--gold);
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            color: var(--primary);
            font-weight: 500;
        }

        .btn-nav {
            padding: 10px 25px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-nav:hover {
            background: var(--secondary);
        }

        .container {
            max-width: 1600px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .page-header p {
            color: var(--secondary);
            opacity: 0.7;
        }

        .batch-filter-section {
            background: var(--white);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .batch-filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .batch-filter-header h3 {
            font-family: 'Playfair Display', serif;
            color: var(--primary);
            font-size: 20px;
        }

        .batch-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .batch-btn {
            padding: 10px 20px;
            background: var(--white);
            border: 2px solid var(--accent);
            color: var(--accent);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .batch-btn:hover {
            background: var(--accent);
            color: var(--white);
        }

        .batch-btn.active {
            background: var(--highlight);
            border-color: var(--highlight);
            color: var(--white);
        }

        .batch-count {
            background: rgba(255, 255, 255, 0.3);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
        }

        .download-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--success), #2ecc71);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .download-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(15, 76, 117, 0.1);
        }

        .tab {
            padding: 15px 30px;
            background: none;
            border: none;
            color: var(--secondary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            font-size: 16px;
        }

        .tab.active {
            color: var(--highlight);
            border-bottom-color: var(--highlight);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            padding: 30px;
            border-radius: 15px;
            color: var(--white);
        }

        .stat-card h3 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-card p {
            opacity: 0.9;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid rgba(15, 76, 117, 0.1);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--secondary);
        }

        tr:hover {
            background: rgba(15, 76, 117, 0.02);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            margin-right: 5px;
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: var(--highlight);
            color: var(--white);
        }

        .btn-view:hover {
            background: var(--accent);
        }

        .btn-approve {
            background: var(--success);
            color: var(--white);
        }

        .btn-approve:hover {
            background: #219150;
        }

        .btn-revoke {
            background: var(--error);
            color: var(--white);
        }

        .btn-revoke:hover {
            background: #c0392b;
        }

        .btn-edit {
            background: var(--gold);
            color: var(--primary);
        }

        .badge {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
        }

        .badge-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error);
        }

        .badge-admin {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold);
        }

        .badge-batch {
            background: rgba(50, 130, 184, 0.1);
            color: var(--highlight);
            padding: 4px 10px;
            font-size: 11px;
        }

        .student-photo {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
        }

        @media (max-width: 768px) {
            table {
                font-size: 13px;
            }

            th, td {
                padding: 10px;
            }

            .tabs {
                overflow-x: auto;
            }

            .batch-buttons {
                max-height: 200px;
                overflow-y: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <div class="logo-text">
                👨‍💼 Admin Panel
                <span class="admin-badge">ADMIN</span>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="../logout.php" class="btn-nav">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <p>Manage students, view records, and control access</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo count($students); ?></h3>
                <p><?php echo $selected_batch ? "Students in Batch $selected_batch" : "Total Students"; ?></p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--gold), #f4d03f);">
                <h3><?php echo count(array_filter($users, fn($u) => $u['is_whitelisted'])); ?></h3>
                <p>Whitelisted Users</p>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--success), #2ecc71);">
                <h3><?php echo count($available_batches); ?></h3>
                <p>Active Batches</p>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('students')">Students</button>
            <button class="tab" onclick="switchTab('whitelist')">Whitelist Management</button>
        </div>

        <div id="students-tab" class="tab-content active">
            <!-- Batch Filter Section -->
            <div class="batch-filter-section">
                <div class="batch-filter-header">
                    <h3>🎓 Filter by Batch</h3>
                    <?php if ($selected_batch): ?>
                        <a href="download_batch.php?batch=<?php echo $selected_batch; ?>" class="download-btn">
                            📥 Download Batch <?php echo $selected_batch; ?> Info
                        </a>
                    <?php endif; ?>
                </div>
                <div class="batch-buttons">
                    <a href="dashboard.php" class="batch-btn <?php echo !$selected_batch ? 'active' : ''; ?>">
                        All Batches
                        <span class="batch-count"><?php 
                            $total_query = "SELECT COUNT(*) as count FROM student_info";
                            $total_result = $conn->query($total_query);
                            $total = $total_result->fetch_assoc();
                            echo $total['count']; 
                        ?></span>
                    </a>
                    <?php foreach ($available_batches as $batch): ?>
                        <a href="dashboard.php?batch=<?php echo $batch['batch_year']; ?>" 
                           class="batch-btn <?php echo $selected_batch == $batch['batch_year'] ? 'active' : ''; ?>">
                            Batch <?php echo $batch['batch_year']; ?>
                            <span class="batch-count"><?php echo $batch_counts[$batch['batch_year']]; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2 style="font-family: 'Playfair Display', serif; margin-bottom: 25px;">
                    <?php echo $selected_batch ? "Batch $selected_batch Students" : "All Students"; ?>
                </h2>
                <?php if (count($students) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Roll No</th>
                                <th>Batch</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Department</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php if ($student['photo']): ?>
                                            <img src="../<?php echo htmlspecialchars($student['photo']); ?>" 
                                                 class="student-photo" alt="Student">
                                        <?php else: ?>
                                            <div class="student-photo" style="background: linear-gradient(135deg, var(--accent), var(--highlight)); 
                                                 display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['roll_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($student['batch_year']): ?>
                                            <span class="badge badge-batch"><?php echo $student['batch_year']; ?></span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['course'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['year'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-view">View</a>
                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-edit">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: var(--secondary); opacity: 0.6; padding: 40px;">
                        <?php echo $selected_batch ? "No students found in Batch $selected_batch." : "No students registered yet."; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div id="whitelist-tab" class="tab-content">
            <div class="card">
                <h2 style="font-family: 'Playfair Display', serif; margin-bottom: 25px;">User Access Control</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Whitelist Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge badge-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge">Student</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_whitelisted']): ?>
                                        <span class="badge badge-success">✓ Whitelisted</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">✗ Not Whitelisted</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <?php if ($user['is_whitelisted']): ?>
                                            <a href="whitelist_action.php?action=revoke&id=<?php echo $user['id']; ?>" 
                                               class="btn btn-revoke"
                                               onclick="return confirm('Revoke whitelist access?')">Revoke</a>
                                        <?php else: ?>
                                            <a href="whitelist_action.php?action=approve&id=<?php echo $user['id']; ?>" 
                                               class="btn btn-approve">Approve</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--secondary); opacity: 0.6; font-size: 13px;">You</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>