<?php
require_once '../config.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin'] || !$_SESSION['is_whitelisted']) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$student_user_id = intval($_GET['id']);

// Get student info
$stmt = $conn->prepare("SELECT u.*, s.* FROM users u 
                        LEFT JOIN student_info s ON u.id = s.user_id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $student_user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    header('Location: dashboard.php');
    exit();
}

// Get marks
$marks = [];
if (isset($student['id'])) {
    $marks_stmt = $conn->prepare("SELECT * FROM marks WHERE student_id = ? ORDER BY semester, subject_name");
    $marks_stmt->bind_param("i", $student['id']);
    $marks_stmt->execute();
    $marks_result = $marks_stmt->get_result();
    $marks = $marks_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student - Admin Panel</title>
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
            --green: #27ae60;
            --green-hover: #219a52;
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
            color: var(--primary);
            font-weight: 700;
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
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .page-header {
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            color: var(--primary);
        }

        .header-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-edit {
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: var(--white);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-family: 'Work Sans', sans-serif;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(15, 76, 117, 0.3);
        }

        /* ── Download button ── */
        .btn-download {
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--green), var(--green-hover));
            color: var(--white);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-family: 'Work Sans', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(39, 174, 96, 0.3);
        }

        .btn-download:disabled {
            background: #aaa;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-download .icon {
            font-size: 18px;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary);
            color: white;
            padding: 14px 24px;
            border-radius: 10px;
            font-size: 15px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.35s ease;
            z-index: 999;
            pointer-events: none;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.error {
            background: #c0392b;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }

        .profile-section {
            text-align: center;
        }

        .profile-photo {
            width: 200px;
            height: 200px;
            border-radius: 20px;
            object-fit: cover;
            margin: 0 auto 25px;
            display: block;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .profile-name {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .profile-email {
            color: var(--secondary);
            opacity: 0.7;
            margin-bottom: 20px;
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 25px;
            font-weight: 600;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(15, 76, 117, 0.1);
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
        }

        .info-value {
            color: var(--primary);
            font-weight: 600;
        }

        table {
            width: 100%;
            margin-top: 20px;
        }

        th {
            text-align: left;
            padding: 12px;
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--secondary);
        }

        .percentage {
            font-weight: 600;
            color: var(--highlight);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--secondary);
            opacity: 0.6;
        }

        @media (max-width: 968px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <div class="logo-text">👨‍💼 Admin Panel</div>
            <a href="dashboard.php" class="btn-nav">← Back to Dashboard</a>
        </div>
    </nav>

    <!-- Toast for feedback -->
    <div class="toast" id="toast"></div>

    <div class="container">
        <div class="page-header">
            <h1>Student Profile</h1>
            <div class="header-buttons">
                <!-- Download as Word button -->
                <button class="btn-download" id="btnDownload" onclick="downloadDoc(<?php echo $student_user_id; ?>)">
                    <span class="icon">📄</span> Download as Word
                </button>
                <a href="edit_student.php?id=<?php echo $student_user_id; ?>" class="btn-edit">Edit Student</a>
            </div>
        </div>

        <div class="cards-grid">
            <div class="card profile-section">
                <?php if (!empty($student['photo'])): ?>
                    <img src="../<?php echo htmlspecialchars($student['photo']); ?>" class="profile-photo" alt="Student Photo">
                <?php else: ?>
                    <div class="profile-photo" style="background: linear-gradient(135deg, var(--accent), var(--highlight)); 
                         display: flex; align-items: center; justify-content: center; color: white; font-size: 72px; font-weight: 700;">
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($student['email']); ?></div>
                
                <div style="margin-top: 20px;">
                    <div class="info-row">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['username']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Joined</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Academic Information</h2>
                <?php if (!empty($student['roll_number'])): ?>
                    <div class="info-row">
                        <span class="info-label">Roll Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['roll_number']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Course</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['course']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Year</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['year']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['department']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date of Birth</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($student['date_of_birth'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($student['address']); ?></span>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Student has not completed their profile yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2>Academic Performance</h2>
            <?php if (count($marks) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Semester</th>
                            <th>Marks Obtained</th>
                            <th>Max Marks</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marks as $mark): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mark['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($mark['semester']); ?></td>
                                <td><?php echo $mark['marks_obtained']; ?></td>
                                <td><?php echo $mark['max_marks']; ?></td>
                                <td class="percentage">
                                    <?php echo round(($mark['marks_obtained'] / $mark['max_marks']) * 100, 2); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No marks have been added yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    /**
     * Sends a POST to download_student_doc.php, receives the .docx blob,
     * and triggers a browser download — no page reload.
     */
    function downloadDoc(studentId) {
        const btn    = document.getElementById('btnDownload');
        const toast  = document.getElementById('toast');

        btn.disabled = true;
        btn.innerHTML = '<span class="icon">⏳</span> Generating...';

        fetch('download_student_doc.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'student_id=' + encodeURIComponent(studentId)
        })
        .then(response => {
            if (!response.ok) {
                // Read raw text first, then try to parse as JSON safely
                return response.text().then(text => {
                    let message = 'Server error ' + response.status;
                    try {
                        const err = JSON.parse(text);
                        message = err.error || message;
                    } catch (e) {
                        // Not JSON — use the raw text if it exists
                        if (text.trim()) message = text.trim().substring(0, 200);
                    }
                    throw new Error(message);
                });
            }
            // Grab the filename the server sent in the header
            const disposition = response.headers.get('Content-Disposition');
            const filename = disposition && disposition.includes('filename=')
                ? disposition.split('filename=')[1].replace(/"/g, '')
                : 'student_report.docx';
            return response.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            // Create a temporary <a> to trigger the download
            const url  = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href     = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);

            showToast('Download started!', false);
        })
        .catch(err => {
            showToast('Error: ' + err.message, true);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<span class="icon">📄</span> Download as Word';
        });
    }

    function showToast(message, isError) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '');
        // Force reflow so the transition fires again if toast is already visible
        void toast.offsetWidth;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
    </script>
</body>
</html>
<?php
$conn->close();
?>