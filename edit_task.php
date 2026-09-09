<?php
include 'auth.php';
include 'db.php';
include 'functions.php';

$message = "";
$isError = true;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id             = (int)$_POST['task_id'];
    $requestId      = trim($_POST['request_id']);
    $jobNo          = trim($_POST['job_no']);
    $taskDate       = trim($_POST['task_date']);
    $estCost        = trim($_POST['est_cost']);
    $type_id        = (int)$_POST['type_id'];
    $capacity_after = isset($_POST['capacity_after']) ? (int)$_POST['capacity_after'] : 0;
    $description    = trim($_POST['description']);
    $status_id      = (int)$_POST['status_id'];
    $assigned_to    = (int)$_POST['assigned_to'];
    $postedSubtypes = isset($_POST['subtypes']) ? $_POST['subtypes'] : array();

    // Does the chosen work type still exist? A job may have none at all.
    $type = null;
    if ($type_id > 0) {
        $stmt = $conn->prepare("SELECT type_id, needs_capacity FROM task_types WHERE type_id = ?");
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
    $jobVal      = ($jobNo !== '')     ? $jobNo     : null;

    $dateVal   = ($taskDate !== '') ? $taskDate : date('Y-m-d');
    $createdAt = $dateVal . ' 00:00:00';

    $costVal = ($estCost !== '' && is_numeric($estCost)) ? (float)$estCost : null;

    // Capacity is only allowed if the work type needs it, or a ticked
    // option needs it. The browser hides the field, but hidden is not
    // the same as blocked — enforce it here too.
    $capacityAllowed = $type ? (bool)$type['needs_capacity'] : false;

    $cleanSubtypes = array_map('intval', $postedSubtypes);

    if (!$capacityAllowed && count($cleanSubtypes) > 0) {
        $ph = implode(',', array_fill(0, count($cleanSubtypes), '?'));
        $cq = $conn->prepare("SELECT COUNT(*) AS n FROM task_subtypes
                              WHERE needs_capacity = 1 AND subtype_id IN ($ph)");
        $cq->bind_param(str_repeat('i', count($cleanSubtypes)), ...$cleanSubtypes);
        $cq->execute();
        $cRow = $cq->get_result()->fetch_assoc();
        $cq->close();
        if ($cRow['n'] > 0) { $capacityAllowed = true; }
    }

    if (!$capacityAllowed) { $capacity_after = 0; }
    $afterVal = ($capacity_after > 0) ? $capacity_after : null;

    // ---- read the job as it stands, before anything is written ----
    $bq = $conn->prepare(
        "SELECT t.request_id, t.job_no, t.task_date, t.est_cost,
                t.capacity_after, t.description,
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
             SET request_id = ?, job_no = ?, type_id = ?, capacity_after = ?,
                 description = ?, est_cost = ?, task_date = ?, date_created = ?,
                 status_id = ?, assigned_to = ?,
                 date_completed = IFNULL(date_completed, NOW())
             WHERE task_id = ?"
        );
        $stmt->bind_param("ssiisdssiii", $requestVal, $jobVal, $typeVal, $afterVal,
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

        $message = "Job completed. The transformer register has been updated.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE tasks
             SET request_id = ?, job_no = ?, type_id = ?, capacity_after = ?,
                 description = ?, est_cost = ?, task_date = ?, date_created = ?,
                 status_id = ?, assigned_to = ?,
                 date_completed = NULL
             WHERE task_id = ?"
        );
        $stmt->bind_param("ssiisdssiii", $requestVal, $jobVal, $typeVal, $afterVal,
                          $description, $costVal, $dateVal, $createdAt,
                          $status_id, $assignedVal, $id);
        $stmt->execute();
        $stmt->close();

        $message = "Job updated.";
    }

    // ---- save the ticked work details ----
    $del = $conn->prepare("DELETE FROM task_subtype_links WHERE task_id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    if ($type_id > 0 && count($cleanSubtypes) > 0) {

        $validSubtypes = array();
        $vs = $conn->prepare("SELECT subtype_id FROM task_subtypes
                              WHERE type_id = ? AND is_active = 1");
        $vs->bind_param("i", $type_id);
        $vs->execute();
        $vsRes = $vs->get_result();
        while ($v = $vsRes->fetch_assoc()) {
            $validSubtypes[] = (int)$v['subtype_id'];
        }
        $vs->close();

        $ins = $conn->prepare("INSERT INTO task_subtype_links (task_id, subtype_id)
                               VALUES (?, ?)");
        foreach ($cleanSubtypes as $sid) {
            if (in_array($sid, $validSubtypes)) {
                $ins->bind_param("ii", $id, $sid);
                $ins->execute();
            }
        }
        $ins->close();
    }

    // ---- write one log row for each field that really changed ----
    if ($old) {
        logChanges($conn, 'task', $id,
            array(
                'Proposal No'  => $old['request_id'],
                'Date'         => $old['task_date'],
                'Work Type'    => $old['type_name'],
                'Job No'       => $old['job_no'],
                'Est. Cost'    => $old['est_cost'],
                'New Capacity' => $old['capacity_after'] ? $old['capacity_after'] . ' kVA' : '',
                'Description'  => $old['description'],
                'Status'       => $old['status_name'],
                'Assigned To'  => $old['assigned_name'],
            ),
            array(
                'Proposal No'  => $requestVal,
                'Date'         => $dateVal,
                'Work Type'    => $newTypeName,
                'Job No'       => $jobVal,
                'Est. Cost'    => $costVal,
                'New Capacity' => $afterVal ? $afterVal . ' kVA' : '',
                'Description'  => $description,
                'Status'       => $newStatusName,
                'Assigned To'  => $newAssignedName,
            ),
            (int)$_SESSION['user_id']);
    }

    $isError = false;
}

// Load the job
$stmt = $conn->prepare(
    "SELECT t.*, tx.transformer_code, tx.capacity_kva AS tx_capacity,
            csc.csc_name, ty.type_name, ty.type_code,
            cu.full_name AS created_name
     FROM tasks t
     LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
     LEFT JOIN csc            ON tx.csc_id = csc.csc_id
     LEFT JOIN task_types ty  ON t.type_id = ty.type_id
     LEFT JOIN users cu       ON t.created_by = cu.user_id
     WHERE t.task_id = ? AND t.is_deleted = 0"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    die("Job not found. <a href='tasks.php'>Back</a>");
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

// Which work types and options bring up the capacity dropdown?
$needsCap = array();
$ncRes = $conn->query("SELECT type_id, needs_capacity FROM task_types WHERE is_active = 1");
while ($nc = $ncRes->fetch_assoc()) {
    $needsCap[(int)$nc['type_id']] = (int)$nc['needs_capacity'];
}

$subNeedsCap = array();
$snRes = $conn->query("SELECT subtype_id, needs_capacity FROM task_subtypes WHERE is_active = 1");
while ($sn = $snRes->fetch_assoc()) {
    $subNeedsCap[(int)$sn['subtype_id']] = (int)$sn['needs_capacity'];
}

$dateValue = $task['task_date']
    ? $task['task_date']
    : date('Y-m-d', strtotime($task['date_created']));

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
        <h2>Job Details</h2>
        <span class="panel-note">
            raised by <?php echo htmlspecialchars($task['created_name']); ?> on
            <?php echo date('d M Y', strtotime($task['date_created'])); ?>
            <span class="action-sep">|</span>
            <a href="history.php?type=task&id=<?php echo $id; ?>" class="action-link">Edit History</a>
        </span>
    </div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">

            <div class="form-row">
                <label>Transformer</label>
                <input type="text" readonly
                       value="<?php echo htmlspecialchars($task['transformer_code'] . " — " . $task['csc_name']); ?>">
            </div>

            <div class="form-row">
                <label for="rid">Proposal No</label>
                <input type="text" id="rid" name="request_id" maxlength="50"
                       value="<?php echo htmlspecialchars($task['request_id'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <label for="tdate">Date</label>
                <input type="date" id="tdate" name="task_date"
                       value="<?php echo htmlspecialchars($dateValue); ?>">
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
                        $sel = ($ty['type_id'] == $task['type_id']) ? " selected" : "";
                        echo "<option value='" . (int)$ty['type_id'] . "'" . $sel . ">"
                             . htmlspecialchars($ty['type_name'] . " (" . $ty['type_code'] . ")")
                             . "</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">Set this once the work type has been decided.</span>
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
                        $chk = in_array((int)$sb['subtype_id'], $ticked) ? " checked" : "";
                        echo "<label class='subtype-option' data-type='" . (int)$sb['type_id'] . "'"
                           . ($sb['description'] ? " title='" . htmlspecialchars($sb['description']) . "'" : "")
                           . " style='display:none;'>"
                           . "<input type='checkbox' name='subtypes[]' value='" . (int)$sb['subtype_id'] . "'" . $chk . "> "
                           . htmlspecialchars($sb['subtype_name'])
                           . "</label>";
                    }
                    ?>
                </div>
                <span class="field-hint">Tick every option that applies.</span>
            </div>

            <div class="form-row">
                <label for="jn">Job No</label>
                <input type="text" id="jn" name="job_no" maxlength="60"
                       value="<?php echo htmlspecialchars($task['job_no'] ?? ''); ?>">
                <span class="field-hint">Enter it exactly as written, e.g. AUG/2026/0057.</span>
            </div>

            <div class="form-row">
                <label for="cost">Estimated Cost</label>
                <input type="number" id="cost" name="est_cost" step="0.01" min="0"
                       inputmode="decimal" placeholder="0.00"
                       value="<?php echo htmlspecialchars($task['est_cost'] ?? ''); ?>">
                <span class="field-hint">Rupees. Leave blank if no estimate has been made yet.</span>
            </div>

            <div class="form-row">
                <label>Capacity Before</label>
                <input type="text" readonly value="<?php echo $task['capacity_before']; ?> kVA">
            </div>

            <div class="form-row" id="capacity-row" style="display:none;">
                <label for="capafter">New Capacity</label>
                <select id="capafter" name="capacity_after">
                    <option value="">-- No capacity change --</option>
                    <?php
                    $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                    while ($cap = $caps->fetch_assoc()) {
                        $sel = ($cap['kva'] == $task['capacity_after']) ? "selected" : "";
                        echo "<option value='" . $cap['kva'] . "' $sel>" . $cap['kva'] . " kVA</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">The capacity the transformer is being upgraded to.</span>
            </div>

            <div class="form-row">
                <label for="desc">Description</label>
                <textarea id="desc" name="description" rows="4"><?php echo htmlspecialchars($task['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <label for="status">Status <span class="req">*</span></label>
                <select id="status" name="status_id">
                    <?php
                    $sts = $conn->query("SELECT status_id, status_name FROM statuses ORDER BY status_id");
                    while ($s = $sts->fetch_assoc()) {
                        $sel = ($s['status_id'] == $task['status_id']) ? "selected" : "";
                        echo "<option value='" . $s['status_id'] . "' $sel>"
                             . htmlspecialchars($s['status_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-row">
                <label for="assigned">Assigned To</label>
                <select id="assigned" name="assigned_to">
                    <option value="0">-- Unassigned --</option>
                    <?php
                    $us = $conn->query("SELECT user_id, full_name FROM users
                                        WHERE is_deleted = 0 ORDER BY full_name");
                    while ($u = $us->fetch_assoc()) {
                        $sel = ($u['user_id'] == $task['assigned_to']) ? "selected" : "";
                        echo "<option value='" . $u['user_id'] . "' $sel>"
                             . htmlspecialchars($u['full_name']) . "</option>";
                    }
                    ?>
                </select>
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
                <a href="tasks.php" class="action-link cancel-link">Back to jobs</a>
            </div>
        </form>
    </div>
</div>

<script>
var needsCapacity    = <?php echo json_encode($needsCap); ?>;
var subNeedsCapacity = <?php echo json_encode($subNeedsCap); ?>;

function updateForm() {
    var typeId  = document.getElementById('type').value;
    var options = document.querySelectorAll('.subtype-option');
    var shown   = 0;

    for (var i = 0; i < options.length; i++) {
        if (typeId !== '' && options[i].getAttribute('data-type') === typeId) {
            options[i].style.display = 'block';
            shown++;
        } else {
            options[i].style.display = 'none';
        }
    }

    document.getElementById('subtype-row').style.display = (shown > 0) ? 'block' : 'none';

    var wantCapacity = (typeId !== '' && needsCapacity[typeId] == 1);

    for (var j = 0; j < options.length; j++) {
        var box = options[j].querySelector('input');
        if (options[j].style.display !== 'none' && box.checked
            && subNeedsCapacity[box.value] == 1) {
            wantCapacity = true;
        }
    }

    document.getElementById('capacity-row').style.display = wantCapacity ? 'block' : 'none';
}

document.getElementById('type').addEventListener('change', updateForm);

var boxes = document.querySelectorAll('.subtype-option input');
for (var k = 0; k < boxes.length; k++) {
    boxes[k].addEventListener('change', updateForm);
}

updateForm();
</script>

<?php include 'footer.php'; ?>