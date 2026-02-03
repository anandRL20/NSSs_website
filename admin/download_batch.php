<?php
require_once '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin'] || !$_SESSION['is_whitelisted']) {
    header('Location: ../login.php');
    exit();
}

// Batch check
$batch_year = isset($_GET['batch']) ? (int)$_GET['batch'] : 0;
if (!$batch_year) {
    die("Batch not specified");
}

$conn = getDBConnection();

// Fetch students
$sql = "SELECT 
            u.id AS user_id,
            u.full_name,
            u.email,
            s.id AS student_id,
            s.roll_number,
            s.course,
            s.year,
            s.department,
            s.phone,
            s.batch_year
        FROM users u
        INNER JOIN student_info s ON s.user_id = u.id
        WHERE u.is_admin = 0 AND s.batch_year = ?
        ORDER BY s.roll_number";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $batch_year);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$students) {
    die("No students found");
}

// Fetch marks
$marks_data = [];
foreach ($students as $st) {
    $q = $conn->prepare("SELECT * FROM marks WHERE student_id = ? ORDER BY semester, subject_name");
    $q->bind_param("i", $st['student_id']);
    $q->execute();
    $marks_data[$st['student_id']] = $q->get_result()->fetch_all(MYSQLI_ASSOC);
}

// File headers
$filename = "Batch_{$batch_year}_Student_Report.doc";
header("Content-Type: application/msword");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: public");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Student Report</title>

<style>
@page {
    size: A4;
    margin: 1in;
}

body {
    font-family: Arial, sans-serif;
    font-size: 11pt;
    color: #222;
}

h1 {
    text-align: center;
    font-size: 20pt;
    border-bottom: 2px solid #1f6aa5;
    padding-bottom: 10px;
}

h2 {
    font-size: 14pt;
    background: #e7f1f9;
    padding: 8px;
    margin-top: 25px;
}

h3 {
    font-size: 12pt;
    margin-top: 20px;
    color: #1f6aa5;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    page-break-inside: avoid;
}

thead {
    display: table-row-group;
}

th {
    background: #1f6aa5;
    color: #fff;
    padding: 8px;
    border: 1px solid #ccc;
    font-size: 10.5pt;
}

td {
    border: 1px solid #ccc;
    padding: 8px;
}

.label {
    width: 30%;
    font-weight: bold;
    background: #f5f7fa;
}

.no-data {
    text-align: center;
    font-style: italic;
    color: #888;
    margin-top: 10px;
}

.page-break {
    page-break-before: always;
}
</style>
</head>

<body>

<h1>Batch <?php echo $batch_year; ?> – Student Academic Report</h1>
<p style="text-align:center;">
    Generated on <?php echo date('d F Y'); ?> |
    Total Students: <?php echo count($students); ?>
</p>

<?php foreach ($students as $i => $student): ?>

<?php if ($i > 0): ?>
<div class="page-break"></div>
<?php endif; ?>

<h2>Student <?php echo $i + 1; ?> of <?php echo count($students); ?></h2>

<h3>Personal Information</h3>
<table>
<tr><td class="label">Full Name</td><td><?php echo htmlspecialchars($student['full_name']); ?></td></tr>
<tr><td class="label">Roll Number</td><td><?php echo htmlspecialchars($student['roll_number']); ?></td></tr>
<tr><td class="label">Batch Year</td><td><?php echo htmlspecialchars($student['batch_year']); ?></td></tr>
<tr><td class="label">Course</td><td><?php echo htmlspecialchars($student['course']); ?></td></tr>
<tr><td class="label">Year</td><td><?php echo htmlspecialchars($student['year']); ?></td></tr>
<tr><td class="label">Department</td><td><?php echo htmlspecialchars($student['department']); ?></td></tr>
<tr><td class="label">Email</td><td><?php echo htmlspecialchars($student['email']); ?></td></tr>
<tr><td class="label">Phone</td><td><?php echo htmlspecialchars($student['phone']); ?></td></tr>
</table>

<h3>Academic Performance</h3>

<?php if (!empty($marks_data[$student['student_id']])): ?>
<table>
<thead>
<tr>
    <th>Subject</th>
    <th>Semester</th>
    <th>Marks</th>
    <th>Max</th>
    <th>Percentage</th>
</tr>
</thead>
<tbody>
<?php foreach ($marks_data[$student['student_id']] as $m):
    $percent = round(($m['marks_obtained'] / $m['max_marks']) * 100, 2);
?>
<tr>
    <td><?php echo htmlspecialchars($m['subject_name']); ?></td>
    <td><?php echo htmlspecialchars($m['semester']); ?></td>
    <td><?php echo $m['marks_obtained']; ?></td>
    <td><?php echo $m['max_marks']; ?></td>
    <td><?php echo $percent; ?>%</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p class="no-data">No academic records available</p>
<?php endif; ?>

<?php endforeach; ?>

</body>
</html>

<?php
$conn->close();
exit();
?>
