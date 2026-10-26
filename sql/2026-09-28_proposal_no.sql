/* ---------------------------------------------------------------------------
   proposal_no — the proposal number a transformer was put up under.

   Entered on the Add Transformer page, corrected on Edit Transformer and
   shown in the transformer's details. It is optional: the transformers
   already in the register have none, and stay blank (NULL) until somebody
   fills one in.

   RUN THIS EVERY TIME THE OFFICE DATABASE IS IMPORTED, until a dump taken
   after this date has been carried to the office PC. A dump made before the
   column existed takes it away again, and the transformer pages stop with
   "Unknown column 'proposal_no'". It is safe to run again and again: if the
   column is already there, nothing happens and nothing is overwritten.

     mysql -u root ceb_system < sql/2026-09-28_proposal_no.sql

   Run it as root. The ceb_app account the site uses has no rights to change
   the shape of a table.
   --------------------------------------------------------------------------- */

ALTER TABLE transformers
  ADD COLUMN IF NOT EXISTS proposal_no VARCHAR(50) NULL DEFAULT NULL AFTER serial_no;
