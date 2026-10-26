<?php
/* ---------------------------------------------------------------------------
   deleted_transformers.php — the transformers taken out of the register.

   Read from removed_transformers: each row is a copy of the transformer's
   details as they were when it was removed, with when and by whom. The
   transformer itself is still in `transformers` under its (R) S/N, so its
   events and history open from here.

   Draughtsman only, the same rule as delete_transformer.php, and checked
   here as well as in the menu, so typing the address gets nobody in.
   --------------------------------------------------------------------------- */
include 'auth.php';
$needArea = 'transformers';
include 'require_staff.php';
include 'db.php';

if (!roleCanDeleteTransformer(currentRole())) {
    $pageTitle = "Access Denied";
    $activeNav = "transformers";
    include 'header.php';
    echo "<div class='message message-error'><strong>Access denied.</strong> "
       . "Only a Draughtsman can see deleted transformers. You are signed in as <strong>"
       . htmlspecialchars(roleLabel(currentRole())) . "</strong>.</div>"
       . "<p><a href='transformers.php' class='btn'>Back to Transformers</a></p>";
    include 'footer.php';
    exit;
}

$message = "";
$isError = true;

// Arriving straight from a delete.
if (isset($_GET['removed']) && $_GET['removed'] !== '') {
    $message = "Transformer removed. It is now " . $_GET['removed'] . ".";
    $isError = false;
}

// ---- Restore -----------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['restore_id'])) {

    $rid = (int)$_POST['restore_id'];

    list($ok, $result) = restoreTransformer($conn, $rid);

    if ($ok) {
        // Back to the transformer itself, so the restore can be seen.
        header("Location: transformer.php?id=" . $rid);
        exit;
    }
    $message = $result;
    $isError = true;
}

$pageTitle = "Deleted Transformers";
$pageDesc  = "Transformers removed from the register. Restoring one brings it back under its old S/N.";
$activeNav = "transformers";
include 'header.php';

$rows = $conn->query(
    "SELECT r.*, c.csc_name, u.full_name AS removed_by_name,
            (SELECT COUNT(*) FROM tasks k
             WHERE k.transformer_id = r.transformer_id AND k.is_deleted = 0) AS events
     FROM removed_transformers r
     LEFT JOIN csc   c ON r.csc_id     = c.csc_id
     LEFT JOIN users u ON r.removed_by = u.user_id
     ORDER BY r.removed_at DESC, r.removed_id DESC"
)->fetch_all(MYSQLI_ASSOC);

$dash = "<span class='field-hint'>&mdash;</span>";
?>

<?php if ($message !== "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2>Deleted Transformers</h2>
        <span class="panel-note"><?php echo count($rows); ?> removed &mdash; newest first</span>
    </div>

    <div class="table-wrap">
        <table class="data plain-table">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Was</th>
                    <th>Substation Name</th>
                    <th>CSC</th>
                    <th class="num">Capacity</th>
                    <th class="num">Events</th>
                    <th>Removed On</th>
                    <th>Removed By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows) { ?>
                <tr><td colspan="9"><div class="table-empty">No transformers have been removed.</div></td></tr>
            <?php } ?>

            <?php foreach ($rows as $r) {
                    $tid = (int)$r['transformer_id']; ?>
                <tr data-href="transformer.php?id=<?php echo $tid; ?>">
                    <td data-label="S/N">
                        <a href="transformer.php?id=<?php echo $tid; ?>" class="code-cell"><?php
                            echo htmlspecialchars($r['removed_code']); ?></a></td>
                    <td data-label="Was" class="code-cell"><?php
                        echo htmlspecialchars($r['original_code']); ?></td>
                    <td data-label="Substation Name"><?php
                        echo trim((string)$r['substation_name']) !== ''
                             ? htmlspecialchars($r['substation_name']) : $dash; ?></td>
                    <td data-label="CSC"><?php
                        echo trim((string)$r['csc_name']) !== ''
                             ? htmlspecialchars($r['csc_name']) : $dash; ?></td>
                    <td data-label="Capacity" class="num"><?php
                        echo $r['capacity_kva'] ? (int)$r['capacity_kva'] . " kVA" : $dash; ?></td>
                    <td data-label="Events" class="num"><?php
                        echo (int)$r['events'] ?: $dash; ?></td>
                    <td data-label="Removed On"><?php
                        echo date('d M Y, h:i A', strtotime($r['removed_at'])); ?></td>
                    <td data-label="Removed By"><?php
                        echo $r['removed_by_name'] ? htmlspecialchars($r['removed_by_name'])
                                                   : "<span class='field-hint'>not recorded</span>"; ?></td>
                    <td>
                        <form method="post" style="margin:0;"
                              onsubmit="return confirm('Restore <?php
                                  echo htmlspecialchars($r['removed_code']); ?> as <?php
                                  echo htmlspecialchars($r['original_code']); ?>?');">
                            <input type="hidden" name="restore_id" value="<?php echo $tid; ?>">
                            <button type="submit" class="btn">Restore</button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
