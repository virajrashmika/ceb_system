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
    if ($idleSeconds > $timeoutMinutes * 600) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();
// ---- end session timeout ----

/* ---------------------------------------------------------------------------
   First login: the shared starting password has to go.

   Every new account is handed the same starting password, so by the time
   somebody signs in with it, it is not a secret — half the office knows it.
   The account carries must_change_password until they pick their own.

   What they see is a box on the home page, drawn by header.php, with no way
   past it but setting a password or signing out. This block is what makes
   the box more than decoration: while the flag is set, the home page is the
   only page that answers. Typing another address, or using a bookmark,
   lands them back on the home page with the same box waiting.

   Three files are let through. change_password.php is where the box posts
   to, and is the whole form again as a plain page for a browser with
   JavaScript switched off. stats_api.php feeds the home page's numbers and
   would otherwise answer a JSON request with a redirect. logout.php is not
   on the list because it never includes this file, so signing out always
   works.
   --------------------------------------------------------------------------- */
include_once 'functions.php';

if (mustChangePassword()) {

    $openWhileLocked = array('index.php', 'change_password.php', 'stats_api.php');

    if (!in_array(basename($_SERVER['PHP_SELF']), $openWhileLocked)) {
        header('Location: index.php');
        exit;
    }
}
?>
