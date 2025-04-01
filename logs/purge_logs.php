<?php
session_start();
require_once '../connexion_bdd.php';

if (isset($_POST['purge_logs'])) {
    
    $stmt = $pdo->prepare("TRUNCATE TABLE logs");
    $stmt->execute();

    header('Location: view.php');
    exit();
}
?>