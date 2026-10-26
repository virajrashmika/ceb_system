<?php
/* Database connection for the CEB System.
   Uses the limited ceb_app account, not root. */

/* ---------------------------------------------------------------------------
   The clock.

   XAMPP ships php.ini with date.timezone = "Europe/Berlin", and nobody ever
   changes it, so PHP believed it was 10:59 while the office clock and the
   database both said 14:29 — three and a half hours out. MariaDB was right
   all along: its system time zone is Asia/Colombo, so NOW() and the change
   log were never affected. It was PHP, and anything PHP wrote or printed.

   Setting it here rather than in php.ini means it holds for every page that
   talks to the database, needs no Apache restart, and survives XAMPP being
   reinstalled over the top. php.ini is still the place for a server-wide
   fix; this is the one that travels with the system.
   --------------------------------------------------------------------------- */
date_default_timezone_set('Asia/Colombo');

$host   = "127.0.0.1";
$user   = "ceb_app";
$pass   = "admin123";          // change this when you change the ceb_app password
$dbname = "ceb_system";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed. Please contact the system administrator.");
}

$conn->set_charset("utf8mb4");