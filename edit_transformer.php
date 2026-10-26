<?php
include 'auth.php';
$needArea = 'transformers';
include 'require_staff.php';
include 'db.php';
include_once 'functions.php';

$message = "";
$isError = true;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id       = (int)$_POST['transformer_id'];
    $code     = strtoupper(trim($_POST['transformer_code']));
    $subName  = trim($_POST['substation_name']);
    $csc_id   = (int)$_POST['csc_id'];
    $capacity = (int)$_POST['capacity_kva'];
    $serial   = trim($_POST['serial_no']);
    $proposal = isset($_POST['proposal_no']) ? trim($_POST['proposal_no']) : '';
    $txType   = isset($_POST['transformer_type']) ? trim($_POST['transformer_type']) : '';
    $details  = trim($_POST['details']);
    $ltf      = isset($_POST['lt_feeders']) ? trim($_POST['lt_feeders']) : '';

    // Blank boxes are stored as NULL, not as empty text.
    $nameVal   = ($subName !== '') ? $subName : null;
    $serialVal = ($serial  !== '') ? $serial  : null;
    $propVal   = ($proposal !== '') ? $proposal : null;
    $ltfVal    = ($ltf !== '' && ctype_digit($ltf)) ? (int)$ltf : null;

    // What type does the record hold now? An imported value that is not one
    // of the three — "Bulk (MHP)" — must survive somebody opening this form
    // and pressing Save without touching the dropdown, so the value as it
    // stands is passed to cleanTransformerType() as the allowed exception.
    $tq = $conn->prepare("SELECT transformer_type FROM transformers WHERE transformer_id = ?");
    $tq->bind_param("i", $id);
    $tq->execute();
    $tRow = $tq->get_result()->fetch_assoc();
    $tq->close();

    $existingType = $tRow ? $tRow['transformer_type'] : null;
    $typeVal      = cleanTransformerType($txType, $existingType);

    if ($code == "" || $csc_id == 0) {
        $message = "Please fill S/N and CSC.";
    } elseif ($ltf !== '' && !preg_match('/^\d{1,3}$/', $ltf)) {
        $message = "No. of LT Feeders must be a whole number, such as 4. Leave it blank if it is not known.";
    } else {

        // Read the record as it stands, so we can see what changed.
        $bq = $conn->prepare(
            "SELECT t.transformer_code, t.substation_name, t.capacity_kva,
                    t.transformer_type, t.lt_feeders, t.serial_no, t.proposal_no, t.details, csc.csc_name
             FROM transformers t
             LEFT JOIN csc ON t.csc_id = csc.csc_id
             WHERE t.transformer_id = ?"
        );
        $bq->bind_param("i", $id);
        $bq->execute();
        $old = $bq->get_result()->fetch_assoc();
        $bq->close();

        // The name of the CSC being saved.
        $cq = $conn->prepare("SELECT csc_name FROM csc WHERE csc_id = ?");
        $cq->bind_param("i", $csc_id);
        $cq->execute();
        $cscRow = $cq->get_result()->fetch_assoc();
        $cq->close();
        $newCscName = $cscRow ? $cscRow['csc_name'] : '';

        try {
            $stmt = $conn->prepare(
                "UPDATE transformers
                 SET transformer_code = ?, substation_name = ?, csc_id = ?,
                     capacity_kva = ?, transformer_type = ?, lt_feeders = ?, serial_no = ?,
                     proposal_no = ?, details = ?
                 WHERE transformer_id = ?"
            );
            $stmt->bind_param("ssiisisssi", $code, $nameVal, $csc_id,
                              $capacity, $typeVal, $ltfVal, $serialVal, $propVal, $details, $id);
            $stmt->execute();
            $stmt->close();

            // Write one log row for each field that really changed.
            if ($old) {
                logChanges($conn, 'transformer', $id,
                    array(
                        'S/N'             => $old['transformer_code'],
                        'Substation Name' => $old['substation_name'],
                        'CSC'             => $old['csc_name'],
                        'Capacity'        => $old['capacity_kva'] ? $old['capacity_kva'] . ' kVA' : '',
                        'Type'            => $old['transformer_type'],
                        'No. of LT Feeders' => $old['lt_feeders'],
                        'Serial No'       => $old['serial_no'],
                        'Proposal No'     => $old['proposal_no'],
                        'Details'         => $old['details'],
                    ),
                    array(
                        'S/N'             => $code,
                        'Substation Name' => $nameVal,
                        'CSC'             => $newCscName,
                        'Capacity'        => $capacity ? $capacity . ' kVA' : '',
                        'Type'            => $typeVal,
                        'No. of LT Feeders' => $ltfVal,
                        'Serial No'       => $serialVal,
                        'Proposal No'     => $propVal,
                        'Details'         => $details,
                    ),
                    (int)$_SESSION['user_id']);
            }

            $message = "Transformer updated.";
            $isError = false;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $message = "That S/N already belongs to another transformer.";
            } else {
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}

$stmt = $conn->prepare("SELECT transformer_id, transformer_code, substation_name,
                               csc_id, capacity_kva, transformer_type, lt_feeders,
                               serial_no, proposal_no, details
                        FROM transformers
                        WHERE transformer_id = ? AND " . txActiveSql('transformers'));
$stmt->bind_param("i", $id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    die("Transformer not found. <a href='transformers.php'>Back</a>");
}

/* A save that was refused shows the form as it was typed, not as it is
   stored, so the mistake can be put right without typing it all again. */
if ($_SERVER["REQUEST_METHOD"] == "POST" && $isError && $message !== "") {
    $tx['transformer_code'] = $code;
    $tx['substation_name']  = $subName;
    $tx['csc_id']           = $csc_id;
    $tx['capacity_kva']     = $capacity;
    $tx['transformer_type'] = $typeVal;
    $tx['lt_feeders']       = $ltf;
    $tx['serial_no']        = $serial;
    $tx['proposal_no']      = $proposal;
    $tx['details']          = $details;
}

$pageTitle = "Edit Transformer";
$activeNav = "transformers";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2><?php echo htmlspecialchars($tx['transformer_code']); ?></h2></div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="transformer_id" value="<?php echo $tx['transformer_id']; ?>">


            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="csc">CSC <span class="req">*</span></label>
                        <select id="csc" name="csc_id">
                            <?php
                            $cscs = $conn->query("SELECT csc.csc_id, csc.csc_name, csc.csc_code, areas.area_name
                                                  FROM csc
                                                  LEFT JOIN areas ON csc.area_id = areas.area_id
                                                  WHERE csc.is_deleted = 0
                                                  ORDER BY areas.area_name, csc.csc_name");
                            while ($c = $cscs->fetch_assoc()) {
                                $sel = ($c['csc_id'] == $tx['csc_id']) ? " selected" : "";
                                echo "<option value='" . $c['csc_id'] . "'" . $sel . ">"
                                     . htmlspecialchars($c['area_name'] . " — " . $c['csc_name'] . " (" . $c['csc_code'] . ")")
                                     . "</option>";
                            }
                            ?>
                        </select>
                        <span class="field-hint">Moving a transformer to another CSC does not change its S/N.</span>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="sn">S/N <span class="req">*</span></label>
                        <input type="text" id="sn" name="transformer_code" maxlength="20"
                               value="<?php echo htmlspecialchars($tx['transformer_code']); ?>">
                        <span class="field-hint">Change this only to correct a mistake.</span>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="sname">Substation Name</label>
                        <input type="text" id="sname" name="substation_name" maxlength="120"
                               placeholder="Lower King Street"
                               value="<?php echo htmlspecialchars($tx['substation_name'] ?? ''); ?>">
                        <span class="field-hint">What the workers call this transformer.</span>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="cap">Capacity</label>
                        <select id="cap" name="capacity_kva">
                            <option value="0">-- Not recorded --</option>
                            <?php
                            $caps = $conn->query("SELECT kva FROM capacities ORDER BY kva");
                            while ($cap = $caps->fetch_assoc()) {
                                $sel = ($cap['kva'] == $tx['capacity_kva']) ? " selected" : "";
                                echo "<option value='" . $cap['kva'] . "'" . $sel . ">" . $cap['kva'] . " kVA</option>";
                            }
                            ?>
                        </select>
                        <span class="field-hint">The rating on the nameplate.</span>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-col">
                    <div class="form-row">
                        <label for="txtype">Type</label>
                        <select id="txtype" name="transformer_type">
                            <?php transformerTypeOptions($tx['transformer_type']); ?>
                        </select>
                        <?php if (isLegacyTransformerType($tx['transformer_type'])) { ?>
                            <span class="field-hint">
                                This transformer carries <strong><?php echo htmlspecialchars($tx['transformer_type']); ?></strong>,
                                which came from the imported register and is not one of the three
                                standard types. It is kept as it is &mdash; saving this form will not
                                change it unless you pick a different type yourself.
                            </span>
                        <?php } else { ?>
                            <span class="field-hint">Leave blank if it is not recorded on the paperwork.</span>
                        <?php } ?>
                    </div>
                </div>
                <div class="form-col last">
                    <div class="form-row">
                        <label for="serial">Serial No</label>
                        <input type="text" id="serial" name="serial_no" maxlength="50"
                               placeholder="T26U010030105"
                               value="<?php echo htmlspecialchars($tx['serial_no'] ?? ''); ?>">
                        <span class="field-hint">The physical unit's serial number.</span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <label for="ltf">No. of LT Feeders</label>
                <input type="number" id="ltf" name="lt_feeders" min="0" max="999" step="1"
                       inputmode="numeric"
                       value="<?php echo htmlspecialchars((string)($tx['lt_feeders'] ?? '')); ?>">
                <span class="field-hint">As on the load sheet. Leave blank if it is not known.</span>
            </div>

            <div class="form-row">
                <label for="prop">Proposal No</label>
                <input type="text" id="prop" name="proposal_no" maxlength="50"
                       value="<?php echo htmlspecialchars($tx['proposal_no'] ?? ''); ?>">
                <span class="field-hint">The proposal this transformer was put up under. Leave blank if there is none.</span>
            </div>

            <div class="form-row">
                <label for="det">Details</label>
                <input type="text" id="det" name="details"
                       value="<?php echo htmlspecialchars($tx['details'] ?? ''); ?>">
                <span class="field-hint">Anything else worth recording about this transformer.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Update</button>
                <a href="transformers.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>