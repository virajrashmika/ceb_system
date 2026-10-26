<?php
include 'auth.php';
include 'db.php';
include_once 'functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare(
    "SELECT tx.transformer_code, csc.csc_name, areas.area_name, provinces.province_name
     FROM transformers tx
     LEFT JOIN csc       ON tx.csc_id = csc.csc_id
     LEFT JOIN areas     ON csc.area_id = areas.area_id
     LEFT JOIN provinces ON areas.province_id = provinces.province_id
     WHERE tx.transformer_id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    die("Transformer not found. <a href='transformers.php'>Back</a>");
}

/* A removed transformer still opens, read only, so its events and history
   can be looked at. Nothing on it can be changed; a Draughtsman may
   restore it. */
$removed = txRemovedInfo($conn, $id);

$pageTitle = $tx['transformer_code'];
$pageDesc  = $tx['csc_name'] . " CSC — " . $tx['area_name'] . " Area, "
           . $tx['province_name'] . " Province";
$activeNav = "transformers";

// Every change ever made to this transformer and to its events.
$pageAction = array('History',
                    'history.php?type=transformer&amp;id=' . $id,
                    '<path d="M3.5 12a8.5 8.5 0 1 0 2.8-6.3L3 8"/>'
                  . '<path d="M3 3.5V8h4.5"/><path d="M12 7.5V12l3 1.8"/>');

include 'header.php';
?>

<?php if ($removed) { ?>
    <div class="message message-error">
        <strong>Removed from the register</strong>
        on <?php echo date('d M Y, h:i A', strtotime($removed['removed_at'])); ?>
        <?php if ($removed['removed_by_name']) { ?>
            by <?php echo htmlspecialchars($removed['removed_by_name']); ?>
        <?php } ?>.
        Its S/N was <strong><?php echo htmlspecialchars($removed['original_code']); ?></strong>.
        <?php if (roleCanDeleteTransformer(currentRole())) { ?>
            <form method="post" action="deleted_transformers.php" style="margin-top:10px;"
                  onsubmit="return confirm('Restore this transformer as <?php
                      echo htmlspecialchars($removed['original_code']); ?>?');">
                <input type="hidden" name="restore_id" value="<?php echo $id; ?>">
                <button type="submit" class="btn">Restore as <?php
                    echo htmlspecialchars($removed['original_code']); ?></button>
            </form>
        <?php } ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2>Transformer Details</h2>
        <span class="panel-note"><?php echo $removed ? 'Removed record' : 'Current record'; ?></span>
    </div>

    <?php renderTransformerDetails($conn, $id); ?>

    <?php /* Nobody is offered a button they would be refused at. The pages
             behind them are guarded by require_staff.php; this only keeps
             the page honest about what is on offer.

             Asked twice, because the three buttons are not one permission:
             a Draughtsman keeps the transformer and so gets Edit and
             Delete, but events are not their work, so Add event is not
             drawn for them. */
           $mayEditTx   = !$removed && roleCan(currentRole(), 'transformers');
           $mayAddEvent = !$removed && roleCan(currentRole(), 'events');
           $mayDeleteTx = !$removed && roleCanDeleteTransformer(currentRole());   // Draughtsman only
           if ($mayEditTx || $mayAddEvent) { ?>
    <div class="panel-body">
        <?php if ($mayEditTx) { ?>
            <a href="edit_transformer.php?id=<?php echo $id; ?>" class="action-link">Edit transformer</a>
        <?php } ?>
        <?php if ($mayEditTx && $mayAddEvent) { ?><span class="action-sep">|</span><?php } ?>
        <?php if ($mayAddEvent) { ?>
            <a href="add_task.php" class="action-link">Add event</a>
        <?php } ?>
        <?php if ($mayDeleteTx) { ?>
            <span class="action-sep">|</span>
            <a href="delete_transformer.php?id=<?php echo $id; ?>"
               class="action-link delete"
               onclick="return confirm('Remove <?php echo htmlspecialchars($tx['transformer_code']); ?>? It becomes (R)<?php echo htmlspecialchars($tx['transformer_code']); ?>, its events and history are kept, and the S/N is freed for a new transformer.');">Delete transformer</a>
        <?php } ?>
    </div>
    <?php } ?>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Event History</h2>
        <span class="panel-note">Newest first &mdash; click a row to open the event</span>
    </div>

    <?php renderTaskHistory($conn, $id, 'No events recorded for this transformer yet.'); ?>

</div>

<p><a href="transformers.php" class="action-link cancel-link">Back to list</a></p>

<?php include 'footer.php'; ?>