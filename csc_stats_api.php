<?php
/* ---------------------------------------------------------------------------
   csc_stats_api.php — how many transformers of each type every CSC holds,
   as JSON, for the "Transformers by type" section of the CSC page.

       csc_stats_api.php?active=1   removed transformers left out (default)
       csc_stats_api.php?active=0   every transformer, removed ones included

   Analysis only: this reads, it never writes.

   The types are whatever the register holds, not a fixed list. A blank
   type — NULL, empty or only spaces — is one bucket, "Unspecified", so the
   type counts always add up to the total.

   Every transformer is counted exactly once. One on a CSC that has since
   been deleted is still counted, against that CSC, and flagged; one whose
   csc_id matches no CSC at all goes in a "No CSC assigned" row (id 0).
   transformers.csc_id is NOT NULL with a foreign key, so that row should
   never appear — but if it ever does, the totals still reconcile.

   The counting is done by the database: one GROUP BY over CSC and type,
   which on 2,215 transformers is about 150 rows back, never the
   transformers themselves.
   --------------------------------------------------------------------------- */
include 'auth_json.php';
include 'db.php';

$activeOnly = !(isset($_GET['active']) && $_GET['active'] === '0');

/* The type as it is grouped and matched. TRIM so " Bulk" is Bulk and a
   string of spaces is blank; IFNULL so NULL is blank too. The collation is
   case-insensitive, so "bulk" and "Bulk" are one type here as well.

   The imported variants "Bulk (MHP)", "Bulk (HT)" and "Bulk (DPP)" — any
   "Bulk (...)" — are counted as Bulk on this page, as the office asked.
   Only the counting here changes; the register keeps what was imported.

   csc_transformers_api.php filters on exactly this expression, which is what
   makes a tab's count and the length of its list the same number. */
$typeSql = "CASE WHEN TRIM(IFNULL(t.transformer_type, '')) LIKE 'Bulk (%'
                 THEN 'Bulk'
                 ELSE TRIM(IFNULL(t.transformer_type, '')) END";

/* ---- the type list ---------------------------------------------------------
   Taken from every transformer, removed ones included, whatever the filter.
   That is what keeps a type's colour fixed: switching "Active only" off
   cannot bring in a type that pushes the others along to a new colour. */
/* Grouped by exact spelling here, so that when "Bulk" and "bulk" are both in
   the register the one shown is the office's own spelling, or failing that
   the one most transformers carry — not whichever row the database met
   first. */
$office = array();
foreach (transformerTypes() as $t) { $office[mb_strtolower($t)] = $t; }

