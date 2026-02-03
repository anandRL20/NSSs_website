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

// Fetch existing student info
$stmt = $conn->prepare("SELECT * FROM student_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student_info = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roll_number = trim($_POST['roll_number']);
    $course = trim($_POST['course']);
    $year = trim($_POST['year']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['date_of_birth'];
    $batch_year = isset($_POST['batch_year']) ? intval($_POST['batch_year']) : null;
    
    // Handle photo upload
    $photo_path = $student_info ? $student_info['photo'] : null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'student_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = 'uploads/' . $new_filename;
            
            if (!is_dir('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Delete old photo if exists
                if ($photo_path && file_exists($photo_path)) {
                    unlink($photo_path);
                }
                $photo_path = $upload_path;
            }
        }
    }
    
    // Update or insert student info
    if ($student_info) {
        $stmt = $conn->prepare("UPDATE student_info SET roll_number=?, course=?, year=?, department=?, phone=?, address=?, date_of_birth=?, batch_year=?, photo=? WHERE user_id=?");
        $stmt->bind_param("sssssssisi", $roll_number, $course, $year, $department, $phone, $address, $dob, $batch_year, $photo_path, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO student_info (user_id, roll_number, course, year, department, phone, address, date_of_birth, batch_year, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss", $user_id, $roll_number, $course, $year, $department, $phone, $address, $dob, $batch_year, $photo_path);
    }
    
    if ($stmt->execute()) {
        $success = 'Profile updated successfully!';
        // Refresh student info
        $stmt = $conn->prepare("SELECT * FROM student_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student_info = $result->fetch_assoc();
    } else {
        $error = 'Failed to update profile. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Student Management</title>
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
            max-width: 900px;
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

        .form-card {
            background: var(--white);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .form-group {
            margin-bottom: 25px;
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
            letter-spacing: 0.3px;
        }

        input, select, textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(15, 76, 117, 0.1);
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Work Sans', sans-serif;
            transition: all 0.3s ease;
            background: var(--white);
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

        .photo-upload input {
            display: none;
        }

        .current-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 12px;
            margin: 20px auto;
            display: block;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: var(--white);
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 10px 30px rgba(15, 76, 117, 0.2);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(15, 76, 117, 0.3);
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
            <div class="logo-text">🎓 Student Portal</div>
            <a href="dashboard.php" class="btn-nav">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><?php echo $student_info ? 'Edit Profile' : 'Create Profile'; ?></h1>
            <p>Update your student information and photo</p>
        </div>

        <div class="form-card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="roll_number">Roll Number</label>
                        <input type="text" id="roll_number" name="roll_number" 
                               value="<?php echo htmlspecialchars($student_info['roll_number'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="course">Course</label>
                        <input type="text" id="course" name="course" 
                               value="<?php echo htmlspecialchars($student_info['course'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="year">Year</label>
                        <select id="year" name="year" required>
                            <option value="">Select Year</option>
                            <option value="1st Year" <?php echo ($student_info['year'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2nd Year" <?php echo ($student_info['year'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3rd Year" <?php echo ($student_info['year'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4th Year" <?php echo ($student_info['year'] ?? '') === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="batch_year">Batch Year</label>
                        <select id="batch_year" name="batch_year" required>
                            <option value="">Select Batch</option>
                            <?php 
                            for ($year = 2022; $year <= 2030; $year++) {
                                $selected = (isset($student_info['batch_year']) && $student_info['batch_year'] == $year) ? 'selected' : '';
                                echo "<option value=\"$year\" $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" 
                               value="<?php echo htmlspecialchars($student_info['department'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($student_info['phone'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo htmlspecialchars($student_info['date_of_birth'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($student_info['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label>Student Photo</label>
                        <?php if ($student_info && $student_info['photo']): ?>
                            <img src="<?php echo htmlspecialchars($student_info['photo']); ?>" class="current-photo" alt="Current Photo">
                        <?php endif; ?>
                        <div class="photo-upload" onclick="document.getElementById('photo').click()">
                            <p style="color: var(--secondary); margin-bottom: 10px;">📸 Click to upload photo</p>
                            <p style="color: var(--secondary); opacity: 0.6; font-size: 13px;">JPG, PNG, or GIF (Max 5MB)</p>
                            <input type="file" id="photo" name="photo" accept="image/*">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Save Profile</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>