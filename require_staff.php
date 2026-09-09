<?php
/* ---------------------------------------------------------------------------
   require_staff.php — allows administrators and Electricity Superintendents.

   Include this AFTER auth.php on pages that manage CSCs and transformers.
   Workers are refused, even if they type the address directly.
   --------------------------------------------------------------------------- */

$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

if ($role !== 'admin' && $role !== 'es') {

    $pageTitle = "Access Denied";
    $activeNav = "home";
    include 'header.php';
    ?>
    <div class="message message-error">
        <strong>Access denied.</strong>
        This page is for administrators and Electricity Superintendents only.
    </div>
    <p><a href="index.php" class="btn">Back to Home</a></p>
    <?php
    include 'footer.php';
    exit;
}
?>