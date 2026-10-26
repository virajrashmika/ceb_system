<?php
include 'auth.php';
$needArea = 'events';
include 'require_staff.php';   // Guest and Worker may read events, not change them
include 'db.php';
include_once 'functions.php';

$message = "";
$isError = true;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Transformer T2 to T5, checked before anything else is saved. Choosing
   the same transformer twice stops the whole save, with the boxes left as
   they were typed so the mistake can be seen and put right. */
$extraTxError  = '';
$extraTxBefore = array();
$postedExtraTx = null;

/* The Load Reading sheet is checked at the same point, by the same rule:
   a box that is not a valid reading stops the whole save. */
$lrErrors = array();
$postedLr = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id = (int)$_POST['task_id'];

    $mq = $conn->prepare("SELECT transformer_id, assigned_to FROM tasks WHERE task_id = ?");
    $mq->bind_param("i", $id);
    $mq->execute();
    $mRow = $mq->get_result()->fetch_assoc();
    $mq->close();

    $extraTxBefore = eventExtraTransformers($conn, $id);
    $linkedIds     = array();
    foreach ($extraTxBefore as $x) { $linkedIds[] = (int)$x['id']; }

    list($postedExtraTx, $extraTxError) = extraTransformersFromPost(
        $conn, $_POST, $mRow ? (int)$mRow['transformer_id'] : 0, $linkedIds);

    if ($extraTxError !== '') { $message = $extraTxError; }

    // Only a work type that asks for the sheet has it checked.
    list($postedLr, $lrErrors) = loadReadingFromPost($_POST);
    $lrAllowedNow = taskAllowedFields($conn, (int)$_POST['type_id']);
    if (empty($lrAllowedNow['loadreading'])) { $lrErrors = array(); }

    if ($extraTxError === '' && $lrErrors) { $message = lrErrorMessage($lrErrors); }
}

/* A save stopped by either check leaves the record untouched, and the form
   is shown again with what was typed rather than what is stored. */
