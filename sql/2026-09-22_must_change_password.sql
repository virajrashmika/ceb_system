/* ---------------------------------------------------------------------------
   must_change_password — force a new account to pick its own password.

   An administrator hands out one starting password to everybody (abc123, set
   by startingPassword() in functions.php). Until this column existed, nothing
   made anyone replace it, so the password the whole office knows stayed on
   the account. With it, the first sign-in is met by a box that will not let
   go until they have chosen their own.

   RUN THIS EVERY TIME THE OFFICE DATABASE IS IMPORTED.

   The dumps phpMyAdmin takes from the office PC were made before this column
   existed, so importing one puts the users table back the way it was and the
   login page stops working. This file puts the column back. It is written to
   be run again and again: if the column is already there, nothing happens and
   nothing is overwritten.

     mysql -u root ceb_system < sql/2026-09-22_must_change_password.sql

   Run it as root. The ceb_app account the site uses deliberately has no
   rights to change the shape of a table.
   --------------------------------------------------------------------------- */

/* Added with DEFAULT 0, so every account that already exists keeps working and
   nobody is locked out by the import. Accounts already in the office database
   have passwords their owners chose, or at least ones they are using. */
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS must_change_password TINYINT(4) NOT NULL DEFAULT 0 AFTER password;

/* Then the default becomes 1, which is what a NEW account picks up. Splitting
   it in two is what makes this file safe to run twice: the line above is
   skipped when the column is there, and this one only restates a default, so
   it never touches anybody's data.

   admin.php writes the 1 explicitly as well. This is the safety net for any
   account created some other way — by hand in phpMyAdmin, for instance. */
ALTER TABLE users
  MODIFY must_change_password TINYINT(4) NOT NULL DEFAULT 1;


/* ---------------------------------------------------------------------------
   Optional: make everybody change once.

   The above leaves the accounts that already exist alone. If the supervisor
   wants every member of staff to set a fresh password — reasonable, since an
   administrator typed the ones they have now — run this as well. Each of them
   meets the box at their next sign-in.

   Leave the administrator out of it, or the person running the system has to
   change their own password before they can fix anybody else's.

     UPDATE users SET must_change_password = 1 WHERE role <> 'admin';
   --------------------------------------------------------------------------- */