$found = array();
$most  = array();
$size  = array();    // lower-cased type => transformers of it, every spelling
$res = $conn->query("SELECT ($typeSql) COLLATE utf8mb4_bin AS ttype, COUNT(*) AS n
                     FROM transformers t
                     GROUP BY ttype");
while ($x = $res->fetch_assoc()) {
    if ($x['ttype'] === '') { continue; }
    $k = mb_strtolower($x['ttype']);
    $size[$k] = (isset($size[$k]) ? $size[$k] : 0) + (int)$x['n'];
    if (isset($office[$k])) {
        $found[$k] = $office[$k];
    } elseif (!isset($most[$k]) || (int)$x['n'] > $most[$k]) {
        $found[$k] = $x['ttype'];
        $most[$k]  = (int)$x['n'];
    }
}

/* Colours are fixed by name, never by size, so a type keeps its colour
   whatever the counts do. The office's own types take the first three, in
   the order the forms offer them — the same blue (Bulk), orange
   (Distribution) and teal (Bulk & Distribution) the dashboard gives them.
   Anything else the register holds (the imported "Bulk (MHP)" and the
   like) takes the next ones, in alphabetical order. */
$slotOf = array();
$s = 0;
foreach ($office as $k => $t) { $slotOf[$k] = ($s++ % 8) + 1; }
$others = array_diff_key($found, $office);
uasort($others, 'strcasecmp');
foreach ($others as $k => $label) { $slotOf[$k] = ($s++ % 8) + 1; }

/* Shown biggest first — Distribution, Bulk, Bulk & Distribution on today's
   register — so the tiles and the table lead with what most of it is.
   'office' tells the page which types get a column of their own. */
$order = array_keys($found);
usort($order, function ($a, $b) use ($size, $found) {
    if ($size[$a] !== $size[$b]) { return $size[$b] - $size[$a]; }
    return strcasecmp($found[$a], $found[$b]);
});

$types = array();
$index = array();     // lower-cased type => position in $types
foreach ($order as $k) {
    $index[$k] = count($types);
    $types[]   = array('label' => $found[$k], 'slot' => $slotOf[$k],
                       'office' => isset($office[$k]), 'total' => 0);
}

// Always last, always present, so "Unspecified" has a tile even at zero.
$index['']  = count($types);
$types[]    = array('label' => '', 'slot' => 'none', 'office' => false, 'total' => 0);
$typeCount  = count($types);

/* ---- the CSCs --------------------------------------------------------------
   Every CSC in the register gets a row, including one with no transformers,
   which is a real answer. A deleted CSC is fetched too, but only shown if
   transformers still point at it. */
$cscs = array();
$res = $conn->query(
    "SELECT c.csc_id, c.csc_name, c.csc_code, c.is_deleted,
            a.area_id, a.area_name, p.province_id, p.province_name
     FROM csc c
     LEFT JOIN areas     a ON c.area_id     = a.area_id
     LEFT JOIN provinces p ON a.province_id = p.province_id"
);
while ($c = $res->fetch_assoc()) {
    $cscs[(int)$c['csc_id']] = array(
        'id'         => (int)$c['csc_id'],
        'name'       => $c['csc_name'],
        'code'       => $c['csc_code'],
        'area'       => $c['area_name'],
        'areaId'     => $c['area_id'] !== null ? (int)$c['area_id'] : null,
        'province'   => $c['province_name'],
        'provinceId' => $c['province_id'] !== null ? (int)$c['province_id'] : null,
        'deleted'    => ((int)$c['is_deleted'] === 1),
        'noCsc'      => false,
        'counts'     => array_fill(0, $typeCount, 0),
        'total'      => 0
    );
}

// ---- the counts -------------------------------------------------------------
$where = $activeOnly ? "WHERE " . txActiveSql('t') : "";

$res = $conn->query(
    "SELECT t.csc_id, $typeSql AS ttype, COUNT(*) AS n
     FROM transformers t
     $where
     GROUP BY t.csc_id, ttype"
);

$total = 0;
while ($x = $res->fetch_assoc()) {

    $id = (int)$x['csc_id'];
    $n  = (int)$x['n'];
    $k  = mb_strtolower($x['ttype']);

    /* Every type here was in the list above, which read the same table a
       moment earlier. A transformer saved in between with a brand-new type
       is the one way to miss; it still goes into the total, and shows under
       its own name on the next load. */
    if (!isset($index[$k])) {
        $index[$k] = count($types);
        $types[]   = array('label' => $x['ttype'], 'slot' => ((count($types) - 1) % 8) + 1,
                           'office' => false, 'total' => 0);
        $typeCount = count($types);
        foreach ($cscs as $cid => $c) { $cscs[$cid]['counts'][] = 0; }
    }
    $ti = $index[$k];

    if (!isset($cscs[$id])) {
        // Points at no CSC that exists. Gathered under id 0.
        $id = 0;
        if (!isset($cscs[0])) {
            $cscs[0] = array(
                'id' => 0, 'name' => 'No CSC assigned', 'code' => '',
                'area' => null, 'areaId' => null, 'province' => null, 'provinceId' => null,
                'deleted' => false, 'noCsc' => true,
                'counts' => array_fill(0, $typeCount, 0), 'total' => 0
            );
        }
    }

    $cscs[$id]['counts'][$ti] += $n;
    $cscs[$id]['total']       += $n;
    $types[$ti]['total']      += $n;
    $total                    += $n;
}

/* Biggest first. "No CSC assigned" is not a CSC, so it goes to the bottom
   whatever its size, where it reads as the remainder it is. */
$rows = array();
foreach ($cscs as $c) {
    if ($c['deleted'] && $c['total'] === 0) { continue; }
    $rows[] = $c;
}

usort($rows, function ($a, $b) {
    if ($a['noCsc'] !== $b['noCsc']) { return $a['noCsc'] ? 1 : -1; }
    if ($a['total'] !== $b['total']) { return $b['total'] - $a['total']; }
    return strcasecmp($a['name'], $b['name']);
});

echo json_encode(array(
    'activeOnly' => $activeOnly,
    'types'      => $types,
    'rows'       => $rows,
    'total'      => $total,
    'updated'    => date('d M Y, H:i')
));
