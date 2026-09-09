<?php
include 'auth.php';
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

$stmt = $conn->prepare("SELECT t.task_id, t.ref_no, tx.transformer_code
                        FROM tasks t
                        LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
                        WHERE t.task_id = ? AND t.is_deleted = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    die("Task not found. <a href='tasks.php'>Back</a>");
}

$pageTitle = "Delete Task";
$activeNav = "tasks";
include 'header.php';
?>

<div class="panel">
    <div class="panel-head"><h2>Confirm Delete</h2></div>
    <div class="panel-body">
        <p>Are you sure you want to delete
           <strong><?php echo htmlspecialchars($task['ref_no']); ?></strong>
           (<?php echo htmlspecialchars($task['transformer_code']); ?>)?</p>

        

        <form method="post">
            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
            <button type="submit" class="btn">Yes, delete</button>
            <a href="tasks.php" class="action-link cancel-link">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>