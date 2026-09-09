<?php
include 'auth.php';
include 'require_staff.php';
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['transformer_id'];

    $stmt = $conn->prepare("UPDATE transformers SET is_deleted = 1 WHERE transformer_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: transformers.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT transformer_id, transformer_code, capacity_kva
                        FROM transformers WHERE transformer_id = ? AND is_deleted = 0");
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

<div class="panel">
    <div class="panel-head"><h2>Confirm Delete</h2></div>
    <div class="panel-body">
        <p>Are you sure you want to delete
           <strong><?php echo htmlspecialchars($tx['transformer_code']); ?></strong>
           (<?php echo $tx['capacity_kva']; ?> kVA)?</p>

        

        <form method="post">
            <input type="hidden" name="transformer_id" value="<?php echo $tx['transformer_id']; ?>">
            <button type="submit" class="btn">Yes, delete</button>
            <a href="transformers.php" class="action-link cancel-link">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>