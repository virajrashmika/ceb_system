<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
    
}
// ---- session timeout ----
$timeoutMinutes = 30;   // use 1 while testing

if (isset($_SESSION['last_activity'])) {
    $idleSeconds = time() - $_SESSION['last_activity'];
    if ($idleSeconds > $timeoutMinutes * 60) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();
// ---- end session timeout ----
?>
