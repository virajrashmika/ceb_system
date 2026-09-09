<?php
include 'auth.php';
include 'db.php';
include 'functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT tx.transformer_code, csc.csc_name, areas.area_name, provinces.province_name
     FROM transformers tx
     LEFT JOIN csc       ON tx.csc_id = csc.csc_id
     LEFT JOIN areas     ON csc.area_id = areas.area_id
     LEFT JOIN provinces ON areas.province_id = provinces.province_id
     WHERE tx.transformer_id = ? AND tx.is_deleted = 0"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    die("Transformer not found. <a href='transformers.php'>Back</a>");
}

$pageTitle = $tx['transformer_code'];
$pageDesc  = $tx['csc_name'] . " CSC — " . $tx['area_name'] . " Area, "
           . $tx['province_name'] . " Province";
$activeNav = "transformers";
include 'header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Transformer Details</h2>
        <span class="panel-note">Current record</span>
    </div>

    <?php renderTransformerDetails($conn, $id); ?>

    <div class="panel-body">
        <a href="edit_transformer.php?id=<?php echo $id; ?>" class="action-link">Edit transformer</a>
        <span class="action-sep">|</span>
        <a href="add_task.php" class="action-link">Add job</a>
        <span class="action-sep">|</span>
        <a href="delete_transformer.php?id=<?php echo $id; ?>"
           class="action-link delete"
           onclick="return confirm('Delete <?php echo htmlspecialchars($tx['transformer_code']); ?>? Its proposals stay in the system but the transformer will no longer appear.');">Delete transformer</a>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Transformer History</h2>
        <span class="panel-note">Newest first &mdash; hover for full text</span>
    </div>

    <?php renderTaskHistory($conn, $id, 'No jobs recorded for this transformer yet.'); ?>

</div>

<p><a href="transformers.php" class="action-link cancel-link">Back to list</a></p>

<?php include 'footer.php'; ?>