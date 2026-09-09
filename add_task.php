<?php
include 'auth.php';
include 'db.php';
include 'functions.php';

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
$formAssign = 0;
$formSubs   = array();
$formDate   = "";
$formCost   = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $requestId      = trim($_POST['request_id']);
    $rid            = trim($_POST['rid']);
    $jobNo          = trim($_POST['job_no']);
    $taskDate       = trim($_POST['task_date']);
    $estCost        = trim($_POST['est_cost']);
    $csc_id         = (int)$_POST['csc_id'];
    $transformer_id = (int)$_POST['transformer_id'];
    $type_id        = (int)$_POST['type_id'];      // 0 means no work type chosen
    $capacity_after = (int)$_POST['capacity_after'];
    $description    = trim($_POST['description']);
    $status_id      = (int)$_POST['status_id'];
    $assigned_to    = (int)$_POST['assigned_to'];  // 0 means unassigned
    $postedSubtypes = isset($_POST['subtypes']) ? $_POST['subtypes'] : array();

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
    $formSubs   = array_map('intval', $postedSubtypes);

    // Only two things are compulsory: the transformer and the proposal number.
    // Old paper records are often incomplete, so everything else may be blank.
    if ($transformer_id == 0 || $requestId === '') {
        $message = "Please choose a transformer and enter the proposal number.";
    } else {

        // The transformer's present capacity becomes "capacity before".
        $stmt = $conn->prepare("SELECT capacity_kva FROM transformers
                                WHERE transformer_id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $transformer_id);
        $stmt->execute();
        $tx = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tx) {
            $message = "That transformer no longer exists.";
        } else {

            $capacity_before = $tx['capacity_kva'];

            // Which type was chosen? We need its code for the reference number,
            // and whether it is allowed to carry a capacity change.
            // A work type is optional now, so there may be no row at all.
            $type = null;

            if ($type_id > 0) {
                $stmt = $conn->prepare("SELECT type_code, needs_capacity FROM task_types WHERE type_id = ?");
                $stmt->bind_param("i", $type_id);
                $stmt->execute();
                $type = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if (!$type) {
                $type_id = 0;   // nothing chosen, or a type that no longer exists
            }

            // No work type yet means this is only a proposal, so the internal
            // reference number falls back to the PL prefix.
            $refCode = $type ? $type['type_code'] : 'PL';

            // Only keep ticked options that really belong to the chosen type.
            // Never trust what came back from the browser.
            $validSubtypes = array();

            if ($type_id > 0) {
                $vs = $conn->prepare("SELECT subtype_id FROM task_subtypes
                                      WHERE type_id = ? AND is_active = 1");
                $vs->bind_param("i", $type_id);
                $vs->execute();
                $vsRes = $vs->get_result();
                while ($v = $vsRes->fetch_assoc()) {
                    $validSubtypes[] = (int)$v['subtype_id'];
                }
                $vs->close();
            }

            $chosenSubtypes = array();
            foreach ($postedSubtypes as $sid) {
                $sid = (int)$sid;
                if (in_array($sid, $validSubtypes)) {
                    $chosenSubtypes[] = $sid;
                }
            }

            // Capacity is allowed if the work type needs it, or if any
            // ticked option needs it. The browser hides the field, but
            // hidden is not the same as blocked — enforce it here too.
            $capacityAllowed = $type ? (bool)$type['needs_capacity'] : false;

            if (!$capacityAllowed && count($chosenSubtypes) > 0) {
                $ph = implode(',', array_fill(0, count($chosenSubtypes), '?'));
                $cq = $conn->prepare("SELECT COUNT(*) AS n FROM task_subtypes
                                      WHERE needs_capacity = 1 AND subtype_id IN ($ph)");
                $cq->bind_param(str_repeat('i', count($chosenSubtypes)), ...$chosenSubtypes);
                $cq->execute();
                $cRow = $cq->get_result()->fetch_assoc();
                $cq->close();
                if ($cRow['n'] > 0) { $capacityAllowed = true; }
            }

            if (!$capacityAllowed) {
                $capacity_after = 0;
            }

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

            // Try to save. If two people save at the same moment the reference
            // number could clash, so try again with a fresh one.
            $attempt = 0;
            $saved   = false;

            while (!$saved && $attempt < 3) {

                $attempt++;
                $refNo = generateRefNo($conn, $refCode);

                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO tasks
                         (ref_no, request_id, rid, job_no, type_id, transformer_id,
                          capacity_before, capacity_after,
                          description, status_id, assigned_to, created_by,
                          task_date, date_created, est_cost)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("ssssiiiisiiissd",
                        $refNo, $requestVal, $ridVal, $jobVal, $typeVal, $transformer_id,
                        $capacity_before, $afterVal,
                        $description, $status_id, $assignedVal, $createdBy,
                        $dateVal, $createdAt, $costVal);
                    $stmt->execute();
                    $newTaskId = $conn->insert_id;
                    $stmt->close();

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
                        ? "Job created and marked completed. The transformer register has been updated."
                        : "Job created.";
                    $isError = false;

                    // Saved cleanly — clear the form for the next entry.
                    // The date is deliberately kept, because a batch of old
                    // records is usually entered with the same date.
                    $formCsc = $formTx = $formType = 0;
                    $formStatus = $formAssign = 0;
                    $formRid = $formPL = $formJob = $formDesc = "";
                    $formCost = "";
                    $formSubs = array();

                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() != 1062) {
                        $message = "Error: " . $e->getMessage();
                        break;
                    }
                    // duplicate reference number — loop and try the next one
                }
            }

            if (!$saved && $message == "") {
                $message = "Could not save the job. Please try again.";
            }
        }
    }
}
$showBack     = true;
$backFallback = 'csc.php';    // transformers.php / tasks.php / index.php
$pageTitle = "Add Job";
$activeNav = "tasks";
include 'header.php';

