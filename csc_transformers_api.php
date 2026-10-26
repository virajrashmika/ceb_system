<?php
/* ---------------------------------------------------------------------------
   csc_transformers_api.php — one CSC's transformers of one type, a page at a
   time, as JSON. Feeds the list under the tabs in the CSC details panel.

       csc_transformers_api.php?csc=12&type=Bulk&active=1&q=&page=1

       csc     the CSC's id; 0 means "No CSC assigned"
       type    the type exactly as csc_stats_api.php returned it; an empty
               value means Unspecified (blank type)
       active  1 leaves removed transformers out (default), 0 keeps them
       q       optional; searched in the S/N, serial number and substation
       page    1, 2, 3 ... of $perPage rows each

   Answers the rows of that page, plus 'total' (how many of that type the
   CSC holds — the number on the tab) and 'matched' (how many of those the
   search leaves). Analysis only: this reads, it never writes.
   --------------------------------------------------------------------------- */
include 'auth_json.php';
include 'db.php';

$perPage = 50;     // csc_breakdown.php reads this back from 'perPage'

if (!isset($_GET['csc']) || !isset($_GET['type'])) {
    http_response_code(400);
    echo json_encode(array('error' => 'Which CSC and which type? Both are needed.'));
    exit;
}

$cscId      = (int)$_GET['csc'];
$type       = trim((string)$_GET['type']);
$activeOnly = !(isset($_GET['active']) && $_GET['active'] === '0');
$keyword    = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

/* The same expression csc_stats_api.php groups on — see the note there.
   It counts any "Bulk (...)" as Bulk, so the Bulk list holds those too. */
$where  = "(CASE WHEN TRIM(IFNULL(t.transformer_type, '')) LIKE 'Bulk (%'
                 THEN 'Bulk'
                 ELSE TRIM(IFNULL(t.transformer_type, '')) END) = ?";
$types  = "s";
$params = array($type);

if ($cscId > 0) {
    $where   .= " AND t.csc_id = ?";
    $types   .= "i";
    $params[] = $cscId;
} else {
    $where .= " AND NOT EXISTS (SELECT 1 FROM csc c WHERE c.csc_id = t.csc_id)";
}

if ($activeOnly) {
    $where .= " AND " . txActiveSql('t');
}

/* The search, as a condition of its own: it is used once to filter the
   page and once to count the matches. % and _ are escaped the way
   search_api.php does it, so they are searched for as themselves. */
$match       = "1";
$matchTypes  = "";
$matchParams = array();

if ($keyword !== '') {
    $like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $keyword) . '%';
    $match       = "(t.transformer_code LIKE ? OR t.serial_no LIKE ? OR t.substation_name LIKE ?)";
    $matchTypes  = "sss";
    $matchParams = array($like, $like, $like);
}

// ---- how many: in this type, and left by the search --------------------------
$st = $conn->prepare(
    "SELECT COUNT(*) AS total, IFNULL(SUM($match), 0) AS matched
     FROM transformers t
     WHERE $where"
);
$bindTypes  = $matchTypes . $types;
$bindParams = array_merge($matchParams, $params);
$st->bind_param($bindTypes, ...$bindParams);
$st->execute();
$c = $st->get_result()->fetch_assoc();
$st->close();

$total   = (int)$c['total'];
$matched = (int)$c['matched'];
$pages   = max(1, (int)ceil($matched / $perPage));
$page    = min($page, $pages);
$offset  = ($page - 1) * $perPage;

// ---- the page itself ------------------------------------------------------------
$rows = array();

if ($matched > 0) {

    $st = $conn->prepare(
        "SELECT t.transformer_id, t.transformer_code, t.serial_no,
                t.substation_name, t.capacity_kva,
                (r.transformer_id IS NOT NULL) AS is_removed
         FROM transformers t
         LEFT JOIN removed_transformers r ON r.transformer_id = t.transformer_id
         WHERE $where AND $match
         ORDER BY t.transformer_code
         LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
    );
    $bindTypes  = $types . $matchTypes;
    $bindParams = array_merge($params, $matchParams);
    $st->bind_param($bindTypes, ...$bindParams);
    $st->execute();
    $res = $st->get_result();

    while ($r = $res->fetch_assoc()) {
        $rows[] = array(
            'id'      => (int)$r['transformer_id'],
            'code'    => $r['transformer_code'],
            'serial'  => $r['serial_no'],
            'name'    => $r['substation_name'],
            'kva'     => (int)$r['capacity_kva'],
            'removed' => ((int)$r['is_removed'] === 1)
        );
    }
    $st->close();
}

echo json_encode(array(
    'rows'    => $rows,
    'total'   => $total,
    'matched' => $matched,
    'page'    => $page,
    'pages'   => $pages,
    'perPage' => $perPage
));
