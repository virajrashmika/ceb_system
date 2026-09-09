<?php
include 'auth.php';
include 'db.php';

$pageTitle = "Consumer Service Centres";
$pageDesc  = "Consumer service centres across the province.";
$activeNav = "csc";
include 'header.php';
?>

<div class="page-actions page-actions-right">
    <a href="add_csc.php" class="btn">+ Add CSC</a>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>CSC Register</h2>
        
    </div>

    <div class="table-wrap">
        <table class="data plain-table">
            <thead>
                <tr>
                    <th>CSC Name</th>
                    <th>Code</th>
                    <th>Area</th>
                    <th>Province</th>
                    <th class="num">Transformers</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT csc.csc_id, csc.csc_name, csc.csc_code,
                           areas.area_name, provinces.province_name,
                           COUNT(t.transformer_id) AS transformer_count
                    FROM csc
                    LEFT JOIN areas     ON csc.area_id = areas.area_id
                    LEFT JOIN provinces ON areas.province_id = provinces.province_id
                    LEFT JOIN transformers t ON t.csc_id = csc.csc_id AND t.is_deleted = 0
                    WHERE csc.is_deleted = 0
                    GROUP BY csc.csc_id, csc.csc_name, csc.csc_code,
                             areas.area_name, provinces.province_name
                    ORDER BY provinces.province_name, areas.area_name, csc.csc_name";

            $result = $conn->query($sql);

            if ($result->num_rows == 0) {
                echo "<tr><td colspan='6'><div class='table-empty'>No CSCs recorded yet.</div></td></tr>";
            }

            while ($row = $result->fetch_assoc()) {

                $id = $row['csc_id'];

                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['csc_name']) . "</td>";
                echo "<td class='code-cell'>" . htmlspecialchars($row['csc_code']) . "</td>";
                echo "<td>" . htmlspecialchars($row['area_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['province_name']) . "</td>";
                echo "<td class='num'>" . $row['transformer_count'] . "</td>";
                echo "<td class='actions'>
                        <a href='edit_csc.php?id=$id' class='action-link'>Edit</a>
                        <span class='action-sep'>|</span>
                        <a href='delete_csc.php?id=$id' class='action-link delete'>Delete</a>
                      </td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>