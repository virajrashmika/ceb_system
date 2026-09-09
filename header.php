<?php
/* ---------------------------------------------------------------------------
   header.php  —  CEB System (Uva Province)

   Every page sets these before including this file:
       $pageTitle = 'Transformers';
       $pageDesc  = 'Short sentence under the heading.';   (optional)
       $activeNav = 'transformers';   // home | csc | transformers | search | tasks | admin | profile
   --------------------------------------------------------------------------- */

if (!isset($pageTitle)) { $pageTitle = 'Dashboard'; }
if (!isset($pageDesc))  { $pageDesc  = ''; }
if (!isset($activeNav)) { $activeNav = 'home'; }

// The signed-in user comes from the session, never typed into the page.
$userName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
$userRole = isset($_SESSION['role'])      ? $_SESSION['role']      : '';
$currentFile = basename($_SERVER['PHP_SELF']);

// ---- back button ----------------------------------------------------
// A page asks for one with:  $showBack = true;
// and may set a fallback:    $backFallback = 'tasks.php';
if (!isset($showBack))     { $showBack = false; }
if (!isset($backFallback)) { $backFallback = 'index.php'; }

$backLink = $backFallback;

if ($showBack && !empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    // Only follow a referrer from this same site.
    if (parse_url($ref, PHP_URL_HOST) === $_SERVER['HTTP_HOST']) {
        $refFile = basename(parse_url($ref, PHP_URL_PATH));
        // Never send them back to the page they are already on.
        if ($refFile !== $currentFile) {
            $backLink = $ref;
        }
    }
}

$nav = array(
    'home'         => array('Home', 'index.php', null,
        '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>'),
    'csc'          => array('CSC',  'csc.php', null,
        '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-5h4v5"/>'),
    'transformers' => array('Transformers', 'transformers.php', array(
                          array('Add Transformer', 'add_transformer.php')
                      ),
        '<path d="M12 2v6"/><path d="M8 8h8l-2 6H10z"/><path d="M12 14v8"/><path d="M8 22h8"/>'),
    'search'       => array('Search', 'search.php', null,
        '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>'),
    'tasks'        => array('Tasks', 'tasks.php', array(
                          array('Add Job', 'add_task.php')
                      ),
        '<path d="M9 4h6v3H9z"/><path d="M5 7h14v14H5z"/><path d="M9 12h6"/><path d="M9 16h6"/>')
);


// Only administrators see the Admin menu.
// Note: this only hides the link. require_admin.php protects the page itself.
if ($userRole === 'admin') {
    $nav['admin'] = array('Admin', 'admin.php', null,
        '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 12 0v2"/>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?> &mdash; CEB System</title>

<!-- Runs before the page is drawn, so dark mode does not flash white on load. -->
<script>
(function () {
    var t = localStorage.getItem('ceb-theme');
    if (t === 'dark') { document.documentElement.setAttribute('data-theme', 'dark'); }
})();
</script>

<link rel="stylesheet" href="style.css?v=37">
<link rel="icon" type="image/png" href="images/ceb-logo.png">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
    <img src="images/ceb-logo.png" alt="CEB" class="brand-logo">
    <span class="brand-name">CEB System</span>
    <span class="brand-sub">Uva Province</span>
</div>

    <button type="button" id="theme-toggle" class="theme-toggle" aria-label="Toggle dark mode">
        <span class="theme-icons">
            <svg class="icon-sun" viewBox="0 0 24 24" width="18" height="18" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
            </svg>
            <svg class="icon-moon" viewBox="0 0 24 24" width="18" height="18" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
            </svg>
        </span>
        <span class="theme-label label-light">Light mode</span>
<span class="theme-label label-dark">Dark mode</span>
    </button>

    <ul class="sidebar-nav">
      
<?php foreach ($nav as $key => $item) { ?>

        <li><a href="<?php echo $item[1]; ?>"<?php echo ($activeNav === $key ? ' class="active"' : ''); ?>>
    <?php if (isset($item[3])) { ?>
    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <?php echo $item[3]; ?>
    </svg>
    <?php } ?>
    <span><?php echo $item[0]; ?></span>
</a></li>

<?php   if (isset($item[2])) {
            foreach ($item[2] as $sub) {
                $subActive = ($sub[1] === $currentFile) ? ' class="active"' : ''; ?>
        <li class="nav-sub"><a href="<?php echo $sub[1]; ?>"<?php echo $subActive; ?>>
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round">
                <path d="M12 5v14"/><path d="M5 12h14"/>
            </svg>
            <span><?php echo $sub[0]; ?></span>
        </a></li>
<?php       }
        }
    } ?>
    </ul>

<?php if ($userName !== '') { ?>
    <div class="sidebar-user">
        <span class="user-label">Signed in as</span>
        <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
<?php if ($userRole !== '') { ?>
        <span class="user-role"><?php echo htmlspecialchars($userRole); ?></span>
<?php } ?>
        <a class="logout-link<?php echo ($activeNav === 'profile' ? ' active' : ''); ?>" href="profile.php">My Profile</a>
        <br>
        <a class="logout-link" href="logout.php">Logout</a>
    </div>
<?php } ?>
</div>

<div class="main">
    <div class="page-head">
        <h1>
            <svg class="heading-bulb" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M9.5 18h5"></path>
                <path d="M10 21h4"></path>
                <path d="M12 2.5a6 6 0 0 0-3.6 10.8c.6.45.95 1.1 1.05 1.8l.05.4h5l.05-.4c.1-.7.45-1.35 1.05-1.8A6 6 0 0 0 12 2.5z"></path>
                <path d="M12 8v4.5"></path>
            </svg>
            <?php echo htmlspecialchars($pageTitle); ?>
        </h1>
<?php if ($pageDesc !== '') { ?>
        <p class="page-desc"><?php echo htmlspecialchars($pageDesc); ?></p>
<?php } ?>
<?php if ($showBack) { ?>
        <a href="<?php echo htmlspecialchars($backLink); ?>" class="back-link">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
            </svg>
            <span>Back</span>
        </a>
<?php } ?>
    </div>

    <div class="content">