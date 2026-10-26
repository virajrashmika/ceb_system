<?php
/* ---------------------------------------------------------------------------
   history.php — Edit History.

   This page used to answer one question only: "what changed on this one
   record?", and it could only be reached from inside an event. It also drew
   one row for every changed field, so a single save that cleared four
   conditional boxes filled the screen with four near-identical tiles all
   carrying the same time, the same record and the same author.

   It now works the other way round. You arrive at the page, search for a
   transformer by its S/N, and get everything that has ever been edited on
   that transformer AND on every event belonging to it, newest first, with
   one card per save instead of one card per field.

   The old deep links still work:
       history.php?type=transformer&id=11
       history.php?type=task&id=470
   --------------------------------------------------------------------------- */
include 'auth.php';
include 'db.php';
include_once 'functions.php';   // eventLabel()

$q    = isset($_GET['q'])    ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type']    : '';
$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;

if ($type !== 'transformer' && $type !== 'task') { $type = ''; }
if ($id <= 0) { $type = ''; }

/* scope decides which changes are listed:
     'task'        one event only          (the old ?type=task link)
     'transformer' a transformer and all its events
     'all'         the whole register, newest first
     ''            nothing found                                          */
$scope   = 'all';
$scopeId = 0;

if ($type === 'transformer') { $scope = 'transformer'; $scopeId = $id; }
if ($type === 'task')        { $scope = 'task';        $scopeId = $id; }


/* ---------------------------------------------------------------------------
   1. The search.

   One box, searched against the things a worker actually has written down
   in front of them: the S/N, the serial number, the substation, and the
   proposal or job number of any event on that transformer. Whatever
   matches, the answer is a transformer, because that is what the history
   is gathered around.
   --------------------------------------------------------------------------- */
$matches   = array();
$counts    = array();
$searchRan = false;
$tooMany   = false;

if ($q !== '') {

    $searchRan = true;
    $scope     = '';          // decided below, by how many transformers matched

    /* % and _ are wildcards inside LIKE. Escaping them means someone
       searching for % finds a % and not the whole register. The backslash
       goes first, or it would escape the escapes added after it. */
    $escaped = str_replace(array('\\', '%', '_'),
                           array('\\\\', '\\%', '\\_'), $q);
    $like = '%' . $escaped . '%';

    $stmt = $conn->prepare(
        "SELECT DISTINCT t.transformer_id, t.transformer_code,
                t.substation_name, t.serial_no, c.csc_name
         FROM transformers t
         LEFT JOIN csc   c ON t.csc_id = c.csc_id
         LEFT JOIN tasks k ON k.transformer_id = t.transformer_id
                          AND k.is_deleted = 0
         /* Removed transformers are searched as well: their history is
            exactly what someone may come here for, and a search for
            UBB 001 finds (R)UBB 001 next to the current one. */
         WHERE (t.transformer_code LIKE ?
             OR t.serial_no        LIKE ?
             OR t.substation_name  LIKE ?
             OR k.request_id       LIKE ?
             OR k.job_no           LIKE ?)
         ORDER BY t.transformer_code
         LIMIT 51"
    );
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    /* One row over the limit is asked for so the page can tell "exactly 50"
       from "more than we are willing to list". Too many to choose from is
       an answer in itself: type more of the S/N. */
    $tooMany = (count($matches) > 50);
    if ($tooMany) { $matches = array_slice($matches, 0, 50); }

    /* How many changes each of them carries, so the chooser can say which
       are worth opening. One query for the whole register — change_log is
       small, and this saves running a subquery once per matched row. */
    $cRes = $conn->query(
        "SELECT CASE WHEN l.record_type = 'transformer'
                     THEN l.record_id ELSE tk.transformer_id END AS tx,
                COUNT(*) AS c
         FROM change_log l
         LEFT JOIN tasks tk ON l.record_type = 'task' AND l.record_id = tk.task_id
         GROUP BY tx"
    );
    while ($c = $cRes->fetch_assoc()) {
        if ($c['tx'] !== null) { $counts[(int)$c['tx']] = (int)$c['c']; }
    }

    // Exactly one transformer means there is nothing to choose. Show it.
    if (count($matches) === 1) {
        $scope   = 'transformer';
        $scopeId = (int)$matches[0]['transformer_id'];
    }
}


/* ---------------------------------------------------------------------------
   2. What are we looking at?
   --------------------------------------------------------------------------- */
$txRow    = null;         // the transformer in scope, when there is one
$taskRow  = null;         // the event in scope, when there is one
$gone     = false;        // a deep link pointed at a record that is not there

if ($scope === 'transformer') {

    $s = $conn->prepare(
        "SELECT t.transformer_id, t.transformer_code, t.substation_name,
                c.csc_name
         FROM transformers t
         LEFT JOIN csc c ON t.csc_id = c.csc_id
         WHERE t.transformer_id = ?"
    );
    $s->bind_param("i", $scopeId);
    $s->execute();
    $txRow = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$txRow) { $scope = ''; $gone = true; }

} elseif ($scope === 'task') {

    $s = $conn->prepare(
        "SELECT t.task_id, t.request_id, t.job_no, t.transformer_id,
                tx.transformer_code
         FROM tasks t
         LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
         WHERE t.task_id = ?"
    );
    $s->bind_param("i", $scopeId);
    $s->execute();
    $taskRow = $s->get_result()->fetch_assoc();
    $s->close();

    if (!$taskRow) { $scope = ''; $gone = true; }
}


