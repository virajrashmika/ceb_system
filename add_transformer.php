<?php
include 'auth.php';
include 'require_staff.php';
include 'db.php';

$message   = "";
$isError   = true;
$restoreId = 0;

$oldNumber = "";
$oldName   = "";
$oldCsc    = 0;
$oldCap    = 0;
$oldSerial = "";
$oldLoc    = "";
$code      = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $csc_id   = (int)$_POST['csc_id'];
    $number   = trim($_POST['sn_number']);
    $subName  = trim($_POST['substation_name']);
    $capacity = (int)$_POST['capacity_kva'];
    $serial   = trim($_POST['serial_no']);
    $details = trim($_POST['details']);

    $oldNumber = $number;
    $oldName   = $subName;
    $oldCsc    = $csc_id;
    $oldCap    = $capacity;
    $oldSerial = $serial;
    $oldLoc    = $details;

    $serialVal = ($serial  !== '') ? $serial  : null;
    $nameVal   = ($subName !== '') ? $subName : null;

    // The prefix comes from the database, not from the browser. The box on
    // screen is only there so the worker can see what is being built.
    $cscCode = '';

    if ($csc_id > 0) {
        $stmt = $conn->prepare("SELECT csc_code FROM csc
                                WHERE csc_id = ? AND is_deleted = 0");
        $stmt->bind_param("i", $csc_id);
        $stmt->execute();
        $cscRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($cscRow) { $cscCode = $cscRow['csc_code']; }
    }

    // Build the full S/N: prefix, one space, the number padded to 3 digits.
    // Doing it here means every S/N in the register has the same shape.
    if ($cscCode !== '' && $number !== '' && ctype_digit($number)) {
        $code = $cscCode . ' ' . str_pad($number, 3, '0', STR_PAD_LEFT);
    }

    // --- Restore was confirmed -------------------------------------------
    if (isset($_POST['restore_id']) && (int)$_POST['restore_id'] > 0) {

        $rid = (int)$_POST['restore_id'];

        $stmt = $conn->prepare("UPDATE transformers
                                SET is_deleted = 0, csc_id = ?, capacity_kva = ?,
                                    substation_name = ?, serial_no = ?, details = ?
                                WHERE transformer_id = ?");
        $stmt->bind_param("iisssi", $csc_id, $capacity, $nameVal,
                          $serialVal, $details, $rid);
        $stmt->execute();
        $stmt->close();

        $message = "Transformer " . $code . " was restored with its history.";
        $isError = false;

        $oldNumber = ""; $oldName = ""; $oldCap = 0; $oldSerial = ""; $oldLoc = "";

    // --- Normal add -------------------------------------------------------
    } elseif ($csc_id == 0) {

        $message = "Please choose a CSC.";

    } elseif ($cscCode === '') {

        $message = "That CSC no longer exists.";

    } elseif ($number === '') {

        $message = "Please enter the S/N number.";

    } elseif (!ctype_digit($number)) {

        $message = "The S/N number should be digits only — the letters are added for you.";

    } else {

        $stmt = $conn->prepare("SELECT transformer_id, is_deleted FROM transformers
                                WHERE transformer_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing && $existing['is_deleted'] == 1) {

            $restoreId = $existing['transformer_id'];
            $message   = "S/N " . $code . " already exists but was deleted earlier.";

        } elseif ($existing) {

            $message = "That S/N already exists.";

        } else {

            $stmt = $conn->prepare("INSERT INTO transformers
                                    (transformer_code, substation_name, csc_id,
                                     capacity_kva, serial_no, details)
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiss", $code, $nameVal, $csc_id,
                              $capacity, $serialVal, $details);
            $stmt->execute();
            $stmt->close();

            $message = "Transformer " . $code . " added successfully.";
            $isError = false;

            $oldNumber = ""; $oldName = ""; $oldCap = 0; $oldSerial = ""; $oldLoc = "";
            // CSC stays selected so several can be added in a row.
        }
    }
}
$showBack     = true;
$backFallback = 'csc.php';    // transformers.php / tasks.php / index.php
$pageTitle = "Add Transformer";
$activeNav = "transformers";
include 'header.php';

// The next free number for each CSC. Deleted transformers are counted too —
// their S/N is still in the table, so reusing it would fail.
$nextNo = array();
$nRes = $conn->query(
    "SELECT csc_id,
            MAX(CAST(SUBSTRING_INDEX(transformer_code, ' ', -1) AS UNSIGNED)) AS maxno
     FROM transformers
     GROUP BY csc_id"
);
while ($n = $nRes->fetch_assoc()) {
    $nextNo[(int)$n['csc_id']] = (int)$n['maxno'] + 1;
}
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>

        <?php if ($restoreId > 0) { ?>
            <form method="post" style="margin-top:10px;">
                <input type="hidden" name="restore_id"   value="<?php echo $restoreId; ?>">
                <input type="hidden" name="sn_number"       value="<?php echo htmlspecialchars($oldNumber); ?>">
                <input type="hidden" name="substation_name" value="<?php echo htmlspecialchars($oldName); ?>">
                <input type="hidden" name="csc_id"       value="<?php echo (int)$oldCsc; ?>">
                <input type="hidden" name="capacity_kva" value="<?php echo (int)$oldCap; ?>">
                <input type="hidden" name="serial_no"    value="<?php echo htmlspecialchars($oldSerial); ?>">
                <input type="hidden" name="details"     value="<?php echo htmlspecialchars($oldLoc); ?>">
                <button type="submit" class="btn">Restore this transformer</button>
            </form>
        <?php } ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>New Transformer</h2></div>
    <div class="panel-body">
        <form method="post">

            <div class="form-row">
                <label for="csc">CSC <span class="req">*</span></label>
                <select id="csc" name="csc_id">
                    <option value="" data-code="">-- Select CSC --</option>
                    <?php
                    $cscs = $conn->query("SELECT csc.csc_id, csc.csc_name, csc.csc_code, areas.area_name
                                          FROM csc
                                          LEFT JOIN areas ON csc.area_id = areas.area_id
                                          WHERE csc.is_deleted = 0
                                          ORDER BY areas.area_name, csc.csc_name");
                    while ($c = $cscs->fetch_assoc()) {
                        $sel = ($c['csc_id'] == $oldCsc) ? " selected" : "";
                        echo "<option value='" . $c['csc_id'] . "'"
                             . " data-code='" . htmlspecialchars($c['csc_code']) . "'" . $sel . ">"
                             . htmlspecialchars($c['area_name'] . " — " . $c['csc_name'] . " (" . $c['csc_code'] . ")")
                             . "</option>";
                    }
                    ?>
                </select>
                <span class="field-hint">Choose this first — the S/N code and next number fill in below.</span>
            </div>

            <div class="form-row">
                <label for="snnum">S/N <span class="req">*</span></label>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="text" id="snprefix" readonly tabindex="-1"
                           style="width:90px; text-align:center; opacity:.7; cursor:not-allowed;"
                           value="">
                    <input type="text" id="snnum" name="sn_number" maxlength="6"
                           inputmode="numeric" style="flex:1;"
                           value="<?php echo htmlspecialchars($oldNumber); ?>">
                </div>
                <span class="field-hint">The code is set by the CSC. The next free number is filled in
                    for you &mdash; change it if the register says otherwise.</span>
            </div>

            <div class="form-row">
                <label for="sname">Substation Name</label>
                <input type="text" id="sname" name="substation_name" maxlength="120"
                       placeholder="Lower King Street"
                       value="<?php echo htmlspecialchars($oldName); ?>">
                <span class="field-hint">What the workers call this transformer. Leave blank if not known.</span>
            </div>

            <div class="form-row">
                <label for="cap">Capacity <span class="req">*</span></label>
                <select id="cap" name="capacity_kva">
                    <option value="">-- Select Capacity --</option>
                    <?php
                    $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                    while ($cap = $caps->fetch_assoc()) {
                        $sel = ($cap['kva'] == $oldCap) ? " selected" : "";
                        echo "<option value='" . $cap['kva'] . "'" . $sel . ">" . $cap['kva'] . " kVA</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-row">
                <label for="serial">Serial No</label>
                <input type="text" id="serial" name="serial_no" maxlength="50"
                       placeholder="T26U010030105"
                       value="<?php echo htmlspecialchars($oldSerial); ?>">
                <span class="field-hint">The physical unit's serial number. Leave blank for older transformers.</span>
            </div>

            <div class="form-row">
                <label for="loc">Details</label>
                <input type="text" id="loc" name="details"
                       value="<?php echo htmlspecialchars($oldLoc); ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="transformers.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {

    var cscSel    = document.getElementById('csc');
    var prefixBox = document.getElementById('snprefix');
    var numBox    = document.getElementById('snnum');
    if (!cscSel || !prefixBox || !numBox) { return; }

    /* Next free number per CSC, worked out by PHP from the database. */
    var nextNumber = <?php echo json_encode($nextNo); ?>;

    function pad(n) {
        var s = String(n);
        while (s.length < 3) { s = '0' + s; }
        return s;
    }

    function showPrefix() {
        var opt = cscSel.options[cscSel.selectedIndex];
        prefixBox.value = opt ? (opt.getAttribute('data-code') || '') : '';
    }

    /* Changing the CSC gives a new prefix and a new suggested number. */
    cscSel.addEventListener('change', function () {

        showPrefix();

        var cscId = this.value;
        if (cscId === '') {
            numBox.value = '';
            return;
        }

        numBox.value = nextNumber[cscId] ? pad(nextNumber[cscId]) : '001';
        numBox.focus();
        numBox.select();
    });

    /* Digits only — the letters come from the CSC. */
    numBox.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    /* On page load, show the prefix for whatever CSC is already chosen. */
    showPrefix();

})();
</script>

<?php include 'footer.php'; ?>