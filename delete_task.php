<?php
include 'auth.php';
$needArea = 'events';
include 'require_staff.php';   // Guest and Worker may read events, not change them
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['task_id'];

    $stmt = $conn->prepare("UPDATE tasks SET is_deleted = 1 WHERE task_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: tasks.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* The proposal number and the transformer are what name an event on paper,
   so they are what the confirmation asks about. It used to quote the
   system's own reference number, which nobody outside this screen ever
   saw and which no longer exists. */
$stmt = $conn->prepare("SELECT t.task_id, t.request_id, t.job_no,
                               tx.transformer_code, ty.type_name
                        FROM tasks t
                        LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
                        LEFT JOIN task_types ty   ON t.type_id = ty.type_id
                        WHERE t.task_id = ? AND t.is_deleted = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    die("Event not found. <a href='tasks.php'>Back</a>");
}

$pageTitle = "Delete Event";
$activeNav = "tasks";
include 'header.php';
?>

<div class="panel">
    <div class="panel-head"><h2>Confirm Delete</h2></div>
    <div class="panel-body">
        <p>Are you sure you want to delete this event?</p>

        <div class="table-wrap">
            <table class="data detail-table">
                <tbody>
                    <tr><th style="width:42%">Proposal No</th>
                        <td><?php echo (trim((string)$task['request_id']) !== '')
                              ? "<span class='code-cell'>" . htmlspecialchars($task['request_id']) . "</span>"
                              : "<span class='field-hint'>Not recorded</span>"; ?></td></tr>
                    <tr><th>Transformer</th>
                        <td><span class="code-cell"><?php echo htmlspecialchars($task['transformer_code']); ?></span></td></tr>
                    <tr><th>Work Type</th>
                        <td><?php echo (trim((string)$task['type_name']) !== '')
                              ? htmlspecialchars($task['type_name'])
                              : "<span class='field-hint'>Not decided yet</span>"; ?></td></tr>
                    <tr><th>Job No</th>
                        <td><?php echo (trim((string)$task['job_no']) !== '')
                              ? "<span class='code-cell'>" . htmlspecialchars($task['job_no']) . "</span>"
                              : "<span class='field-hint'>Not recorded</span>"; ?></td></tr>
                </tbody>
            </table>
        </div>

        <p class="field-hint">The event is hidden, not erased &mdash; its row stays in the
           database and an administrator can bring it back.</p>

        <form method="post">
            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
            <button type="submit" class="btn">Yes, delete</button>
            <a href="tasks.php" class="action-link cancel-link">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>