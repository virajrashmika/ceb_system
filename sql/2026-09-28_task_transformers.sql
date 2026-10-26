/* ---------------------------------------------------------------------------
   task_transformers — the extra transformers on one event.

   Most events are about one transformer, and that one stays where it always
   was: tasks.transformer_id. It is Transformer 1 (T1), it is still required,
   and everything that already reads it — the events list, the search, the
   counts, the capacity update when an event is completed — carries on
   exactly as before.

   Some jobs cover more than one transformer, up to five. Transformers 2 to 5
   are kept here, one row each, with their position so the event view can
   show them in the order they were entered: T2, T3, T4, T5. An event with
   only one transformer has no rows here at all.

   RUN THIS EVERY TIME THE OFFICE DATABASE IS IMPORTED, until a dump taken
   after this date has been carried to the office PC. It is safe to run again
   and again: if the table is already there, nothing happens and nothing in
   it is touched.

     mysql -u root ceb_system < sql/2026-09-28_task_transformers.sql

   Run it as root. The ceb_app account the site uses has no rights to create
   a table — but it already has SELECT, INSERT, UPDATE and DELETE on every
   table in ceb_system, this one included, so no new grant is needed.
   --------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS task_transformers (
  task_id        INT(11)    NOT NULL,
  position       TINYINT(4) NOT NULL,   -- 2, 3, 4 or 5; T1 is tasks.transformer_id
  transformer_id INT(11)    NOT NULL,
  PRIMARY KEY (task_id, position),
  UNIQUE KEY uk_task_transformer (task_id, transformer_id),
  KEY transformer_id (transformer_id),
  CONSTRAINT task_transformers_ibfk_1 FOREIGN KEY (task_id)        REFERENCES tasks (task_id),
  CONSTRAINT task_transformers_ibfk_2 FOREIGN KEY (transformer_id) REFERENCES transformers (transformer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
