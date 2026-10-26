<?php
include 'auth.php';
$needArea = 'csc';
include 'require_staff.php';
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)$_POST['csc_id'];

    $stmt = $conn->prepare("UPDATE csc SET is_deleted = 1 WHERE csc_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: csc.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT csc_id, csc_name, csc_code FROM csc WHERE csc_id = ? AND is_deleted = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$csc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$csc) {
    die("CSC not found. <a href='csc.php'>Back</a>");
}

$pageTitle = "Delete CSC";
$activeNav = "csc";
include 'header.php';
?>

<div class="panel">
    <div class="panel-head"><h2>Confirm Delete</h2></div>
    <div class="panel-body">
        <p>Are you sure you want to delete
           <strong><?php echo htmlspecialchars($csc['csc_name']); ?></strong>
           (<?php echo htmlspecialchars($csc['csc_code']); ?>)?</p>

        

        <form method="post">
            <input type="hidden" name="csc_id" value="<?php echo $csc['csc_id']; ?>">
            <button type="submit" class="btn">Yes, delete</button>
            <a href="csc.php" class="action-link cancel-link">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>