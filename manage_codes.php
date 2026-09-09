<?php
include 'auth.php';
include 'db.php';
include 'require_admin.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['status_desc'])) {
        foreach ($_POST['status_desc'] as $id => $desc) {
            $id   = (int)$id;
            $desc = trim($desc);
            if ($desc === '') { $desc = null; }
            $stmt = $conn->prepare("UPDATE statuses SET description = ? WHERE status_id = ?");
            $stmt->bind_param("si", $desc, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (isset($_POST['type_desc'])) {
        foreach ($_POST['type_desc'] as $id => $desc) {
            $id   = (int)$id;
            $desc = trim($desc);
            if ($desc === '') { $desc = null; }
            $stmt = $conn->prepare("UPDATE task_types SET description = ? WHERE type_id = ?");
            $stmt->bind_param("si", $desc, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    $message = "Descriptions saved.";
}

$pageTitle = "Manage Codes";
$pageDesc  = "Plain-English meanings for status codes and task types. Fill these in as the office confirms them.";
$activeNav = "admin";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <p><b><?php echo htmlspecialchars($message); ?></b></p>
<?php } ?>

<form method="post">

    <div class="panel">
        <div class="panel-head">
            <h2>Status Codes</h2>
            <span class="panel-note">Leave blank if not yet confirmed</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Code</th><th>Description</th></tr>
                </thead>
                <tbody>
                <?php
                $res = $conn->query("SELECT status_id, status_name, description
                                     FROM statuses ORDER BY status_id");
                while ($row = $res->fetch_assoc()) {
                ?>
                    <tr>
                        <td class="code-cell"><?php echo htmlspecialchars($row['status_name']); ?></td>
                        <td>
                            <input type="text" size="60" maxlength="255"
                                   name="status_desc[<?php echo (int)$row['status_id']; ?>]"
                                   value="<?php echo htmlspecialchars($row['description']); ?>">
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Task Types</h2>
            <span class="panel-note">Leave blank if not yet confirmed</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Code</th><th>Description</th></tr>
                </thead>
                <tbody>
                <?php
                $res = $conn->query("SELECT type_id, type_name, description
                                     FROM task_types ORDER BY type_id");
                while ($row = $res->fetch_assoc()) {
                ?>
                    <tr>
                        <td class="code-cell"><?php echo htmlspecialchars($row['type_name']); ?></td>
                        <td>
                            <input type="text" size="60" maxlength="255"
                                   name="type_desc[<?php echo (int)$row['type_id']; ?>]"
                                   value="<?php echo htmlspecialchars($row['description']); ?>">
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <p><button type="submit" class="btn">Save Descriptions</button></p>

</form>

<?php include 'footer.php'; ?>