// Which work types bring up the capacity dropdown?
$needsCap = array();
$ncRes = $conn->query("SELECT type_id, needs_capacity FROM task_types WHERE is_active = 1");
while ($nc = $ncRes->fetch_assoc()) {
    $needsCap[(int)$nc['type_id']] = (int)$nc['needs_capacity'];
}

// Which sub-options also bring up the capacity dropdown?
$subNeedsCap = array();
$snRes = $conn->query("SELECT subtype_id, needs_capacity FROM task_subtypes WHERE is_active = 1");
while ($sn = $snRes->fetch_assoc()) {
    $subNeedsCap[(int)$sn['subtype_id']] = (int)$sn['needs_capacity'];
}

// Every transformer, handed to the page as a list. Choosing a CSC filters
// this down so the worker never scrolls through the whole province.
$txList = array();
$txRes = $conn->query("SELECT transformer_id, transformer_code, capacity_kva, csc_id
                       FROM transformers
                       WHERE is_deleted = 0
                       ORDER BY transformer_code");
while ($t = $txRes->fetch_assoc()) {
    $txList[] = array(
        'id'   => (int)$t['transformer_id'],
        'code' => $t['transformer_code'],
        'kva'  => (int)$t['capacity_kva'],
        'csc'  => (int)$t['csc_id']
    );
}
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>New Job</h2></div>
    <div class="panel-body">
        <form method="post">

            <div class="form-row">
                <label for="csc">CSC <span class="req">*</span></label>
                <select id="csc" name="csc_id">
                    <option value="">-- Select CSC --</option>
                    <?php
                    $cscs = $conn->query("SELECT csc.csc_id, csc.csc_name, csc.csc_code,
                                                 areas.area_name
                                          FROM csc
                                          LEFT JOIN areas ON csc.area_id = areas.area_id
                                          WHERE csc.is_deleted = 0
                                          ORDER BY csc.csc_name");
                    while ($c = $cscs->fetch_assoc()) {
                        $sel = ($formCsc == $c['csc_id']) ? " selected" : "";
                        echo "<option value='" . (int)$c['csc_id'] . "'" . $sel . ">"
                             . htmlspecialchars($c['csc_name'] . " (" . $c['csc_code'] . ")"
                                                . ($c['area_name'] ? " — " . $c['area_name'] : ""))
                             . "</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">Pick the CSC first — it shortens the transformer list.</span>
            </div>

            <div class="form-row">
                <label for="tx">Transformer <span class="req">*</span></label>
                <select id="tx" name="transformer_id">
                    <option value="">-- Select a CSC first --</option>
                </select>
                <span class="field-hint">Only transformers in the chosen CSC are listed.</span>
            </div>

            <div class="form-row">
                <label for="rid">RID</label>
                <input type="text" id="rid" name="rid" maxlength="50"
                       value="<?php echo htmlspecialchars($formRid); ?>">
            </div>

            <div class="form-row">
                <label for="pl">Proposal No <span class="req">*</span></label>
                <input type="text" id="pl" name="request_id" maxlength="50"
                       value="<?php echo htmlspecialchars($formPL); ?>">
                <span class="field-hint">e.g. PL/26/0013.</span>
            </div>

            <div class="form-row">
                <label for="tdate">Date</label>
                <input type="date" id="tdate" name="task_date"
                       value="<?php echo htmlspecialchars($formDate); ?>">
                <span class="field-hint">The date written on the paper. Leave blank to use today.</span>
            </div>

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
                <span class="field-hint">Leave blank for a proposal with no work type yet.</span>
            </div>

            <div class="form-row" id="subtype-row" style="display:none;">
                <label>Work Details</label>
                <div id="subtype-boxes">
                    <?php
                    $subs = $conn->query("SELECT subtype_id, type_id, subtype_name, description
                                          FROM task_subtypes
                                          WHERE is_active = 1
                                          ORDER BY subtype_id");
                    while ($sb = $subs->fetch_assoc()) {
                        $chk = in_array((int)$sb['subtype_id'], $formSubs) ? " checked" : "";
                        echo "<label class='subtype-option' data-type='" . (int)$sb['type_id'] . "'"
                           . ($sb['description'] ? " title='" . htmlspecialchars($sb['description']) . "'" : "")
                           . " style='display:none;'>"
                           . "<input type='checkbox' name='subtypes[]' value='" . (int)$sb['subtype_id'] . "'" . $chk . "> "
                           . htmlspecialchars($sb['subtype_name'])
                           . "</label>";
                    }
                    ?>
                </div>
                <span class="field-hint">Tick every option that applies. You can choose more than one.</span>
            </div>

            <div class="form-row">
                <label for="jn">Job No</label>
                <input type="text" id="jn" name="job_no" maxlength="60"
                       value="<?php echo htmlspecialchars($formJob); ?>">
                <span class="field-hint">e.g. AUG/2026/0057. Leave blank if no job number was issued.</span>
            </div>

            <div class="form-row" id="capacity-row" style="display:none;">
                <label for="capafter">New Capacity</label>
                <select id="capafter" name="capacity_after">
                    <option value="">-- No capacity change --</option>
                    <?php
                    $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                    while ($cap = $caps->fetch_assoc()) {
                        echo "<option value='" . $cap['kva'] . "'>" . $cap['kva'] . " kVA</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">The capacity the transformer is being upgraded to.</span>
            </div>

            <div class="form-row">
                <label for="cost">Estimated Cost</label>
                <input type="number" id="cost" name="est_cost"
                       step="0.01" min="0" inputmode="decimal"
                       placeholder="0.00"
                       value="<?php echo htmlspecialchars($formCost); ?>">
                <span class="field-hint">Rupees. Leave blank if no estimate has been made yet.</span>
            </div>

            <div class="form-row">
                <label for="desc">Description</label>
                <textarea id="desc" name="description" rows="4"><?php echo htmlspecialchars($formDesc); ?></textarea>
            </div>

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

            <div class="form-row">
                <label for="assigned">Assigned To</label>
                <select id="assigned" name="assigned_to">
                    <option value="0">-- Unassigned --</option>
                    <?php
                    $us = $conn->query("SELECT user_id, full_name FROM users
                                        WHERE is_deleted = 0 ORDER BY full_name");
                    while ($u = $us->fetch_assoc()) {
                        $sel = ($formAssign == $u['user_id']) ? " selected" : "";
                        echo "<option value='" . (int)$u['user_id'] . "'" . $sel . ">"
                             . htmlspecialchars($u['full_name']) . "</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">Leave unassigned if nobody has taken it yet.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="tasks.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
var needsCapacity    = <?php echo json_encode($needsCap); ?>;
var subNeedsCapacity = <?php echo json_encode($subNeedsCap); ?>;
var allTransformers  = <?php echo json_encode($txList); ?>;
var chosenTx         = <?php echo (int)$formTx; ?>;

/* Refill the transformer dropdown with only the chosen CSC's transformers. */
function updateTransformers() {

    var cscId = document.getElementById('csc').value;
    var txSel = document.getElementById('tx');
    var keep  = txSel.value;          // what was selected before, if anything

    txSel.innerHTML = '';

    if (cscId === '') {
        txSel.appendChild(new Option('-- Select a CSC first --', ''));
        return;
    }

    var list = [];
    for (var i = 0; i < allTransformers.length; i++) {
        if (allTransformers[i].csc == cscId) { list.push(allTransformers[i]); }
    }

    if (list.length === 0) {
        txSel.appendChild(new Option('-- No transformers in this CSC --', ''));
        return;
    }

    txSel.appendChild(new Option('-- Select transformer --', ''));

    for (var j = 0; j < list.length; j++) {
        var label = list[j].code + ' (' + list[j].kva + ' kVA)';
        var opt   = new Option(label, list[j].id);
        txSel.appendChild(opt);
        if (list[j].id == keep || list[j].id == chosenTx) { opt.selected = true; }
    }

    chosenTx = 0;   // only used once, when the page first loads
}

function updateForm() {
    var typeId = document.getElementById('type').value;

    // --- tick boxes: show only the ones for this work type ---
    var options = document.querySelectorAll('.subtype-option');
    var shown = 0;

    for (var i = 0; i < options.length; i++) {
        if (typeId !== '' && options[i].getAttribute('data-type') === typeId) {
            options[i].style.display = 'block';
            shown++;
        } else {
            options[i].style.display = 'none';
            options[i].querySelector('input').checked = false;
        }
    }

    document.getElementById('subtype-row').style.display = (shown > 0) ? 'block' : 'none';

    // --- capacity dropdown: the work type may need it, or a ticked option may ---
    var wantCapacity = (typeId !== '' && needsCapacity[typeId] == 1);

    for (var j = 0; j < options.length; j++) {
        var box = options[j].querySelector('input');
        if (options[j].style.display !== 'none' && box.checked
            && subNeedsCapacity[box.value] == 1) {
            wantCapacity = true;
        }
    }

    var capRow = document.getElementById('capacity-row');
    if (wantCapacity) {
        capRow.style.display = 'block';
    } else {
        capRow.style.display = 'none';
        document.getElementById('capafter').value = '';
    }
}

document.getElementById('csc').addEventListener('change', updateTransformers);
document.getElementById('type').addEventListener('change', updateForm);

// Ticking a box can now change what's shown, so listen to those too.
var boxes = document.querySelectorAll('.subtype-option input');
for (var k = 0; k < boxes.length; k++) {
    boxes[k].addEventListener('change', updateForm);
}

updateTransformers();
updateForm();
</script>

<?php include 'footer.php'; ?>