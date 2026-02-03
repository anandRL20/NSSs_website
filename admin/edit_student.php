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
$error = '';
$success = '';

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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $roll_number = trim($_POST['roll_number']);
    $course = trim($_POST['course']);
    $year = trim($_POST['year']);
    $batch_year = isset($_POST['batch_year']) ? intval($_POST['batch_year']) : null;
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['date_of_birth'];
    
    $photo_path = $student['photo'] ?? null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'student_' . $student_user_id . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/' . $new_filename;
            
            if (!is_dir('../uploads')) {
                mkdir('../uploads', 0755, true);
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                if ($photo_path && file_exists('../' . $photo_path)) {
                    unlink('../' . $photo_path);
                }
                $photo_path = 'uploads/' . $new_filename;
            }
        }
    }
    
    if (isset($student['id']) && $student['id']) {
        $stmt = $conn->prepare("UPDATE student_info SET roll_number=?, course=?, year=?, batch_year=?, department=?, phone=?, address=?, date_of_birth=?, photo=? WHERE user_id=?");
        $stmt->bind_param("sssississi", $roll_number, $course, $year, $batch_year, $department, $phone, $address, $dob, $photo_path, $student_user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO student_info (user_id, roll_number, course, year, batch_year, department, phone, address, date_of_birth, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssisssss", $student_user_id, $roll_number, $course, $year, $batch_year, $department, $phone, $address, $dob, $photo_path);
    }
    
    if ($stmt->execute()) {
        $success = 'Profile updated successfully!';
        // Refresh data
        $stmt = $conn->prepare("SELECT u.*, s.* FROM users u LEFT JOIN student_info s ON u.id = s.user_id WHERE u.id = ?");
        $stmt->bind_param("i", $student_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
    } else {
        $error = 'Failed to update profile.';
    }
}

// Handle mark operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_mark']) && isset($student['id'])) {
        $subject = trim($_POST['subject_name']);
        $marks_obtained = intval($_POST['marks_obtained']);
        $max_marks = intval($_POST['max_marks']);
        $semester = trim($_POST['semester']);
        
        if ($marks_obtained <= $max_marks) {
            $stmt = $conn->prepare("INSERT INTO marks (student_id, subject_name, marks_obtained, max_marks, semester) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isiis", $student['id'], $subject, $marks_obtained, $max_marks, $semester);
            if ($stmt->execute()) {
                $success = 'Mark added successfully!';
            }
        } else {
            $error = 'Marks obtained cannot exceed maximum marks';
        }
    } elseif (isset($_POST['update_mark'])) {
        $mark_id = $_POST['mark_id'];
        $marks_obtained = intval($_POST['marks_obtained']);
        $max_marks = intval($_POST['max_marks']);
        
        if ($marks_obtained <= $max_marks) {
            $stmt = $conn->prepare("UPDATE marks SET marks_obtained = ?, max_marks = ? WHERE id = ?");
            $stmt->bind_param("iii", $marks_obtained, $max_marks, $mark_id);
            if ($stmt->execute()) {
                $success = 'Mark updated successfully!';
            }
        } else {
            $error = 'Marks obtained cannot exceed maximum marks';
        }
    } elseif (isset($_POST['delete_mark'])) {
        $mark_id = $_POST['mark_id'];
        $stmt = $conn->prepare("DELETE FROM marks WHERE id = ?");
        $stmt->bind_param("i", $mark_id);
        if ($stmt->execute()) {
            $success = 'Mark deleted successfully!';
        }
    }
}

