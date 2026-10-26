<?php
include 'auth.php';
include 'require_admin.php';
include 'db.php';

$message = "";
$isError = true;
$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* The roles this form may hand out, from the one list in functions.php.

   An account already holding a retired role is added to its own list
   further down. Without that, opening this form for the tworker account
   would show a list its role is not in, nothing would be selected, and
   saving a change of name would quietly move the account to another role. */
$roleLabels = assignableRoles();


$adminCount = $conn->query("SELECT COUNT(*) AS n FROM users
                            WHERE role = 'admin' AND is_deleted = 0")->fetch_assoc()['n'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $id       = (int)$_POST['user_id'];
    $fullName = trim($_POST['full_name']);
    $username = strtolower(trim($_POST['username']));
    /* Accept anything the form could legitimately have offered: an
       assignable role, or the role this account already has. */
    $postedRole = isset($_POST['role']) ? $_POST['role'] : '';
    $known      = userRoles();
    $role       = (isset($roleLabels[$postedRole]) || isset($known[$postedRole]))
                ? $postedRole : 'guest';
    $newPass  = $_POST['new_password'];

    /* "Send them back to the starting password" — the answer to somebody
       who has forgotten theirs. It is the same string every new account
       gets, so it is exempt from the eight-character rule in the same way,
       and it re-arms the box at their next sign-in just as a new account
       would. Ticked, it wins over anything typed in the box above. */
    $resetToStarting = !empty($_POST['reset_starting']);
    if ($resetToStarting) { $newPass = startingPassword(); }

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

    } elseif (!$resetToStarting && $newPass !== "" && strlen($newPass) < 8) {
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
                /* A reset puts the account back where a new one starts: an
                   administrator knows this password, so the person has to
                   replace it at their next sign-in before anything else
                   loads. Saving a name or a role on its own leaves the flag
                   alone — only handing out a password sets it. */
                $stmt = $conn->prepare("UPDATE users
                                        SET full_name = ?, username = ?, role = ?, password = ?,
                                            must_change_password = 1
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

            if ($resetToStarting) {
                $message = $fullName . " was updated and put back on the starting password ("
                         . startingPassword() . "). They will be asked to set their own"
                         . " the next time they sign in.";
            } elseif ($newPass !== "") {
                $message = $fullName . " was updated and given a new password. They will be"
                         . " asked to set their own the next time they sign in.";
            } else {
                $message = $fullName . " was updated.";
            }
            $isError = false;

            // If an admin renamed themselves, the sidebar should follow.
            if ($id == $_SESSION['user_id']) {
                $_SESSION['full_name'] = $fullName;

                /* An administrator who resets their own password here is
                   subject to the same rule. Without this the session would
                   still say "no change needed" and the flag would only bite
                   at the next login. */
                if ($newPass !== "") {
                    $_SESSION['must_change_password'] = 1;
                }
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
                    <?php
                    /* The assignable roles, plus this account's own if it is
                       one that is no longer handed out — so the list always
                       has something selected and saving cannot move the
                       account somewhere nobody chose. */
                    /* The office database stores some roles capitalised —
                       "CC" and "Clerk" arrived that way — while the list in
                       functions.php is keyed in lower case.

                       Everything that decides what a role may DO already
                       lowercases before it looks up, so those accounts have
                       always had the right permissions. It was only this
                       dropdown that compared the two as they stand, found no
                       match, and offered a second copy of the role marked
                       "(retired)" — which it is not.

                       Matching on the lower-cased key settles it. Saving
                       then writes the tidy code back, so each account sorts
                       itself out the first time anybody edits it. */
                    $roleKey = strtolower(trim((string)$user['role']));

                    $options = $roleLabels;
                    if (!isset($options[$roleKey])) {
                        $options[$roleKey] = roleLabel($user['role']) . ' (retired)';
                    }

                    foreach ($options as $code => $label) {
                        $sel = ($roleKey === $code) ? " selected" : "";
                        echo "<option value='" . htmlspecialchars($code) . "'" . $sel . ">"
                           . htmlspecialchars($label) . "</option>";
                    } ?>
                </select>
                <?php if ($isSelf) { ?>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                    <span class="field-hint">You cannot change your own role. Another administrator must do it.</span>
                <?php } else { ?>
                    <span class="field-hint">CE, EE, ES, DEU, DEA and DEO may add, edit and
                        delete transformers, CSCs and events. Draughtsman may change the
                        transformer register only &mdash; not events, not CSCs. Guest and
                        Clerk can read the register and nothing more. Administrators can
                        also manage accounts and work-type codes.</span>
                <?php } ?>
            </div>

            <div class="form-row">
                <label for="pw">Reset Password</label>
                <input type="password" id="pw" name="new_password" autocomplete="new-password">
                <span class="field-hint">
                    Leave blank to keep the current password. At least 8 characters.
                    You cannot see the existing password &mdash; it is stored scrambled.
                    Whatever you set here counts as a starting password: they must
                    choose their own before the system will show them anything.
                </span>
            </div>

            <div class="form-row">
                <label for="rs">Forgotten Password</label>
                <label class="inline-check" for="rs">
                    <input type="checkbox" id="rs" name="reset_starting" value="1">
                    Put this account back on the standard starting password
                    (<strong><?php echo htmlspecialchars(startingPassword()); ?></strong>)
                </label>
                <span class="field-hint">
                    For somebody who cannot get in. Tell them the starting password;
                    the system asks them to set a new one of their own as soon as they
                    sign in with it. Ticking this ignores anything typed above.
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