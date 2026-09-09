<?php
include 'auth.php';
include 'db.php';

$pageTitle = "Tasks";
$pageDesc  = "Transformer upgrades and related field jobs. Check the assigned worker before starting a task.";
$activeNav = "tasks";
include 'header.php';

// status name -> badge class used by style.css
$badgeMap = array(
    'Pending'   => 'badge-pending',
    'Created'   => 'badge-created',
    'Completed' => 'badge-completed',
    'Cancelled' => 'badge-cancelled'
);

// Which status is being shown? 0 means all of them.
$filterStatus = isset($_GET['status']) ? (int)$_GET['status'] : 0;

// Counts for the summary tiles. These always show the full totals,
// so a worker can see every status even while one is filtered.
$counts = array();
$res = $conn->query("SELECT s.status_id, s.status_name, COUNT(t.task_id) AS n
                     FROM statuses s
                     LEFT JOIN tasks t ON t.status_id = s.status_id AND t.is_deleted = 0
                     GROUP BY s.status_id, s.status_name
                     ORDER BY s.status_id");
while ($r = $res->fetch_assoc()) {
    $counts[] = $r;
}
?>

<div class="stats">
<?php foreach ($counts as $c) {
        $sid    = (int)$c['status_id'];
        $active = ($filterStatus === $sid) ? ' stat-active' : ''; ?>
    <a class="stat stat-link<?php echo $active; ?>"
       href="tasks.php?status=<?php echo $sid; ?>">
        <span class="stat-label"><?php echo htmlspecialchars($c['status_name']); ?></span>
        <span class="stat-value"><?php echo $c['n']; ?></span>
    </a>
<?php } ?>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Task Register</h2>
        <span class="panel-note">
            <?php if ($filterStatus > 0) { ?>
                <a href="tasks.php" class="action-link">Show all jobs</a>
            <?php } else { ?>
                Newest first
            <?php } ?>
        </span>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>PL/NO</th>
                    <th>S/N</th>
                    <th>CSC</th>
                    <th>Work Type</th>
                    <th>Work Details</th>
                    <th>Description</th>
                    <th class="num">Est. Cost</th>
                    <th>Capacity Change</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $where = "WHERE t.is_deleted = 0";
            if ($filterStatus > 0) { $where .= " AND t.status_id = ?"; }

            $sql = "SELECT t.task_id, t.request_id, t.description, t.est_cost,
                           t.capacity_before, t.capacity_after, t.date_created,
                           tx.transformer_code,
                           csc.csc_name,
                           s.status_name,
                           tt.type_name, tt.type_code,
                           u.full_name AS assigned_name,
                           (SELECT GROUP_CONCAT(st.subtype_name
                                                ORDER BY st.subtype_id SEPARATOR ', ')
                              FROM task_subtype_links l
                              JOIN task_subtypes st ON l.subtype_id = st.subtype_id
                             WHERE l.task_id = t.task_id) AS subtype_list
                    FROM tasks t
                    LEFT JOIN transformers tx ON t.transformer_id = tx.transformer_id
                    LEFT JOIN csc            ON tx.csc_id = csc.csc_id
                    LEFT JOIN statuses s     ON t.status_id = s.status_id
                    LEFT JOIN task_types tt  ON t.type_id = tt.type_id
                    LEFT JOIN users u        ON t.assigned_to = u.user_id
                    $where
                    ORDER BY t.date_created DESC";

            $stmt = $conn->prepare($sql);
            if ($filterStatus > 0) { $stmt->bind_param("i", $filterStatus); }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<tr><td colspan='12'><div class='table-empty'>No jobs to show.</div></td></tr>";
            }

            while ($row = $result->fetch_assoc()) {

                $id         = $row['task_id'];
                $unassigned = ($row['assigned_name'] === null);
                $dash       = "<span class='field-hint'>&mdash;</span>";

                $badge = isset($badgeMap[$row['status_name']])
                       ? $badgeMap[$row['status_name']] : '';

                echo "<tr" . ($unassigned ? " class='row-unassigned'" : "") . ">";

                echo "<td data-label='PL/NO' class='code-cell'>"
                   . (trim((string)$row['request_id']) !== ''
                      ? htmlspecialchars($row['request_id']) : $dash) . "</td>";

                echo "<td data-label='S/N' class='code-cell'>"
                   . htmlspecialchars($row['transformer_code']) . "</td>";

                echo "<td data-label='CSC'>" . htmlspecialchars($row['csc_name']) . "</td>";

                echo "<td data-label='Work Type'>"
                   . (trim((string)$row['type_name']) !== ''
                      ? htmlspecialchars($row['type_name']) : $dash) . "</td>";

                echo "<td data-label='Work Details'"
                   . ($row['subtype_list'] ? " title='" . htmlspecialchars($row['subtype_list']) . "'" : "")
                   . ">" . ($row['subtype_list'] ? htmlspecialchars($row['subtype_list']) : $dash) . "</td>";

                echo "<td data-label='Description'>"
                   . (trim((string)$row['description']) !== ''
                      ? htmlspecialchars($row['description']) : $dash) . "</td>";

                echo "<td data-label='Est. Cost' class='num'>"
                   . ($row['est_cost'] !== null
                      ? 'Rs. ' . number_format((float)$row['est_cost'], 2) : $dash) . "</td>";

                echo "<td data-label='Capacity Change' class='cap-change'>";
                if ($row['capacity_after']) {
                    echo $row['capacity_before']
                       . " <span class='cap-arrow'>&rarr;</span> "
                       . "<span class='cap-new'>" . $row['capacity_after'] . " kVA</span>";
                } else {
                    echo $row['capacity_before'] ? $row['capacity_before'] . " kVA" : $dash;
                }
                echo "</td>";

                echo "<td data-label='Status'><span class='badge " . $badge . "'>"
                   . htmlspecialchars($row['status_name']) . "</span></td>";

                echo "<td data-label='Assigned To'>"
                   . ($unassigned ? "<span class='unassigned-tag'>UNASSIGNED</span>"
                                  : htmlspecialchars($row['assigned_name'])) . "</td>";

                echo "<td data-label='Created'>"
                   . date('d M Y', strtotime($row['date_created'])) . "</td>";

                echo "<td data-label='Action' class='actions'>
                        <a href='edit_task.php?id=$id' class='action-link'>Edit</a>
                        <span class='action-sep'>|</span>
                        <a href='delete_task.php?id=$id' class='action-link delete'>Delete</a>
                      </td>";

                echo "</tr>";
            }

            $stmt->close();
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>