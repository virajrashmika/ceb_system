<?php
/* ---------------------------------------------------------------------------
   header.php  —  CEB System (Uva Province)

   Every page sets these before including this file:
       $pageTitle = 'Transformers';
       $pageDesc  = 'Short sentence under the heading.';   (optional)
       $activeNav = 'transformers';   // home | csc | transformers | tasks | admin | profile
   --------------------------------------------------------------------------- */

/* roleIsAdmin() and roleIsStaff() live here. include_once, because a
   page may well have loaded it already. */
include_once 'functions.php';

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

// ---- corner button ---------------------------------------------------
// One button in the top right of the heading, for the thing this page is
// most often left for. A page asks for one with:
//     $pageAction = array('History', 'history.php?id=1', '<path .../>');
// The third part is the inside of an SVG icon and may be left out.
//
// A fourth part turns it into a submit button for a form further down the
// page instead of a link:
//     $pageAction = array('Search', '', '<path .../>', 'live-form');
// The form attribute is what lets a button sit outside the form it
// submits, so the corner can hold it and the form still works with
// JavaScript switched off.
if (!isset($pageAction)) { $pageAction = null; }

// ---- search bar in the corner ----------------------------------------
// A page that is mostly a search puts its box up here rather than in the
// body, so the answer can start at the top of the page instead of below
// everything else. Asked for with:
//     $pageSearch = array('id' => 'live-form', 'input_id' => 'live-q',
//                         'name' => 'q', 'value' => $keyword,
//                         'action' => 'index.php', 'placeholder' => '...');
//
// An optional 'hidden' => array('status' => 3) adds hidden fields, so a
// search keeps whatever else the page is filtered by.
//
// 'wide' => true lets the box run the whole width up to the page title
// instead of stopping at 820px on the right.
//
// Only pages that ask get a box — the home page and the Events page. Every
// other page used to get one that sent the words to the home page; that was
// taken off, so a box only appears where it searches the page it is on.
if (!isset($pageSearch)) { $pageSearch = null; }

// ---- light / dark switch ---------------------------------------------
// Home only. The choice is kept in the browser and read back before the
// page is drawn, so it holds everywhere else without a button on screen.
$showTheme = ($currentFile === 'index.php');

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

/* An account is not offered an Add link it would be refused at. The pages
   themselves are guarded by require_staff.php; this only keeps the menu
   honest about what is on offer.

   Asked per area rather than once, because a Draughtsman may add a
   transformer but not an event, and a menu offering both would be lying
   about one of them. */
$mayAddTx    = roleCan($userRole, 'transformers');
$mayAddEvent = roleCan($userRole, 'events');

/* Sub-items under Transformers. A sub-item is (label, file) and may carry a
   third part, the inside of its own SVG icon; without one it gets the plus.
   Deleted Transformers is the Draughtsman's alone, the same rule as the
   Delete link and the page itself. */
$txSubs = array();
if ($mayAddTx) { $txSubs[] = array('Add Transformer', 'add_transformer.php'); }
if (roleCanDeleteTransformer($userRole)) {
    $txSubs[] = array('Deleted Transformers', 'deleted_transformers.php',
        '<path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/>'
      . '<path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>');
}

$nav = array(
    'home'         => array('Home', 'index.php', null,
        '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>'),
    'csc'          => array('CSC',  'csc.php', null,
        '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-5h4v5"/>'),
    'transformers' => array('Transformers', 'transformers.php',
                      $txSubs ? $txSubs : null,
        '<path d="M12 2v6"/><path d="M8 8h8l-2 6H10z"/><path d="M12 14v8"/><path d="M8 22h8"/>'),
    'tasks'        => array('Events', 'tasks.php',
                      $mayAddEvent ? array(array('Add Event', 'add_task.php')) : null,
        '<path d="M9 4h6v3H9z"/><path d="M5 7h14v14H5z"/><path d="M9 12h6"/><path d="M9 16h6"/>')
);


