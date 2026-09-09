<?php
include 'auth.php';
include 'require_admin.php';
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// You cannot deactivate yourself.
if ($id === (int)$_SESSION['user_id']) {
    header("Location: admin.php");
    exit;
}

$stmt = $conn->prepare("SELECT user_id, role, is_deleted FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: admin.php");
    exit;
}

// Never leave the system without an administrator.
if ($user['is_deleted'] == 0 && $user['role'] === 'admin') {
    $n = $conn->query("SELECT COUNT(*) AS n FROM users
                       WHERE role = 'admin' AND is_deleted = 0")->fetch_assoc()['n'];
    if ($n <= 1) {
        header("Location: admin.php");
        exit;
    }
}

$new = ($user['is_deleted'] == 1) ? 0 : 1;

$stmt = $conn->prepare("UPDATE users SET is_deleted = ? WHERE user_id = ?");
$stmt->bind_param("ii", $new, $id);
$stmt->execute();
$stmt->close();

header("Location: admin.php");
exit;