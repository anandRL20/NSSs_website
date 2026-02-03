<?php
require_once '../config.php';

// Check if user is admin and whitelisted
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin'] || !$_SESSION['is_whitelisted']) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['action']) || !isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = intval($_GET['id']);
$action = $_GET['action'];

// Prevent admin from modifying their own whitelist status
if ($user_id === $_SESSION['user_id']) {
    header('Location: dashboard.php?error=cannot_modify_self');
    exit();
}

if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE users SET is_whitelisted = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header('Location: dashboard.php?success=approved');
} elseif ($action === 'revoke') {
    $stmt = $conn->prepare("UPDATE users SET is_whitelisted = 0 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header('Location: dashboard.php?success=revoked');
} else {
    header('Location: dashboard.php');
}

$conn->close();
exit();
?>