$saveStopped = ($extraTxError !== '' || $lrErrors);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$saveStopped) {

    $id             = (int)$_POST['task_id'];
    $requestId      = trim($_POST['request_id']);
    $rid            = isset($_POST['rid']) ? trim($_POST['rid']) : '';
    $jobNo          = trim($_POST['job_no']);
    $taskDate       = trim($_POST['task_date']);
    $estCost        = trim($_POST['est_cost']);
    $type_id        = (int)$_POST['type_id'];
    $capacity_after = isset($_POST['capafter']) ? (int)$_POST['capafter'] : 0;
    $description    = trim($_POST['description']);
    $status_id      = (int)$_POST['status_id'];
    $assigned_to    = (int)$_POST['assigned_to'];

    // One of the six in eventAssigneeIds(), or nobody. Whoever already holds
    // the event may stay, so saving an older event does not quietly drop them.
    $prevAssignee = $mRow ? (int)$mRow['assigned_to'] : 0;
    if ($assigned_to !== $prevAssignee
        && !in_array($assigned_to, eventAssigneeIds(), true)) {
        $assigned_to = 0;
    }
    $postedSubtypes = isset($_POST['subtypes']) ? $_POST['subtypes'] : array();

    // Work types like Retail Connection use a choose-one dropdown.
    if (isset($_POST['subtype_pick']) && (int)$_POST['subtype_pick'] > 0) {
        $postedSubtypes[] = (int)$_POST['subtype_pick'];
    }

    // Does the chosen work type still exist? A job may have none at all.
    $type = null;
    if ($type_id > 0) {
        $stmt = $conn->prepare("SELECT type_id FROM task_types WHERE type_id = ?");
        $stmt->bind_param("i", $type_id);
        $stmt->execute();
        $type = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$type) { $type_id = 0; }
    $typeVal = ($type_id > 0) ? $type_id : null;

    // What is the status called? "Completed" needs special handling.
    $stmt = $conn->prepare("SELECT status_name FROM statuses WHERE status_id = ?");
    $stmt->bind_param("i", $status_id);
    $stmt->execute();
    $st = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $assignedVal = ($assigned_to > 0) ? $assigned_to : null;
    $requestVal  = ($requestId !== '') ? $requestId : null;
    $ridVal      = ($rid !== '')       ? $rid       : null;
    $jobVal      = ($jobNo !== '')     ? $jobNo     : null;

    $dateVal   = ($taskDate !== '') ? $taskDate : date('Y-m-d');
    $createdAt = $dateVal . ' 00:00:00';

    $costVal = ($estCost !== '' && is_numeric($estCost)) ? (float)$estCost : null;

    // What options does this event already carry? An option that has since
    // been deactivated must survive a plain Save — otherwise opening an old
    // event and pressing Save would quietly wipe its Work Details.
    $heldSubtypes = array();
    $hs = $conn->prepare("SELECT subtype_id FROM task_subtype_links WHERE task_id = ?");
    $hs->bind_param("i", $id);
    $hs->execute();
    $hsRes = $hs->get_result();
    while ($h = $hsRes->fetch_assoc()) { $heldSubtypes[] = (int)$h['subtype_id']; }
    $hs->close();

    $cleanSubtypes = resolveSubtypes($conn, $type_id, $postedSubtypes, $heldSubtypes);

    // Which extra boxes does this work type allow? The browser hides the
    // ones it does not need, but hidden is not blocked.
    $allowed = taskAllowedFields($conn, $type_id, $cleanSubtypes);
    $extra   = taskExtraValues($allowed, $_POST);

    // The ABC cable grid, read by the same rule: a work type that does not
    // ask for one cannot have one, whatever the browser sent.
    $abcBefore = loadAbcGrid($conn, $id);
    $abcAfter  = !empty($allowed['abcgrid'])
               ? abcGridFromPost($conn, $_POST) : array();

    // The Load Reading sheet, by the same rule. A type that no longer asks
    // for it saves an empty sheet, exactly as the ABC grid does.
    $lrBefore = loadLoadReading($conn, $id);
    $lrAfter  = !empty($allowed['loadreading']) ? $postedLr : lrBlank();

    $capacity_after = $extra['capacity'] ? (int)$extra['capacity'] : 0;
    $afterVal = ($capacity_after > 0) ? $capacity_after : null;

    // ---- read the job as it stands, before anything is written ----
    $bq = $conn->prepare(
        "SELECT t.request_id, t.rid, t.job_no, t.task_date, t.est_cost,
                t.capacity_after, t.no_of_connections, t.no_of_feeders,
                t.feeder_length, t.description,
                ty.type_name, s.status_name, u.full_name AS assigned_name
         FROM tasks t
         LEFT JOIN task_types ty ON t.type_id     = ty.type_id
         LEFT JOIN statuses   s  ON t.status_id   = s.status_id
         LEFT JOIN users      u  ON t.assigned_to = u.user_id
         WHERE t.task_id = ?"
    );
    $bq->bind_param("i", $id);
    $bq->execute();
    $old = $bq->get_result()->fetch_assoc();
    $bq->close();

    // Readable names for the values being saved.
    $newTypeName = '';
    if ($type_id > 0) {
        $q = $conn->prepare("SELECT type_name FROM task_types WHERE type_id = ?");
        $q->bind_param("i", $type_id);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        $newTypeName = $r ? $r['type_name'] : '';
    }

    $newAssignedName = '';
    if ($assigned_to > 0) {
        $q = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
        $q->bind_param("i", $assigned_to);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        $newAssignedName = $r ? $r['full_name'] : '';
    }

    $newStatusName = $st ? $st['status_name'] : '';

    if ($st && $st['status_name'] === 'Completed') {

        $stmt = $conn->prepare(
            "UPDATE tasks
             SET request_id = ?, rid = ?, job_no = ?, type_id = ?, capacity_after = ?,
                 no_of_connections = ?, no_of_feeders = ?, feeder_length = ?,
                 description = ?, est_cost = ?, task_date = ?, date_created = ?,
                 status_id = ?, assigned_to = ?,
                 date_completed = IFNULL(date_completed, NOW())
             WHERE task_id = ?"
        );
        $stmt->bind_param("sssiiiissdssiii", $requestVal, $ridVal, $jobVal, $typeVal, $afterVal,
                          $extra['connections'], $extra['feeders'], $extra['length'],
                          $description, $costVal, $dateVal, $createdAt,
                          $status_id, $assignedVal, $id);
        $stmt->execute();
        $stmt->close();

        if ($afterVal) {
            $stmt = $conn->prepare(
                "UPDATE transformers tx
                 JOIN tasks t ON t.transformer_id = tx.transformer_id
                 SET tx.capacity_kva = ?
                 WHERE t.task_id = ?"
            );
            $stmt->bind_param("ii", $afterVal, $id);
            $stmt->execute();
            $stmt->close();
        }

        $message = "Event completed. The transformer register has been updated.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE tasks
             SET request_id = ?, rid = ?, job_no = ?, type_id = ?, capacity_after = ?,
                 no_of_connections = ?, no_of_feeders = ?, feeder_length = ?,
                 description = ?, est_cost = ?, task_date = ?, date_created = ?,
                 status_id = ?, assigned_to = ?,
                 date_completed = NULL
             WHERE task_id = ?"
        );
        $stmt->bind_param("sssiiiissdssiii", $requestVal, $ridVal, $jobVal, $typeVal, $afterVal,
                          $extra['connections'], $extra['feeders'], $extra['length'],
                          $description, $costVal, $dateVal, $createdAt,
                          $status_id, $assignedVal, $id);
        $stmt->execute();
        $stmt->close();

        $message = "Event updated.";
    }

    // ---- save the ABC cable grid ----
    saveAbcGrid($conn, $id, $abcAfter);

    // ---- save the Load Reading sheet ----
    // Only rewritten when there is or was something on it, so an event that
    // has never been a Load Reading costs no queries.
    if (lrHasAny($lrBefore) || lrHasAny($lrAfter)) {
        saveLoadReading($conn, $id, $lrAfter);
    }

    // ---- save Transformer T2 to T5 ----
    saveExtraTransformers($conn, $id, $postedExtraTx);
    $extraTxAfter = eventExtraTransformers($conn, $id);

    // ---- save the ticked work details ----
    $del = $conn->prepare("DELETE FROM task_subtype_links WHERE task_id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    // resolveSubtypes() has already checked that every id belongs to this
    // work type, and has carried forward any option the record held that has
    // since been deactivated. Re-filtering on is_active here would throw
    // those away again, so the list is written as it stands.
    if ($type_id > 0 && count($cleanSubtypes) > 0) {

        $ins = $conn->prepare("INSERT INTO task_subtype_links (task_id, subtype_id)
                               VALUES (?, ?)");
        foreach ($cleanSubtypes as $sid) {
            $ins->bind_param("ii", $id, $sid);
            $ins->execute();
        }
        $ins->close();
    }

    // ---- write one log row for each field that really changed ----
    if ($old) {

        $logBefore = array(
                'Proposal No'  => $old['request_id'],
                'RID'          => $old['rid'],
                'Date'         => $old['task_date'],
                'Work Type'    => $old['type_name'],
                'Job No'       => $old['job_no'],
                'Est. Cost'    => $old['est_cost'],
                'New Capacity' => $old['capacity_after'] ? $old['capacity_after'] . ' kVA' : '',
                'No. of Connections' => $old['no_of_connections'],
                'No. of Feeders' => $old['no_of_feeders'],
                'Length'       => $old['feeder_length'],
                'Description'  => $old['description'],
                'Status'       => $old['status_name'],
                'Assigned To'  => $old['assigned_name'],
        );

        $logAfter = array(
                'Proposal No'  => $requestVal,
                'RID'          => $ridVal,
                'Date'         => $dateVal,
                'Work Type'    => $newTypeName,
                'Job No'       => $jobVal,
                'Est. Cost'    => $costVal,
                'New Capacity' => $afterVal ? $afterVal . ' kVA' : '',
                'No. of Connections' => $extra['connections'],
                'No. of Feeders' => $extra['feeders'],
                'Length'       => $extra['length'],
                'Description'  => $description,
                'Status'       => $newStatusName,
                'Assigned To'  => $newAssignedName,
        );

        /* Every box of the ABC grid is added to both lists, so that clearing
           a number is recorded just as plainly as entering one. logChanges()
           writes a row only where the two differ, so the boxes nobody touched
           cost nothing. Done only for events that have a grid or used to have
           one — an event that has never seen ABC gains no extra rows. */
        if (!empty($allowed['abcgrid']) || $abcBefore) {

            $abcPhases = array_merge(array_keys($abcBefore), array_keys($abcAfter));
            $abcSizes  = array();
            foreach (array($abcBefore, $abcAfter) as $grid) {
                foreach ($grid as $gRow) {
                    foreach (array_keys($gRow) as $sid) { $abcSizes[] = $sid; }
                }
            }

            $logBefore = array_merge($logBefore,
                abcGridLogValues($conn, $abcBefore, $abcPhases, $abcSizes));
            $logAfter  = array_merge($logAfter,
                abcGridLogValues($conn, $abcAfter,  $abcPhases, $abcSizes));
        }

        /* Every box of the Load Reading sheet, the same way — one history
           row for each box whose reading changed, was entered or cleared.
           Only for events that have a sheet or used to have one. */
        if (!empty($allowed['loadreading']) || lrHasAny($lrBefore)) {
            $logBefore = array_merge($logBefore, loadReadingLogValues($lrBefore));
            $logAfter  = array_merge($logAfter,  loadReadingLogValues($lrAfter));
        }

        /* Transformer T2 to T5, by S/N. Every position is put in both lists,
           so taking a transformer off is recorded as plainly as adding one. */
        $txBeforeByPos = array();
        $txAfterByPos  = array();
        foreach ($extraTxBefore as $x) { $txBeforeByPos[(int)$x['position']] = $x['code']; }
        foreach ($extraTxAfter  as $x) { $txAfterByPos[(int)$x['position']]  = $x['code']; }
        for ($p = 2; $p <= maxEventTransformers(); $p++) {
            $logBefore['Transformer T' . $p] = isset($txBeforeByPos[$p]) ? $txBeforeByPos[$p] : '';
            $logAfter['Transformer T' . $p]  = isset($txAfterByPos[$p])  ? $txAfterByPos[$p]  : '';
        }

        logChanges($conn, 'task', $id, $logBefore, $logAfter,
                   (int)$_SESSION['user_id']);
    }

    $isError = false;
}

// Load the job
$lrReady = loadReadingReady($conn);
$stmt = $conn->prepare(
    "SELECT t.*, tx.transformer_code, tx.capacity_kva AS tx_capacity,
            tx.substation_name, tx.transformer_type,"
            . ($lrReady ? " tx.lt_feeders," : "") . "
            csc.csc_name, csc.csc_code, areas.area_name, ty.type_name, ty.type_code,
            cu.full_name AS created_name
     FROM tasks t
     LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
     LEFT JOIN csc            ON tx.csc_id = csc.csc_id
     LEFT JOIN areas          ON csc.area_id = areas.area_id
     LEFT JOIN task_types ty  ON t.type_id = ty.type_id
     LEFT JOIN users cu       ON t.created_by = cu.user_id
     WHERE t.task_id = ? AND t.is_deleted = 0"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    die("Event not found. <a href='tasks.php'>Back</a>");
}

/* Transformer T2 to T5: what the event carries now — or, after a save that
   was stopped, what was typed. The boxes offer every transformer in the
   event's CSC, plus any it already carries that has since moved or been
   deleted, so an old choice is never silently lost. */
$extraTxNow   = eventExtraTransformers($conn, $id);
$extraTxShown = array();
if ($postedExtraTx !== null && $saveStopped) {
    $extraTxShown = $postedExtraTx;
} else {
    foreach ($extraTxNow as $x) { $extraTxShown[] = (int)$x['id']; }
}

$extraTxList = array();
$listed      = array();
$lq = $conn->prepare("SELECT tx.transformer_id, tx.transformer_code, tx.substation_name, tx.capacity_kva
                      FROM transformers tx
                      JOIN transformers main ON main.transformer_id = ?
                      WHERE tx.csc_id = main.csc_id AND " . txActiveSql('tx') . "
                        AND tx.transformer_id <> main.transformer_id
                      ORDER BY tx.transformer_code");
$lq->bind_param("i", $task['transformer_id']);
$lq->execute();
$lRes = $lq->get_result();
while ($l = $lRes->fetch_assoc()) {
    $extraTxList[] = array('id'   => (int)$l['transformer_id'],
                           'code' => $l['transformer_code'],
                           'name' => (string)$l['substation_name'],
                           'kva'  => (int)$l['capacity_kva']);
    $listed[(int)$l['transformer_id']] = true;
}
$lq->close();
foreach ($extraTxNow as $x) {
    if (!isset($listed[(int)$x['id']])) {
        $extraTxList[] = array('id' => (int)$x['id'], 'code' => $x['code'],
                               'name' => (string)$x['substation'], 'kva' => 0);
    }
}

// Which options are ticked right now?
$ticked = array();
$tk = $conn->prepare("SELECT subtype_id FROM task_subtype_links WHERE task_id = ?");
$tk->bind_param("i", $id);
$tk->execute();
$tkRes = $tk->get_result();
while ($t = $tkRes->fetch_assoc()) {
    $ticked[] = (int)$t['subtype_id'];
}
$tk->close();

/* Is this event's work type still on offer? An event raised under a type
   that has since been retired must still show that type, and must not lose
   it just because somebody opened the form and pressed Save. */
$typeStillActive = true;

if ($task['type_id']) {
    $ta = $conn->prepare("SELECT is_active FROM task_types WHERE type_id = ?");
    $ta->bind_param("i", $task['type_id']);
    $ta->execute();
    $taRow = $ta->get_result()->fetch_assoc();
    $ta->close();
    $typeStillActive = ($taRow && (int)$taRow['is_active'] === 1);
}

$dateValue = $task['task_date']
    ? $task['task_date']
    : date('Y-m-d', strtotime($task['date_created']));

$lrShown = loadLoadReading($conn, $id);

/* A stopped save: show the form as it was typed, not as it is stored, so
   nothing has to be entered twice. Only the boxes are refilled — the record
   itself has not been touched. A work type is taken back only if it is one
   the dropdown offers, and an assignee only if the form could have offered
   them; anything else stays as stored. */
if ($_SERVER["REQUEST_METHOD"] == "POST" && $saveStopped) {

    $p = function ($k) { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; };

    $task['request_id']        = $p('request_id');
    $task['rid']               = $p('rid');
    $task['job_no']            = $p('job_no');
    $task['est_cost']          = $p('est_cost');
    $task['description']       = $p('description');
    $task['status_id']         = (int)$p('status_id');
    $task['capacity_after']    = (int)$p('capafter');
    $task['no_of_connections'] = $p('connections');
    $task['no_of_feeders']     = $p('feeders');
    $task['feeder_length']     = $p('length');
    if ($p('task_date') !== '') { $dateValue = $p('task_date'); }

    $postedType = (int)$p('type_id');
    if ($postedType === 0) {
        $task['type_id'] = null;
    } elseif ($postedType !== (int)$task['type_id']) {
        $tq = $conn->prepare("SELECT 1 FROM task_types WHERE type_id = ? AND is_active = 1");
        $tq->bind_param("i", $postedType);
        $tq->execute();
        if ($tq->get_result()->fetch_row()) {
            $task['type_id']  = $postedType;
            $typeStillActive  = true;   // only active types are taken back
        }
        $tq->close();
    }

    $postedAssign = (int)$p('assigned_to');
    if ($postedAssign === 0 || in_array($postedAssign, eventAssigneeIds(), true)) {
        $task['assigned_to'] = $postedAssign;
    }

    $ticked = array();
    if (isset($_POST['subtypes']) && is_array($_POST['subtypes'])) {
        $ticked = array_map('intval', $_POST['subtypes']);
    }
    if ((int)$p('subtype_pick') > 0) { $ticked[] = (int)$p('subtype_pick'); }

    if ($postedLr !== null) { $lrShown = $postedLr; }
}

$typeLabel = $task['type_name'] ? $task['type_name'] : "Not decided yet";

$pageTitle    = $task['transformer_code'];
$pageDesc     = $typeLabel . " — " . $task['csc_name'];
$activeNav    = "tasks";
$showBack     = true;
$backFallback = 'tasks.php';
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2>Event Details</h2>
        <span class="panel-note">
            raised by <?php echo htmlspecialchars($task['created_name']); ?> on
            <?php echo date('d M Y', strtotime($task['date_created'])); ?>
            <span class="action-sep">|</span>
            <a href="event.php?id=<?php echo $id; ?>" class="action-link">View</a>
            <span class="action-sep">|</span>
            <a href="history.php?type=task&id=<?php echo $id; ?>" class="action-link">Edit History</a>
        </span>
    </div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label>CSC</label>
                        <input type="text" readonly
                               value="<?php echo htmlspecialchars($task['csc_name']); ?>">
                        <span class="field-hint">Set when the event was raised. It cannot be moved.</span>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label>Transformer T1</label>
                        <div class="tx-line">
                            <input type="text" readonly
                                   value="<?php echo htmlspecialchars($task['transformer_code']
                                       . (trim((string)$task['substation_name']) !== ''
                                          ? ' — ' . $task['substation_name'] : '')); ?>">
                            <?php renderExtraTransformerAddButton(); ?>
                        </div>
                        <span class="field-hint">Capacity before this event: <?php echo (int)$task['capacity_before']; ?> kVA.</span>
                    </div>
                    <?php renderExtraTransformerRows($extraTxShown); ?>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="type">Work Type</label>
                        <select id="type" name="type_id">
                            <option value="">-- Not decided yet --</option>
                            <?php
                            $types = $conn->query("SELECT type_id, type_code, type_name
                                                   FROM task_types
                                                   WHERE is_active = 1
                                                   ORDER BY type_name");
                            $seen = false;
                            while ($ty = $types->fetch_assoc()) {
                                $sel = ($ty['type_id'] == $task['type_id']) ? " selected" : "";
                                if ($sel !== '') { $seen = true; }
                                echo "<option value='" . (int)$ty['type_id'] . "'" . $sel . ">"
                                     . htmlspecialchars($ty['type_name'] . " (" . $ty['type_code'] . ")")
                                     . "</option>";
                            }
                            /* This event may be on a work type that has since been
                               retired. Showing it keeps the record readable, and
                               stops a plain Save from silently clearing the type. */
                            if (!$seen && $task['type_id']) {
                                echo "<option value='" . (int)$task['type_id'] . "' selected>"
                                     . htmlspecialchars($task['type_name'] . " (" . $task['type_code'] . ")")
                                     . " — retired</option>";
                            }
                            ?>
                        </select>
                        <?php if (!$typeStillActive && $task['type_id']) { ?>
                            <span class="field-hint">
                                This event uses <strong><?php echo htmlspecialchars($task['type_name']); ?></strong>,
                                which is no longer offered for new events. It is kept as it is.
                            </span>
                        <?php } else { ?>
                            <span class="field-hint">Set this once the work type has been decided.</span>
                        <?php } ?>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="ridno">RID</label>
                        <input type="text" id="ridno" name="rid" maxlength="50"
                               value="<?php echo htmlspecialchars($task['rid'] ?? ''); ?>">
                        <span class="field-hint">The Request ID, if one was issued. e.g. 2609-LVR-0596.</span>
                    </div>
                </div>
            </div>

            <?php renderSubtypeControls($conn, $ticked); ?>

            <div class="form-row" id="capacity-row" style="display:none;">
                <label for="capafter">New Capacity</label>
                <select id="capafter" name="capafter">
                    <option value="">-- No capacity change --</option>
                    <?php
                    $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                    while ($cap = $caps->fetch_assoc()) {
                        $sel = ($cap['kva'] == $task['capacity_after']) ? " selected" : "";
                        echo "<option value='" . $cap['kva'] . "'" . $sel . ">"
                             . $cap['kva'] . " kVA</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">The capacity the transformer is being upgraded to.</span>
            </div>

            <?php renderTaskExtraRows(array(
                'connections' => $task['no_of_connections'],
                'feeders'     => $task['no_of_feeders'],
                'length'      => $task['feeder_length'],
            )); ?>

            <?php renderAbcGrid($conn, loadAbcGrid($conn, $id)); ?>

            <?php
            /* The transformer as it is in the register, but the capacity the
               event was raised against — so an old reading's percentages do
               not move when the transformer is later augmented. */
            renderLoadReadingForm($conn, $lrShown, array(
                'area' => $task['area_name'],
                'csc'  => $task['csc_name'] ? $task['csc_name'] . ' (' . $task['csc_code'] . ')' : '',
                'name' => $task['substation_name'],
                'type' => $task['transformer_type'],
                'code' => $task['transformer_code'],
                'kva'  => (int)$task['capacity_before'],
                'ltf'  => isset($task['lt_feeders']) ? $task['lt_feeders'] : '',
            )); ?>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="pl">Proposal No</label>
                        <input type="text" id="pl" name="request_id" maxlength="50"
                               value="<?php echo htmlspecialchars($task['request_id'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="tdate">Date</label>
                        <input type="date" id="tdate" name="task_date"
                               value="<?php echo htmlspecialchars($dateValue); ?>">
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="jn">Job No</label>
                        <input type="text" id="jn" name="job_no" maxlength="60"
                               value="<?php echo htmlspecialchars($task['job_no'] ?? ''); ?>">
                        <span class="field-hint">Enter it exactly as written, e.g. AUG/2026/0057.</span>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="cost">Estimated Cost</label>
                        <input type="number" id="cost" name="est_cost" step="0.01" min="0"
                               inputmode="decimal" placeholder="0.00"
                               value="<?php echo htmlspecialchars($task['est_cost'] ?? ''); ?>">
                        <span class="field-hint">Rupees. Leave blank if no estimate has been made yet.</span>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="status">Status <span class="req">*</span></label>
                        <select id="status" name="status_id">
                            <?php
                            $sts = $conn->query("SELECT status_id, status_name FROM statuses ORDER BY status_id");
                            while ($s = $sts->fetch_assoc()) {
                                $sel = ($s['status_id'] == $task['status_id']) ? " selected" : "";
                                echo "<option value='" . (int)$s['status_id'] . "'" . $sel . ">"
                                     . htmlspecialchars($s['status_name']) . "</option>";
                            }
                            ?>
                        </select>
                        <span class="field-hint">Choosing Completed also updates the transformer's capacity.</span>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="assigned">Assigned To</label>
                        <select id="assigned" name="assigned_to">
                            <option value="0">-- Unassigned --</option>
                            <?php
                            $assignees = eventAssignees($conn);

                            /* Someone outside the six who already holds this
                               event is still shown, so the form does not
                               unassign them behind the user's back. */
                            $current = (int)$task['assigned_to'];
                            if ($current > 0 && !isset($assignees[$current])) {
                                $q = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
                                $q->bind_param("i", $current);
                                $q->execute();
                                $r = $q->get_result()->fetch_assoc();
                                $q->close();
                                if ($r) { $assignees[$current] = $r['full_name']; }
                            }

                            foreach ($assignees as $uid => $name) {
                                $sel = ($uid == $current) ? " selected" : "";
                                echo "<option value='" . (int)$uid . "'" . $sel . ">"
                                     . htmlspecialchars($name) . "</option>";
                            }
                            ?>
                        </select>
                        <span class="field-hint">Leave unassigned if nobody has taken it yet.</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <label for="desc">Description</label>
                <textarea id="desc" name="description" rows="4"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
            </div>

            <?php if ($task['date_completed']) { ?>
            <div class="form-row">
                <label>Completed On</label>
                <input type="text" readonly
                       value="<?php echo date('d M Y, H:i', strtotime($task['date_completed'])); ?>">
            </div>
            <?php } ?>

            <div class="form-actions">
                <button type="submit" class="btn">Save Changes</button>
                <a href="tasks.php" class="action-link cancel-link">Back to events</a>
            </div>
        </form>
    </div>
</div>

<script>
<?php taskFormScript($conn); ?>
<?php abcGridScript(); ?>
<?php loadReadingScript(); ?>
<?php extraTransformerScript(); ?>

extraTxFill(<?php echo json_encode($extraTxList); ?>);
extraTxInit();
updateForm();
</script>

<?php include 'footer.php'; ?>