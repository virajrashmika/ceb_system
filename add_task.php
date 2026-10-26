<?php
include 'auth.php';
$needArea = 'events';
include 'require_staff.php';   // Guest and Worker may read events, not change them
include 'db.php';
include_once 'functions.php';

$message = "";
$isError = true;

// Values typed by the user. Kept so the form can be filled back in
// if saving fails — nobody should have to type it all again.
$formCsc    = 0;
$formTx     = 0;
$formType   = 0;
$formRid    = "";
$formPL     = "";
$formJob    = "";
$formDesc   = "";
$formStatus = 0;
$myUserId   = (int)$_SESSION['user_id'];
// A new event starts assigned to whoever raises it, if they are one of the
// six in eventAssigneeIds(); otherwise it starts unassigned.
$formAssign = in_array($myUserId, eventAssigneeIds(), true) ? $myUserId : 0;
$formSubs   = array();
$formDate   = "";
$formCost   = "";
$formCap    = 0;
$formExtraTx = array();   // Transformer T2 to T5

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $requestId      = trim($_POST['request_id']);
    $rid            = trim($_POST['rid']);
    $jobNo          = trim($_POST['job_no']);
    $taskDate       = trim($_POST['task_date']);
    $estCost        = trim($_POST['est_cost']);
    $csc_id         = (int)$_POST['csc_id'];
    $transformer_id = (int)$_POST['transformer_id'];
    $type_id        = (int)$_POST['type_id'];      // 0 means no work type chosen
    $capacity_after = isset($_POST['capafter']) ? (int)$_POST['capafter'] : 0;
    $description    = trim($_POST['description']);
    $status_id      = (int)$_POST['status_id'];
    $assigned_to    = (int)$_POST['assigned_to'];  // 0 means unassigned

    // One of the six in eventAssigneeIds(), or nobody. The dropdown offers
    // nothing else, and a value sent some other way is not trusted.
    if (!in_array($assigned_to, eventAssigneeIds(), true)) { $assigned_to = 0; }
    $postedSubtypes = isset($_POST['subtypes']) ? $_POST['subtypes'] : array();

    // Work types like Retail Connection use a choose-one dropdown instead of
    // tick boxes. Either way the answer ends up in task_subtype_links.
    if (isset($_POST['subtype_pick']) && (int)$_POST['subtype_pick'] > 0) {
        $postedSubtypes[] = (int)$_POST['subtype_pick'];
    }

    $formCsc    = $csc_id;
    $formTx     = $transformer_id;
    $formType   = $type_id;
    $formRid    = $rid;
    $formPL     = $requestId;
    $formJob    = $jobNo;
    $formDesc   = $description;
    $formStatus = $status_id;
    $formAssign = $assigned_to;
    $formDate   = $taskDate;
    $formCost   = $estCost;
    $formCap    = $capacity_after;
    $formSubs   = array_map('intval', $postedSubtypes);

    // Transformer T2 to T5, for a job that covers more than one.
    list($extraTx, $extraTxError) = extraTransformersFromPost($conn, $_POST, $transformer_id);
    $formExtraTx = $extraTx;

    // The Load Reading sheet, checked before anything is saved. A bad box
    // stops the save with the form left as typed. Only a work type that asks
    // for the sheet is checked — any other type ignores it altogether.
    $lrAllowedNow = taskAllowedFields($conn, $type_id);
    list($lrValues, $lrErrors) = loadReadingFromPost($_POST);
    if (empty($lrAllowedNow['loadreading'])) { $lrErrors = array(); }

    // Only the transformer is compulsory. The proposal number used to be as
    // well, but new jobs no longer get one, so it is optional like the rest.
    if ($transformer_id == 0) {
        $message = "Please choose a transformer.";
    } elseif ($extraTxError !== '') {
        $message = $extraTxError;
    } elseif ($lrErrors) {
        $message = lrErrorMessage($lrErrors);
    } else {

        // The transformer's present capacity becomes "capacity before".
        $stmt = $conn->prepare("SELECT capacity_kva FROM transformers
                                WHERE transformer_id = ? AND " . txActiveSql('transformers'));
        $stmt->bind_param("i", $transformer_id);
        $stmt->execute();
        $tx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tx) {
            $message = "That transformer no longer exists.";
        } else {

            $capacity_before = $tx['capacity_kva'];

            // Does the chosen work type still exist? A work type is optional,
            // so there may be no row at all, and one picked a moment ago can
            // have been retired since the form was opened.
            $type = null;

            if ($type_id > 0) {
                $stmt = $conn->prepare("SELECT type_id FROM task_types WHERE type_id = ?");
                $stmt->bind_param("i", $type_id);
                $stmt->execute();
                $type = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if (!$type) {
                $type_id = 0;   // nothing chosen, or a type that no longer exists
            }

            // Only keep options that really belong to the chosen type.
            // Never trust what came back from the browser.
            $chosenSubtypes = resolveSubtypes($conn, $type_id, $postedSubtypes);

            // Which extra boxes does this work type allow? The browser hides
            // the ones it does not need, but hidden is not blocked, so the
            // same question is asked again here.
            $allowed = taskAllowedFields($conn, $type_id, $chosenSubtypes);
            $extra   = taskExtraValues($allowed, $_POST);

            // The ABC cable grid is twelve numbers, not one, so it is read
            // separately — but by the same rule: a work type that does not
            // ask for it cannot have one, whatever the browser sent.
            $abcGrid = !empty($allowed['abcgrid'])
                     ? abcGridFromPost($conn, $_POST) : array();

            // The same rule for the Load Reading sheet.
            $lrSave = !empty($allowed['loadreading']) ? $lrValues : lrBlank();

            $capacity_after = $extra['capacity'] ? (int)$extra['capacity'] : 0;

            // Is this job being created already finished? Historical records
            // from the old books will be entered this way.
            $cs = $conn->prepare("SELECT status_name FROM statuses WHERE status_id = ?");
            $cs->bind_param("i", $status_id);
            $cs->execute();
            $csRow = $cs->get_result()->fetch_assoc();
            $cs->close();

            $createdComplete = ($csRow && $csRow['status_name'] === 'Completed');

            $createdBy   = (int)$_SESSION['user_id'];
            $assignedVal = ($assigned_to > 0) ? $assigned_to : null;
            $afterVal    = ($capacity_after > 0) ? $capacity_after : null;
            $requestVal  = ($requestId !== '') ? $requestId : null;
            $ridVal      = ($rid       !== '') ? $rid       : null;
            $jobVal      = ($jobNo     !== '') ? $jobNo     : null;
            $typeVal     = ($type_id   >   0)  ? $type_id   : null;

            // The date written on the paper. Left blank, the record simply
            // carries today's date, exactly as it did before.
            $dateVal   = ($taskDate !== '') ? $taskDate : null;
            $createdAt = ($taskDate !== '') ? $taskDate . ' 00:00:00'
                                           : date('Y-m-d H:i:s');

            // Estimated cost. Blank means "not estimated yet", which is not
            // the same as zero, so it is stored as NULL.
            $costVal = ($estCost !== '' && is_numeric($estCost))
                     ? (float)$estCost : null;

            /* One attempt is enough now. The loop that used to sit here tried
               three times, because two people saving at the same moment could
               be handed the same generated reference number and the second
               INSERT would be refused. With that number gone, task_id is the
               only unique thing on the row and the database allocates it, so
               there is nothing left to collide. */
            $saved = false;

            try {
                $stmt = $conn->prepare(
                    "INSERT INTO tasks
                     (request_id, rid, job_no, type_id, transformer_id,
                      capacity_before, capacity_after,
                      no_of_connections, no_of_feeders, feeder_length,
                      description, status_id, assigned_to, created_by,
                      task_date, date_created, est_cost)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("sssiiiiiissiiissd",
                    $requestVal, $ridVal, $jobVal, $typeVal, $transformer_id,
                    $capacity_before, $afterVal,
                    $extra['connections'], $extra['feeders'], $extra['length'],
                    $description, $status_id, $assignedVal, $createdBy,
                    $dateVal, $createdAt, $costVal);
                $stmt->execute();
                $newTaskId = $conn->insert_id;
                $stmt->close();

                // Save the ABC cable grid against the new event.
                saveAbcGrid($conn, $newTaskId, $abcGrid);

                // And the Load Reading sheet, when there is anything on it.
                if (lrHasAny($lrSave)) { saveLoadReading($conn, $newTaskId, $lrSave); }

                // Transformer T2 to T5, if this job covers more than one.
                saveExtraTransformers($conn, $newTaskId, $extraTx);

                // Save the ticked options against the new job.
                if (count($chosenSubtypes) > 0) {
                    $ls = $conn->prepare("INSERT INTO task_subtype_links (task_id, subtype_id)
                                          VALUES (?, ?)");
                    foreach ($chosenSubtypes as $sid) {
                        $ls->bind_param("ii", $newTaskId, $sid);
                        $ls->execute();
                    }
                    $ls->close();
                }

                // A job created already marked Completed must behave the
                // same as one completed later through edit_task.php.
                if ($createdComplete) {

                    $up = $conn->prepare("UPDATE tasks SET date_completed = NOW()
                                          WHERE task_id = ?");
                    $up->bind_param("i", $newTaskId);
                    $up->execute();
                    $up->close();

                    if ($afterVal) {
                        $up = $conn->prepare("UPDATE transformers
                                              SET capacity_kva = ?
                                              WHERE transformer_id = ?");
                        $up->bind_param("ii", $afterVal, $transformer_id);
                        $up->execute();
                        $up->close();
                    }
                }

                $saved   = true;
                $message = $createdComplete
                    ? "Event created and marked completed. The transformer register has been updated."
                    : "Event created.";
                $isError = false;

                // Saved cleanly — clear the form for the next entry.
                // The date is deliberately kept, because a batch of old
                // records is usually entered with the same date.
                $formCsc = $formTx = $formType = 0;
                $formStatus = 0;
                $formAssign = in_array($myUserId, eventAssigneeIds(), true) ? $myUserId : 0;
                $formRid = $formPL = $formJob = $formDesc = "";
                $formCost = "";
                $formCap  = 0;
                $formSubs = array();
                $formExtraTx = array();

            } catch (mysqli_sql_exception $e) {
                $message = "Error: " . $e->getMessage();
            }

            if (!$saved && $message == "") {
                $message = "Could not save the event. Please try again.";
            }
        }
    }
}
$showBack     = true;
$backFallback = 'csc.php';    // transformers.php / tasks.php / index.php
$pageTitle = "Add Event";
$activeNav = "tasks";
include 'header.php';

// Values typed into the conditional boxes, kept for redisplay after an error.
$formExtra = array(
    'connections' => isset($_POST['connections']) ? trim($_POST['connections']) : '',
    'feeders'     => isset($_POST['feeders'])     ? trim($_POST['feeders'])     : '',
    'length'      => isset($_POST['length'])      ? trim($_POST['length'])      : '',
);
if (!$isError) { $formExtra = array('connections' => '', 'feeders' => '', 'length' => ''); }

// The same for the ABC grid: keep what was typed if the save failed, and
// start empty once it succeeded.
$formAbc = ($_SERVER["REQUEST_METHOD"] == "POST" && $isError)
         ? abcGridFromPost($conn, $_POST) : array();

// And the Load Reading sheet — everything as typed, bad boxes included,
// so the mistake can be seen and put right.
if ($_SERVER["REQUEST_METHOD"] == "POST" && $isError) {
    list($formLr) = loadReadingFromPost($_POST);
} else {
    $formLr = lrBlank();
}

$lrReady = loadReadingReady($conn);

// Every transformer, handed to the page as a list. Choosing a CSC filters
// this down so the worker never scrolls through the whole province.
// The substation name goes into each option, because the workers know a
// transformer by its name far better than by its S/N. Type and LT feeders
// ride along for the Load Reading form, which shows them read-only — only
// once that form exists.
$txList = array();
$txRes = $conn->query("SELECT transformer_id, transformer_code, substation_name, capacity_kva, csc_id"
                      . ($lrReady ? ", transformer_type, lt_feeders" : "") . "
                       FROM transformers
                       WHERE " . txActiveSql('transformers') . "
                       ORDER BY transformer_code");
while ($t = $txRes->fetch_assoc()) {
    $row = array(
        'id'   => (int)$t['transformer_id'],
        'code' => $t['transformer_code'],
        'name' => (string)$t['substation_name'],
        'kva'  => (int)$t['capacity_kva'],
        'csc'  => (int)$t['csc_id']
    );
    if ($lrReady) {
        $row['type'] = (string)$t['transformer_type'];
        $row['ltf']  = ($t['lt_feeders'] !== null) ? (int)$t['lt_feeders'] : '';
    }
    $txList[] = $row;
}

// Each CSC's name and area, for the same read-only details.
$cscInfo = array();
$ciRes = $conn->query("SELECT csc.csc_id, csc.csc_name, csc.csc_code, areas.area_name
                       FROM csc LEFT JOIN areas ON csc.area_id = areas.area_id");
while ($ci = $ciRes->fetch_assoc()) {
    $cscInfo[(int)$ci['csc_id']] = array(
        'csc'  => $ci['csc_name'] . ' (' . $ci['csc_code'] . ')',
        'area' => (string)$ci['area_name'],
    );
}
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>New Event</h2></div>
    <div class="panel-body">
        <form method="post">

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="csc">CSC <span class="req">*</span></label>
                        <select id="csc" name="csc_id">
                            <option value="">-- Select CSC --</option>
                            <?php
                            /* Ordered and labelled the way the Transformers
                               pages do it — the area first, then the CSC.
                               The office thinks in areas, so the three
                               Badulla CSCs belong together in the list
                               rather than scattered down an alphabet that
                               mixes all five areas into each other. A CSC
                               with no area still reads properly; it simply
                               has nothing in front of it. */
                            $cscs = $conn->query("SELECT csc.csc_id, csc.csc_name, csc.csc_code,
                                                         areas.area_name
                                                  FROM csc
                                                  LEFT JOIN areas ON csc.area_id = areas.area_id
                                                  WHERE csc.is_deleted = 0
                                                  ORDER BY areas.area_name, csc.csc_name");
                            while ($c = $cscs->fetch_assoc()) {
                                $sel = ($formCsc == $c['csc_id']) ? " selected" : "";
                                echo "<option value='" . (int)$c['csc_id'] . "'" . $sel . ">"
                                     . htmlspecialchars(($c['area_name'] ? $c['area_name'] . " — " : "")
                                                        . $c['csc_name'] . " (" . $c['csc_code'] . ")")
                                     . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="tx">Transformer T1 <span class="req">*</span></label>
                        <div class="tx-line">
                            <select id="tx" name="transformer_id">
                                <option value="">-- Select a CSC first --</option>
                            </select>
                            <?php renderExtraTransformerAddButton(); ?>
                        </div>
                    </div>
                    <?php renderExtraTransformerRows($formExtraTx); ?>
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
                            while ($ty = $types->fetch_assoc()) {
                                $sel = ($formType == $ty['type_id']) ? " selected" : "";
                                echo "<option value='" . (int)$ty['type_id'] . "'" . $sel . ">"
                                     . htmlspecialchars($ty['type_name'] . " (" . $ty['type_code'] . ")")
                                     . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="rid">RID</label>
                        <input type="text" id="rid" name="rid" maxlength="50"
                               value="<?php echo htmlspecialchars($formRid); ?>">
                    </div>
                </div>
            </div>


            <?php renderSubtypeControls($conn, $formSubs); ?>

            <div class="form-row" id="capacity-row" style="display:none;">
                <label for="capafter">New Capacity</label>
                <select id="capafter" name="capafter">
                    <option value="">-- No capacity change --</option>
                    <?php
                    $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                    while ($cap = $caps->fetch_assoc()) {
                        $sel = ($formCap == $cap['kva']) ? " selected" : "";
                        echo "<option value='" . $cap['kva'] . "'" . $sel . ">"
                             . $cap['kva'] . " kVA</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">The capacity the transformer is being upgraded to.</span>
            </div>

            <?php renderTaskExtraRows($formExtra); ?>

            <?php renderAbcGrid($conn, $formAbc); ?>

            <?php renderLoadReadingForm($conn, $formLr); ?>


            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="pl">Proposal No</label>
                        <input type="text" id="pl" name="request_id" maxlength="50"
                               value="<?php echo htmlspecialchars($formPL); ?>">
                        
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="tdate">Date</label>
                        <input type="date" id="tdate" name="task_date"
                               value="<?php echo htmlspecialchars($formDate); ?>">
                        <span class="field-hint">The date written on the paper. Leave blank to use today.</span>
                    </div>
                </div>
            </div>


            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="jn">Job No</label>
                        <input type="text" id="jn" name="job_no" maxlength="60"
                               value="<?php echo htmlspecialchars($formJob); ?>">
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="cost">Estimated Cost</label>
                        <input type="number" id="cost" name="est_cost"
                               step="0.01" min="0" inputmode="decimal"
                               placeholder="0.00"
                               value="<?php echo htmlspecialchars($formCost); ?>">
                    </div>
                </div>
            </div>


            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="status">Status</label>
                        <select id="status" name="status_id">
                            <?php
                            $sts = $conn->query("SELECT status_id, status_name FROM statuses ORDER BY status_id");
                            while ($s = $sts->fetch_assoc()) {
                                $sel = ($formStatus == $s['status_id']) ? " selected" : "";
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
                            foreach (eventAssignees($conn) as $uid => $name) {
                                $sel = ($uid == $formAssign) ? " selected" : "";
                                echo "<option value='" . (int)$uid . "'" . $sel . ">"
                                     . htmlspecialchars($name) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>


            <div class="form-row">
                <label for="desc">Description</label>
                <textarea id="desc" name="description" rows="4"><?php echo htmlspecialchars($formDesc); ?></textarea>
            </div>


            <div class="form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="tasks.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
var allTransformers  = <?php echo json_encode($txList); ?>;
var chosenTx         = <?php echo (int)$formTx; ?>;

function updateTransformers() {

    var cscId = document.getElementById('csc').value;
    var txSel = document.getElementById('tx');
    var keep  = txSel.value;          // what was selected before, if anything

    txSel.innerHTML = '';

    var list = [];
    for (var i = 0; i < allTransformers.length; i++) {
        if (cscId !== '' && allTransformers[i].csc == cscId) { list.push(allTransformers[i]); }
    }

    // Transformer T2 to T5 choose from the same CSC as T1.
    extraTxFill(list);

    if (cscId === '') {
        txSel.appendChild(new Option('-- Select a CSC first --', ''));
        return;
    }

    if (list.length === 0) {
        txSel.appendChild(new Option('-- No transformers in this CSC --', ''));
        return;
    }

    txSel.appendChild(new Option('-- Select transformer --', ''));

    for (var j = 0; j < list.length; j++) {
        var label = txLabel(list[j]);
        var opt   = new Option(label, list[j].id);
        txSel.appendChild(opt);
        if (list[j].id == keep || list[j].id == chosenTx) { opt.selected = true; }
    }

    chosenTx = 0;   // only used once, when the page first loads
}

<?php taskFormScript($conn); ?>
<?php abcGridScript(); ?>
<?php loadReadingScript(); ?>
<?php extraTransformerScript(); ?>

var cscInfo = <?php echo json_encode($cscInfo); ?>;

/* The Load Reading form shows the chosen transformer's details, read-only,
   and works its percentages from that transformer's capacity — the figure
   that is saved as the event's capacity before. */
function showChosenTx() {
    var id = document.getElementById('tx').value;
    var info = null;
    for (var i = 0; i < allTransformers.length; i++) {
        if (String(allTransformers[i].id) === id) {
            var t = allTransformers[i];
            var c = cscInfo[t.csc] || {};
            info = { area: c.area, csc: c.csc, name: t.name, type: t.type,
                     code: t.code, kva: t.kva, ltf: t.ltf };
            break;
        }
    }
    lrShowTx(info);
}

document.getElementById('csc').addEventListener('change', function () {
    updateTransformers();
    showChosenTx();
});
document.getElementById('tx').addEventListener('change', showChosenTx);

updateTransformers();
showChosenTx();
extraTxInit();
updateForm();
</script>

<?php include 'footer.php'; ?>