<?php
/* ---------------------------------------------------------------------------
   require_staff.php — may this person change records?

   Yes for an administrator and for the engineer roles (CE, EE, ES, DEU,
   DEA). No for Guest, and no for the retired Worker role, which are both
   read only.

   Include this AFTER auth.php on every page that adds, edits or deletes a
   transformer, a CSC or an event. Typing the address directly must not get
   anybody past it, which is why the check is here and not only in whether
   a button was drawn.

   Who counts as staff is not decided here — roleIsStaff() in functions.php
   is the one list, so adding a role is one line there and nothing else.
   --------------------------------------------------------------------------- */

include_once 'functions.php';

/* A page may name the area it belongs to before including this file:

       $needArea = 'transformers';   // or 'events', or 'csc'
       include 'require_staff.php';

   That is what lets a Draughtsman keep the transformer register while the
   event pages stay shut to them. A page that names nothing asks the older,
   blunter question — may this person change anything at all — so every page
   written before areas existed still behaves exactly as it did. */
$needArea = isset($needArea) ? $needArea : null;

$mayPass = ($needArea === null)
         ? roleIsStaff(currentRole())
         : roleCan(currentRole(), $needArea);

if (!$mayPass) {

    /* Two different refusals. Somebody who may change nothing is told so;
       somebody who may change something, just not this, is told which is
       which, because otherwise a Draughtsman meeting this page would think
       their account was broken. */
    $areaNames = array('transformers' => 'the transformer register',
                       'events'       => 'the event register',
                       'csc'          => 'the CSC list');

    $areaName = ($needArea !== null && isset($areaNames[$needArea]))
              ? $areaNames[$needArea] : 'this part of the system';

    $canChangeSomething = roleIsStaff(currentRole());

    $pageTitle = "Access Denied";
    $activeNav = "home";
    include 'header.php';
    ?>
    <div class="message message-error">
        <strong>Access denied.</strong>
        <?php if ($canChangeSomething) { ?>
            Your account can change records, but not <?php echo $areaName; ?>.
        <?php } else { ?>
            Your account can read the register but not change it.
        <?php } ?>
        <?php if (currentRole() !== '') { ?>
            You are signed in as <strong><?php echo htmlspecialchars(roleLabel(currentRole())); ?></strong>.
        <?php } ?>
    </div>
    <p><a href="index.php" class="btn">Back to Home</a></p>
    <?php
    include 'footer.php';
    exit;
}
