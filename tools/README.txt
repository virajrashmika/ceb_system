AUTOMATIC DATABASE BACKUP
=========================

backup_db.bat exports the ceb_system database to
    Documents\ceb_backups\ceb_system_<date>_<time>.sql
and deletes backups older than 30 days. backup.log in the same folder
records every run, OK or FAILED.

Run it once by hand
-------------------
Double-click backup_db.bat. A window shows "Backup saved: ..." when it
worked. MySQL must be started in the XAMPP Control Panel first.

Make Windows run it every day
-----------------------------
Open Command Prompt (no need for administrator) and paste ONE line:

    schtasks /create /tn "CEB database backup" /tr "C:\xampp\htdocs\ceb_system\tools\backup_db.bat" /sc daily /st 16:30

That runs it every day at 4:30 pm. Pick a time when the PC is on and MySQL
is running - near the end of the working day is best, so the backup holds
the whole day's work.

    Run it now, to test:   schtasks /run /tn "CEB database backup"
    See when it last ran:  schtasks /query /tn "CEB database backup" /v /fo list
    Remove it:             schtasks /delete /tn "CEB database backup" /f

Or use the Task Scheduler window: Start > "Task Scheduler" > find
"CEB database backup" in the Task Scheduler Library.

Restore a backup
----------------
phpMyAdmin > select ceb_system > Import > choose the .sql file > Go.
Take a fresh backup of what is there now before restoring an older one.

Settings (top of backup_db.bat)
-------------------------------
    BACKUP_DIR   where the files go    (default Documents\ceb_backups)
    KEEP_DAYS    how long to keep them (default 30)
    DB_USER      MySQL user            (root, no password on XAMPP)

For a copy that survives the PC itself failing, point BACKUP_DIR at a USB
drive or a network folder, or copy the latest file off the PC now and then.
