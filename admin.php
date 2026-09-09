<?php
include 'auth.php';
include 'require_admin.php';
include 'db.php';

$message = "";
$isError = true;

// Role codes stored in the database, and how they are shown on screen.
$roleLabels = array(
    'admin'  => 'Administrator',
    'es'     => 'Electricity Superintendent',
    'worker' => 'Worker'
);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_user') {

    $fullName = trim($_POST['full_name']);
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    // The role must be one we recognise. Anything else becomes a worker.
    $role = isset($roleLabels[$_POST['role']]) ? $_POST['role'] : 'worker';

    if ($fullName == "" || $username == "" || $password == "") {
        $message = "Please fill name, username and password.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hash, $fullName, $role);
            $stmt->execute();
            $stmt->close();

            $message = $fullName . " was added as a " . $roleLabels[$role] . ".";
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
    the tasks they created or were assigned.
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
            $users = $conn->query("SELECT user_id, username, full_name, role, is_deleted
                                   FROM users ORDER BY is_deleted, full_name");

            while ($u = $users->fetch_assoc()) {

                $uid      = $u['user_id'];
                $inactive = ($u['is_deleted'] == 1);
                $isSelf   = ($uid == $_SESSION['user_id']);

                // A colour and a short label for each role.
                $roleBadge = 'badge-pending';                       // worker
                if ($u['role'] === 'admin') { $roleBadge = 'badge-progress'; }
                if ($u['role'] === 'es')    { $roleBadge = 'badge-maintenance'; }

                $roleShort = ($u['role'] === 'es') ? 'ES' : strtoupper($u['role']);
                $roleFull  = isset($roleLabels[$u['role']]) ? $roleLabels[$u['role']] : $u['role'];

                echo "<tr" . ($inactive ? " class='row-inactive'" : "") . ">";
                echo "<td>" . htmlspecialchars($u['full_name']);
                if ($isSelf) { echo " <span class='field-hint'>(you)</span>"; }
                echo "</td>";
                echo "<td class='code-cell'>" . htmlspecialchars($u['username']) . "</td>";
                echo "<td><span class='badge " . $roleBadge . "' title='"
                   . htmlspecialchars($roleFull) . "'>"
                   . htmlspecialchars($roleShort) . "</span></td>";
                echo "<td>" . ($inactive ? "Deactivated" : "Active") . "</td>";

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
                <label for="pw">Password <span class="req">*</span></label>
                <input type="password" id="pw" name="password">
                <span class="field-hint">At least 8 characters. Tell the user to change it after first login.</span>
            </div>

            <div class="form-row">
                <label for="rl">Role <span class="req">*</span></label>
                <select id="rl" name="role">
                    <option value="worker">Worker</option>
                    <option value="es">Electricity Superintendent</option>
                    <option value="admin">Administrator</option>
                </select>
                <span class="field-hint">Superintendents can manage CSCs and transformers, but cannot assign tasks or manage user accounts.</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Submit</button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>Value Lists</h2>
        <span class="panel-note">Reference codes used across the system</span>
    </div>
    <div class="panel-body">
        <p><a href="manage_codes.php" class="action-link">Manage status codes and task types</a></p>
    </div>
</div>

<?php include 'footer.php'; ?>