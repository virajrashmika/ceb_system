<?php
include 'auth.php';
$needArea = 'transformers';
include 'require_staff.php';
include 'db.php';

// Deleting is narrower than editing: only a Draughtsman may do it. Checked
// here, before GET or POST, so typing the address does not get round the
// hidden link on transformer.php.
if (!roleCanDeleteTransformer(currentRole())) {
    $pageTitle = "Access Denied";
    $activeNav = "transformers";
    include 'header.php';
    echo "<div class='message message-error'><strong>Access denied.</strong> "
       . "Only a Draughtsman can delete a transformer. You are signed in as <strong>"
       . htmlspecialchars(roleLabel(currentRole())) . "</strong>.</div>"
       . "<p><a href='transformers.php' class='btn'>Back to Transformers</a></p>";
    include 'footer.php';
    exit;
}

$message = "";

/* Removing copies the transformer's details into removed_transformers and
   renames its S/N to (R)UBB 001, freeing UBB 001 for a new transformer.
   Its events stay linked to it. See removeTransformer() in functions.php. */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['transformer_id'];

    list($ok, $result) = removeTransformer($conn, $id, (int)$_SESSION['user_id']);

    if ($ok) {
        header("Location: deleted_transformers.php?removed=" . urlencode($result));
        exit;
    }
    $message = $result;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['transformer_id']) ? (int)$_POST['transformer_id'] : 0);

$stmt = $conn->prepare("SELECT transformer_id, transformer_code, capacity_kva
                        FROM transformers WHERE transformer_id = ? AND " . txActiveSql('transformers'));
$stmt->bind_param("i", $id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    die("Transformer not found. <a href='transformers.php'>Back</a>");
}

$pageTitle = "Delete Transformer";
$activeNav = "transformers";
include 'header.php';
?>

<?php if ($message !== "") { ?>
    <div class="message message-error"><?php echo htmlspecialchars($message); ?></div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>Confirm Delete</h2></div>
    <div class="panel-body">
        <p>Are you sure you want to delete
           <strong><?php echo htmlspecialchars($tx['transformer_code']); ?></strong>
           (<?php echo $tx['capacity_kva']; ?> kVA)?</p>

        <p class="field-hint">It will be renamed
           <strong>(R)<?php echo htmlspecialchars($tx['transformer_code']); ?></strong>
           and moved to Deleted Transformers. Its events and history are kept,
           and <?php echo htmlspecialchars($tx['transformer_code']); ?> becomes free
           for a new transformer.</p>

        <form method="post">
            <input type="hidden" name="transformer_id" value="<?php echo $tx['transformer_id']; ?>">
            <button type="submit" class="btn">Yes, delete</button>
            <a href="transformers.php" class="action-link cancel-link">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>