/* ---------------------------------------------------------------------------
   3. The changes themselves.

   One query shape for all three scopes. A log row points either at a
   transformer or at an event; the joins bring the transformer back either
   way, so a card can always say which machine it belongs to.
   --------------------------------------------------------------------------- */
$logRows = array();
$LIMIT   = 400;
$capped  = false;

if ($scope !== '') {

    $select =
        "SELECT l.log_id, l.record_type, l.record_id, l.field_name,
                l.old_value, l.new_value, l.changed_at, l.changed_by,
                u.full_name,
                tk.request_id, tk.job_no, tk.task_date, tk.date_created AS task_created,
                CASE WHEN l.record_type = 'transformer'
                     THEN l.record_id ELSE tk.transformer_id END AS tx_id,
                CASE WHEN l.record_type = 'transformer'
                     THEN txd.transformer_code ELSE txk.transformer_code END AS tx_code
         FROM change_log l
         LEFT JOIN users u ON l.changed_by = u.user_id
         LEFT JOIN tasks tk ON l.record_type = 'task' AND l.record_id = tk.task_id
         LEFT JOIN transformers txk ON tk.transformer_id = txk.transformer_id
         LEFT JOIN transformers txd ON l.record_type = 'transformer'
                                   AND l.record_id = txd.transformer_id ";

    /* One row over the limit is asked for, purely so the page can tell the
       difference between "exactly this many" and "more than we are showing". */
    $order = " ORDER BY l.changed_at DESC, l.log_id DESC LIMIT " . ($LIMIT + 1);

    if ($scope === 'transformer') {
        $stmt = $conn->prepare($select .
            "WHERE (l.record_type = 'transformer' AND l.record_id = ?)
                OR (l.record_type = 'task' AND tk.transformer_id = ?)" . $order);
        $stmt->bind_param("ii", $scopeId, $scopeId);

    } elseif ($scope === 'task') {
        $stmt = $conn->prepare($select .
            "WHERE l.record_type = 'task' AND l.record_id = ?" . $order);
        $stmt->bind_param("i", $scopeId);

    } else {
        $stmt = $conn->prepare($select . $order);
    }

    $stmt->execute();
    $logRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($logRows) > $LIMIT) {
        $capped  = true;
        $logRows = array_slice($logRows, 0, $LIMIT);
    }
}


/* ---------------------------------------------------------------------------
   4. One save, one card.

   logChanges() writes a separate row for every field, all of them sharing
   the same record, author and second. They are written in a single loop,
   so once the list is ordered they are always next to each other — walking
   the list and starting a new card whenever that key changes is enough.
   --------------------------------------------------------------------------- */
$groups  = array();
$lastKey = null;

foreach ($logRows as $r) {

    $key = $r['record_type'] . '|' . $r['record_id'] . '|'
         . $r['changed_at']  . '|' . (int)$r['changed_by'];

    if ($key !== $lastKey) {
        $groups[] = array('head' => $r, 'changes' => array());
        $lastKey  = $key;
    }

    $groups[count($groups) - 1]['changes'][] = $r;
}


$pageTitle = "Edit History";
$pageDesc  = "Every change made to a transformer and to its events.";
$activeNav = ($scope === 'task') ? "tasks" : "transformers";

/* You always arrive here from a transformer or an event, so Back is how you
   get out. The fallback only matters for a pasted link. */
$showBack     = true;
$backFallback = 'transformers.php';

include 'header.php';

$dash  = "<span class='field-hint'>&mdash;</span>";
$blank = "<span class='field-hint'>blank</span>";
?>

<!-- A plain GET form: it works with JavaScript switched off, and the address
     it produces can be pasted into an e-mail. -->
<form method="get" action="history.php" class="search-hero-row">
    <input type="text" name="q" autocomplete="off"
           value="<?php echo htmlspecialchars($q); ?>"
           placeholder="Search by S/N, serial no, substation, proposal no or job no">
    <button type="submit" class="btn">Search</button>
</form>

<p class="search-hero-hints">
<?php
if ($gone) {
    echo "That record is no longer in the system. "
       . "<a href='history.php'>See the latest changes instead</a>.";
} elseif ($searchRan && count($matches) > 1) {
    echo $tooMany
         ? "More than 50 transformers match &mdash; the first 50 are listed. "
           . "Type more of the S/N to narrow it down."
         : count($matches) . " transformers match. Choose one to see its history.";
} elseif ($searchRan && $scope === '') {
    echo "No transformer or event found for &ldquo;" . htmlspecialchars($q) . "&rdquo;.";
} elseif ($scope === 'all') {
    echo "Showing the latest changes across the whole register. "
       . "Search above for one transformer.";
}
// One transformer or one event in view: the panel title says which, so
// there is nothing to add here.
?>
</p>

