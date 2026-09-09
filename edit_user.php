<?php
include 'auth.php';
include 'require_admin.php';
include 'db.php';

$message = "";
$isError = true;
$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$roleLabels = array(
    'admin'  => 'Administrator',
    'es'     => 'Electricity Superintendent',
    'worker' => 'Worker'
);


$adminCount = $conn->query("SELECT COUNT(*) AS n FROM users
                            WHERE role = 'admin' AND is_deleted = 0")->fetch_assoc()['n'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id       = (int)$_POST['user_id'];
    $fullName = trim($_POST['full_name']);
    $username = strtolower(trim($_POST['username']));
    $role     = isset($roleLabels[$_POST['role']]) ? $_POST['role'] : 'worker';
    $newPass  = $_POST['new_password'];

    // What is this account now? Needed for the safety checks below.
    $stmt = $conn->prepare("SELECT role, is_deleted FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $before = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$before) {
        $message = "That user no longer exists.";

    } elseif ($fullName == "" || $username == "") {
        $message = "Please fill both the name and the username.";

    } elseif ($newPass !== "" && strlen($newPass) < 8) {
        $message = "The new password must be at least 8 characters.";

    // You cannot change your own role — that is how people lock themselves out.
    } elseif ($id == $_SESSION['user_id'] && $role !== $before['role']) {
        $message = "You cannot change your own role. Ask another administrator.";

    // Never leave the system without an administrator.
    } elseif ($before['role'] === 'admin' && $role !== 'admin'
              && $before['is_deleted'] == 0 && $adminCount <= 1) {
        $message = "This is the last active administrator. Promote someone else first.";

    } else {
        try {
            if ($newPass !== "") {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users
                                        SET full_name = ?, username = ?, role = ?, password = ?
                                        WHERE user_id = ?");
                $stmt->bind_param("ssssi", $fullName, $username, $role, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users
                                        SET full_name = ?, username = ?, role = ?
                                        WHERE user_id = ?");
                $stmt->bind_param("sssi", $fullName, $username, $role, $id);
            }
            $stmt->execute();
            $stmt->close();

            $message = $newPass !== ""
                ? $fullName . " was updated and given a new password."
                : $fullName . " was updated.";
            $isError = false;

            // If an admin renamed themselves, the sidebar should follow.
            if ($id == $_SESSION['user_id']) {
                $_SESSION['full_name'] = $fullName;
            }

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $message = "That username is already taken.";
            } else {
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}

// Load the account for the form.
$stmt = $conn->prepare("SELECT user_id, username, full_name, role, is_deleted
                        FROM users WHERE user_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User not found. <a href='admin.php'>Back to Administration</a>");
}

$isSelf = ($user['user_id'] == $_SESSION['user_id']);

$pageTitle = "Edit User";
$pageDesc  = $user['full_name'];
$activeNav = "admin";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<?php if ($user['is_deleted'] == 1) { ?>
    <div class="message message-info">
        <strong>This account is deactivated.</strong>
        It can still be edited, but the person cannot sign in until it is reactivated
        from the <a href="admin.php">Administration</a> page.
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2>Account Details</h2>
        <span class="panel-note"><?php echo $isSelf ? "This is your own account" : "&nbsp;"; ?></span>
    </div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">

            <div class="form-row">
                <label for="fn">Full Name <span class="req">*</span></label>
                <input type="text" id="fn" name="full_name"
                       value="<?php echo htmlspecialchars($user['full_name']); ?>">
            </div>

            <div class="form-row">
                <label for="un">Username <span class="req">*</span></label>
                <input type="text" id="un" name="username"
                       value="<?php echo htmlspecialchars($user['username']); ?>">
                <span class="field-hint">Must be unique. Changing this changes how they sign in.</span>
            </div>

            <div class="form-row">
                <label for="rl">Role <span class="req">*</span></label>
                <select id="rl" name="role"<?php echo $isSelf ? " disabled" : ""; ?>>
                    <?php foreach ($roleLabels as $code => $label) {
                        $sel = ($user['role'] === $code) ? " selected" : "";
                        echo "<option value='" . $code . "'" . $sel . ">"
                           . htmlspecialchars($label) . "</option>";
                    } ?>
                </select>
                <?php if ($isSelf) { ?>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                    <span class="field-hint">You cannot change your own role. Another administrator must do it.</span>
                <?php } else { ?>
                    <span class="field-hint">Superintendents can manage CSCs and transformers, but not user accounts.</span>
                <?php } ?>
            </div>

            <div class="form-row">
                <label for="pw">Reset Password</label>
                <input type="password" id="pw" name="new_password" autocomplete="new-password">
                <span class="field-hint">
                    Leave blank to keep the current password. At least 8 characters.
                    You cannot see the existing password &mdash; it is stored scrambled.
                </span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Changes</button>
                <a href="admin.php" class="action-link cancel-link">Back to Administration</a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>