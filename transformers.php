<?php
include 'auth.php';
include 'db.php';

$pageTitle = "Transformers";
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

    <?php /* plain-table keeps the classic row layout. Without it section 20
             turns every row into a stacked tile, which is right for the wide
             event tables but wrong here: three short columns read better as
             one line per transformer, the way the CSC register does. */ ?>
    <div class="table-wrap">
        <table class="data plain-table">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Substation Name</th>
                    <th>Serial No</th>
                    <th>Type</th>
                    <th class="num">Capacity</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Build the filter conditions and the values that go with them.
            $where  = "WHERE " . txActiveSql('t');
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

            /* Only the three columns the register shows. The substation, CSC,
               area, details and event count were dropped from the list — they
               are all on the transformer's own page, one click away. Losing
               the event count also loses a COUNT(*) run once per row, which
               on 2,209 transformers was the slowest thing on the page. The
               filters work off t.csc_id directly, so neither join is needed. */
            $sql = "SELECT t.transformer_id, t.transformer_code, t.substation_name,
                           t.serial_no, t.transformer_type, t.capacity_kva
                    FROM transformers t
                    $where
                    ORDER BY t.transformer_code";

            $stmt = $conn->prepare($sql);
            if ($types !== "") { $stmt->bind_param($types, ...$params); }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 0) {
                echo "<tr><td colspan='5'><div class='table-empty'>No transformers match these filters.</div></td></tr>";
            }

            while ($row = $result->fetch_assoc()) {

                $id   = $row['transformer_id'];
                $dash = "<span class='field-hint'>&mdash;</span>";

                /* The whole row opens the transformer, the same as a row in
                   the event register. data-href is picked up by the handler
                   in footer.php; the S/N below stays a real link, so the
                   keyboard and "open in new tab" keep working and the row is
                   not something only a mouse can press. */
                echo "<tr data-href='transformer.php?id=$id'>";

                echo "<td data-label='S/N' class='code-cell'><a href='transformer.php?id=$id'>"
                   . htmlspecialchars($row['transformer_code']) . "</a></td>";

                echo "<td data-label='Substation Name'>"
                   . (trim((string)$row['substation_name']) !== ''
                      ? htmlspecialchars($row['substation_name']) : $dash) . "</td>";

                echo "<td data-label='Serial No' class='code-cell'>"
                   . (trim((string)$row['serial_no']) !== ''
                      ? htmlspecialchars($row['serial_no']) : $dash) . "</td>";

                echo "<td data-label='Type'>"
                   . (trim((string)$row['transformer_type']) !== ''
                      ? htmlspecialchars($row['transformer_type']) : $dash) . "</td>";

                echo "<td data-label='Capacity' class='num'>"
                   . ($row['capacity_kva'] ? $row['capacity_kva'] . " kVA" : $dash) . "</td>";

                echo "</tr>";
            }

            $stmt->close();
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>