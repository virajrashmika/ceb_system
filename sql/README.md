# Moving the database between this PC and the office PC

Written for whoever is keeping the two copies in step — most of the time that
is one person moving a phpMyAdmin dump back and forth on a memory stick.

There are two copies of `ceb_system`: the one on the office PC, which is the
real register, and the one on the development PC, where changes are tried out
first. Work flows in both directions, and each direction has one thing that is
easy to get wrong.

## "#1050 - Table 'abc_cable_sizes' already exists"

This is the error a phpMyAdmin dump gives when the database it is being
imported into already has the tables. The dump says `CREATE TABLE` and nothing
else, so the very first table stops it dead.

It is not a corrupt file and nothing has been damaged. The import simply has
to be told what to do about the tables that are already there.

**The fix, on the PC you are exporting FROM:** on the Export tab choose
**Custom** rather than Quick, and under *Object creation options* tick
**"Add DROP TABLE / VIEW / PROCEDURE / FUNCTION / EVENT"**. Every dump made
that way replaces what it lands on instead of colliding with it. It is worth
doing this every time, in both directions.

If you already have a dump without it, the database has to be emptied before
importing. See the next section — emptying it table by table runs into a
second error, and there is a shorter way.

## "#1451 - Cannot delete or update a parent row: a foreign key constraint fails"

This is what you get for trying to empty the database from the **Structure**
tab with *Check all → Drop*. The fifteen tables are tied together by fifteen
foreign keys — `tasks` points at `transformers`, `users`, `statuses` and
`task_types`; `task_abc_cables` points at `abc_cable_sizes` — and a table
cannot be dropped while another still points at it. phpMyAdmin drops them in
alphabetical order, so `abc_cable_sizes` fails on the first attempt.

Dropping the database itself has no such problem, and it is one statement.
In the **SQL** tab:

```sql
DROP DATABASE ceb_system;
CREATE DATABASE ceb_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

Then select the new, empty `ceb_system` on the left and import into it.

This does not cost the site its database account. `ceb_app` is granted rights
on `ceb_system` by name, so it looks as though dropping the database would
take the grant with it — on this server it does not. Tested on MariaDB 10.4:
a grant made on a database and then dropped is still listed afterwards, and
comes back to life when a database of that name is created again. If the site
ever does answer "Database connection failed" after an import, check with

```sql
SHOW GRANTS FOR 'ceb_app'@'localhost';
```

and put the line back if it is missing:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON ceb_system.* TO 'ceb_app'@'localhost';
FLUSH PRIVILEGES;
```

If you would rather keep the database and empty it table by table, that works
too, but the foreign keys have to be switched off around it. Paste the whole
block into the SQL tab at once, so it runs as one session:

```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS task_transformers, task_abc_cables, task_subtype_links, change_log, tasks,
                     task_subtypes, transformers, csc, areas, users,
                     statuses, task_types, capacities, provinces,
                     abc_cable_sizes, abc_phase_types;
SET FOREIGN_KEY_CHECKS = 1;
```

## Office PC → here

1. Export from the office PC with **Add DROP TABLE** ticked, as above.
2. Import it here.
3. **Run the password migration.** This is the step that is easy to forget:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root ceb_system < sql\2026-09-22_must_change_password.sql
   ```

   The office dumps were taken before `users.must_change_password` existed, so
   importing one takes the column away again and the login page stops working
   with *Unknown column 'must_change_password' in 'field list'*. The migration
   puts it back. It is written to be run as often as you like: if the column
   is already there it does nothing, and it never clears a flag that somebody
   is still waiting to act on.

   Then the proposal number migration, for the same reason — dumps taken
   before `transformers.proposal_no` existed take it away again, and the
   transformer pages stop with *Unknown column 'proposal_no'*:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root ceb_system < sql\2026-09-28_proposal_no.sql
   ```

   And the table that holds Transformer T2 to T5 on an event — without it
   the Add Event, Edit Event and event pages stop with *Table
   'ceb_system.task_transformers' doesn't exist*:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root ceb_system < sql\2026-09-28_task_transformers.sql
   ```

   And the Augmentation work detail under Low Voltage — without it the
   option is simply missing from Add Event:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root ceb_system < sql\2026-10-02_lv_augmentation.sql
   ```

   And the removed-transformers change — it creates `removed_transformers`,
   converts any transformer the office had deleted into an (R) S/N, and
   drops `transformers.is_deleted`. Without it every transformer page stops
   with *Table 'ceb_system.removed_transformers' doesn't exist*:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root ceb_system < sql\2026-10-06_removed_transformers.sql
   ```

   And the settings of 8 October — 1400 kVA, Medium Voltage switched off,
   and "Conversion" under Retail Connection:

   ```
   C:\xampp\mysql\bin\mysql.exe -u root ceb_system < sql\2026-10-08_settings.sql
   ```

   (On 8 October the office database itself was brought up to date with all
   of the above and loaded on both PCs, so a dump taken after that date
   already has everything and needs none of these steps.)

4. Sign in once to check.

## Here → office PC

1. Export from here with **Add DROP TABLE** ticked.
2. Import on the office PC.

No migration is needed in this direction. The export was taken from a database
that already has `must_change_password`, so the column travels inside the
`CREATE TABLE` and arrives on the office PC by itself.

What must travel with it is the code. A database with the column and an old
`login.php` without it will run, but nobody will ever be asked to change a
password; the opposite — new code, old database — is the error above. Copy the
PHP files and the database in the same trip.

## Before you overwrite either side

Take a dump of what is about to be replaced, and keep it until the new copy
has been opened and looked at. It costs a minute:

```
C:\xampp\mysql\bin\mysqldump.exe -u root --add-drop-table ceb_system > backup_before_import.sql
```
