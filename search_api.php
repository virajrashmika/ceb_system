<?php
/* ---------------------------------------------------------------------------
   search_api.php — answers the live search box on the home page.

   This is not a page. It returns JSON, not HTML, so it never includes
   header.php or footer.php. index.php calls it in the background while
   the worker types.

   It searches the same six columns as index.php does, so both behave the same.
   --------------------------------------------------------------------------- */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Nobody reads the register without signing in. auth.php redirects a
// browser to the login page, which is no use to JavaScript expecting
// JSON, so the session is checked here instead and the answer is JSON.
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array(
        'error' => 'Your session has ended. Please sign in again.',
        'rows'  => array(),
        'total' => 0,
        'shown' => 0
    ));
    exit;
}

// The same idle timeout the rest of the system uses. A box left open on
// a desk overnight must not keep answering questions in the morning.
$timeoutMinutes = 30;

if (isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > $timeoutMinutes * 60) {

    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(array(
        'error' => 'You were signed out because of inactivity. Please sign in again.',
        'rows'  => array(),
        'total' => 0,
        'shown' => 0
    ));
    exit;
}

// Typing in the search box counts as being at work, the same as opening
// a page, so the session is kept alive.
$_SESSION['last_activity'] = time();

include 'db.php';
include_once 'functions.php';   // txActiveSql()

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

// 16 transformers are numbered 016, one per S/N prefix, so a list of 15
// would cut one of them off.
$limit   = 25;

// One letter is enough to search on: a leading-wildcard LIKE reads the
// whole table whatever the length, and on 2,209 rows that is about 3
// milliseconds, so a short word costs no more than a long one. Only an
// empty box is refused. The browser checks this too, but the browser can
// be bypassed, so the real check is here.
//
// Keep this the same number as MIN_CHARS in index.php.
if (mb_strlen($keyword) < 1) {
    echo json_encode(array(
        'rows'  => array(),
        'total' => 0,
        'shown' => 0,
        'short' => true
    ));
    exit;
}

/* In a LIKE pattern, % means "any run of characters" and _ means "any one
   character". A worker typing % expects to find a transformer with % in
   its name, not every transformer in Uva. Escaping them first turns the
   pattern back into plain text. The backslash goes first, or it would
   escape the escapes added after it. */
$escaped = str_replace(
    array('\\', '%', '_'),
    array('\\\\', '\\%', '\\_'),
    $keyword
);
$like = '%' . $escaped . '%';

/* A search that is only a number — "16" — is nearly always a worker after
   the S/N with that number, but "16" also sits inside almost every serial
   number, so a plain S/N sort fills the list with UBB rows that only match
   on their serial. The S/Ns whose number is exactly the search (UBB 016,
   UBH 016, … and UMB 0016 too) go to the top; everything else follows in
   S/N order as before. The number is compared as a number, so 16, 016 and
   0016 are the same. ctype_digit has already proved $keyword is digits,
   which is what makes it safe to place in the SQL. */
$orderBy = "t.transformer_code";
if (ctype_digit($keyword) && strlen($keyword) <= 9) {
    $orderBy = "(t.transformer_code REGEXP '^[A-Z]+ [0-9]+$'
                 AND CAST(SUBSTRING_INDEX(t.transformer_code, ' ', -1) AS UNSIGNED) = "
             . (int)$keyword . ") DESC, t.transformer_code";
}

/* Deliberately no event-count subqueries here. The old Search page ran two of
   them per row for its Events column; they are what make that query slow
   and this list does not show the counts. */
$sql = "SELECT t.transformer_id, t.transformer_code, t.substation_name,
               t.capacity_kva, t.transformer_type, t.serial_no, t.details,
               csc.csc_name, csc.csc_code, areas.area_name
        FROM transformers t
        LEFT JOIN csc   ON t.csc_id = csc.csc_id
        LEFT JOIN areas ON csc.area_id = areas.area_id
        WHERE " . txActiveSql('t') . "
          AND (t.transformer_code LIKE ?
            OR t.serial_no        LIKE ?
            OR t.substation_name  LIKE ?
            OR t.details          LIKE ?
            OR csc.csc_name       LIKE ?
            OR areas.area_name    LIKE ?)
        ORDER BY " . $orderBy . "
        LIMIT " . (int)$limit;

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
$stmt->execute();
$res = $stmt->get_result();

$rows = array();
while ($r = $res->fetch_assoc()) {
    $rows[] = array(
        'id'      => (int)$r['transformer_id'],
        'code'    => $r['transformer_code'],
        'name'    => $r['substation_name'],
        'kva'     => (int)$r['capacity_kva'],
        'serial'  => $r['serial_no'],
        'txtype'  => $r['transformer_type'],
        'details' => $r['details'],
        'csc'     => $r['csc_name'],
        'csccode' => $r['csc_code'],
        'area'    => $r['area_name']
    );
}
$stmt->close();

/* How many matched altogether? Only worth asking when the first query
   filled up, otherwise what came back is already the whole answer. */
$total = count($rows);

if ($total === $limit) {

    $cq = $conn->prepare(
        "SELECT COUNT(*) AS n
         FROM transformers t
         LEFT JOIN csc   ON t.csc_id = csc.csc_id
         LEFT JOIN areas ON csc.area_id = areas.area_id
         WHERE " . txActiveSql('t') . "
           AND (t.transformer_code LIKE ?
             OR t.serial_no        LIKE ?
             OR t.substation_name  LIKE ?
             OR t.details          LIKE ?
             OR csc.csc_name       LIKE ?
             OR areas.area_name    LIKE ?)"
    );
    $cq->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
    $cq->execute();
    $cRow = $cq->get_result()->fetch_assoc();
    $cq->close();

    $total = (int)$cRow['n'];
}

echo json_encode(array(
    'rows'  => $rows,
    'total' => $total,
    'shown' => count($rows),
    'limit' => $limit
));
