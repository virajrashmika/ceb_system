<?php
/* ---------------------------------------------------------------------------
   change_password.php — where the first-login box posts, and the same job
   again as an ordinary page.

   Two ways in, and they are told apart by the flag on the account rather
   than by anything in the request:

     Forced. The account still carries must_change_password. The box in
     header.php posts here, in the background if the browser can and as a
     plain form if it cannot. The current password is not asked for: they
     typed it at the login page a moment ago, and in any case it is the
     starting password the whole office is given, so asking again would
     prove nothing.

     Voluntary. Anyone changing their password because they want to. The
     current password is required, because a signed-in session left open on
     a desk should not be enough to lock the owner out of their own account.

   profile.php carries its own copy of the voluntary form. Both clear the
   flag on success, so an account can never end up changed in the database
   and still flagged in the session.
   --------------------------------------------------------------------------- */
include 'auth.php';
include 'db.php';

$forced  = mustChangePassword();

/* The box asks for JSON so it can stay on the page it is drawn over. The
   same POST without this is an ordinary form submission and gets a page. */
$wantsJson = !empty($_POST['ajax']);

$message = "";
$isError = true;
$done    = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new1    = isset($_POST['new_password'])     ? $_POST['new_password']     : '';
    $new2    = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    $uid = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $message = "Your account could not be found.";

    } elseif (!$forced && !password_verify($current, $user['password'])) {
        $message = "Your current password is not correct.";

    } elseif ($new1 === '') {
        $message = "Please type a new password.";

    /* Before the length rule, not after it. The starting password is
       shorter than eight characters, so somebody typing it straight back in
       would otherwise be told it was too short — true, but not the reason
       it is being refused, and not a complaint they can act on. */
    } elseif (password_verify($new1, $user['password'])) {
        $message = $forced
            ? "That is the starting password. Please choose a different one."
            : "The new password must be different from the old one.";

    } elseif (strlen($new1) < 8) {
        $message = "The new password must be at least 8 characters.";

    } elseif ($new1 !== $new2) {
        $message = "The two new passwords do not match.";

    } else {
        $hash = password_hash($new1, PASSWORD_DEFAULT);

        /* The flag is cleared in the same statement as the password. They
           are one change: an account whose password was replaced is not one
           that still needs replacing. */
        $stmt = $conn->prepare("UPDATE users
                                SET password = ?, must_change_password = 0
                                WHERE user_id = ?");
        $stmt->bind_param("si", $hash, $uid);
        $stmt->execute();
        $stmt->close();

        $_SESSION['must_change_password'] = 0;

        $message = $forced
            ? "Your password is set. The rest of the system is open to you now."
            : "Your password has been changed.";
        $isError = false;
        $done    = true;
        $forced  = false;   // the menu works from here on
    }

    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(array('ok' => !$isError, 'message' => $message));
        exit;
    }

    /* No JavaScript, and it worked: back to the home page rather than
       leaving them on a form they have finished with. */
    if ($done && !empty($_POST['from_gate'])) {
        header('Location: index.php');
        exit;
    }
}

/* Still forced, and the box posted here without JavaScript: hand the
   complaint to header.php so it appears inside the box, which is about to
   be drawn over the top of this page. */
if ($forced && $isError && $message !== "" && !empty($_POST['from_gate'])) {
    $gateMessage = $message;
    $message     = "";
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
                <input type="password" id="cp" name="current_password" autocomplete="current-password">
            </div>

            <div class="form-row">
                <label for="np">New Password <span class="req">*</span></label>
                <input type="password" id="np" name="new_password" autocomplete="new-password">
                <span class="field-hint">At least 8 characters. Do not reuse a password from another system.</span>
            </div>

            <div class="form-row">
                <label for="cf">Confirm New Password <span class="req">*</span></label>
                <input type="password" id="cf" name="confirm_password" autocomplete="new-password">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Change Password</button>
                <a href="index.php" class="action-link cancel-link"><?php
                    echo $done ? "Back to Home" : "Cancel"; ?></a>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
