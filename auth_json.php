<?php
/* ---------------------------------------------------------------------------
   auth_json.php — the sign-in check for endpoints that answer JSON.

   auth.php answers a stranger with a redirect to the login page, which is
   right for a browser opening a page and no use to JavaScript expecting
   JSON: it would get the login form back and choke on it. This is the same
   three checks, answered in JSON instead:

     - nobody signed in              -> 401
     - signed in, but idle too long  -> 401, and the session is ended
     - still on the starting password -> 403, the same lock auth.php keeps

   The idle limit is the one search_api.php uses. Include this first, before
   db.php, so a stranger never costs a database connection.
   --------------------------------------------------------------------------- */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// A count is only true for the instant it was made.
header('Cache-Control: no-store, max-age=0');

session_start();

function jsonRefuse($status, $message) {
    http_response_code($status);
    echo json_encode(array('error' => $message));
    exit;
}

if (!isset($_SESSION['user_id'])) {
    jsonRefuse(401, 'Your session has ended. Please sign in again.');
}

$timeoutMinutes = 30;

if (isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > $timeoutMinutes * 60) {

    session_unset();
    session_destroy();
    jsonRefuse(401, 'You were signed out because of inactivity. Please sign in again.');
}

$_SESSION['last_activity'] = time();

include_once 'functions.php';   // mustChangePassword(), txActiveSql()

if (mustChangePassword()) {
    jsonRefuse(403, 'Please set your own password before using the register.');
}

/* Nothing below writes to the session, so let it go now. PHP holds the
   session file locked until the script ends, and the CSC page asks for the
   counts and a transformer list at the same moment — without this the
   second request would wait for the first. */
session_write_close();
