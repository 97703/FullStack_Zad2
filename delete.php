<?php
include 'db.php';

$id = $_GET['id'];
$conn->query("DELETE FROM attendance WHERE id=$id");
header("Location: index.php");
exit;
?>