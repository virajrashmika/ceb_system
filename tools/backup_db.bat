@echo off
rem ---------------------------------------------------------------------------
rem  backup_db.bat - export the ceb_system database to a dated .sql file.
rem
rem  Run it by double-clicking, or let Windows Task Scheduler run it every
rem  day (see README.txt in this folder). Each run writes one file:
rem
rem      Documents\ceb_backups\ceb_system_2026-10-08_1630.sql
rem
rem  Backups older than KEEP_DAYS are deleted, so the folder never fills the
rem  disk. The file can be imported in phpMyAdmin like any other dump: it
rem  carries DROP TABLE, so it replaces the tables it lands on.
rem
rem  MySQL must be running (XAMPP Control Panel) when this runs; if it is
rem  not, nothing is written and the reason goes into backup.log.
rem ---------------------------------------------------------------------------
setlocal

set "MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe"
set "DB=ceb_system"
set "DB_USER=root"
set "BACKUP_DIR=%USERPROFILE%\Documents\ceb_backups"
set "KEEP_DAYS=30"

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
set "LOG=%BACKUP_DIR%\backup.log"

rem Date and time as yyyy-MM-dd_HHmm, the same whatever the PC's region setting.
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set "STAMP=%%i"
set "OUT=%BACKUP_DIR%\%DB%_%STAMP%.sql"

"%MYSQLDUMP%" -u %DB_USER% --add-drop-table --routines --skip-events --single-transaction "%DB%" > "%OUT%" 2>> "%LOG%"

if errorlevel 1 goto failed
rem A dump that is empty or tiny is a failure too, whatever the exit code said.
for %%f in ("%OUT%") do if %%~zf LSS 10000 goto failed

echo %STAMP%  OK      %OUT%>> "%LOG%"
echo Backup saved: %OUT%

rem Remove backups older than KEEP_DAYS. Only files this script made are touched.
forfiles /p "%BACKUP_DIR%" /m "%DB%_*.sql" /d -%KEEP_DAYS% /c "cmd /c del @path" >nul 2>&1
goto end

:failed
if exist "%OUT%" del "%OUT%"
echo %STAMP%  FAILED  is MySQL running in XAMPP? see the line above>> "%LOG%"
echo BACKUP FAILED - is MySQL running in the XAMPP Control Panel? Details in %LOG%
exit /b 1

:end
endlocal
