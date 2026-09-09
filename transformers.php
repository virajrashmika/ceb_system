<?php
include 'auth.php';
include 'db.php';

$pageTitle = "Transformers";
$pageDesc  = "Transformers registered across Uva Province.";
$activeNav = "transformers";
include 'header.php';

// ---- filters -------------------------------------------------------
$filterCsc = isset($_GET['csc']) ? (int)$_GET['csc'] : 0;
$filterKva = isset($_GET['kva']) ? (int)$_GET['kva'] : 0;
?>

<div class="panel">
    <div class="panel-head">
        <h2>Transformer Register</h2>
        <span class="panel-note">Sorted by S/N</span>
    </div>

    <div class="toolbar">
        <form method="get" class="search-box">

            <div class="filter-group">
                <label for="fcsc">CSC</label>
                <select id="fcsc" name="csc">
                    <option value="0">All CSCs</option>
                    <?php
                    $cscs = $conn->query("SELECT csc.csc_id, csc.csc_name, csc.csc_code,
                                                 areas.area_name
                                          FROM csc
                                          LEFT JOIN areas ON csc.area_id = areas.area_id
                                          WHERE csc.is_deleted = 0
                                          ORDER BY areas.area_name, csc.csc_name");
                    while ($c = $cscs->fetch_assoc()) {
                        $sel = ($filterCsc == $c['csc_id']) ? " selected" : "";
                        echo "<option value='" . (int)$c['csc_id'] . "'" . $sel . ">"
                             . htmlspecialchars($c['area_name'] . " — " . $c['csc_name']
                                                . " (" . $c['csc_code'] . ")")
                             . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="fkva">Capacity</label>
                <select id="fkva" name="kva">
                    <option value="0">All capacities</option>
                    <?php
                    $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                    while ($k = $caps->fetch_assoc()) {
                        $sel = ($filterKva == $k['kva']) ? " selected" : "";
                        echo "<option value='" . (int)$k['kva'] . "'" . $sel . ">"
                             . (int)$k['kva'] . " kVA</option>";
                    }
                    ?>
                </select>
            </div>

            <button type="submit" class="btn">Apply</button>

            <?php if ($filterCsc > 0 || $filterKva > 0) { ?>
                <a href="transformers.php" class="action-link">Clear</a>
            <?php } ?>
        </form>
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
                    <th class="num">Proposals</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Build the filter conditions and the values that go with them.
            $where  = "WHERE t.is_deleted = 0";
            $types  = "";
            $params = array();

            if ($filterCsc > 0) {
                $where   .= " AND t.csc_id = ?";
                $types   .= "i";
                $params[] = $filterCsc;
            }

            if ($filterKva > 0) {
                $where   .= " AND t.capacity_kva = ?";
                $types   .= "i";
                $params[] = $filterKva;
            }

            $sql = "SELECT t.transformer_id, t.transformer_code, t.substation_name,
                           t.capacity_kva, t.serial_no, t.details,
                           csc.csc_name, areas.area_name,
                           (SELECT COUNT(*) FROM tasks x
                             WHERE x.transformer_id = t.transformer_id
                               AND x.is_deleted = 0) AS job_count
                    FROM transformers t
                    LEFT JOIN csc   ON t.csc_id = csc.csc_id
                    LEFT JOIN areas ON csc.area_id = areas.area_id
                    $where
                    ORDER BY t.transformer_code";

            $stmt = $conn->prepare($sql);
            if ($types !== "") { $stmt->bind_param($types, ...$params); }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<tr><td colspan='8'><div class='table-empty'>No transformers match these filters.</div></td></tr>";
            }

            while ($row = $result->fetch_assoc()) {

                $id   = $row['transformer_id'];
                $dash = "<span class='field-hint'>&mdash;</span>";

                echo "<tr>";

                echo "<td data-label='S/N' class='code-cell'><a href='transformer.php?id=$id'>"
                   . htmlspecialchars($row['transformer_code']) . "</a></td>";

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

                echo "<td data-label='Proposals' class='num'>" . (int)$row['job_count'] . "</td>";

                echo "</tr>";
            }

            $stmt->close();
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>