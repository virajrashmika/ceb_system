<?php
session_start();
include 'db.php';

$message = "";
$isError = true;

if (isset($_GET['timeout'])) {
    $message = "You were logged out because of inactivity. Please log in again.";
    $isError = false;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password, full_name, role, must_change_password FROM users WHERE username = ? AND is_deleted = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']       = $user['user_id'];
        $_SESSION['full_name']     = $user['full_name'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();

        /* Read once, here, so no later page has to ask the database again.
           If it is set, the home page they are about to land on draws the
           box that asks them to replace the starting password. */
        $_SESSION['must_change_password'] = (int)$user['must_change_password'];

        header("Location: index.php");
        exit;
    } else {
        $message = "Invalid username or password.";
        $isError = true;
    }
}

$pageTitle = "Login";
$pageDesc  = "Sign in with your own account.";
include 'header.php';
?>

<?php if ($message != "") { ?>
    <div class="message <?php echo $isError ? 'message-error' : 'message-info'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<div class="panel">
    <div class="panel-head"><h2>CEB System Login</h2></div>
    <div class="panel-body">
        <form method="post">

            <div class="form-row">
                <label for="un">Username</label>
                <input type="text" id="un" name="username" autocomplete="username" autofocus>
            </div>

            <div class="form-row">
                <label for="pw">Password</label>
                <input type="password" id="pw" name="password" autocomplete="current-password">
            </div>

            <p>
                <button type="submit" class="bulb-btn">
                    <span class="bulb-cap"></span>
                    <span class="bulb-glass">
                        <svg class="bulb-filament" viewBox="0 0 24 28" fill="none"
                             stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 2v7M18 2v7"></path>
                            <path d="M6 9c0 3 3 1.5 3 4.5S6 15 6 18M18 9c0 3-3 1.5-3 4.5s3 1.5 3 4.5"></path>
                            <path d="M6 18v8M18 18v8"></path>
                        </svg>
                        <span class="bulb-text">Login</span>
                    </span>
                </button>
            </p>

        </form>
    </div>
</div>

<?php include 'footer.php'; ?>