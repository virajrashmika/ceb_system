<?php
include 'auth.php';
include 'db.php';

$pageTitle = "Home";
$pageDesc  = "Welcome to the CEB Transformer Management System.";
$activeNav = "home";
include 'header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2>Quick Links</h2>
    </div>
    <div class="panel-body">
        <p><a href="csc.php">Consumer Service Centres</a></p>
        <p><a href="transformers.php">Transformers</a></p>
        <p><a href="search.php">Search by S/N</a></p>
    </div>
</div>

<?php include 'footer.php'; ?>