// Only administrators see the Admin menu.
// Note: this only hides the link. require_admin.php protects the page itself.
if (roleIsAdmin($userRole)) {
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

<link rel="stylesheet" href="style.css?v=94">
<link rel="icon" type="image/png" href="images/ceb-logo.png">
</head>
<body>

<?php /* Slow-drifting colour behind the glass. Without something moving back
         there, a blurred panel has nothing to refract and just looks grey. */ ?>
<div class="bg-blobs" aria-hidden="true">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>
</div>

<?php
/* ---------------------------------------------------------------------------
   The first-login box.

   Drawn on every page that includes this header, for an account still
   carrying must_change_password — which in practice means the home page,
   because auth.php sends a locked account there from anywhere else.

   There is no close button and no way to click it away. The only two ways
   out are setting a password and signing out. The form posts to
   change_password.php, so it works with JavaScript switched off; the script
   at the bottom of the box upgrades that to a request made in the
   background, which keeps the person on the page they are already looking
   at. Once their password is set, the flag is cleared and this never
   appears again.

   login.php includes this header too, and a flagged session that walks back
   to the login page would otherwise get the box over the top of a login
   form — a welcome and a sign-in prompt at once. It is left off there.
   --------------------------------------------------------------------------- */
if (mustChangePassword() && $currentFile !== 'login.php') { ?>
<div class="pw-gate" id="pw-gate" role="dialog" aria-modal="true"
     aria-labelledby="pw-gate-title" aria-describedby="pw-gate-lead">
    <div class="pw-gate-box">

        <div class="pw-gate-head">
            <svg class="pw-gate-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                 aria-hidden="true">
                <rect x="4" y="10.5" width="16" height="10" rx="2"></rect>
                <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path>
                <circle cx="12" cy="15.5" r="1.3"></circle>
            </svg>
            <h2 id="pw-gate-title">Set your own password</h2>
        </div>

        <div class="pw-gate-body">

            <p class="pw-gate-lead" id="pw-gate-lead">
                Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong>.
                You signed in with the starting password the office gives to
                everyone, so it is not private. Choose one only you know.
                This is asked once.
            </p>

            <?php /* With JavaScript the script below fills this in. Without
                     it, the box has just posted as an ordinary form and
                     change_password.php has handed the complaint back in
                     $gateMessage — it has to be shown here, because the box
                     covers the page that would otherwise carry it. */
                  $gateMessage = isset($gateMessage) ? $gateMessage : ''; ?>
            <div class="message message-error pw-gate-msg" id="pw-gate-msg" role="alert"
                 <?php if ($gateMessage !== '') { echo 'style="display:block"'; } ?>><?php
                echo htmlspecialchars($gateMessage); ?></div>

            <form method="post" action="change_password.php" id="pw-gate-form">
                <input type="hidden" name="from_gate" value="1">

                <div class="form-row">
                    <label for="pw-gate-new">New Password <span class="req">*</span></label>
                    <input type="password" id="pw-gate-new" name="new_password"
                           autocomplete="new-password" autofocus>
                    <span class="field-hint">At least 8 characters. Do not reuse the
                        starting password or a password from another system.</span>
                </div>

                <div class="form-row">
                    <label for="pw-gate-confirm">Confirm New Password <span class="req">*</span></label>
                    <input type="password" id="pw-gate-confirm" name="confirm_password"
                           autocomplete="new-password">
                </div>

                <div class="pw-gate-actions">
                    <button type="submit" class="btn" id="pw-gate-save">Save Password</button>
                    <a href="logout.php" class="pw-gate-out">Sign out instead</a>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
(function () {
    var gate    = document.getElementById('pw-gate');
    var form    = document.getElementById('pw-gate-form');
    var msg     = document.getElementById('pw-gate-msg');
    var save    = document.getElementById('pw-gate-save');
    var box     = gate.querySelector('.pw-gate-box');

    /* Nothing behind the box should be reachable while it is up. Without
       this, Tab walks out of the box and into the page underneath, which
       reads as if the menu were usable when every link on it leads back
       here anyway. */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { e.preventDefault(); return; }
        if (e.key !== 'Tab') { return; }

        var able = box.querySelectorAll('input, button, a[href]');
        if (!able.length) { return; }

        var first = able[0], last = able[able.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        } else if (!box.contains(document.activeElement)) {
            e.preventDefault(); first.focus();
        }
    });

    function show(text) {
        msg.textContent = text;
        msg.style.display = 'block';
    }

    /* fetch is not on the very oldest office browsers. If it is missing,
       this listener is never added and the form posts normally to
       change_password.php, which answers with the same form as a page. */
    if (!window.fetch) { return; }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var body = new URLSearchParams();
        body.append('from_gate', '1');
        body.append('ajax', '1');
        body.append('new_password', document.getElementById('pw-gate-new').value);
        body.append('confirm_password', document.getElementById('pw-gate-confirm').value);

        save.disabled = true;
        save.textContent = 'Saving…';

        fetch('change_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d && d.ok) {
                /* Reload rather than just hiding the box. The page behind
                   was drawn for a locked account; coming back fresh is the
                   simplest way to be sure nothing on it still thinks so. */
                window.location.reload();
                return;
            }
            show(d && d.message ? d.message : 'That could not be saved.');
            save.disabled = false;
            save.textContent = 'Save Password';
        })
        .catch(function () {
            /* The background request failed — fall back to the plain page,
               which does the same job without any script at all. */
            form.submit();
        });
    });
})();
</script>
<?php } ?>

