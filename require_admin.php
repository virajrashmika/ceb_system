<?php
// Include this AFTER auth.php on any page only admins may open.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $pageTitle = "Not allowed";
    $activeNav = "home";
    include 'header.php';
    echo "<div class='message message-error'>";
    echo "<strong>Access denied.</strong> This page is for administrators only.";
    echo "</div>";
    echo "<p><a href='index.php' class='btn'>Back to Home</a></p>";
    include 'footer.php';
    exit;
}