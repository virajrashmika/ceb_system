<?php
include 'auth.php';
include 'db.php';
include 'functions.php';

$keyword = isset($_GET['q']) ? trim($_GET['q']) : "";

$pageTitle = "Search";
$pageDesc  = "Search by S/N, serial no, CSC, area or details. One exact match opens the full record.";
$activeNav = "search";
include 'header.php';
?>

<div class="panel">
    <div class="panel-body">
        <form method="get">
            <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>"
                   placeholder="e.g. UBB 105, T355468846, or Badulla" size="40">
            <button type="submit" class="btn">Search</button>
        </form>
    </div>
</div>

<?php
if ($keyword != "") {

    $like = "%" . $keyword . "%";

    $stmt = $conn->prepare(
        "SELECT t.transformer_id, t.transformer_code, t.substation_name,
                t.capacity_kva, t.serial_no, t.details,
                csc.csc_name, areas.area_name,
                (SELECT COUNT(*) FROM tasks x
                  WHERE x.transformer_id = t.transformer_id
                    AND x.is_deleted = 0) AS task_count,
                (SELECT COUNT(*) FROM tasks x
                  JOIN statuses s2 ON x.status_id = s2.status_id
                  WHERE x.transformer_id = t.transformer_id
                    AND x.is_deleted = 0
                    AND s2.status_name NOT IN ('Completed','Cancelled')) AS open_count
         FROM transformers t
         LEFT JOIN csc   ON t.csc_id = csc.csc_id
         LEFT JOIN areas ON csc.area_id = areas.area_id
         WHERE t.is_deleted = 0
           AND (t.transformer_code LIKE ?
             OR t.serial_no LIKE ?
             OR t.substation_name LIKE ?
             OR t.details LIKE ?
             OR csc.csc_name LIKE ?
             OR areas.area_name LIKE ?)
         ORDER BY t.transformer_code"
    );
    $stmt->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $found = count($rows);

    if ($found == 0) {
        echo "<div class='message message-info'>No transformers found for \""
           . htmlspecialchars($keyword) . "\".</div>";
    }

    // ------------------------------------------------------------------
    // EXACTLY ONE MATCH -> show the full record with its job history
    // ------------------------------------------------------------------
    elseif ($found == 1) {

        $tx  = $rows[0];
        $tid = $tx['transformer_id'];
?>

    <div class="panel">
        <div class="panel-head">
            <h2><?php echo htmlspecialchars($tx['transformer_code']); ?></h2>
            <span class="panel-note">
                <?php echo (int)$tx['task_count']; ?> job(s) on record
                <span class="action-sep">|</span>
                <a href="history.php?type=transformer&id=<?php echo $tid; ?>"
                   class="action-link">Edit History</a>
            </span>
        </div>

        <?php renderTransformerDetails($conn, $tid); ?>

        <div class="panel-body">
            <a href="edit_transformer.php?id=<?php echo $tid; ?>" class="action-link">Edit transformer</a>
            <span class="action-sep">|</span>
            <a href="add_task.php" class="action-link">Add job</a>
            <span class="action-sep">|</span>
            <a href="delete_transformer.php?id=<?php echo $tid; ?>"
               class="action-link delete"
               onclick="return confirm('Delete <?php echo htmlspecialchars($tx['transformer_code']); ?>? Its proposals stay in the system but the transformer will no longer appear.');">Delete transformer</a>
        </div>
    </div>

<?php
        if ($tx['open_count'] > 0) {
            echo "<div class='message message-error'><strong>Work in progress.</strong> "
               . "This transformer has " . (int)$tx['open_count']
               . " job(s) not yet completed. Check below before starting new work.</div>";
        }
?>

    <div class="panel">
        <div class="panel-head">
            <h2>Job History</h2>
            <span class="panel-note">Newest first &mdash; hover for full text</span>
        </div>

        <?php renderTaskHistory($conn, $tid); ?>

    </div>

<?php
    }

    // ------------------------------------------------------------------
    // MANY MATCHES -> show the list
    // ------------------------------------------------------------------
    else {
?>

    <div class="panel">
        <div class="panel-head">
            <h2>Search Results</h2>
            <span class="panel-note"><?php echo $found; ?> transformer(s) found</span>
        </div>

        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Substation Name</th>
                        <th class="num">Capacity</th>
                        <th>Serial No</th>
                        <th>CSC</th>
                        <th>Area</th>
                        <th>Details</th>
                        <th>Jobs</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
<?php
        foreach ($rows as $row) {

            $id   = $row['transformer_id'];
            $dash = "<span class='field-hint'>&mdash;</span>";

            echo "<tr>";

            echo "<td data-label='S/N' class='code-cell'>"
               . htmlspecialchars($row['transformer_code']) . "</td>";

            echo "<td data-label='Substation Name'>"
               . (trim((string)$row['substation_name']) !== ''
                  ? htmlspecialchars($row['substation_name']) : $dash) . "</td>";

            echo "<td data-label='Capacity' class='num'>"
               . ($row['capacity_kva'] ? $row['capacity_kva'] . " kVA" : $dash) . "</td>";

            echo "<td data-label='Serial No' class='code-cell'>"
               . (trim((string)$row['serial_no']) !== ''
                  ? htmlspecialchars($row['serial_no']) : $dash) . "</td>";

            echo "<td data-label='CSC'>" . htmlspecialchars($row['csc_name']) . "</td>";
            echo "<td data-label='Area'>" . htmlspecialchars($row['area_name']) . "</td>";

            echo "<td data-label='Details'>"
               . (trim((string)$row['details']) !== ''
                  ? htmlspecialchars($row['details']) : $dash) . "</td>";

            echo "<td data-label='Jobs'>";
            if ($row['open_count'] > 0) {
                echo "<span class='badge badge-progress'>" . (int)$row['open_count'] . " OPEN</span>";
            } else {
                echo (int)$row['task_count'] . " done";
            }
            echo "</td>";

            echo "<td data-label='Action' class='actions'>
                    <a href='transformer.php?id=$id' class='action-link'>History</a>
                    <span class='action-sep'>|</span>
                    <a href='edit_transformer.php?id=$id' class='action-link'>Edit</a>
                  </td>";

            echo "</tr>";
        }
?>
                </tbody>
            </table>
        </div>
    </div>

<?php
    }
}
?>

<?php include 'footer.php'; ?>