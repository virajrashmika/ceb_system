<?php
/* ---------------------------------------------------------------------------
   stats_api.php — the home dashboard's numbers, as JSON.

   The page draws itself once from PHP and then asks this for a fresh set
   every half minute, so the charts follow the register without anybody
   pressing reload.

   It answers the same dashboardStats() the page was built from, so the two
   can never drift apart. Signed-in users only: the counts describe the whole
   register and are nobody else's business.
   --------------------------------------------------------------------------- */
include 'auth.php';
include 'db.php';
include_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

// A count is only true for the instant it was made. Never let a browser or a
// proxy hand back a stale one as though it were current.
header('Cache-Control: no-store, max-age=0');

echo json_encode(dashboardStats($conn));