// Get marks
$marks = [];
if (isset($student['id']) && $student['id']) {
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
    <title>Edit Student - Admin Panel</title>
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

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 30px;
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
            margin: 25px 0;
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
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            color: var(--primary);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input, select, textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(15, 76, 117, 0.1);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Work Sans', sans-serif;
            transition: all 0.3s ease;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--highlight);
            box-shadow: 0 0 0 4px rgba(50, 130, 184, 0.1);
        }

        .photo-upload {
            border: 2px dashed rgba(15, 76, 117, 0.2);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-upload:hover {
            border-color: var(--highlight);
            background: rgba(50, 130, 184, 0.05);
        }

        .current-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            margin: 20px auto;
            display: block;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 16px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: var(--white);
            box-shadow: 0 10px 30px rgba(15, 76, 117, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(15, 76, 117, 0.3);
        }

        table {
            width: 100%;
            margin-top: 20px;
        }

        th {
            text-align: left;
            padding: 15px;
            background: rgba(15, 76, 117, 0.05);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--secondary);
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

        .btn-add {
            background: var(--success);
            color: var(--white);
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

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-content">
            <div class="logo-text">👨‍💼 Admin Panel</div>
            <a href="view_student.php?id=<?php echo $student_user_id; ?>" class="btn-nav">← Back to Student</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>Edit Student: <?php echo htmlspecialchars($student['full_name']); ?></h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Profile Information</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Roll Number</label>
                        <input type="text" name="roll_number" value="<?php echo htmlspecialchars($student['roll_number'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Course</label>
                        <input type="text" name="course" value="<?php echo htmlspecialchars($student['course'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" required>
                            <option value="">Select Year</option>
                            <option value="1st Year" <?php echo ($student['year'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd Year" <?php echo ($student['year'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd Year" <?php echo ($student['year'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th Year" <?php echo ($student['year'] ?? '') === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Batch Year</label>
                        <select name="batch_year" required>
                            <option value="">Select Batch</option>
                            <?php for ($year = 2022; $year <= 2030; $year++): ?>
                                <option value="<?php echo $year; ?>" <?php echo (isset($student['batch_year']) && $student['batch_year'] == $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="<?php echo htmlspecialchars($student['department'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Address</label>
                        <textarea name="address" required><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Student Photo</label>
                        <?php if (!empty($student['photo'])): ?>
                            <img src="../<?php echo htmlspecialchars($student['photo']); ?>" class="current-photo">
                        <?php endif; ?>
                        <div class="photo-upload" onclick="document.getElementById('photo').click()">
                            <p style="color: var(--secondary); margin-bottom: 10px;">📸 Click to upload new photo</p>
                            <input type="file" id="photo" name="photo" accept="image/*" style="display: none;">
                        </div>
                    </div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                    Save Profile Changes
                </button>
            </form>
        </div>

        <?php if (isset($student['id']) && $student['id']): ?>
        <div class="card">
            <h2>Add New Mark</h2>
            <form method="POST">
                <div class="form-grid" style="grid-template-columns: 2fr 1fr 1fr 1fr auto; align-items: end;">
                    <div class="form-group">
                        <label>Subject Name</label>
                        <input type="text" name="subject_name" required>
                    </div>
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester" required>
                            <option value="">Select</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="Sem <?php echo $i; ?>">Sem <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marks</label>
                        <input type="number" name="marks_obtained" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Max Marks</label>
                        <input type="number" name="max_marks" min="1" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" name="add_mark" class="btn btn-small btn-add">Add</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Student Marks</h2>
            <?php if (count($marks) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Semester</th>
                            <th>Marks</th>
                            <th>Max</th>
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
                                <td style="font-weight: 600; color: var(--highlight);">
                                    <?php echo round(($mark['marks_obtained'] / $mark['max_marks']) * 100, 2); ?>%
                                </td>
                                <td>
                                    <button onclick="toggleEdit(<?php echo $mark['id']; ?>)" class="btn btn-small btn-edit">Edit</button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mark_id" value="<?php echo $mark['id']; ?>">
                                        <button type="submit" name="delete_mark" class="btn btn-small btn-delete" 
                                                onclick="return confirm('Delete this mark?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="6">
                                    <div id="edit-<?php echo $mark['id']; ?>" class="edit-form">
                                        <form method="POST">
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
                                                    <button type="submit" name="update_mark" class="btn btn-small btn-add">Save</button>
                                                    <button type="button" onclick="toggleEdit(<?php echo $mark['id']; ?>)" 
                                                            class="btn btn-small" style="background: var(--secondary); color: white;">Cancel</button>
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
                <p style="text-align: center; color: var(--secondary); opacity: 0.6; padding: 40px;">No marks added yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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