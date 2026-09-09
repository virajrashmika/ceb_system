<?php
include 'auth.php';
include 'db.php';

$type = isset($_GET['type']) ? $_GET['type'] : 'transformer';
$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;

if ($type !== 'transformer' && $type !== 'task') { $type = 'transformer'; }

$label = '';

if ($type === 'transformer') {

    $q = $conn->prepare("SELECT transformer_code FROM transformers WHERE transformer_id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    $label = $r ? $r['transformer_code'] : '';

    // Every change to this transformer, and to any of its jobs.
    $stmt = $conn->prepare(
        "SELECT l.record_type, l.record_id, l.field_name,
                l.old_value, l.new_value, l.changed_at,
                u.full_name,
                tk.request_id
         FROM change_log l
         LEFT JOIN users u  ON l.changed_by = u.user_id
         LEFT JOIN tasks tk ON l.record_type = 'task' AND l.record_id = tk.task_id
         WHERE (l.record_type = 'transformer' AND l.record_id = ?)
            OR (l.record_type = 'task' AND l.record_id IN
                 (SELECT task_id FROM tasks WHERE transformer_id = ?))
         ORDER BY l.changed_at DESC, l.log_id DESC"
    );
    $stmt->bind_param("ii", $id, $id);

} else {

    $q = $conn->prepare("SELECT request_id FROM tasks WHERE task_id = ?");
    $q->bind_param("i", $id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $q->close();
    $label = $r ? $r['request_id'] : '';

    $stmt = $conn->prepare(
        "SELECT l.record_type, l.record_id, l.field_name,
                l.old_value, l.new_value, l.changed_at,
                u.full_name,
                tk.request_id
         FROM change_log l
         LEFT JOIN users u  ON l.changed_by = u.user_id
         LEFT JOIN tasks tk ON l.record_id = tk.task_id
         WHERE l.record_type = 'task' AND l.record_id = ?
         ORDER BY l.changed_at DESC, l.log_id DESC"
    );
    $stmt->bind_param("i", $id);
}

if ($label === '') {
    die("Record not found. <a href='search.php'>Back to search</a>");
}

$pageTitle    = "Edit History";
$pageDesc     = ($type === 'transformer' ? "Transformer " : "Job ") . $label;
$activeNav    = ($type === 'transformer') ? "transformers" : "tasks";
$showBack     = true;
$backFallback = 'search.php';
include 'header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Edit History</h2>
        <span class="panel-note">
            <?php echo ($type === 'transformer')
                       ? 'The transformer and all its jobs &mdash; newest first'
                       : 'Newest first'; ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Record</th>
                    <th>Field</th>
                    <th>Changed From</th>
                    <th>Changed To</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $stmt->execute();
            $rows = $stmt->get_result();

            if ($rows->num_rows == 0) {
                echo "<tr><td colspan='6'><div class='table-empty'>"
                   . "No changes have been recorded yet.</div></td></tr>";
            }

            $dash = "<span class='field-hint'>&mdash;</span>";

            while ($r = $rows->fetch_assoc()) {

                // What does this row belong to?
                if ($r['record_type'] === 'transformer') {
                    $what = "Transformer";
                } else {
                    $what = trim((string)$r['request_id']) !== ''
                          ? "Job " . htmlspecialchars($r['request_id'])
                          : "Job #" . (int)$r['record_id'];
                }

                echo "<tr>";

                echo "<td data-label='When'>"
                   . date('d M Y, H:i', strtotime($r['changed_at'])) . "</td>";

                echo "<td data-label='Record'>" . $what . "</td>";

                echo "<td data-label='Field'><strong>"
                   . htmlspecialchars($r['field_name']) . "</strong></td>";

                echo "<td data-label='Changed From'>"
                   . (trim((string)$r['old_value']) !== ''
                      ? htmlspecialchars($r['old_value']) : $dash) . "</td>";

                echo "<td data-label='Changed To'>"
                   . (trim((string)$r['new_value']) !== ''
                      ? htmlspecialchars($r['new_value']) : $dash) . "</td>";

                echo "<td data-label='By'>"
                   . ($r['full_name'] ? htmlspecialchars($r['full_name']) : $dash) . "</td>";

                echo "</tr>";
            }

            $stmt->close();
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>