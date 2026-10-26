<?php
include 'auth.php';
include 'require_admin.php';
include 'db.php';

$message = "";
$isError = true;

/* The roles a new account may be given. One list, in functions.php, so
   this form and edit_user.php can never drift apart. */
$roleLabels = assignableRoles();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_user') {

    $fullName = trim($_POST['full_name']);
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    /* The role must be one this form offers. Anything else — a hand-made
       POST — falls back to Guest, which can read and nothing more. */
    $role = isset($roleLabels[$_POST['role']]) ? $_POST['role'] : 'guest';

    /* Left blank means the standard starting password. That is the normal
       way to add somebody: the administrator does not invent a password,
       because the person is made to replace it at their first sign-in
       anyway. A password typed in by hand still has to be a real one. */
    $usedStarting = ($password === "");
    if ($usedStarting) { $password = startingPassword(); }

    if ($fullName == "" || $username == "") {
        $message = "Please fill in both the name and the username.";
    } elseif (!$usedStarting && strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            /* must_change_password is 1 for every new account. The password
               typed above is read out to the person, so it is a way in and
               not a secret; the system makes them replace it before it shows
               them anything. The column defaults to 1 anyway — it is written
               out here so the rule is visible where the account is made. */
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, must_change_password)
                                    VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("ssss", $username, $hash, $fullName, $role);
            $stmt->execute();
            $stmt->close();

            $message = $fullName . " was added as a " . $roleLabels[$role] . ". "
                     . "Tell them to sign in as \"" . $username . "\" with "
                     . ($usedStarting
                        ? "the standard starting password (" . startingPassword() . ")"
                        : "the password you typed")
                     . " — the system will then ask them to set their own.";
            $isError = false;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $message = "That username is already taken.";
            } else {
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}
$showBack     = true;
$backFallback = 'csc.php';    // transformers.php / tasks.php / index.php
$pageTitle = "Administration";
$pageDesc  = "System users and the value lists used across the province.";
$activeNav = "admin";
include 'header.php';

// How many active admins are there? Used to protect the last one.
$adminCount = $conn->query("SELECT COUNT(*) AS n FROM users
                            WHERE role = 'admin' AND is_deleted = 0")->fetch_assoc()['n'];
?>

<div class="message message-info">
    <strong>Administrator access.</strong>
    Only admin accounts can open this page. Deactivating a user does not remove
    the events they created or were assigned.
</div>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head">
        <h2>Users</h2>
        <span class="panel-note"><?php echo $adminCount; ?> active administrator(s)</span>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $users = $conn->query("SELECT user_id, username, full_name, role, is_deleted,
                                          must_change_password
                                   FROM users ORDER BY is_deleted, full_name");

            while ($u = $users->fetch_assoc()) {

                $uid      = $u['user_id'];
                $inactive = ($u['is_deleted'] == 1);
                $isSelf   = ($uid == $_SESSION['user_id']);

                /* Colour by what the role can do, not by its name, so a
                   role added later is coloured right without touching this. */
                $rank = roleRank($u['role']);
                $roleBadge = ($rank === 'admin') ? 'badge-progress'
                           : (($rank === 'staff') ? 'badge-maintenance' : 'badge-pending');

                $roleShort = roleLabel($u['role']);
                $roleFull  = $roleShort . ' \u2014 '
                           . (($rank === 'admin') ? 'accounts, codes and all records'
                              : (($rank === 'staff') ? 'may add, edit and delete records'
                                                    : 'may read the register only'));

                echo "<tr" . ($inactive ? " class='row-inactive'" : "") . ">";
                echo "<td>" . htmlspecialchars($u['full_name']);
                if ($isSelf) { echo " <span class='field-hint'>(you)</span>"; }
                echo "</td>";
                echo "<td class='code-cell'>" . htmlspecialchars($u['username']) . "</td>";
                echo "<td><span class='badge " . $roleBadge . "' title='"
                   . htmlspecialchars($roleFull) . "'>"
                   . htmlspecialchars($roleShort) . "</span></td>";
                /* An account still carrying the flag has never been signed
                   into, or was signed into and abandoned at the password
                   page. Worth seeing at a glance: it means the password the
                   administrator read out still opens the account. */
                echo "<td>" . ($inactive ? "Deactivated" : "Active");
                if (!$inactive && $u['must_change_password'] == 1) {
                    echo " <span class='field-hint' title='They have not set their own password yet."
                       . " Until they do, they see nothing but the password page.'>&mdash; starting password</span>";
                }
                echo "</td>";

                echo "<td class='actions'>";
                echo "<a href='edit_user.php?id=$uid' class='action-link'>Edit</a>";

                if ($inactive) {
                    echo "<span class='action-sep'>|</span>";
                    echo "<a href='toggle_user.php?id=$uid' class='action-link'>Reactivate</a>";
                } elseif ($isSelf) {
                    echo "<span class='action-sep'>|</span>";
                    echo "<span class='field-hint'>cannot deactivate yourself</span>";
                } elseif ($u['role'] === 'admin' && $adminCount <= 1) {
                    echo "<span class='action-sep'>|</span>";
                    echo "<span class='field-hint'>last administrator</span>";
                } else {
                    echo "<span class='action-sep'>|</span>";
                    echo "<a href='toggle_user.php?id=$uid' class='action-link delete'>Deactivate</a>";
                }

                echo "</td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h2>Add User</h2></div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="action" value="add_user">

            <div class="form-row">
                <label for="fn">Full Name <span class="req">*</span></label>
                <input type="text" id="fn" name="full_name">
            </div>

            <div class="form-row">
                <label for="un">Username <span class="req">*</span></label>
                <input type="text" id="un" name="username">
                <span class="field-hint">First initial and surname, lower case. Must be unique.</span>
            </div>

            <div class="form-row">
                <label for="pw">Starting Password</label>
                <input type="password" id="pw" name="password"
                       placeholder="<?php echo htmlspecialchars(startingPassword()); ?>">
                <span class="field-hint">Leave this blank to use the standard starting password,
                    <strong><?php echo htmlspecialchars(startingPassword()); ?></strong>. It is only a
                    way in: the first time they sign in, the system asks them to set their own and
                    shows them nothing else until they do. To give this one person a different
                    starting password instead, type one of at least 8 characters.</span>
            </div>

            <div class="form-row">
                <label for="rl">Role <span class="req">*</span></label>
                <select id="rl" name="role">
                <?php foreach ($roleLabels as $code => $label) { ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"<?php
                        echo ($code === 'guest' ? ' selected' : ''); ?>><?php
                        echo htmlspecialchars($label); ?></option>
                <?php } ?>
                </select>
                <span class="field-hint">CE, EE, ES, DEU, DEA and DEO may add, edit and delete
                    transformers, CSCs and events. Draughtsman may change the transformer
                    register only &mdash; not events, not CSCs. Guest and Clerk can read the
                    register and nothing more. Administrators can also manage accounts and
                    work-type codes.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Submit</button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>