/* ---------------------------------------------------------------------------
   removed_transformers — a transformer taken out of the register.

   Replaces transformers.is_deleted. A transformer is "removed" when it has a
   row here; every list, search and count leaves those out.

   The transformer's own row stays in `transformers`, because its events
   (tasks.transformer_id, task_transformers.transformer_id) still point at
   it — that is how its history survives. What changes is its S/N: UBB 001
   becomes (R)UBB 001, which frees UBB 001 for a new transformer. A second
   removal of the same S/N becomes (R2)UBB 001, then (R3), and so on.

   Each row keeps a copy of the transformer's details as they were at the
   moment of removal, with when and by whom.

   RUN THIS EVERY TIME THE OFFICE DATABASE IS IMPORTED, until a dump taken
   after this date has been carried to the office PC. An older dump brings
   is_deleted back and takes this table away; this file converts any
   transformer the office had deleted, then drops the column again. It is
   safe to run again and again.

     mysql -u root ceb_system < sql/2026-10-06_removed_transformers.sql

   Run it as root. The ceb_app account the site uses has no rights to change
   the shape of a table.
   --------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS removed_transformers (
    removed_id        INT          NOT NULL AUTO_INCREMENT,
    transformer_id    INT          NOT NULL,
    original_code     VARCHAR(20)  NOT NULL,   -- the S/N before removal, e.g. UBB 001
    removed_code      VARCHAR(20)  NOT NULL,   -- the S/N it was given, e.g. (R)UBB 001
    csc_id            INT          NULL,
    substation_name   VARCHAR(120) NULL,
    capacity_kva      INT          NULL,
    transformer_type  VARCHAR(30)  NULL,
    serial_no         VARCHAR(50)  NULL,
    proposal_no       VARCHAR(50)  NULL,
    details           VARCHAR(255) NULL,
    removed_at        DATETIME     NOT NULL,
    removed_by        INT          NULL,
    PRIMARY KEY (removed_id),
    UNIQUE KEY transformer_id (transformer_id),
    KEY original_code (original_code),
    CONSTRAINT removed_transformers_ibfk_1 FOREIGN KEY (transformer_id)
        REFERENCES transformers (transformer_id),
    CONSTRAINT removed_transformers_ibfk_2 FOREIGN KEY (removed_by)
        REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


/* Convert anything still marked is_deleted = 1. Only runs while the column
   exists, so a second run does nothing. Who removed them and when was never
   recorded, so removed_at is the time of this conversion and removed_by is
   left empty. No (R) S/N exists before this conversion, so (R) + the S/N is
   always free here. */
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'transformers'
                   AND COLUMN_NAME  = 'is_deleted');

SET @sql := IF(@has_col = 1,
   'INSERT INTO removed_transformers
        (transformer_id, original_code, removed_code, csc_id, substation_name,
         capacity_kva, transformer_type, serial_no, proposal_no, details,
         removed_at, removed_by)
    SELECT t.transformer_id, t.transformer_code, CONCAT(''(R)'', t.transformer_code),
           t.csc_id, t.substation_name, t.capacity_kva, t.transformer_type,
           t.serial_no, t.proposal_no, t.details, NOW(), NULL
    FROM transformers t
    WHERE t.is_deleted = 1
      AND NOT EXISTS (SELECT 1 FROM removed_transformers r
                      WHERE r.transformer_id = t.transformer_id)',
   'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF(@has_col = 1,
   'UPDATE transformers t
    JOIN removed_transformers r ON r.transformer_id = t.transformer_id
    SET t.transformer_code = r.removed_code
    WHERE t.is_deleted = 1',
   'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

ALTER TABLE transformers DROP COLUMN IF EXISTS is_deleted;
