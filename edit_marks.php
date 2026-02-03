<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get student info
$stmt = $conn->prepare("SELECT id FROM student_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student_info = $result->fetch_assoc();

if (!$student_info) {
    header('Location: edit_profile.php');
    exit();
}

$student_id = $student_info['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $mark_id = $_POST['mark_id'];
        $stmt = $conn->prepare("DELETE FROM marks WHERE id = ? AND student_id = ?");
        $stmt->bind_param("ii", $mark_id, $student_id);
        if ($stmt->execute()) {
            $success = 'Mark deleted successfully!';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add') {
        $subject = trim($_POST['subject_name']);
        $marks_obtained = intval($_POST['marks_obtained']);
        $max_marks = intval($_POST['max_marks']);
        $semester = trim($_POST['semester']);
        
        if ($marks_obtained > $max_marks) {
            $error = 'Marks obtained cannot be greater than maximum marks';
        } else {
            $stmt = $conn->prepare("INSERT INTO marks (student_id, subject_name, marks_obtained, max_marks, semester) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isiis", $student_id, $subject, $marks_obtained, $max_marks, $semester);
            if ($stmt->execute()) {
                $success = 'Mark added successfully!';
            } else {
                $error = 'Failed to add mark';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $mark_id = $_POST['mark_id'];
        $marks_obtained = intval($_POST['marks_obtained']);
        $max_marks = intval($_POST['max_marks']);
        
        if ($marks_obtained > $max_marks) {
            $error = 'Marks obtained cannot be greater than maximum marks';
        } else {
            $stmt = $conn->prepare("UPDATE marks SET marks_obtained = ?, max_marks = ? WHERE id = ? AND student_id = ?");
            $stmt->bind_param("iiii", $marks_obtained, $max_marks, $mark_id, $student_id);
            if ($stmt->execute()) {
                $success = 'Mark updated successfully!';
            }
        }
    }
}

// Fetch all marks
$stmt = $conn->prepare("SELECT * FROM marks WHERE student_id = ? ORDER BY semester, subject_name");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$marks = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Marks - Student Management</title>
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
            --error: #e74c3c;
            --success: #27ae60;
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

        .btn-nav {
            padding: 10px 25px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-nav:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .container {
            max-width: 1200px;
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

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .card {
            background: var(--white);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .card h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 25px;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        label {
            display: block;
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid rgba(15, 76, 117, 0.1);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Work Sans', sans-serif;
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 4px rgba(50, 130, 184, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-add {
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: var(--white);
            box-shadow: 0 5px 20px rgba(15, 76, 117, 0.2);
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 76, 117, 0.3);
        }

        .marks-table {
            width: 100%;
            margin-top: 20px;
        }

        .marks-table th {
            text-align: left;
            padding: 15px;
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid rgba(15, 76, 117, 0.1);
        }

        .marks-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--secondary);
        }

        .marks-table tr:hover {
            background: rgba(50, 130, 184, 0.03);
        }

        .percentage {
            font-weight: 600;
            color: var(--highlight);
            font-size: 16px;
        }

        .edit-form {
            display: none;
            padding: 15px;
            background: rgba(50, 130, 184, 0.05);
            border-radius: 10px;
            margin: 10px 0;
        }

        .edit-form.active {
            display: block;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
            margin-right: 8px;
        }

        .btn-edit {
            background: var(--highlight);
            color: var(--white);
        }

        .btn-delete {
            background: var(--error);
            color: var(--white);
        }

        .btn-save {
            background: var(--success);
            color: var(--white);
        }

        .btn-cancel {
            background: var(--secondary);
            color: var(--white);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--secondary);
            opacity: 0.6;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .marks-table {
                font-size: 13px;
            }

            .marks-table th, .marks-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <div class="logo-text">🎓 Student Portal</div>
            <a href="dashboard.php" class="btn-nav">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Manage Marks</h1>
            <p>Add and update your academic marks</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Add New Mark</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="subject_name">Subject Name</label>
                        <input type="text" id="subject_name" name="subject_name" required>
                    </div>
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required>
                            <option value="">Select</option>
                            <option value="Sem 1">Sem 1</option>
                            <option value="Sem 2">Sem 2</option>
                            <option value="Sem 3">Sem 3</option>
                            <option value="Sem 4">Sem 4</option>
                            <option value="Sem 5">Sem 5</option>
                            <option value="Sem 6">Sem 6</option>
                            <option value="Sem 7">Sem 7</option>
                            <option value="Sem 8">Sem 8</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="marks_obtained">Marks</label>
                        <input type="number" id="marks_obtained" name="marks_obtained" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="max_marks">Max Marks</label>
                        <input type="number" id="max_marks" name="max_marks" min="1" required>
                    </div>
                    <div class="form-group">
                        <label style="opacity: 0;">Add</label>
                        <button type="submit" class="btn btn-add">Add Mark</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Your Marks</h2>
            <?php if (count($marks) > 0): ?>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Semester</th>
                            <th>Marks Obtained</th>
                            <th>Max Marks</th>
                            <th>Percentage</th>
                            <th>Actions</th>
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
                                <td>
                                    <button onclick="toggleEdit(<?php echo $mark['id']; ?>)" class="btn btn-small btn-edit">Edit</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="mark_id" value="<?php echo $mark['id']; ?>">
                                        <button type="submit" class="btn btn-small btn-delete" 
                                                onclick="return confirm('Are you sure you want to delete this mark?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6">
                                    <div id="edit-<?php echo $mark['id']; ?>" class="edit-form">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="mark_id" value="<?php echo $mark['id']; ?>">
                                            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">
                                                <div>
                                                    <label>Marks Obtained</label>
                                                    <input type="number" name="marks_obtained" value="<?php echo $mark['marks_obtained']; ?>" min="0" required>
                                                </div>
                                                <div>
                                                    <label>Max Marks</label>
                                                    <input type="number" name="max_marks" value="<?php echo $mark['max_marks']; ?>" min="1" required>
                                                </div>
                                                <div>
                                                    <button type="submit" class="btn btn-small btn-save">Save</button>
                                                    <button type="button" onclick="toggleEdit(<?php echo $mark['id']; ?>)" 
                                                            class="btn btn-small btn-cancel">Cancel</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No marks added yet. Use the form above to add your marks.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleEdit(markId) {
            const editForm = document.getElementById('edit-' + markId);
            editForm.classList.toggle('active');
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>