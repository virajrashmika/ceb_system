<?php
/* ---------------------------------------------------------------------------
   change.php — one save from the Edit History, on a page of its own.

   history.php lists one row per save. Clicking a row comes here with the
   log_id of the first field that save wrote; every other field written in
   the same save shares its record, author and second, so they are found
   again by that key, exactly as history.php groups them.
   --------------------------------------------------------------------------- */
include 'auth.php';
include 'db.php';
include_once 'functions.php';   // eventLabel(), renderDetailColumns()

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT l.log_id, l.record_type, l.record_id, l.changed_at, l.changed_by,
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
                               AND l.record_id = txd.transformer_id
     WHERE l.log_id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$h = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$h) {
    die("Change not found. <a href='history.php'>Back</a>");
}

// Every field written in the same save.
$stmt = $conn->prepare(
    "SELECT field_name, old_value, new_value
     FROM change_log
     WHERE record_type = ? AND record_id = ? AND changed_at = ? AND changed_by <=> ?
     ORDER BY log_id"
);
$stmt->bind_param("sisi", $h['record_type'], $h['record_id'],
                          $h['changed_at'], $h['changed_by']);
$stmt->execute();
$changes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$isTask = ($h['record_type'] === 'task');
$txCode = trim((string)$h['tx_code']);

$who = ($h['full_name'] !== null && trim($h['full_name']) !== '')
     ? $h['full_name'] : 'unknown user';

// The header carries the S/N, when and who, so the page itself only shows the fields.
$pageTitle    = 'Change on ' . ($txCode !== '' ? $txCode : ($isTask ? 'event' : 'transformer'));
$pageDesc     = date('d M Y, h:i A', strtotime($h['changed_at'])) . ' by ' . $who;
$activeNav    = $isTask ? "tasks" : "transformers";
$showBack     = true;
$backFallback = $h['tx_id'] ? 'history.php?type=transformer&id=' . (int)$h['tx_id'] : 'history.php';

include 'header.php';

$empty  = "<span class='field-hint'>blank</span>";
$evDate = '';

if ($isTask) {
    $evDate = $h['task_date']    ? date('d M Y', strtotime($h['task_date']))
            : ($h['task_created'] ? date('d M Y', strtotime($h['task_created'])) : '');
    $record = "Event <span class='code-cell'>" . htmlspecialchars(eventLabel($h)) . "</span>";
} else {
    $record = 'Transformer';
}

$n = count($changes);

// One row per changed field: old value → new value.
$fieldRows = array();
foreach ($changes as $c) {
    $old = trim((string)$c['old_value']) !== '' ? htmlspecialchars($c['old_value']) : $empty;
    $new = trim((string)$c['new_value']) !== '' ? htmlspecialchars($c['new_value']) : $empty;
    $label = $c['field_name'];
    // Two edits to the same field in one save would collide as array keys.
    while (array_key_exists($label, $fieldRows)) { $label .= ' '; }
    $fieldRows[$label] = "<span class='hist-old'>$old</span>"
                       . "<span class='hist-arrow'>&rarr;</span>"
                       . "<span class='hist-new'>$new</span>";
}

$href = $isTask ? 'event.php?id=' . (int)$h['record_id']
                : ($h['tx_id'] ? 'transformer.php?id=' . (int)$h['tx_id'] : '');
?>

<div class="panel">
    <div class="panel-head">
        <h2>What Changed &mdash; <?php echo $record; ?></h2>
        <span class="panel-note"><?php
            echo ($isTask && $evDate !== '' ? 'Event date ' . $evDate . ' &middot; ' : '')
               . $n . ' change' . ($n === 1 ? '' : 's')
               . ' &middot; old value &rarr; new value'; ?></span>
    </div>

    <?php if ($fieldRows) {
        renderDetailColumns($fieldRows);
    } else { ?>
        <div class="table-empty">No fields recorded for this save.</div>
    <?php } ?>

    <?php if ($href !== '') { ?>
    <div class="panel-body">
        <a href="<?php echo $href; ?>" class="action-link"><?php
            echo $isTask ? 'Open event' : 'Open transformer'; ?></a>
        <?php if ($h['tx_id']) { ?>
            <span class="action-sep">|</span>
            <a href="history.php?type=transformer&amp;id=<?php echo (int)$h['tx_id']; ?>"
               class="action-link">Transformer history</a>
        <?php } ?>
    </div>
    <?php } ?>
</div>

<?php include 'footer.php'; ?>
