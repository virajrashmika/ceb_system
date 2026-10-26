<?php
/* ---------------------------------------------------------------------------
   require_admin.php — may this person manage accounts and codes?

   Administrators only. The engineer roles can change records; they cannot
   create users or retire a work type.

   Include this AFTER auth.php. Who counts as an administrator is decided by
   roleIsAdmin() in functions.php, not here.
   --------------------------------------------------------------------------- */

include_once 'functions.php';

if (!roleIsAdmin(currentRole())) {

    $pageTitle = "Not allowed";
    $activeNav = "home";
    include 'header.php';
    ?>
    <div class="message message-error">
        <strong>Access denied.</strong>
        This page is for administrators only.
        <?php if (currentRole() !== '') { ?>
            You are signed in as <strong><?php echo htmlspecialchars(roleLabel(currentRole())); ?></strong>.
        <?php } ?>
    </div>
    <p><a href="index.php" class="btn">Back to Home</a></p>
    <?php
    include 'footer.php';
    exit;
}
