<?php
include 'auth.php';
include 'require_staff.php';
include 'require_admin.php';
include 'db.php';

$message = "";
$isError = true;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id      = (int)$_POST['csc_id'];
    $name    = trim($_POST['csc_name']);
    $code    = trim($_POST['csc_code']);
    $area_id = (int)$_POST['area_id'];

    if ($name == "" || $code == "" || $area_id == 0) {

        $message = "Please fill all fields.";

    } else {

        // Is another record already using this name or code?
        $stmt = $conn->prepare("SELECT csc_name, csc_code FROM csc
                                WHERE csc_id != ? AND (csc_name = ? OR csc_code = ?)");
        $stmt->bind_param("iss", $id, $name, $code);
        $stmt->execute();
        $clash = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($clash) {

            if ($clash['csc_name'] === $name) {
                $message = "That CSC name is already used by another record.";
            } else {
                $message = "That CSC code is already used by another record.";
            }

        } else {

            $stmt = $conn->prepare("UPDATE csc SET csc_name = ?, csc_code = ?, area_id = ?
                                    WHERE csc_id = ?");
            $stmt->bind_param("ssii", $name, $code, $area_id, $id);
            $stmt->execute();
            $stmt->close();

            $message = "CSC updated.";
            $isError = false;
        }
    }
}

// Load current values
$stmt = $conn->prepare("SELECT csc_id, csc_name, csc_code, area_id FROM csc
                        WHERE csc_id = ? AND is_deleted = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$csc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$csc) {
    die("CSC not found. <a href='csc.php'>Back</a>");
}

$pageTitle = "Edit CSC";
$activeNav = "csc";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2><?php echo htmlspecialchars($csc['csc_name']); ?></h2>
        <span class="panel-note"><?php echo htmlspecialchars($csc['csc_code']); ?></span>
    </div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="csc_id" value="<?php echo $csc['csc_id']; ?>">

            <div class="form-row">
                <label for="cname">CSC Name <span class="req">*</span></label>
                <input type="text" id="cname" name="csc_name"
                       value="<?php echo htmlspecialchars($csc['csc_name']); ?>">
            </div>

            <div class="form-row">
                <label for="ccode">CSC Code <span class="req">*</span></label>
                <input type="text" id="ccode" name="csc_code" maxlength="10"
                       value="<?php echo htmlspecialchars($csc['csc_code']); ?>">
                <span class="field-hint">Changing this does not change transformer S/Ns already recorded.</span>
            </div>

            <div class="form-row">
                <label for="area">Area <span class="req">*</span></label>
                <select id="area" name="area_id">
                    <?php
                    $areas = $conn->query("SELECT areas.area_id, areas.area_name, provinces.province_name
                                           FROM areas
                                           LEFT JOIN provinces ON areas.province_id = provinces.province_id
                                           WHERE areas.is_deleted = 0
                                           ORDER BY provinces.province_name, areas.area_name");
                    while ($a = $areas->fetch_assoc()) {
                        $sel = ($a['area_id'] == $csc['area_id']) ? "selected" : "";
                        echo "<option value='" . $a['area_id'] . "' $sel>"
                             . htmlspecialchars($a['province_name'] . " — " . $a['area_name'])
                             . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Update</button>
                <a href="csc.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>