<?php
include 'auth.php';
include 'db.php';

$message = "";
$isError = true;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $current = $_POST['current_password'];
    $new1    = $_POST['new_password'];
    $new2    = $_POST['confirm_password'];

    $uid = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $message = "Your account could not be found.";
    } elseif (!password_verify($current, $user['password'])) {
        $message = "Your current password is not correct.";
    } elseif (strlen($new1) < 8) {
        $message = "The new password must be at least 8 characters.";
    } elseif ($new1 !== $new2) {
        $message = "The two new passwords do not match.";
    } elseif ($new1 === $current) {
        $message = "The new password must be different from the old one.";
    } else {
        $hash = password_hash($new1, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $uid);
        $stmt->execute();
        $stmt->close();

        $message = "Your password has been changed.";
        $isError = false;
    }
}

$pageTitle = "Change Password";
$pageDesc  = "Change the password for your own account.";
$activeNav = "";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2><?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
        <span class="panel-note">Nobody else can see your password, including administrators.</span>
    </div>
    <div class="panel-body">
        <form method="post">
            <div class="form-row">
                <label for="cp">Current Password <span class="req">*</span></label>
                <input type="password" id="cp" name="current_password">
            </div>

            <div class="form-row">
                <label for="np">New Password <span class="req">*</span></label>
                <input type="password" id="np" name="new_password">
                <span class="field-hint">At least 8 characters. Do not reuse a password from another system.</span>
            </div>

            <div class="form-row">
                <label for="cf">Confirm New Password <span class="req">*</span></label>
                <input type="password" id="cf" name="confirm_password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Change Password</button>
                <a href="index.php" class="action-link cancel-link">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>