<div class="sidebar">
    <div class="sidebar-brand">
    <img src="images/ceb-logo.png" alt="CEB" class="brand-logo">
    <span class="brand-name">CEB System</span>
    <span class="brand-sub">Uva Province</span>
</div>

    <?php /* The light/dark button used to live here. It is now in the top
             right of the home page's heading, and nowhere else. */ ?>

    <ul class="sidebar-nav">

    <?php /* The sliding pill. It sits behind the links and is moved by the
             script in footer.php, which also adds the has-pill class. With
             JavaScript switched off neither happens, and the plain fill
             behind the active link is used instead, exactly as before. */ ?>
    <li class="nav-pill-slot" aria-hidden="true"><span id="nav-pill"></span></li>

<?php foreach ($nav as $key => $item) {

        /* When the open page is one of a group's sub-items, that sub-item is
           the active link and its parent is not. Marking both was why the
           pill sat on Transformers while Add Transformer was the page you
           were on: the script takes the first active link it finds, and the
           parent comes first in the list. */
        $subIsOpen = false;

        if (isset($item[2])) {
            foreach ($item[2] as $sub) {
                if ($sub[1] === $currentFile) { $subIsOpen = true; }
            }
        }

        $itemActive = ($activeNav === $key && !$subIsOpen);
?>

        <li><a href="<?php echo $item[1]; ?>"<?php echo ($itemActive ? ' class="active"' : ''); ?>>
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
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <?php echo isset($sub[2]) ? $sub[2]
                         : '<path d="M12 5v14"/><path d="M5 12h14"/>'; ?>
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
        <div class="page-head-main">
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

        <?php /* The top right corner: this page's one action, if it has one,
                 and on the home page the light/dark switch. The theme is
                 remembered in the browser, so setting it once on the way in
                 holds for every page and for the next visit. */ ?>
<?php if ($pageSearch || $pageAction || $showTheme) { ?>
        <div class="page-head-side">

<?php if ($pageSearch) { ?>
            <form class="head-search<?php echo !empty($pageSearch['wide']) ? ' head-search-wide' : ''; ?>" method="get"
                  action="<?php echo htmlspecialchars($pageSearch['action']); ?>"
                  id="<?php echo htmlspecialchars($pageSearch['id']); ?>"
                  role="search">
                <svg class="head-search-icon" viewBox="0 0 24 24" width="16" height="16"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                </svg>
                <input type="search" autocomplete="off"
                       id="<?php echo htmlspecialchars($pageSearch['input_id']); ?>"
                       name="<?php echo htmlspecialchars($pageSearch['name']); ?>"
                       value="<?php echo htmlspecialchars($pageSearch['value']); ?>"
                       placeholder="<?php echo htmlspecialchars($pageSearch['placeholder']); ?>">
                <?php if (!empty($pageSearch['hidden'])) {
                          foreach ($pageSearch['hidden'] as $hn => $hv) { ?>
                <input type="hidden" name="<?php echo htmlspecialchars($hn); ?>"
                       value="<?php echo htmlspecialchars($hv); ?>">
                <?php } } ?>
                <?php /* Real submit button, so the box still works with
                         JavaScript switched off — and so Enter submits. */ ?>
                <button type="submit" class="head-search-go">Search</button>
            </form>
<?php } ?>

<?php if ($pageAction) {
          $paIcon = isset($pageAction[2]) ? $pageAction[2] : null;
          $paForm = isset($pageAction[3]) ? $pageAction[3] : null; ?>
    <?php if ($paForm !== null) { ?>
            <button type="submit" form="<?php echo htmlspecialchars($paForm); ?>"
                    class="back-link page-action-link">
    <?php } else { ?>
            <a href="<?php echo $pageAction[1]; ?>" class="back-link page-action-link">
    <?php } ?>
    <?php if ($paIcon !== null) { ?>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <?php echo $paIcon; ?>
                </svg>
    <?php } ?>
                <span><?php echo htmlspecialchars($pageAction[0]); ?></span>
    <?php echo ($paForm !== null) ? '</button>' : '</a>'; ?>
<?php } ?>

<?php if ($showTheme) { ?>
            <button type="button" id="theme-toggle" class="back-link theme-toggle"
                    aria-label="Toggle dark mode">
                <span class="theme-icons">
                    <svg class="icon-sun" viewBox="0 0 24 24" width="16" height="16" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>
                    </svg>
                    <svg class="icon-moon" viewBox="0 0 24 24" width="16" height="16" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"></path>
                    </svg>
                </span>
                <span class="theme-label label-light">Light mode</span>
                <span class="theme-label label-dark">Dark mode</span>
            </button>
<?php } ?>

        </div>
<?php } ?>
    </div>

    <div class="content">