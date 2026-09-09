<?php
/* Shared helper functions for the CEB System. */

/**
 * Build the next reference number for a work type.
 * Format: TYPE/YY/NNNN   e.g. TFA/26/0016
 * This number is internal — it is not shown on any page.
 */
function generateRefNo(mysqli $conn, string $typeCode) {

    $year   = date('y');
    $prefix = $typeCode . '/' . $year . '/';
    $like   = $prefix . '%';

    $stmt = $conn->prepare("SELECT ref_no FROM tasks
                            WHERE ref_no LIKE ?
                            ORDER BY ref_no DESC
                            LIMIT 1");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next = 1;

    if ($row) {
        $parts = explode('/', $row['ref_no']);
        $next  = (int)end($parts) + 1;
    }

    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}


/**
 * Record what changed on a record.
 * Only real differences are written, one row per field.
 */
function logChanges(mysqli $conn, string $recordType, int $recordId,
                    array $before, array $after, int $userId) {

    $stmt = $conn->prepare(
        "INSERT INTO change_log
         (record_type, record_id, field_name, old_value, new_value, changed_by, changed_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );

    foreach ($after as $field => $newVal) {

        $oldVal = isset($before[$field]) ? $before[$field] : null;

        // Treat null and empty string as the same thing.
        $o = ($oldVal === null) ? '' : trim((string)$oldVal);
        $n = ($newVal === null) ? '' : trim((string)$newVal);

        if ($o === $n) { continue; }

        $oStore = ($o === '') ? null : $o;
        $nStore = ($n === '') ? null : $n;

        $stmt->bind_param("sisssi",
            $recordType, $recordId, $field, $oStore, $nStore, $userId);
        $stmt->execute();
    }

    $stmt->close();
}


/**
 * Print the transformer detail table.
 * Used by search.php and transformer.php so both stay identical.
 */
function renderTransformerDetails(mysqli $conn, int $transformerId) {

    $stmt = $conn->prepare(
        "SELECT t.transformer_code, t.substation_name, t.capacity_kva,
                t.serial_no, t.details,
                csc.csc_name, csc.csc_code,
                areas.area_name, provinces.province_name
         FROM transformers t
         LEFT JOIN csc       ON t.csc_id = csc.csc_id
         LEFT JOIN areas     ON csc.area_id = areas.area_id
         LEFT JOIN provinces ON areas.province_id = provinces.province_id
         WHERE t.transformer_id = ? AND t.is_deleted = 0"
    );
    $stmt->bind_param("i", $transformerId);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tx) {
        echo "<div class='table-empty'>Transformer not found.</div>";
        return;
    }

    $blank = "<span class='field-hint'>Not recorded</span>";

    $rows = array(
        'S/N'             => "<span class='code-cell'>"
                             . htmlspecialchars($tx['transformer_code']) . "</span>",
        'Substation Name' => (trim((string)$tx['substation_name']) !== '')
                             ? htmlspecialchars($tx['substation_name']) : $blank,
        'Capacity'        => $tx['capacity_kva']
                             ? $tx['capacity_kva'] . " kVA" : $blank,
        'Serial No'       => (trim((string)$tx['serial_no']) !== '')
                             ? "<span class='code-cell'>"
                               . htmlspecialchars($tx['serial_no']) . "</span>"
                             : $blank,
        'CSC'             => htmlspecialchars($tx['csc_name']
                             . " (" . $tx['csc_code'] . ")"),
        'Area'            => htmlspecialchars($tx['area_name']),
        'Province'        => htmlspecialchars($tx['province_name']),
        'Details'         => (trim((string)$tx['details']) !== '')
                             ? htmlspecialchars($tx['details']) : $blank
    );

    echo "<div class='table-wrap'><table class='data detail-table'><tbody>";
    foreach ($rows as $label => $value) {
        echo "<tr><th style='width:35%'>" . $label . "</th>"
           . "<td>" . $value . "</td></tr>";
    }
    echo "</tbody></table></div>";
}


/**
 * Print the job history table — one row per proposal.
 * Used by search.php and transformer.php so both stay identical.
 */
function renderTaskHistory(mysqli $conn, int $transformerId,
                           string $emptyText = 'No proposals recorded for this transformer.') {

    $badgeMap = array(
        'Pending'   => 'badge-pending',
        'Created'   => 'badge-created',
        'Completed' => 'badge-completed',
        'Cancelled' => 'badge-cancelled'
    );

    $stmt = $conn->prepare(
        "SELECT t.task_id, t.request_id, t.job_no,
                t.capacity_before, t.capacity_after,
                t.date_created,
                ty.type_name, ty.description AS type_desc,
                s.status_name, s.description AS status_desc,
                u.full_name AS assigned_name,
                (SELECT GROUP_CONCAT(st.subtype_name
                                     ORDER BY st.subtype_id SEPARATOR ', ')
                   FROM task_subtype_links l
                   JOIN task_subtypes st ON l.subtype_id = st.subtype_id
                  WHERE l.task_id = t.task_id) AS subtype_list
         FROM tasks t
         LEFT JOIN task_types ty ON t.type_id = ty.type_id
         LEFT JOIN statuses s    ON t.status_id = s.status_id
         LEFT JOIN users u       ON t.assigned_to = u.user_id
         WHERE t.transformer_id = ? AND t.is_deleted = 0
         ORDER BY t.date_created DESC"
    );
    $stmt->bind_param("i", $transformerId);
    $stmt->execute();
    $jobs = $stmt->get_result();

    echo "<div class='table-wrap'>";
    echo "<table class='data'>";
    echo "<thead><tr>
            <th>Proposal No</th>
            <th>Date</th>
            <th>Work Type</th>
            <th>Work Details</th>
            <th>Job No</th>
            <th>Capacity Change</th>
            <th>Status</th>
            <th>Assigned To</th>
            <th>Action</th>
          </tr></thead><tbody>";

    if ($jobs->num_rows == 0) {
        echo "<tr><td colspan='9'><div class='table-empty'>"
           . htmlspecialchars($emptyText) . "</div></td></tr>";
    }

    while ($j = $jobs->fetch_assoc()) {

        $badge = isset($badgeMap[$j['status_name']]) ? $badgeMap[$j['status_name']] : '';
        $tid   = (int)$j['task_id'];
        $dash  = "<span class='field-hint'>&mdash;</span>";

        echo "<tr>";

        echo "<td data-label='Proposal No' class='code-cell'>"
           . (trim((string)$j['request_id']) !== ''
              ? htmlspecialchars($j['request_id']) : $dash) . "</td>";

        echo "<td data-label='Date'>" . date('d M Y', strtotime($j['date_created'])) . "</td>";

        echo "<td data-label='Work Type'"
           . ($j['type_desc'] ? " title='" . htmlspecialchars($j['type_desc']) . "'" : "")
           . ">" . (trim((string)$j['type_name']) !== ''
                    ? htmlspecialchars($j['type_name']) : $dash) . "</td>";

        echo "<td data-label='Work Details'"
           . ($j['subtype_list'] ? " title='" . htmlspecialchars($j['subtype_list']) . "'" : "")
           . ">" . ($j['subtype_list'] ? htmlspecialchars($j['subtype_list']) : $dash) . "</td>";

        echo "<td data-label='Job No' class='code-cell'>"
           . (trim((string)$j['job_no']) !== ''
              ? htmlspecialchars($j['job_no']) : $dash) . "</td>";

        echo "<td data-label='Capacity Change' class='cap-change'>";
        if ($j['capacity_after']) {
            echo $j['capacity_before'] . " <span class='cap-arrow'>&rarr;</span> "
               . "<span class='cap-new'>" . $j['capacity_after'] . " kVA</span>";
        } else {
            echo $j['capacity_before'] ? $j['capacity_before'] . " kVA" : $dash;
        }
        echo "</td>";

        echo "<td data-label='Status'><span class='badge " . $badge . "'"
           . ($j['status_desc'] ? " title='" . htmlspecialchars($j['status_desc']) . "'" : "")
           . ">" . htmlspecialchars($j['status_name']) . "</span></td>";

        echo "<td data-label='Assigned To'>" . ($j['assigned_name']
                ? htmlspecialchars($j['assigned_name'])
                : "<span class='unassigned-tag'>UNASSIGNED</span>") . "</td>";

        echo "<td data-label='Action' class='actions'>"
           . "<a href='edit_task.php?id=$tid' class='action-link'>Edit</a>"
           . "<span class='action-sep'>|</span>"
           . "<a href='history.php?type=task&id=$tid' class='action-link'>History</a></td>";

        echo "</tr>";
    }

    $stmt->close();

    echo "</tbody></table></div>";
}