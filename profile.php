<?php
include 'auth.php';
include 'db.php';

$uid     = $_SESSION['user_id'];
$message = "";
$isError = true;

// ---- handle the change-password form ----
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $current = $_POST['current_password'];
    $new1    = $_POST['new_password'];
    $new2    = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row['password'])) {
        $message = "Your current password is wrong.";
    } elseif (strlen($new1) < 8) {
        $message = "The new password must be at least 8 characters.";
    } elseif ($new1 !== $new2) {
        $message = "The two new passwords do not match.";
    } else {
        $hash = password_hash($new1, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $uid);
        $stmt->execute();
        $stmt->close();

        $message = "Password changed successfully.";
        $isError = false;
    }
}

// ---- fetch this user's own details ----
$stmt = $conn->prepare("SELECT username, full_name, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle = "My Profile";
$pageDesc  = "Your account details.";
$activeNav = 'profile';
include 'header.php';
?>

<div class="panel">
    <div class="panel-head"><h2>Account Details</h2></div>

    <div class="table-wrap">
        <table class="data detail-table">
            <tbody>
                <tr>
                    <th style="width:35%">Full Name</th>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td class="code-cell"><?php echo htmlspecialchars($user['username']); ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td><span class="role"><?php echo htmlspecialchars($user['role']); ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h2>Change Password</h2></div>
    <div class="panel-body">

        <?php if ($message != "") { ?>
            <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php } ?>

        <form method="post">

            <div class="form-row">
                <label for="cur">Current Password <span class="req">*</span></label>
                <input type="password" id="cur" name="current_password" autocomplete="current-password">
            </div>

            <div class="form-row">
                <label for="new1">New Password <span class="req">*</span></label>
                <input type="password" id="new1" name="new_password" autocomplete="new-password">
                <span class="field-hint">At least 8 characters.</span>
            </div>

            <div class="form-row">
                <label for="new2">Confirm New Password <span class="req">*</span></label>
                <input type="password" id="new2" name="confirm_password" autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Change Password</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>