<?php
/* ---- several transformers matched: let them choose ---------------------- */
if ($searchRan && count($matches) > 1) { ?>

<div class="panel">
    <div class="panel-head"><h2>Which transformer?</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Substation Name</th>
                    <th>CSC</th>
                    <th>Serial No</th>
                    <th class="num">Changes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $m) {
                    $mid = (int)$m['transformer_id'];
                    $n   = isset($counts[$mid]) ? $counts[$mid] : 0; ?>
                <tr>
                    <td data-label="S/N">
                        <a href="history.php?type=transformer&amp;id=<?php echo $mid; ?>"
                           class="code-cell"><?php echo htmlspecialchars($m['transformer_code']); ?></a>
                    </td>
                    <td data-label="Substation Name"><?php
                        echo trim((string)$m['substation_name']) !== ''
                             ? htmlspecialchars($m['substation_name']) : $dash; ?></td>
                    <td data-label="CSC"><?php
                        echo trim((string)$m['csc_name']) !== ''
                             ? htmlspecialchars($m['csc_name']) : $dash; ?></td>
                    <td data-label="Serial No" class="code-cell"><?php
                        echo trim((string)$m['serial_no']) !== ''
                             ? htmlspecialchars($m['serial_no']) : $dash; ?></td>
                    <td data-label="Changes" class="num"><?php
                        echo $n === 0 ? $dash : $n; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php }

/* ---- the history itself ------------------------------------------------- */
if ($scope !== '') {

    // What the panel calls itself, and how to widen or narrow the view.
    if ($scope === 'transformer') {
        $title = 'Transformer ' . htmlspecialchars($txRow['transformer_code']);
        $note  = 'The transformer and every event on it &mdash; newest first';
    } elseif ($scope === 'task') {
        $title = 'Event ' . htmlspecialchars(eventLabel($taskRow));
        $note  = 'This event only &mdash; newest first';
    } else {
        $title = 'Latest changes';
        $note  = 'Everywhere in the register &mdash; newest first';
    }
?>

<div class="panel">
    <div class="panel-head">
        <h2><?php echo $title; ?></h2>
        <span class="panel-note"><?php echo $note; ?></span>
    </div>
    <?php $widen = ($scope === 'task' && (int)$taskRow['transformer_id'] > 0);
          if ($widen || !$groups) { ?>
    <div class="panel-body">

        <?php
        /* The only link kept here is the one that goes somewhere new:
           widening one event out to everything on its transformer. Getting
           back to the record you came from is what the Back button is for. */
        if ($widen) {
            echo "<p class='page-actions'>"
               . "<a class='action-link' href='history.php?type=transformer&amp;id="
               . (int)$taskRow['transformer_id'] . "'>Show the whole transformer&rsquo;s history"
               . (trim((string)$taskRow['transformer_code']) !== ''
                  ? " (" . htmlspecialchars($taskRow['transformer_code']) . ")" : "")
               . "</a></p>";
        }
        ?>

        <?php if (!$groups) { ?>
            <div class="table-empty">No changes have been recorded yet.</div>
        <?php } ?>
    </div>
    <?php } ?>

    <?php if ($groups) { ?>
    <?php /* One row per save. Clicking a row opens change.php, which lists
             every field that save changed, old value to new. */ ?>
    <div class="table-wrap">
        <table class="data plain-table hist-table">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Record</th>
                    <th>Changed On</th>
                    <th>Changed By</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($groups as $gi => $g) {

                $h    = $g['head'];
                $href = 'change.php?id=' . (int)$h['log_id'];

                // Was it the transformer itself, or one of its events?
                $what = ($h['record_type'] === 'transformer')
                      ? 'Transformer'
                      : 'Event <span class="code-cell">'
                        . htmlspecialchars(eventLabel($h)) . '</span>';

                $who = ($h['full_name'] !== null && trim($h['full_name']) !== '')
                     ? htmlspecialchars($h['full_name']) : 'unknown user';
            ?>
                <?php /* The whole row opens the save on its own page.
                         data-href is picked up by the handler in footer.php;
                         the link in the first cell is there for the keyboard
                         and for a browser with JavaScript off. */ ?>
                <tr data-href="<?php echo $href; ?>">
                    <td data-label="S/N">
                        <a href="<?php echo $href; ?>" class="code-cell"><?php
                        echo trim((string)$h['tx_code']) !== ''
                             ? htmlspecialchars($h['tx_code']) : '&mdash;'; ?></a></td>
                    <td data-label="Record"><?php echo $what; ?></td>
                    <td data-label="Changed On"><?php
                        echo date('d M Y, h:i A', strtotime($h['changed_at'])); ?></td>
                    <td data-label="Changed By"><?php echo $who; ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <?php if ($capped) { ?>
    <div class="panel-body">
        <p class="field-hint">Only the most recent <?php echo $LIMIT; ?> changes are shown.
            Search for one transformer to see all of its history.</p>
    </div>
    <?php } ?>

    <?php } ?>
</div>

<?php } ?>

<?php include 'footer.php'; ?>
