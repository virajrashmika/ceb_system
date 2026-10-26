<?php
include 'auth.php';
$needArea = 'transformers';
include 'require_staff.php';
include 'db.php';
include_once 'functions.php';

$message   = "";
$isError   = true;

$oldNumber = "";
$oldName   = "";
$oldCsc    = 0;
$oldCap    = 0;
$oldSerial = "";
$oldLoc    = "";
$oldType   = "";
$oldProp   = "";
$oldLtf    = "";
$code      = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $csc_id   = (int)$_POST['csc_id'];

    // S/Ns are written in capitals throughout the register, so a lower-case
    // g01 is stored as G01. Nothing else about what was typed is changed —
    // anything that is not a letter or a digit is refused below rather than
    // quietly removed, because silently turning 12-3 into 123 would create
    // the wrong S/N.
    $number   = strtoupper(trim($_POST['sn_number']));
    $subName  = trim($_POST['substation_name']);
    $capacity = (int)$_POST['capacity_kva'];
    $serial   = trim($_POST['serial_no']);
    $proposal = isset($_POST['proposal_no']) ? trim($_POST['proposal_no']) : '';
    $txType   = isset($_POST['transformer_type']) ? trim($_POST['transformer_type']) : '';
    $details = trim($_POST['details']);
    $ltf      = isset($_POST['lt_feeders']) ? trim($_POST['lt_feeders']) : '';

    $oldNumber = $number;
    $oldName   = $subName;
    $oldCsc    = $csc_id;
    $oldCap    = $capacity;
    $oldSerial = $serial;
    $oldLoc    = $details;
    $oldType   = $txType;
    $oldProp   = $proposal;
    $oldLtf    = $ltf;

    $serialVal = ($serial  !== '') ? $serial  : null;
    $typeVal   = cleanTransformerType($txType);
    $nameVal   = ($subName !== '') ? $subName : null;
    $propVal   = ($proposal !== '') ? $proposal : null;

    // No. of LT Feeders, from the load sheet. Blank is "not recorded".
    $ltfVal    = ($ltf !== '' && ctype_digit($ltf)) ? (int)$ltf : null;

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

    /* Build the full S/N: prefix, one space, then the number.

       A plain number is padded to three digits, so 5 becomes 005 and every
       ordinary transformer in the register keeps the same shape.

       A code containing a letter is stored exactly as typed. Those are the
       generators — the register holds UBH G01, UBP G01, UGK G02 and nine
       more, all mini hydro, biomass or hydro sites — and the office has
       given no padding rule for them, so none is invented here.

       The keyboard rule in the script at the foot of this page must agree
       with the one below. */
    if ($cscCode !== '' && $number !== '' && preg_match('/^[0-9A-Z]+$/', $number)) {
        $code = $cscCode . ' ' . (ctype_digit($number)
                                  ? str_pad($number, 3, '0', STR_PAD_LEFT)
                                  : $number);
    }

    if ($csc_id == 0) {

        $message = "Please choose a CSC.";

    } elseif ($cscCode === '') {

        $message = "That CSC no longer exists.";

    } elseif ($number === '') {

        $message = "Please enter the S/N number.";

    } elseif (!preg_match('/^[0-9A-Z]+$/', $number)) {

        $message = "The S/N can only contain letters and numbers — no spaces, "
                 . "dashes or slashes. The CSC code is added for you.";

    } elseif ($ltf !== '' && !preg_match('/^\d{1,3}$/', $ltf)) {

        $message = "No. of LT Feeders must be a whole number, such as 4. Leave it blank if it is not known.";

    } else {

        /* A removed transformer no longer holds its S/N — it was renamed
           (R)UBB 001 when it was removed — so only a transformer still in
           the register can stand in the way here. The UNIQUE index on
           transformer_code backs this up if two people save at once. */
        $stmt = $conn->prepare("SELECT transformer_id FROM transformers
                                WHERE transformer_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {

            $message = "That S/N already exists.";

        } else {

            $stmt = $conn->prepare("INSERT INTO transformers
                                    (transformer_code, substation_name, csc_id,
                                     capacity_kva, transformer_type, lt_feeders, serial_no, proposal_no, details)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiisisss", $code, $nameVal, $csc_id,
                              $capacity, $typeVal, $ltfVal, $serialVal, $propVal, $details);
            $stmt->execute();
            $stmt->close();

            $message = "Transformer " . $code . " added successfully.";
            $isError = false;

            // Say so when this S/N belonged to a transformer removed earlier,
            // so nobody wonders where the old unit's history went.
            $pq = $conn->prepare("SELECT removed_code FROM removed_transformers
                                  WHERE original_code = ? ORDER BY removed_at");
            $pq->bind_param("s", $code);
            $pq->execute();
            $prev = array();
            foreach ($pq->get_result()->fetch_all(MYSQLI_ASSOC) as $p) { $prev[] = $p['removed_code']; }
            $pq->close();

            if ($prev) {
                $message .= " A removed transformer had this S/N before ("
                          . implode(', ', $prev) . "); its history is kept separately.";
            }

            $oldNumber = ""; $oldName = ""; $oldCap = 0; $oldSerial = ""; $oldLoc = ""; $oldType = ""; $oldProp = ""; $oldLtf = "";
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
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>New Transformer</h2></div>
    <div class="panel-body">
        <form method="post">


            <div class="form-grid">
                <div class="form-col">
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
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="snnum">S/N <span class="req">*</span></label>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="text" id="snprefix" readonly tabindex="-1"
                                   style="width:90px; text-align:center; opacity:.7; cursor:not-allowed;"
                                   value="">
                            <input type="text" id="snnum" name="sn_number" maxlength="6"
                                   autocapitalize="characters" style="flex:1;"
                                   value="<?php echo htmlspecialchars($oldNumber); ?>">
                        </div>
                       
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="sname">Substation Name</label>
                        <input type="text" id="sname" name="substation_name" maxlength="120"
                              
                               value="<?php echo htmlspecialchars($oldName); ?>">
                       
                    </div>
                </div>
                <div class="form-col last">
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
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="txtype">Type</label>
                        <select id="txtype" name="transformer_type">
                            <?php transformerTypeOptions($oldType); ?>
                        </select>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="serial">Serial No</label>
                        <input type="text" id="serial" name="serial_no" maxlength="50"
                               
                               value="<?php echo htmlspecialchars($oldSerial); ?>">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <label for="ltf">No. of LT Feeders</label>
                <input type="number" id="ltf" name="lt_feeders" min="0" max="999" step="1"
                       inputmode="numeric"
                       value="<?php echo htmlspecialchars($oldLtf); ?>">
                <span class="field-hint">As on the load sheet. Leave blank if it is not known.</span>
            </div>

            <div class="form-row">
                <label for="prop">Proposal No</label>
                <input type="text" id="prop" name="proposal_no" maxlength="50"
                       value="<?php echo htmlspecialchars($oldProp); ?>">
                <span class="field-hint">The proposal this transformer was put up under. Leave blank if there is none.</span>
            </div>

            <div class="form-row">
                <label for="loc">Details</label>
                <input type="text" id="loc" name="details"
                       value="<?php echo htmlspecialchars($oldLoc); ?>">
                <span class="field-hint">Anything else worth recording about this transformer.</span>
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

    /* Letters and numbers only, in capitals — the same rule PHP applies when
       the form is saved.

       Spaces and punctuation are kept out because the CSC code and one space
       are added on saving: a second space would split the S/N in two, and the
       next-number lookup reads everything after the last space.

       The caret is put back where it was, so correcting a character in the
       middle of G01 does not jump the cursor to the end. */
    numBox.addEventListener('input', function () {

        var clean = this.value.replace(/[^0-9A-Za-z]/g, '').toUpperCase();
        if (clean === this.value) { return; }

        var pos     = this.selectionStart;
        var removed = this.value.length - clean.length;

        this.value = clean;
        this.setSelectionRange(pos - removed, pos - removed);
    });

    /* On page load, show the prefix for whatever CSC is already chosen. */
    showPrefix();

})();
</script>

<?php include 'footer.php'; ?>