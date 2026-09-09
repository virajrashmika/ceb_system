<?php
/* Database connection for the CEB System.
   Uses the limited ceb_app account, not root. */

$host   = "127.0.0.1";
$user   = "ceb_app";
$pass   = "admin123";          // change this when you change the ceb_app password
$dbname = "ceb_system";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed. Please contact the system administrator.");
}

$conn->set_charset("utf8mb4");