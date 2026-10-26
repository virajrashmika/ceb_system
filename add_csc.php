<?php
include 'auth.php';
$needArea = 'csc';
include 'require_staff.php';
include 'require_admin.php';
include 'db.php';

$message   = "";
$isError   = true;
$restoreId = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name    = trim($_POST['csc_name']);
    $code    = trim($_POST['csc_code']);
    $area_id = (int)$_POST['area_id'];

    // --- Restore was confirmed -------------------------------------------
    if (isset($_POST['restore_id']) && (int)$_POST['restore_id'] > 0) {

        $rid = (int)$_POST['restore_id'];

        $stmt = $conn->prepare("UPDATE csc
                                SET is_deleted = 0, csc_name = ?, csc_code = ?, area_id = ?
                                WHERE csc_id = ?");
        $stmt->bind_param("ssii", $name, $code, $area_id, $rid);
        $stmt->execute();
        $stmt->close();

        $message = $name . " was restored with its transformers.";
        $isError = false;

    } elseif ($name == "" || $code == "" || $area_id == 0) {

        $message = "Please fill all fields.";

    } else {

        // 1. Does an ACTIVE record already use this name or code?
        $stmt = $conn->prepare("SELECT csc_name, csc_code FROM csc
                                WHERE is_deleted = 0 AND (csc_name = ? OR csc_code = ?)");
        $stmt->bind_param("ss", $name, $code);
        $stmt->execute();
        $active = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($active) {

            if ($active['csc_name'] === $name) {
                $message = "That CSC name already exists.";
            } else {
                $message = "That CSC code already exists.";
            }

        } else {

            // 2. Does a DELETED record use this name or code?
            $stmt = $conn->prepare("SELECT csc_id, csc_name, csc_code FROM csc
                                    WHERE is_deleted = 1 AND (csc_name = ? OR csc_code = ?)
                                    LIMIT 1");
            $stmt->bind_param("ss", $name, $code);
            $stmt->execute();
            $deleted = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($deleted) {

                $restoreId = $deleted['csc_id'];
                $message   = $deleted['csc_name'] . " (" . $deleted['csc_code']
                           . ") already exists but was deleted earlier.";

            } else {

                // 3. Free — insert it.
                $stmt = $conn->prepare("INSERT INTO csc (csc_name, csc_code, area_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $name, $code, $area_id);
                $stmt->execute();
                $stmt->close();

                $message = $name . " added successfully.";
                $isError = false;
            }
        }
    }
}
$showBack     = true;
$backFallback = 'csc.php';    // transformers.php / tasks.php / index.php
$pageTitle = "Add CSC";
$activeNav = "csc";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>

        <?php if ($restoreId > 0) { ?>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="restore_id" value="<?php echo $restoreId; ?>">
                <input type="hidden" name="csc_name"   value="<?php echo htmlspecialchars($_POST['csc_name']); ?>">
                <input type="hidden" name="csc_code"   value="<?php echo htmlspecialchars($_POST['csc_code']); ?>">
                <input type="hidden" name="area_id"    value="<?php echo (int)$_POST['area_id']; ?>">
                <button type="submit" class="btn">Restore this CSC</button>
            </form>
        <?php } ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>New Consumer Service Centre</h2></div>
    <div class="panel-body">
        <form method="post">
            <div class="form-row">
                <label for="cname">CSC Name <span class="req">*</span></label>
                <input type="text" id="cname" name="csc_name">
            </div>

            <div class="form-row">
                <label for="ccode">CSC Code <span class="req">*</span></label>
                <input type="text" id="ccode" name="csc_code" maxlength="10">
                </div>

            <div class="form-row">
                <label for="area">Area <span class="req">*</span></label>
                <select id="area" name="area_id">
                    <option value="">-- Select Area --</option>
                    <?php
                    $areas = $conn->query("SELECT areas.area_id, areas.area_name, provinces.province_name
                                           FROM areas
                                           LEFT JOIN provinces ON areas.province_id = provinces.province_id
                                           WHERE areas.is_deleted = 0
                                           ORDER BY provinces.province_name, areas.area_name");
                    while ($a = $areas->fetch_assoc()) {
                        echo "<option value='" . $a['area_id'] . "'>"
                             . htmlspecialchars($a['province_name'] . " — " . $a['area_name'])
                             . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="csc.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>